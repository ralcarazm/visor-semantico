/* global VisorSemanticoGraphViewer */

(function () {
    const status = document.getElementById('status');
    const btnPing = document.getElementById('btn-ping');
    const btnDebug = document.getElementById('btn-debug');
    const btnLoadSample = document.getElementById('btn-load-sample');
    const btnClear = document.getElementById('btn-clear');
    const btnClearCache = document.getElementById('btn-clear-cache');
    const btnToggleJson = document.getElementById('btn-toggle-json');
    const btnToggleTriples = document.getElementById('btn-toggle-triples');
    const btnApplyFilters = document.getElementById('btn-apply-filters');
    const btnResetFilters = document.getElementById('btn-reset-filters');
    const btnExportJson = document.getElementById('btn-export-json');
    const btnExportSvg = document.getElementById('btn-export-svg');
    const btnExportPng = document.getElementById('btn-export-png');
    const btnExportWebp = document.getElementById('btn-export-webp');
    const btnZoomOut = document.getElementById('btn-zoom-out');
    const btnZoomIn = document.getElementById('btn-zoom-in');
    const btnFit = document.getElementById('btn-fit');
    const btnRestartLayout = document.getElementById('btn-restart-layout');
    const fileInput = document.getElementById('rdf-file');
    const btnUpload = document.getElementById('btn-upload');
    const graphElement = document.getElementById('graph');
    const enrichCheckbox = document.getElementById('enrich-wikidata');
    const includeTriplesCheckbox = document.getElementById('include-triples');
    const summary = document.getElementById('summary');
    const jsonPanel = document.getElementById('json-panel');
    const jsonOutput = document.getElementById('json-output');
    const triplesPanel = document.getElementById('triples-panel');
    const triplesTableBody = document.querySelector('#triples-table tbody');
    const focusNode = document.getElementById('focus-node');
    const depthFilter = document.getElementById('depth-filter');
    const groupFilters = document.getElementById('group-filters');
    const predicateFilters = document.getElementById('predicate-filters');
    const nodeDetails = document.getElementById('node-details');

    const viewer = new KnowledgeMapGraphViewer(graphElement, {
        onNodeSelect: showNodeDetails,
    });

    let lastGraphData = null;
    let lastVisibleGraphData = null;

    function onClickIfExists(element, handler) {
        if (element) {
            element.addEventListener('click', handler);
        }
    }

    const groupLabels = {
        work: 'Obra',
        person: 'Persona',
        material: 'Material',
        image: 'Imagen',
        external: 'Entidad externa',
        entity: 'Entidad',
        literal: 'Literal',
    };

    function writeStatus(data) {
        status.textContent = typeof data === 'string'
            ? data
            : JSON.stringify(data, null, 2);
    }

    function setBusy(isBusy) {
        document.body.classList.toggle('is-busy', isBusy);
        [btnPing, btnDebug, btnLoadSample, btnClearCache, btnClear, btnUpload, btnApplyFilters, btnResetFilters, btnExportJson, btnExportSvg, btnExportPng, btnExportWebp, btnZoomOut, btnZoomIn, btnFit, btnRestartLayout].forEach((button) => {
            if (!button) return;
            if (button === btnUpload && fileInput.files.length === 0) {
                button.disabled = true;
                return;
            }
            if ([btnApplyFilters, btnResetFilters, btnExportJson, btnExportSvg, btnExportPng, btnExportWebp, btnZoomOut, btnZoomIn, btnFit, btnRestartLayout].includes(button) && !lastGraphData) {
                button.disabled = true;
                return;
            }
            button.disabled = isBusy;
        });
    }

    function setGraphControlsEnabled(enabled) {
        [focusNode, depthFilter, btnApplyFilters, btnResetFilters, btnExportJson, btnExportSvg, btnExportPng, btnExportWebp, btnZoomOut, btnZoomIn, btnFit, btnRestartLayout].forEach((control) => {
            if (control) control.disabled = !enabled;
        });
    }

    async function getJson(url, options = {}) {
        const response = await fetch(url, options);
        const text = await response.text();
        let data;

        try {
            data = JSON.parse(text);
        } catch (error) {
            throw new Error('La respuesta no es JSON válido. Respuesta recibida: ' + text.slice(0, 500));
        }

        if (!response.ok || data.status === 'error') {
            const extra = data.extra ? ' ' + JSON.stringify(data.extra) : '';
            throw new Error((data.message || 'Error en la petición.') + extra);
        }

        return data;
    }

    function withOptions(url) {
        const params = [];
        params.push('enrich=' + (enrichCheckbox.checked ? '1' : '0'));
        params.push('include_triples=' + (includeTriplesCheckbox.checked ? '1' : '0'));

        const separator = url.includes('?') ? '&' : '?';
        return url + separator + params.join('&');
    }

    async function loadGraph(url, label) {
        setBusy(true);
        try {
            writeStatus('Generando grafo: ' + label + '...');
            const data = await getJson(withOptions(url));
            lastGraphData = data;
            initialiseFilterControls(data);
            renderCurrentGraph();
            updateJson(data);
            updateTriples(data.triples || []);
            showNodeDetails(null);
            writeStatus(data.metadata || data);
            setGraphControlsEnabled(true);
        } finally {
            setBusy(false);
        }
    }

    function renderCurrentGraph() {
        if (!lastGraphData) return;

        const visible = buildFilteredGraph(lastGraphData);
        lastVisibleGraphData = visible;
        viewer.render(visible.nodes || [], visible.edges || []);
        updateSummary(lastGraphData.metadata || {}, visible);

        const totalNodes = (lastGraphData.nodes || []).length;
        const totalEdges = (lastGraphData.edges || []).length;
        writeStatus({
            ...(lastGraphData.metadata || {}),
            visible_nodes: `${visible.nodes.length}/${totalNodes}`,
            visible_edges: `${visible.edges.length}/${totalEdges}`,
        });
    }

    function initialiseFilterControls(data) {
        const nodes = data.nodes || [];
        const edges = data.edges || [];

        focusNode.innerHTML = '<option value="">Todo el grafo</option>';
        [...nodes].sort((a, b) => String(a.label || a.id).localeCompare(String(b.label || b.id), 'es')).forEach((node) => {
            const option = document.createElement('option');
            option.value = String(node.id);
            option.textContent = String(node.label || node.id);
            focusNode.appendChild(option);
        });
        focusNode.value = '';
        depthFilter.value = 'all';

        renderCheckboxList(
            groupFilters,
            [...new Set(nodes.map((node) => String(node.group || 'entity')))].sort(),
            'filter-group',
            (group) => groupLabels[group] || group,
        );

        const predicateMap = new Map();
        edges.forEach((edge) => {
            const key = predicateKey(edge);
            if (!predicateMap.has(key)) {
                predicateMap.set(key, String(edge.label || edge.title || key));
            }
        });

        renderCheckboxList(
            predicateFilters,
            [...predicateMap.keys()].sort((a, b) => predicateMap.get(a).localeCompare(predicateMap.get(b), 'es')),
            'filter-predicate',
            (key) => predicateMap.get(key),
        );
    }

    function renderCheckboxList(container, values, name, labelFactory) {
        container.innerHTML = '';
        container.classList.toggle('empty-filter', values.length === 0);

        if (!values.length) {
            container.textContent = 'No hay valores disponibles.';
            return;
        }

        values.forEach((value) => {
            const label = document.createElement('label');
            const input = document.createElement('input');
            const span = document.createElement('span');
            input.type = 'checkbox';
            input.name = name;
            input.value = value;
            input.checked = true;
            span.textContent = labelFactory(value);
            label.append(input, span);
            container.appendChild(label);
        });
    }

    function buildFilteredGraph(data) {
        const nodes = data.nodes || [];
        const edges = data.edges || [];
        const selectedGroups = selectedValues('filter-group');
        const selectedPredicates = selectedValues('filter-predicate');
        const focus = focusNode.value;
        const depth = depthFilter.value;

        let nodeIds = new Set(nodes.map((node) => String(node.id)));

        if (focus !== '' && depth !== 'all') {
            nodeIds = nodeIdsWithinDepth(focus, parseInt(depth, 10), edges);
        }

        nodeIds = new Set(nodes
            .filter((node) => nodeIds.has(String(node.id)))
            .filter((node) => selectedGroups.has(String(node.group || 'entity')))
            .map((node) => String(node.id)));

        const filteredEdges = edges.filter((edge) => {
            return nodeIds.has(String(edge.from))
                && nodeIds.has(String(edge.to))
                && selectedPredicates.has(predicateKey(edge));
        });

        const filteredNodes = nodes.filter((node) => nodeIds.has(String(node.id)));

        return {
            status: data.status || 'ok',
            nodes: filteredNodes,
            edges: filteredEdges,
            metadata: {
                ...(data.metadata || {}),
                visible_nodes_count: filteredNodes.length,
                visible_edges_count: filteredEdges.length,
                focus_node: focus || null,
                depth_filter: depth,
            },
        };
    }

    function selectedValues(name) {
        return new Set([...document.querySelectorAll(`input[name="${name}"]:checked`)].map((input) => input.value));
    }

    function predicateKey(edge) {
        return String(edge.title || edge.label || edge.id || 'relation');
    }

    function nodeIdsWithinDepth(startId, depth, edges) {
        const visited = new Set([startId]);
        let frontier = new Set([startId]);

        for (let level = 0; level < depth; level++) {
            const next = new Set();
            edges.forEach((edge) => {
                const from = String(edge.from);
                const to = String(edge.to);
                if (frontier.has(from) && !visited.has(to)) next.add(to);
                if (frontier.has(to) && !visited.has(from)) next.add(from);
            });
            next.forEach((id) => visited.add(id));
            frontier = next;
            if (frontier.size === 0) break;
        }

        return visited;
    }

    function updateSummary(metadata, visible = null) {
        const enrichment = metadata.enrichment || {};
        const wikidata = enrichment.enabled
            ? `${enrichment.wikidata_nodes_enriched || 0}/${enrichment.wikidata_nodes_detected || 0}`
            : 'desactivado';

        const totalNodes = metadata.nodes_count ?? '–';
        const totalEdges = metadata.edges_count ?? '–';
        const visibleNodes = visible ? `${visible.nodes.length}/${totalNodes}` : totalNodes;
        const visibleEdges = visible ? `${visible.edges.length}/${totalEdges}` : totalEdges;

        summary.innerHTML = '';
        [
            ['Tripletas', metadata.triples_count ?? '–'],
            ['Nodos visibles', visibleNodes],
            ['Relaciones visibles', visibleEdges],
            ['Wikidata', wikidata],
        ].forEach(([label, value]) => {
            const item = document.createElement('div');
            const span = document.createElement('span');
            const strong = document.createElement('strong');
            span.textContent = label;
            strong.textContent = String(value);
            item.append(span, strong);
            summary.appendChild(item);
        });
    }

    function updateJson(data) {
        jsonOutput.textContent = JSON.stringify(data, null, 2);
    }

    function updateTriples(triples) {
        triplesTableBody.innerHTML = '';

        if (!triples.length) {
            const row = document.createElement('tr');
            const cell = document.createElement('td');
            cell.colSpan = 5;
            cell.textContent = includeTriplesCheckbox.checked
                ? 'No se han incluido tripletas en la respuesta.'
                : 'Activa “Incluir tripletas leídas” y vuelve a cargar el grafo.';
            row.appendChild(cell);
            triplesTableBody.appendChild(row);
            return;
        }

        triples.forEach((triple) => {
            const row = document.createElement('tr');
            ['subject', 'predicate', 'object', 'object_type', 'lang'].forEach((field) => {
                const cell = document.createElement('td');
                cell.textContent = triple[field] || '';
                row.appendChild(cell);
            });
            triplesTableBody.appendChild(row);
        });
    }

    function togglePanel(panel, button, openLabel, closedLabel) {
        const shouldShow = panel.hidden;
        panel.hidden = !shouldShow;
        button.textContent = shouldShow ? closedLabel : openLabel;
    }

    function showNodeDetails(node) {
        nodeDetails.innerHTML = '<h3>Detalle del nodo</h3>';

        if (!node) {
            const p = document.createElement('p');
            p.textContent = 'Selecciona un nodo del grafo para ver sus datos principales.';
            nodeDetails.appendChild(p);
            return;
        }

        const title = document.createElement('h4');
        title.textContent = String(node.label || node.id || 'Nodo');
        nodeDetails.appendChild(title);

        if (node.image) {
            const img = document.createElement('img');
            img.src = String(node.image);
            img.alt = '';
            img.loading = 'lazy';
            nodeDetails.appendChild(img);
        }

        const dl = document.createElement('dl');
        addDetail(dl, 'Tipo', node.type_label || node.group || '');
        addDetail(dl, 'Descripción', node.description || '');
        addDetail(dl, 'Wikidata', node.wikidata_id || '');
        addDetail(dl, 'URI', node.uri || node.id || '');
        nodeDetails.appendChild(dl);
    }

    function addDetail(dl, label, value) {
        if (!value) return;
        const dt = document.createElement('dt');
        const dd = document.createElement('dd');
        dt.textContent = label;
        dd.textContent = String(value);
        dl.append(dt, dd);
    }

    function resetAllFilters() {
        focusNode.value = '';
        depthFilter.value = 'all';
        document.querySelectorAll('input[name="filter-group"], input[name="filter-predicate"]').forEach((input) => {
            input.checked = true;
        });
        renderCurrentGraph();
    }

    function exportVisibleJson() {
        if (!lastVisibleGraphData) {
            writeStatus('No hay grafo visible para exportar.');
            return;
        }

        const exportData = {
            ...lastVisibleGraphData,
            exported_at: new Date().toISOString(),
            export_note: 'Datos del grafo visible después de aplicar filtros en la interfaz. Sirve para reutilizar el grafo en otras herramientas o reimportarlo en desarrollos posteriores.',
        };
        downloadBlob('visorsemantico-visible.json', new Blob([JSON.stringify(exportData, null, 2)], { type: 'application/json' }));
    }

    function exportVisibleSvg() {
        if (!lastVisibleGraphData) {
            writeStatus('No hay grafo visible para exportar.');
            return;
        }

        const blob = viewer.exportSvgBlob();
        downloadBlob('visorsemantico-visible.svg', blob);
    }

    async function exportVisibleBitmap(format) {
        if (!lastVisibleGraphData) {
            writeStatus('No hay grafo visible para exportar.');
            return;
        }

        const isWebp = format === 'webp';
        const mimeType = isWebp ? 'image/webp' : 'image/png';
        const extension = isWebp ? 'webp' : 'png';

        setBusy(true);
        try {
            writeStatus('Generando ' + extension.toUpperCase() + ' transparente con imágenes de nodos...');
            const blob = await viewer.exportBitmapBlob(mimeType, 0.95);
            downloadBlob('visorsemantico-visible-transparent.' + extension, blob);
            writeStatus('Imagen ' + extension.toUpperCase() + ' exportada.');
        } catch (error) {
            writeStatus('Error al exportar imagen: ' + error.message);
        } finally {
            setBusy(false);
        }
    }

    function downloadBlob(filename, blob) {
        const url = URL.createObjectURL(blob);
        const link = document.createElement('a');
        link.href = url;
        link.download = filename;
        document.body.appendChild(link);
        link.click();
        link.remove();
        setTimeout(() => URL.revokeObjectURL(url), 1000);
    }

    btnPing.addEventListener('click', async () => {
        setBusy(true);
        try {
            writeStatus('Consultando api/ping.php...');
            const data = await getJson('api/ping.php');
            writeStatus(data);
        } catch (error) {
            writeStatus('Error: ' + error.message);
        } finally {
            setBusy(false);
        }
    });

    btnDebug.addEventListener('click', async () => {
        setBusy(true);
        try {
            writeStatus('Consultando api/debug.php...');
            const data = await getJson('api/debug.php');
            writeStatus(data);
            updateJson(data);
        } catch (error) {
            writeStatus('Error: ' + error.message);
        } finally {
            setBusy(false);
        }
    });

    btnLoadSample.addEventListener('click', async () => {
        try {
            const mode = enrichCheckbox.checked ? 'Hokusai con enriquecimiento externo' : 'Hokusai sin enriquecimiento externo';
            await loadGraph('api/graph.php?sample=hokusai.ttl', mode);
        } catch (error) {
            writeStatus('Error: ' + error.message);
        }
    });

    btnClearCache.addEventListener('click', async () => {
        setBusy(true);
        try {
            writeStatus('Limpiando caché de Wikidata/Commons...');
            const data = await getJson('api/cache-clear.php', { method: 'POST' });
            writeStatus(data);
        } catch (error) {
            writeStatus('Error: ' + error.message);
        } finally {
            setBusy(false);
        }
    });

    btnClear.addEventListener('click', () => {
        viewer.clear();
        lastGraphData = null;
        lastVisibleGraphData = null;
        updateSummary({});
        updateJson({ status: 'empty' });
        updateTriples([]);
        showNodeDetails(null);
        groupFilters.innerHTML = 'Carga un grafo para ver filtros.';
        predicateFilters.innerHTML = 'Carga un grafo para ver filtros.';
        focusNode.innerHTML = '<option value="">Todo el grafo</option>';
        setGraphControlsEnabled(false);
        writeStatus('Grafo limpiado.');
    });

    btnApplyFilters.addEventListener('click', renderCurrentGraph);
    btnResetFilters.addEventListener('click', resetAllFilters);
    btnExportJson.addEventListener('click', exportVisibleJson);
    btnExportSvg.addEventListener('click', exportVisibleSvg);
    btnExportPng.addEventListener('click', () => exportVisibleBitmap('png'));
    btnExportWebp.addEventListener('click', () => exportVisibleBitmap('webp'));
    onClickIfExists(btnZoomOut, () => viewer.zoomBy(0.82));
    onClickIfExists(btnZoomIn, () => viewer.zoomBy(1.22));
    onClickIfExists(btnFit, () => viewer.fit());
    onClickIfExists(btnRestartLayout, () => viewer.restartLayout());

    focusNode.addEventListener('change', renderCurrentGraph);
    depthFilter.addEventListener('change', renderCurrentGraph);
    groupFilters.addEventListener('change', renderCurrentGraph);
    predicateFilters.addEventListener('change', renderCurrentGraph);

    btnToggleJson.addEventListener('click', () => {
        togglePanel(jsonPanel, btnToggleJson, 'Ver JSON', 'Ocultar JSON');
        if (lastGraphData) {
            updateJson(lastGraphData);
        }
    });

    btnToggleTriples.addEventListener('click', () => {
        togglePanel(triplesPanel, btnToggleTriples, 'Ver tripletas', 'Ocultar tripletas');
    });

    fileInput.addEventListener('change', () => {
        btnUpload.disabled = fileInput.files.length === 0;
    });

    btnUpload.addEventListener('click', async () => {
        if (!fileInput.files.length) {
            writeStatus('Selecciona primero un fichero.');
            return;
        }

        const formData = new FormData();
        formData.append('file', fileInput.files[0]);

        setBusy(true);
        try {
            writeStatus('Subiendo fichero...');
            const uploadData = await getJson('api/upload.php', {
                method: 'POST',
                body: formData,
            });

            writeStatus('Fichero subido. Generando grafo...');
            await loadGraph(uploadData.graph_url, 'fichero subido');
        } catch (error) {
            writeStatus('Error: ' + error.message);
        } finally {
            setBusy(false);
        }
    });
})();
