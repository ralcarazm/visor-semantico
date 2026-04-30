<?php
declare(strict_types=1);

require __DIR__ . '/app/bootstrap.php';

$appName = km_config('app.name', 'Knowledge Map');
$appVersion = km_config('app.version', '0.13.0');
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title><?= htmlspecialchars($appName, ENT_QUOTES, 'UTF-8') ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <link rel="stylesheet" href="assets/css/app.css">
    <!--
        vis-network es opcional. Si el fichero local y el CDN no cargan, la
        aplicación usa un renderizador HTML/SVG propio para que el grafo no quede en blanco.
    -->
    <script src="assets/vendor/vis-network/vis-network.min.js"></script>
</head>
<body>
    <header class="app-header">
        <div>
            <h1><?= htmlspecialchars($appName, ENT_QUOTES, 'UTF-8') ?></h1>
            <p>Visualizador experimental de tripletas RDF como mapa de conocimiento.</p>
        </div>
        <span class="version">v<?= htmlspecialchars($appVersion, ENT_QUOTES, 'UTF-8') ?></span>
    </header>

    <main class="layout">
        <section class="panel">
            <h2>Controles</h2>

            <div class="control-group">
                <button id="btn-ping" type="button">Probar API</button>
                <button id="btn-debug" type="button">Diagnóstico del entorno</button>
                <button id="btn-load-sample" type="button">Cargar ejemplo</button>
                <button id="btn-clear-cache" type="button" class="secondary-button">Limpiar caché</button>
                <button id="btn-clear" type="button" class="secondary-button">Limpiar</button>
            </div>

            <div class="control-group checkbox-group">
                <label>
                    <input id="enrich-wikidata" type="checkbox" checked>
                    Enriquecer URIs de Wikidata y buscar imágenes en Commons
                </label>
            </div>

            <div class="control-group checkbox-group">
                <label>
                    <input id="include-triples" type="checkbox" checked>
                    Incluir tripletas leídas en la respuesta para depuración
                </label>
            </div>

            <div class="control-group">
                <label for="rdf-file">Fichero de tripletas</label>
                <input id="rdf-file" type="file" accept=".ttl,.nt,.n3,.rdf,.xml,.jsonld,.csv,.tsv">
                <button id="btn-upload" type="button" disabled>Subir y visualizar</button>
                <p class="sample-download">Formatos soportados por extensión: .ttl, .nt, .n3, .rdf, .xml, .jsonld, .csv y .tsv.</p>
            </div>

            <details class="advanced-controls" open>
                <summary>Filtros, foco y exportación</summary>

                <div class="control-group">
                    <label for="focus-node">Nodo central</label>
                    <select id="focus-node" disabled>
                        <option value="">Todo el grafo</option>
                    </select>
                </div>

                <div class="control-group">
                    <label>
                        Profundidad
                        <select id="depth-filter" disabled>
                            <option value="all">Todos los niveles</option>
                            <option value="0">Solo nodo central</option>
                            <option value="1">1 salto</option>
                            <option value="2" selected>2 saltos</option>
                            <option value="3">3 saltos</option>
                        </select>
                    </label>
                    <p class="interaction-help">Mueve el grafo arrastrando. Amplía o reduce con la rueda del ratón.</p>
                </div>

                <div class="filter-block">
                    <h3>Tipos de nodo</h3>
                    <div id="group-filters" class="filter-list empty-filter">Carga un grafo para ver filtros.</div>
                </div>

                <div class="filter-block">
                    <h3>Relaciones</h3>
                    <div id="predicate-filters" class="filter-list empty-filter">Carga un grafo para ver filtros.</div>
                </div>

                <div class="control-group split-buttons">
                    <button id="btn-apply-filters" type="button" disabled>Aplicar filtros</button>
                    <button id="btn-reset-filters" type="button" class="secondary-button" disabled>Restablecer</button>
                </div>

                <div class="control-group split-buttons export-buttons">
                    <button id="btn-export-json" type="button" class="secondary-button" disabled>Exportar datos del grafo (JSON)</button>
                    <button id="btn-export-svg" type="button" class="secondary-button" disabled>Exportar SVG</button>
                    <button id="btn-export-png" type="button" class="secondary-button" disabled>Exportar PNG</button>
                    <button id="btn-export-webp" type="button" class="secondary-button" disabled>Exportar WEBP</button>
                </div>
            </details>

            <div class="control-group split-buttons">
                <button id="btn-toggle-json" type="button" class="secondary-button">Ver JSON</button>
                <button id="btn-toggle-triples" type="button" class="secondary-button">Ver tripletas</button>
            </div>

            <div id="summary" class="summary" aria-live="polite">
                <div><span>Tripletas</span><strong>–</strong></div>
                <div><span>Nodos</span><strong>–</strong></div>
                <div><span>Relaciones</span><strong>–</strong></div>
                <div><span>Wikidata</span><strong>–</strong></div>
            </div>

            <section id="node-details" class="node-details" aria-live="polite">
                <h3>Detalle del nodo</h3>
                <p>Selecciona un nodo del grafo para ver sus datos principales.</p>
            </section>

            <div class="control-group small-help">
                <p><strong>Formatos soportados sin instalación adicional:</strong> Turtle básico, N-Triples, RDF/XML básico, JSON-LD básico, CSV y TSV.</p>
                <p>El parser integrado cubre RDF/XML y JSON-LD sencillos; si más adelante se instala EasyRDF, la aplicación puede usarlo de forma opcional.</p>
                <p>Si el servidor no puede acceder a Internet, el grafo se dibuja igualmente, pero sin enriquecimiento externo.</p>
            </div>

            <pre id="status" class="status" aria-live="polite">Preparado.</pre>
        </section>

        <section class="viewer-panel">
            <section class="viewer">
                <div id="graph" class="graph" role="region" aria-label="Visualizador del mapa de conocimiento">
                    <p class="empty-message">Pulsa "Cargar ejemplo" para probar un ejemplo de visualización, o sube un fichero propio.</p>
                </div>
            </section>

            <section id="json-panel" class="data-panel" hidden>
                <h2>JSON generado</h2>
                <pre id="json-output">Todavía no se ha generado ningún grafo.</pre>
            </section>

            <section id="triples-panel" class="data-panel" hidden>
                <h2>Tripletas leídas</h2>
                <div class="table-wrapper">
                    <table id="triples-table">
                        <thead>
                            <tr>
                                <th>Sujeto</th>
                                <th>Predicado</th>
                                <th>Objeto</th>
                                <th>Tipo</th>
                                <th>Lengua</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr><td colspan="5">Todavía no se ha generado ningún grafo.</td></tr>
                        </tbody>
                    </table>
                </div>
            </section>
        </section>
    </main>

    <script src="assets/js/graph-viewer.js"></script>
    <script src="assets/js/app.js"></script>
</body>
</html>
