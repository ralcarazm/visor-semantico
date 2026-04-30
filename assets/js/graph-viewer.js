class KnowledgeMapGraphViewer {
    constructor(container, options = {}) {
        this.container = container;
        this.options = options;
        this.network = null;
        this.currentNodes = [];
        this.currentEdges = [];
        this.resizeTimer = null;
        this.resizeObserver = null;
        this.fallbackPanZoomCleanup = null;
        this.fallbackWrapper = null;
        this.fallbackPanZoomState = { x: 0, y: 0, scale: 1 };
        this.lastFallbackWidth = 0;
        this.lastFallbackHeight = 0;
        this.isInteracting = false;
        this.interactionTimer = null;
        this.currentRenderer = null;
        this.lastNetworkWidth = 0;
        this.lastNetworkHeight = 0;
        this.handleViewportResize = () => this.scheduleResponsiveResize(true);
        window.addEventListener('resize', this.handleViewportResize, { passive: true });
        window.addEventListener('orientationchange', this.handleViewportResize, { passive: true });
    }

    render(nodes, edges) {
        const normalisedNodes = this.normaliseNodes(nodes || []);
        const normalisedEdges = this.normaliseEdges(edges || []);
        this.currentNodes = normalisedNodes;
        this.currentEdges = normalisedEdges;

        if (!Array.isArray(normalisedNodes) || normalisedNodes.length === 0) {
            this.clear('No hay nodos para visualizar. Comprueba que el fichero contiene tripletas válidas o revisa los filtros aplicados.');
            return;
        }

        if (typeof vis !== 'undefined' && vis.Network && vis.DataSet) {
            this.renderWithVis(normalisedNodes, normalisedEdges);
            return;
        }

        this.renderWithHtmlFallback(normalisedNodes, normalisedEdges);
    }

    normaliseNodes(nodes) {
        return nodes.map((node) => {
            const copy = { ...node };
            const image = String(copy.image || '');
            const rawTitle = String(copy.title || copy.label || copy.id || '');
            copy.title_html = rawTitle;
            copy.title_text = this.htmlToPlainText(rawTitle);
            // vis-network puede interpretar `title` como HTML en algunas versiones. Usamos texto plano
            // para evitar que aparezcan etiquetas como <strong> o <br> en los tooltips.
            copy.title = copy.title_text;

            if (image !== '') {
                copy.image = image;
                copy.shape = copy.shape || 'circularImage';
                if (copy.shape === 'image') {
                    copy.shape = 'circularImage';
                }
                copy.brokenImage = '';
            }

            return copy;
        });
    }

    normaliseEdges(edges) {
        return edges.map((edge) => ({
            ...edge,
            arrows: edge.arrows || 'to',
        }));
    }

    renderWithVis(nodes, edges) {
        this.destroyNetwork();
        this.container.innerHTML = '';
        const isSmallViewport = this.isSmallViewport();
        const isDenseGraph = nodes.length > 45 || edges.length > 90;
        const isVeryDenseGraph = nodes.length > 80 || edges.length > 160;
        const containerWidth = this.container.clientWidth || window.innerWidth || 900;
        const containerHeight = this.container.clientHeight || (isSmallViewport ? 420 : 650);
        this.lastNetworkWidth = containerWidth;
        this.lastNetworkHeight = containerHeight;
        this.currentRenderer = 'vis';
        this.container.classList.add('has-vis-renderer');
        this.container.classList.remove('has-html-renderer');
        this.container.style.touchAction = 'none';

        let visNodes = nodes;
        if (isVeryDenseGraph) {
            const layoutWidth = Math.max(containerWidth, 1200);
            const layoutHeight = Math.max(containerHeight, 800);
            const positions = this.computePositions(nodes, edges, layoutWidth / 2, layoutHeight / 2, Math.min(layoutWidth, layoutHeight) * 0.42);
            visNodes = nodes.map((node) => {
                const pos = positions.get(String(node.id));
                return pos ? { ...node, x: pos.x - (layoutWidth / 2), y: pos.y - (layoutHeight / 2), physics: false } : { ...node, physics: false };
            });
        }

        const data = {
            nodes: new vis.DataSet(visNodes),
            edges: new vis.DataSet(edges),
        };

        const options = {
            autoResize: true,
            layout: {
                improvedLayout: !isDenseGraph,
            },
            nodes: {
                borderWidth: 1,
                size: isSmallViewport ? 26 : 34,
                margin: 12,
                shapeProperties: {
                    useBorderWithImage: true,
                    interpolation: false,
                },
                font: {
                    size: isSmallViewport ? 12 : 14,
                    multi: true,
                    face: 'system-ui',
                },
            },
            edges: {
                arrows: {
                    to: {
                        enabled: true,
                        scaleFactor: 0.8,
                    },
                },
                font: {
                    align: 'middle',
                    size: isSmallViewport ? 11 : 13,
                    strokeWidth: 4,
                    face: 'system-ui',
                },
                smooth: isDenseGraph ? false : {
                    type: 'dynamic',
                },
            },
            interaction: {
                dragNodes: true,
                dragView: true,
                hover: !isVeryDenseGraph,
                multiselect: true,
                navigationButtons: !isSmallViewport,
                keyboard: !isSmallViewport,
                tooltipDelay: 120,
                zoomView: true,
                zoomSpeed: 0.85,
                hideEdgesOnDrag: isVeryDenseGraph,
                hideEdgesOnZoom: isVeryDenseGraph,
            },
            physics: {
                enabled: !isVeryDenseGraph,
                solver: isDenseGraph ? 'barnesHut' : 'forceAtlas2Based',
                stabilization: {
                    enabled: true,
                    iterations: isDenseGraph ? 220 : 140,
                    updateInterval: 25,
                },
                barnesHut: {
                    gravitationalConstant: -2800,
                    centralGravity: 0.18,
                    springLength: isDenseGraph ? 135 : 115,
                    springConstant: 0.035,
                    damping: 0.35,
                    avoidOverlap: 0.45,
                },
            },
            groups: {
                work: { borderWidth: 2 },
                person: {},
                material: {},
                image: {},
                external: {},
                entity: {},
                literal: {},
            },
        };

        this.network = new vis.Network(this.container, data, options);
        this.installResizeObserver();
        this.scheduleResponsiveResize(false);

        const markInteraction = (timeout = 240) => {
            this.isInteracting = true;
            window.clearTimeout(this.interactionTimer);
            this.interactionTimer = window.setTimeout(() => {
                this.isInteracting = false;
            }, timeout);
        };

        this.network.on('dragStart', () => markInteraction(600));
        this.network.on('dragging', () => markInteraction(600));
        this.network.on('dragEnd', () => markInteraction(140));
        this.network.on('zoom', () => markInteraction(320));

        this.network.on('click', (params) => {
            const nodeId = params.nodes && params.nodes.length ? String(params.nodes[0]) : null;
            if (!nodeId) {
                this.emitNodeSelect(null);
                return;
            }
            this.emitNodeSelect(data.nodes.get(nodeId) || null);
        });

        if (isVeryDenseGraph) {
            window.requestAnimationFrame(() => {
                if (!this.network) return;
                this.network.redraw();
                this.fit();
            });
        } else {
            this.network.once('stabilizationIterationsDone', () => {
                if (!this.network) return;
                this.network.setOptions({ physics: false });
                this.fit();
            });
        }
    }

    renderWithHtmlFallback(nodes, edges) {
        this.destroyNetwork();
        this.container.innerHTML = '';
        this.currentRenderer = 'html';
        this.container.classList.add('has-html-renderer');
        this.container.classList.remove('has-vis-renderer');
        this.container.style.touchAction = 'none';

        const containerWidth = this.container.clientWidth || window.innerWidth || 900;
        const containerHeight = this.container.clientHeight || (this.isSmallViewport() ? 420 : 650);
        const isSmallViewport = this.isSmallViewport();
        const width = isSmallViewport ? Math.max(containerWidth, 320) : Math.max(containerWidth, 740);
        const height = isSmallViewport ? Math.max(containerHeight, 340) : Math.max(containerHeight, 540);
        this.lastFallbackWidth = width;
        this.lastFallbackHeight = height;
        const centerX = width / 2;
        const centerY = height / 2;
        const radius = Math.max(isSmallViewport ? 108 : 170, Math.min(width, height) * (isSmallViewport ? 0.30 : 0.33));
        const positioned = this.computePositions(nodes, edges, centerX, centerY, radius);
        const ns = 'http://www.w3.org/2000/svg';

        const wrapper = document.createElement('div');
        wrapper.className = 'html-graph';
        wrapper.style.width = width + 'px';
        wrapper.style.height = height + 'px';

        const svg = document.createElementNS(ns, 'svg');
        svg.setAttribute('viewBox', `0 0 ${width} ${height}`);
        svg.setAttribute('class', 'html-edge-svg');
        svg.setAttribute('aria-hidden', 'true');

        const defs = document.createElementNS(ns, 'defs');
        const marker = document.createElementNS(ns, 'marker');
        marker.setAttribute('id', 'html-arrow');
        marker.setAttribute('viewBox', '0 0 10 10');
        marker.setAttribute('refX', '10');
        marker.setAttribute('refY', '5');
        marker.setAttribute('markerWidth', '7');
        marker.setAttribute('markerHeight', '7');
        marker.setAttribute('orient', 'auto-start-reverse');
        const arrowPath = document.createElementNS(ns, 'path');
        arrowPath.setAttribute('d', 'M 0 0 L 10 5 L 0 10 z');
        marker.appendChild(arrowPath);
        defs.appendChild(marker);
        svg.appendChild(defs);

        (edges || []).forEach((edge) => {
            const from = positioned.get(String(edge.from));
            const to = positioned.get(String(edge.to));
            if (!from || !to) {
                return;
            }

            const line = document.createElementNS(ns, 'line');
            line.setAttribute('x1', String(from.x));
            line.setAttribute('y1', String(from.y));
            line.setAttribute('x2', String(to.x));
            line.setAttribute('y2', String(to.y));
            line.setAttribute('class', 'edge-line');
            line.setAttribute('marker-end', 'url(#html-arrow)');
            svg.appendChild(line);

            const label = String(edge.label || '');
            if (label !== '') {
                const edgeLabel = document.createElement('span');
                edgeLabel.className = 'html-edge-label';
                edgeLabel.textContent = label;
                edgeLabel.style.left = ((from.x + to.x) / 2) + 'px';
                edgeLabel.style.top = (((from.y + to.y) / 2) - 8) + 'px';
                wrapper.appendChild(edgeLabel);
            }
        });

        wrapper.appendChild(svg);

        nodes.forEach((node) => {
            const pos = positioned.get(String(node.id));
            if (!pos) {
                return;
            }

            const group = String(node.group || 'entity');
            const image = String(node.image || '');
            const label = String(node.label || node.id || '');
            const title = node.title_text || this.htmlToPlainText(String(node.title_html || label));

            const card = document.createElement('button');
            card.type = 'button';
            card.className = `html-node html-node-${this.cssSafe(group)}${image !== '' ? ' has-image' : ''}`;
            card.style.left = pos.x + 'px';
            card.style.top = pos.y + 'px';
            card.title = title;
            card.setAttribute('aria-label', title);
            card.addEventListener('click', () => this.emitNodeSelect(node));

            if (image !== '') {
                const img = document.createElement('img');
                img.src = image;
                img.alt = '';
                img.loading = 'lazy';
                img.referrerPolicy = 'no-referrer';
                img.crossOrigin = 'anonymous';
                img.addEventListener('error', () => {
                    img.remove();
                    card.classList.remove('has-image');
                    card.classList.add('image-error');
                });
                card.appendChild(img);
            }

            const labelSpan = document.createElement('span');
            labelSpan.textContent = label;
            card.appendChild(labelSpan);
            wrapper.appendChild(card);
        });

        const note = document.createElement('p');
        note.className = 'fallback-note';
        note.textContent = 'Renderizador HTML local activo. Las imágenes se muestran con etiquetas img para evitar problemas del fallback SVG con imágenes externas.';

        this.container.appendChild(wrapper);
        this.container.appendChild(note);
        this.enableHtmlPanZoom(wrapper);
        this.installResizeObserver();
    }

    enableHtmlPanZoom(wrapper) {
        if (this.fallbackPanZoomCleanup) {
            this.fallbackPanZoomCleanup();
            this.fallbackPanZoomCleanup = null;
        }

        this.fallbackWrapper = wrapper;
        this.fallbackPanZoomState = { x: 0, y: 0, scale: 1 };
        wrapper.classList.add('is-pan-zoom');
        wrapper.style.touchAction = 'none';
        this.container.style.touchAction = 'none';
        this.applyFallbackTransform();

        const pointers = new Map();
        let lastSinglePointer = null;
        let lastPinch = null;
        let hasDragged = false;

        const clampScale = (value) => Math.max(0.18, Math.min(5, value));
        const distance = (a, b) => Math.hypot(a.x - b.x, a.y - b.y);
        const midpoint = (a, b) => ({ x: (a.x + b.x) / 2, y: (a.y + b.y) / 2 });
        const eventPoint = (event) => {
            const rect = this.container.getBoundingClientRect();
            return { x: event.clientX - rect.left, y: event.clientY - rect.top };
        };
        const zoomAt = (factor, point) => {
            const state = this.fallbackPanZoomState;
            const oldScale = state.scale;
            const newScale = clampScale(oldScale * factor);
            if (newScale === oldScale) return;
            state.x = point.x - ((point.x - state.x) * (newScale / oldScale));
            state.y = point.y - ((point.y - state.y) * (newScale / oldScale));
            state.scale = newScale;
            this.applyFallbackTransform();
        };

        const onWheel = (event) => {
            event.preventDefault();
            event.stopPropagation();
            const factor = Math.exp(-event.deltaY * 0.0012);
            zoomAt(factor, eventPoint(event));
        };

        const onPointerDown = (event) => {
            if (event.button !== undefined && event.button !== 0) return;
            const point = eventPoint(event);
            pointers.set(event.pointerId, point);
            try { this.container.setPointerCapture?.(event.pointerId); } catch (error) { /* no-op */ }
            this.isInteracting = true;
            hasDragged = false;
            wrapper.classList.add('is-panning');

            if (pointers.size === 1) {
                lastSinglePointer = point;
                lastPinch = null;
            } else if (pointers.size === 2) {
                const values = [...pointers.values()];
                lastPinch = { distance: distance(values[0], values[1]), center: midpoint(values[0], values[1]) };
                lastSinglePointer = null;
            }
        };

        const onPointerMove = (event) => {
            if (!pointers.has(event.pointerId)) return;
            event.preventDefault();
            event.stopPropagation();
            const point = eventPoint(event);
            pointers.set(event.pointerId, point);
            const state = this.fallbackPanZoomState;

            if (pointers.size === 1 && lastSinglePointer) {
                const dx = point.x - lastSinglePointer.x;
                const dy = point.y - lastSinglePointer.y;
                if (Math.abs(dx) > 0.5 || Math.abs(dy) > 0.5) {
                    hasDragged = true;
                    state.x += dx;
                    state.y += dy;
                    lastSinglePointer = point;
                    this.applyFallbackTransform();
                }
            } else if (pointers.size >= 2) {
                const values = [...pointers.values()].slice(0, 2);
                const newDistance = distance(values[0], values[1]);
                const newCenter = midpoint(values[0], values[1]);

                if (lastPinch && lastPinch.distance > 0) {
                    hasDragged = true;
                    state.x += newCenter.x - lastPinch.center.x;
                    state.y += newCenter.y - lastPinch.center.y;
                    this.applyFallbackTransform();
                    zoomAt(newDistance / lastPinch.distance, newCenter);
                }

                lastPinch = { distance: newDistance, center: newCenter };
            }
        };

        const onPointerUp = (event) => {
            if (pointers.has(event.pointerId)) {
                pointers.delete(event.pointerId);
            }
            try { this.container.releasePointerCapture?.(event.pointerId); } catch (error) { /* no-op */ }

            if (pointers.size === 0) {
                lastSinglePointer = null;
                lastPinch = null;
                wrapper.classList.remove('is-panning');
                window.setTimeout(() => { this.isInteracting = false; }, 80);
            } else if (pointers.size === 1) {
                lastSinglePointer = [...pointers.values()][0];
                lastPinch = null;
            }
        };

        const onClickCapture = (event) => {
            if (hasDragged) {
                event.preventDefault();
                event.stopPropagation();
                hasDragged = false;
            }
        };

        this.container.addEventListener('wheel', onWheel, { passive: false, capture: true });
        this.container.addEventListener('pointerdown', onPointerDown, { passive: false, capture: true });
        this.container.addEventListener('pointermove', onPointerMove, { passive: false, capture: true });
        this.container.addEventListener('pointerup', onPointerUp, { passive: false, capture: true });
        this.container.addEventListener('pointercancel', onPointerUp, { passive: false, capture: true });
        this.container.addEventListener('click', onClickCapture, true);

        this.fallbackPanZoomCleanup = () => {
            this.container.removeEventListener('wheel', onWheel, true);
            this.container.removeEventListener('pointerdown', onPointerDown, true);
            this.container.removeEventListener('pointermove', onPointerMove, true);
            this.container.removeEventListener('pointerup', onPointerUp, true);
            this.container.removeEventListener('pointercancel', onPointerUp, true);
            this.container.removeEventListener('click', onClickCapture, true);
            wrapper.classList.remove('is-pan-zoom', 'is-panning');
            wrapper.style.touchAction = '';
            this.fallbackWrapper = null;
        };
    }

    applyFallbackTransform() {
        if (!this.fallbackWrapper) return;
        const state = this.fallbackPanZoomState;
        this.fallbackWrapper.style.transform = `translate(${state.x}px, ${state.y}px) scale(${state.scale})`;
    }

    computePositions(nodes, edges, centerX, centerY, radius) {
        const positions = new Map();
        const degree = new Map();

        nodes.forEach((node) => degree.set(String(node.id), 0));
        (edges || []).forEach((edge) => {
            degree.set(String(edge.from), (degree.get(String(edge.from)) || 0) + 1);
            degree.set(String(edge.to), (degree.get(String(edge.to)) || 0) + 1);
        });

        const ordered = [...nodes].sort((a, b) => {
            return (degree.get(String(b.id)) || 0) - (degree.get(String(a.id)) || 0);
        });

        if (ordered.length === 1) {
            positions.set(String(ordered[0].id), { x: centerX, y: centerY });
            return positions;
        }

        const first = ordered[0];
        positions.set(String(first.id), { x: centerX, y: centerY });

        const rest = ordered.slice(1);
        rest.forEach((node, index) => {
            const angle = (-Math.PI / 2) + (index * 2 * Math.PI / rest.length);
            positions.set(String(node.id), {
                x: centerX + radius * Math.cos(angle),
                y: centerY + radius * Math.sin(angle),
            });
        });

        return positions;
    }

    exportSvgBlob() {
        const svg = this.buildExportSvg();
        return new Blob([svg], { type: 'image/svg+xml;charset=utf-8' });
    }

    async exportBitmapBlob(mimeType = 'image/png', quality = 0.95) {
        const canvas = await this.buildExportCanvas();
        return new Promise((resolve, reject) => {
            try {
                canvas.toBlob((blob) => {
                    if (!blob) {
                        reject(new Error('El navegador no ha podido generar la imagen.'));
                        return;
                    }
                    resolve(blob);
                }, mimeType, quality);
            } catch (error) {
                reject(error);
            }
        });
    }

    async buildExportCanvas() {
        const width = 1600;
        const height = 1100;
        const centerX = width / 2;
        const centerY = height / 2;
        const radius = Math.max(280, Math.min(width, height) * 0.34);
        const positions = this.getExportPositions(width, height, centerX, centerY, radius);
        const canvas = document.createElement('canvas');
        canvas.width = width;
        canvas.height = height;
        const ctx = canvas.getContext('2d');

        // No se pinta ningún rectángulo de fondo: el PNG/WEBP conserva transparencia.
        ctx.clearRect(0, 0, width, height);
        ctx.font = '15px system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif';
        ctx.textAlign = 'center';
        ctx.textBaseline = 'middle';

        const images = await this.loadNodeImages();

        this.currentEdges.forEach((edge) => {
            const from = positions.get(String(edge.from));
            const to = positions.get(String(edge.to));
            if (!from || !to) return;
            this.drawEdge(ctx, from, to, String(edge.label || ''));
        });

        this.currentNodes.forEach((node) => {
            const pos = positions.get(String(node.id));
            if (!pos) return;
            this.drawNode(ctx, node, pos, images.get(String(node.id)) || null);
        });

        return canvas;
    }

    async loadNodeImages() {
        const imageMap = new Map();
        const tasks = this.currentNodes.map(async (node) => {
            const id = String(node.id);
            const url = String(node.image || node.full_image || '');
            if (url === '') {
                imageMap.set(id, null);
                return;
            }
            imageMap.set(id, await this.loadImage(url));
        });
        await Promise.all(tasks);
        return imageMap;
    }

    loadImage(url) {
        return new Promise((resolve) => {
            const image = new Image();
            image.crossOrigin = 'anonymous';
            image.referrerPolicy = 'no-referrer';
            image.onload = () => resolve(image);
            image.onerror = () => resolve(null);
            image.src = url;
        });
    }

    drawEdge(ctx, from, to, label) {
        const angle = Math.atan2(to.y - from.y, to.x - from.x);
        const startPad = 55;
        const endPad = 58;
        const x1 = from.x + Math.cos(angle) * startPad;
        const y1 = from.y + Math.sin(angle) * startPad;
        const x2 = to.x - Math.cos(angle) * endPad;
        const y2 = to.y - Math.sin(angle) * endPad;

        ctx.save();
        ctx.strokeStyle = '#98a2b3';
        ctx.lineWidth = 2.4;
        ctx.beginPath();
        ctx.moveTo(x1, y1);
        ctx.lineTo(x2, y2);
        ctx.stroke();

        const arrowSize = 10;
        ctx.fillStyle = '#101828';
        ctx.beginPath();
        ctx.moveTo(x2, y2);
        ctx.lineTo(x2 - arrowSize * Math.cos(angle - Math.PI / 6), y2 - arrowSize * Math.sin(angle - Math.PI / 6));
        ctx.lineTo(x2 - arrowSize * Math.cos(angle + Math.PI / 6), y2 - arrowSize * Math.sin(angle + Math.PI / 6));
        ctx.closePath();
        ctx.fill();

        if (label !== '') {
            const lx = (from.x + to.x) / 2;
            const ly = ((from.y + to.y) / 2) - 14;
            this.drawPillLabel(ctx, label, lx, ly);
        }
        ctx.restore();
    }

    drawPillLabel(ctx, label, x, y) {
        const text = this.truncateText(ctx, label, 190);
        const metrics = ctx.measureText(text);
        const width = Math.min(220, metrics.width + 28);
        const height = 30;
        ctx.save();
        ctx.fillStyle = 'rgba(255, 255, 255, 0.92)';
        ctx.strokeStyle = '#d0d5dd';
        ctx.lineWidth = 1;
        this.roundRect(ctx, x - width / 2, y - height / 2, width, height, 15);
        ctx.fill();
        ctx.stroke();
        ctx.fillStyle = '#344054';
        ctx.font = '14px system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif';
        ctx.fillText(text, x, y + 1);
        ctx.restore();
    }

    drawNode(ctx, node, pos, image) {
        const group = String(node.group || 'entity');
        const label = String(node.label || node.id || '');
        const stroke = this.groupStroke(group);
        const fill = this.groupFill(group);
        const hasImage = image !== null;

        ctx.save();
        ctx.shadowColor = 'rgba(16, 24, 40, 0.18)';
        ctx.shadowBlur = 18;
        ctx.shadowOffsetY = 8;

        if (hasImage) {
            const cardWidth = group === 'work' ? 170 : 142;
            const cardHeight = 142;
            this.roundRect(ctx, pos.x - cardWidth / 2, pos.y - 70, cardWidth, cardHeight, 22);
            ctx.fillStyle = '#ffffff';
            ctx.fill();
            ctx.shadowColor = 'transparent';

            this.drawCircularImage(ctx, image, pos.x, pos.y - 21, group === 'work' ? 48 : 44, stroke);
            this.drawWrappedText(ctx, label, pos.x, pos.y + 47, cardWidth - 22, 2, '#101828', true);
        } else if (group === 'work') {
            const cardWidth = 190;
            const cardHeight = 78;
            this.roundRect(ctx, pos.x - cardWidth / 2, pos.y - cardHeight / 2, cardWidth, cardHeight, 18);
            ctx.fillStyle = fill;
            ctx.strokeStyle = stroke;
            ctx.lineWidth = 2.5;
            ctx.fill();
            ctx.shadowColor = 'transparent';
            ctx.stroke();
            this.drawWrappedText(ctx, label, pos.x, pos.y, cardWidth - 24, 3, '#101828', true);
        } else {
            ctx.beginPath();
            ctx.arc(pos.x, pos.y, 48, 0, Math.PI * 2);
            ctx.fillStyle = fill;
            ctx.strokeStyle = stroke;
            ctx.lineWidth = 2.5;
            ctx.fill();
            ctx.shadowColor = 'transparent';
            ctx.stroke();
            this.drawWrappedText(ctx, label, pos.x, pos.y, 82, 3, '#101828', true);
        }

        ctx.restore();
    }

    drawCircularImage(ctx, image, cx, cy, radius, stroke) {
        ctx.save();
        ctx.beginPath();
        ctx.arc(cx, cy, radius, 0, Math.PI * 2);
        ctx.closePath();
        ctx.clip();
        this.drawImageCover(ctx, image, cx - radius, cy - radius, radius * 2, radius * 2);
        ctx.restore();

        ctx.save();
        ctx.beginPath();
        ctx.arc(cx, cy, radius, 0, Math.PI * 2);
        ctx.strokeStyle = stroke || '#101828';
        ctx.lineWidth = 3;
        ctx.stroke();
        ctx.restore();
    }

    drawImageCover(ctx, image, x, y, width, height) {
        const scale = Math.max(width / image.naturalWidth, height / image.naturalHeight);
        const sw = width / scale;
        const sh = height / scale;
        const sx = (image.naturalWidth - sw) / 2;
        const sy = (image.naturalHeight - sh) / 2;
        ctx.drawImage(image, sx, sy, sw, sh, x, y, width, height);
    }

    drawWrappedText(ctx, text, x, y, maxWidth, maxLines, color, bold = false) {
        const fontWeight = bold ? '700' : '500';
        ctx.save();
        ctx.fillStyle = color;
        ctx.font = `${fontWeight} 15px system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif`;
        const lines = this.wrapCanvasText(ctx, text, maxWidth, maxLines);
        const lineHeight = 18;
        const startY = y - ((lines.length - 1) * lineHeight) / 2;
        lines.forEach((line, index) => {
            ctx.fillText(line, x, startY + index * lineHeight);
        });
        ctx.restore();
    }

    wrapCanvasText(ctx, text, maxWidth, maxLines) {
        const words = String(text).split(/\s+/).filter(Boolean);
        const lines = [];
        let current = '';

        words.forEach((word) => {
            const candidate = current === '' ? word : current + ' ' + word;
            if (ctx.measureText(candidate).width > maxWidth && current !== '') {
                lines.push(current);
                current = word;
            } else {
                current = candidate;
            }
        });

        if (current !== '') lines.push(current);

        if (lines.length > maxLines) {
            const limited = lines.slice(0, maxLines);
            limited[maxLines - 1] = this.truncateText(ctx, limited[maxLines - 1] + '…', maxWidth);
            return limited;
        }

        return lines.length ? lines : [''];
    }

    truncateText(ctx, text, maxWidth) {
        let output = String(text);
        if (ctx.measureText(output).width <= maxWidth) return output;
        while (output.length > 1 && ctx.measureText(output + '…').width > maxWidth) {
            output = output.slice(0, -1);
        }
        return output + '…';
    }

    buildExportSvg() {
        const width = 1200;
        const height = 820;
        const centerX = width / 2;
        const centerY = height / 2;
        const radius = Math.max(230, Math.min(width, height) * 0.34);
        const positions = this.getExportPositions(width, height, centerX, centerY, radius);
        const parts = [];

        parts.push(`<?xml version="1.0" encoding="UTF-8"?>`);
        parts.push(`<svg xmlns="http://www.w3.org/2000/svg" width="${width}" height="${height}" viewBox="0 0 ${width} ${height}" role="img" aria-label="Visor Semántico exportado">`);
        parts.push(`<defs><marker id="arrow" viewBox="0 0 10 10" refX="10" refY="5" markerWidth="7" markerHeight="7" orient="auto-start-reverse"><path d="M 0 0 L 10 5 L 0 10 z" fill="#101828"/></marker></defs>`);

        this.currentEdges.forEach((edge) => {
            const from = positions.get(String(edge.from));
            const to = positions.get(String(edge.to));
            if (!from || !to) return;
            parts.push(`<line x1="${from.x}" y1="${from.y}" x2="${to.x}" y2="${to.y}" stroke="#98a2b3" stroke-width="2" marker-end="url(#arrow)"/>`);
            const label = this.escapeXml(String(edge.label || ''));
            if (label !== '') {
                const lx = (from.x + to.x) / 2;
                const ly = ((from.y + to.y) / 2) - 10;
                parts.push(`<text x="${lx}" y="${ly}" text-anchor="middle" font-family="system-ui, Segoe UI, sans-serif" font-size="15" fill="#344054" stroke="#ffffff" stroke-width="5" paint-order="stroke">${label}</text>`);
            }
        });

        this.currentNodes.forEach((node) => {
            const pos = positions.get(String(node.id));
            if (!pos) return;
            const group = String(node.group || 'entity');
            const shape = group === 'work' ? 'rect' : 'circle';
            const stroke = this.groupStroke(group);
            const fill = this.groupFill(group);
            const label = String(node.label || node.id || '');

            if (shape === 'rect') {
                parts.push(`<rect x="${pos.x - 82}" y="${pos.y - 32}" width="164" height="64" rx="16" fill="${fill}" stroke="${stroke}" stroke-width="2"/>`);
            } else {
                parts.push(`<circle cx="${pos.x}" cy="${pos.y}" r="42" fill="${fill}" stroke="${stroke}" stroke-width="2"/>`);
            }

            this.wrapLabel(label, 20).forEach((line, index, lines) => {
                const y = pos.y + ((index - (lines.length - 1) / 2) * 18) + 5;
                parts.push(`<text x="${pos.x}" y="${y}" text-anchor="middle" font-family="system-ui, Segoe UI, sans-serif" font-size="15" font-weight="650" fill="#101828">${this.escapeXml(line)}</text>`);
            });
        });

        parts.push(`</svg>`);
        return parts.join('\n');
    }

    getExportPositions(width, height, centerX, centerY, radius) {
        if (this.network && this.currentNodes.length > 0) {
            const ids = this.currentNodes.map((node) => String(node.id));
            const raw = this.network.getPositions(ids);
            const values = Object.values(raw);
            if (values.length > 0) {
                const minX = Math.min(...values.map((p) => p.x));
                const maxX = Math.max(...values.map((p) => p.x));
                const minY = Math.min(...values.map((p) => p.y));
                const maxY = Math.max(...values.map((p) => p.y));
                const rawWidth = Math.max(1, maxX - minX);
                const rawHeight = Math.max(1, maxY - minY);
                const scale = Math.min((width - 240) / rawWidth, (height - 240) / rawHeight, 2.2);
                const positions = new Map();
                ids.forEach((id) => {
                    const p = raw[id];
                    positions.set(id, {
                        x: 120 + ((p.x - minX) * scale),
                        y: 120 + ((p.y - minY) * scale),
                    });
                });
                return positions;
            }
        }

        return this.computePositions(this.currentNodes, this.currentEdges, centerX, centerY, radius);
    }

    buildTooltipElement(html) {
        const wrapper = document.createElement('div');
        wrapper.className = 'km-tooltip';
        wrapper.innerHTML = String(html || '');
        return wrapper;
    }

    htmlToPlainText(html) {
        const withBreaks = String(html || '')
            .replace(/<br\s*\/?\s*>/gi, '\n')
            .replace(/<\/p>/gi, '\n')
            .replace(/<\/div>/gi, '\n')
            .replace(/<\/li>/gi, '\n');
        const div = document.createElement('div');
        div.innerHTML = withBreaks;
        return (div.textContent || div.innerText || '')
            .split('\n')
            .map((line) => line.trim())
            .filter(Boolean)
            .join('\n');
    }

    wrapLabel(label, maxLength) {
        const words = String(label).split(/\s+/).filter(Boolean);
        const lines = [];
        let current = '';

        words.forEach((word) => {
            const candidate = current === '' ? word : current + ' ' + word;
            if (candidate.length > maxLength && current !== '') {
                lines.push(current);
                current = word;
            } else {
                current = candidate;
            }
        });

        if (current !== '') lines.push(current);
        return lines.length ? lines.slice(0, 3) : [''];
    }

    roundRect(ctx, x, y, width, height, radius) {
        const r = Math.min(radius, width / 2, height / 2);
        ctx.beginPath();
        ctx.moveTo(x + r, y);
        ctx.lineTo(x + width - r, y);
        ctx.quadraticCurveTo(x + width, y, x + width, y + r);
        ctx.lineTo(x + width, y + height - r);
        ctx.quadraticCurveTo(x + width, y + height, x + width - r, y + height);
        ctx.lineTo(x + r, y + height);
        ctx.quadraticCurveTo(x, y + height, x, y + height - r);
        ctx.lineTo(x, y + r);
        ctx.quadraticCurveTo(x, y, x + r, y);
        ctx.closePath();
    }

    groupStroke(group) {
        if (group === 'work') return '#2563eb';
        if (group === 'person') return '#d97706';
        if (group === 'material') return '#16a34a';
        return '#101828';
    }

    groupFill(group) {
        if (group === 'work') return '#eff6ff';
        if (group === 'person') return '#fef3c7';
        if (group === 'material') return '#ecfdf3';
        if (group === 'external' || group === 'entity') return '#f8fafc';
        return '#ffffff';
    }

    cssSafe(value) {
        return String(value).toLowerCase().replace(/[^a-z0-9_-]+/g, '-');
    }

    escapeXml(value) {
        return String(value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&apos;');
    }

    emitNodeSelect(node) {
        if (typeof this.options.onNodeSelect === 'function') {
            this.options.onNodeSelect(node);
        }
    }

    isSmallViewport() {
        return window.matchMedia && window.matchMedia('(max-width: 700px)').matches;
    }

    installResizeObserver() {
        if (this.resizeObserver) {
            this.resizeObserver.disconnect();
            this.resizeObserver = null;
        }

        if (typeof ResizeObserver === 'undefined') {
            return;
        }

        this.resizeObserver = new ResizeObserver(() => this.scheduleResponsiveResize(false));
        this.resizeObserver.observe(this.container);
    }

    scheduleResponsiveResize(refit = true) {
        window.clearTimeout(this.resizeTimer);
        this.resizeTimer = window.setTimeout(() => {
            if (this.network) {
                const w = this.container.clientWidth || window.innerWidth || 0;
                const h = this.container.clientHeight || 0;
                const changed = Math.abs(w - this.lastNetworkWidth) > 8 || Math.abs(h - this.lastNetworkHeight) > 8;
                this.lastNetworkWidth = w;
                this.lastNetworkHeight = h;
                this.network.redraw();
                if (refit && changed && !this.isInteracting) {
                    this.network.fit({ animation: { duration: 220, easingFunction: 'easeInOutQuad' } });
                }
                return;
            }

            if (this.currentNodes.length > 0 && this.container.querySelector('.html-graph') && !this.isInteracting) {
                const w = this.container.clientWidth || window.innerWidth || 0;
                const h = this.container.clientHeight || 0;
                if (Math.abs(w - this.lastFallbackWidth) < 8 && Math.abs(h - this.lastFallbackHeight) < 8) {
                    return;
                }
                this.renderWithHtmlFallback(this.currentNodes, this.currentEdges);
            }
        }, 120);
    }

    fit() {
        if (this.network) {
            this.network.fit({ animation: true });
            return;
        }

        if (this.fallbackWrapper) {
            const rect = this.container.getBoundingClientRect();
            const graphWidth = this.lastFallbackWidth || rect.width || 1;
            const graphHeight = this.lastFallbackHeight || rect.height || 1;
            const scale = Math.max(0.25, Math.min(1, rect.width / graphWidth, rect.height / graphHeight));
            this.fallbackPanZoomState = {
                x: Math.max(0, (rect.width - graphWidth * scale) / 2),
                y: Math.max(0, (rect.height - graphHeight * scale) / 2),
                scale,
            };
            this.applyFallbackTransform();
        }
    }

    zoomBy(factor) {
        if (this.network) {
            const currentScale = this.network.getScale ? this.network.getScale() : 1;
            const position = this.network.getViewPosition ? this.network.getViewPosition() : undefined;
            const nextScale = Math.max(0.1, Math.min(5, currentScale * factor));
            this.network.moveTo({
                position,
                scale: nextScale,
                animation: { duration: 160, easingFunction: 'easeInOutQuad' },
            });
            return;
        }

        if (this.fallbackWrapper) {
            const rect = this.container.getBoundingClientRect();
            const state = this.fallbackPanZoomState;
            const point = { x: rect.width / 2, y: rect.height / 2 };
            const oldScale = state.scale;
            const newScale = Math.max(0.25, Math.min(4, oldScale * factor));
            state.x = point.x - ((point.x - state.x) * (newScale / oldScale));
            state.y = point.y - ((point.y - state.y) * (newScale / oldScale));
            state.scale = newScale;
            this.applyFallbackTransform();
        }
    }

    restartLayout() {
        if (!this.network) {
            this.fit();
            return;
        }

        this.network.setOptions({ physics: { enabled: true } });
        this.network.stabilize(260);
        this.network.once('stabilized', () => {
            this.network.setOptions({ physics: false });
        });
    }

    destroyNetwork() {
        if (this.fallbackPanZoomCleanup) {
            this.fallbackPanZoomCleanup();
            this.fallbackPanZoomCleanup = null;
        }

        if (this.interactionTimer) {
            window.clearTimeout(this.interactionTimer);
            this.interactionTimer = null;
        }

        if (this.resizeObserver) {
            this.resizeObserver.disconnect();
            this.resizeObserver = null;
        }

        if (this.network) {
            this.network.destroy();
            this.network = null;
        }

        this.container.classList.remove('has-vis-renderer', 'has-html-renderer');
        this.currentRenderer = null;
    }

    clear(message = 'Grafo vacío.') {
        this.destroyNetwork();
        this.currentNodes = [];
        this.currentEdges = [];
        this.container.innerHTML = '<p class="empty-message">' + message + '</p>';
        this.emitNodeSelect(null);
    }
}

window.KnowledgeMapGraphViewer = KnowledgeMapGraphViewer;
