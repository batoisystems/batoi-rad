<?php
$seed = $this->runData['data']['diagram_seed'] ?? [
    'nodes' => [],
    'edges' => [],
    'summary' => [
        'node_total' => 0,
        'edge_total' => 0,
        'node_counts_by_type' => ['ms' => 0, 'route' => 0, 'controller' => 0],
        'orphan_count' => 0,
        'component_count' => 0,
        'inferred_edge_count' => 0,
    ],
    'top_degree' => [],
    'generated_at' => '',
    'seed_version' => 'v2',
    'truncation' => [
        'is_truncated' => false,
        'microservices' => false,
        'routes' => false,
        'controllers' => false,
    ],
    'limits' => [
        'microservices' => 200,
        'routes' => 400,
        'controllers' => 400,
    ],
];
?>

<style>
:root {
    --diag-ms: #0d6efd;
    --diag-route: #6f42c1;
    --diag-controller: #198754;
    --diag-edge: #d0d7de;
    --diag-node-dim: rgba(108, 117, 125, 0.28);
}
.diag-legend-dot {
    display: inline-block;
    width: 0.75rem;
    height: 0.75rem;
    border-radius: 50%;
    margin-right: 0.375rem;
}
.diag-legend-ms { background: var(--diag-ms); }
.diag-legend-route { background: var(--diag-route); }
.diag-legend-controller { background: var(--diag-controller); }
.diag-summary-chip {
    border: 1px solid #e3e8ef;
    border-radius: 999px;
    font-size: 0.75rem;
    padding: 0.2rem 0.55rem;
    background: #f8fafc;
}
</style>

<div class="alert alert-info d-flex align-items-start">
    <div class="me-2"><i class="bi bi-diagram-3" aria-hidden="true"></i></div>
    <div class="small">
        Visualizes services, routes, and controllers detected from the database. Use filters, search, and highlighting to inspect architecture hotspots.
    </div>
</div>

<div class="row g-3">
    <div class="col-lg-3">
        <div class="card h-100">
            <div class="card-body">
                <h2 class="h6 mb-3">Filters</h2>
                <div class="mb-3">
                    <label class="form-label form-label-sm" for="nodeSearch">Find node</label>
                    <div class="input-group input-group-sm">
                        <input id="nodeSearch" type="search" class="form-control" placeholder="Search label, ID, or UID" aria-label="Search node by label ID or UID">
                        <button class="btn btn-outline-secondary" id="focusNodeBtn" type="button" title="Focus first matching node" aria-label="Focus matching node"><i class="bi bi-search"></i></button>
                    </div>
                    <div id="searchFeedback" class="small text-muted mt-1">Press Enter to focus.</div>
                </div>

                <div class="mb-3">
                    <label class="form-label form-label-sm">Node types</label>
                    <div class="d-flex gap-2 flex-wrap">
                        <div class="form-check form-check-inline">
                            <input class="form-check-input filter-type" type="checkbox" value="ms" id="filterMs" checked>
                            <label class="form-check-label" for="filterMs">Microservices</label>
                        </div>
                        <div class="form-check form-check-inline">
                            <input class="form-check-input filter-type" type="checkbox" value="route" id="filterRoute" checked>
                            <label class="form-check-label" for="filterRoute">Routes</label>
                        </div>
                        <div class="form-check form-check-inline">
                            <input class="form-check-input filter-type" type="checkbox" value="controller" id="filterController" checked>
                            <label class="form-check-label" for="filterController">Controllers</label>
                        </div>
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label form-label-sm" for="filterMsSelect">Microservice focus</label>
                    <select class="form-select form-select-sm" id="filterMsSelect" aria-label="Filter by microservice">
                        <option value="">Choose microservice</option>
                    </select>
                </div>

                <div class="mb-3">
                    <label class="form-label form-label-sm" for="depthRange">Depth (hops)</label>
                    <input type="range" min="1" max="3" step="1" class="form-range" id="depthRange" value="2" aria-label="Traversal depth in hops">
                    <div class="small text-muted">Depth: <span id="depthLabel">2</span></div>
                </div>

                <div class="mb-3">
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" id="orphansOnly" aria-label="Show orphan nodes only">
                        <label class="form-check-label" for="orphansOnly">Show orphans only</label>
                    </div>
                    <div class="small text-muted">Orphans are nodes with no visible connections.</div>
                </div>

                <div class="mb-3">
                    <label class="form-label form-label-sm" for="neighborMode">Neighbor highlight</label>
                    <select class="form-select form-select-sm" id="neighborMode" aria-label="Highlight neighbor mode">
                        <option value="none">Off</option>
                        <option value="1hop">Selected + 1 hop</option>
                    </select>
                </div>

                <div class="mb-3">
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" id="showInferred" aria-label="Show inferred route controller links">
                        <label class="form-check-label" for="showInferred">Show inferred links</label>
                    </div>
                    <div class="small text-muted">Includes heuristics (name-matched route and controller pairs).</div>
                </div>

                <div class="d-flex gap-2">
                    <button class="btn btn-outline-secondary btn-sm w-100" id="redrawDiagram" aria-label="Redraw diagram"><i class="bi bi-arrow-clockwise"></i> Redraw</button>
                </div>

                <hr>
                <h2 class="h6 mb-2">Saved Views</h2>
                <div class="mb-2">
                    <select class="form-select form-select-sm" id="diagPresetSelect" aria-label="Saved views"></select>
                </div>
                <div class="input-group input-group-sm">
                    <input type="text" class="form-control" id="diagPresetName" placeholder="Preset name" aria-label="Preset name">
                    <button class="btn btn-outline-primary" id="diagPresetSave" type="button">Save</button>
                </div>
                <div class="d-flex gap-2 mt-2">
                    <button class="btn btn-outline-secondary btn-sm w-100" id="diagPresetLoad" type="button">Load</button>
                    <button class="btn btn-outline-danger btn-sm w-100" id="diagPresetDelete" type="button">Delete</button>
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-body">
                <div class="d-flex gap-2 flex-wrap mb-2" id="summaryStrip">
                    <span class="diag-summary-chip">MS: <span id="sumMs"><?php echo (int)($seed['summary']['node_counts_by_type']['ms'] ?? 0); ?></span></span>
                    <span class="diag-summary-chip">Routes: <span id="sumRoute"><?php echo (int)($seed['summary']['node_counts_by_type']['route'] ?? 0); ?></span></span>
                    <span class="diag-summary-chip">Controllers: <span id="sumController"><?php echo (int)($seed['summary']['node_counts_by_type']['controller'] ?? 0); ?></span></span>
                    <span class="diag-summary-chip">Orphans: <span id="sumOrphans"><?php echo (int)($seed['summary']['orphan_count'] ?? 0); ?></span></span>
                    <span class="diag-summary-chip">Components: <span id="sumComponents"><?php echo (int)($seed['summary']['component_count'] ?? 0); ?></span></span>
                    <span class="diag-summary-chip">Inferred edges: <span id="sumInferred"><?php echo (int)($seed['summary']['inferred_edge_count'] ?? 0); ?></span></span>
                </div>
                <div class="small text-muted mb-2" id="seedMeta">
                    Seed: <span id="seedVersion"><?php echo htmlspecialchars((string)($seed['seed_version'] ?? 'v2')); ?></span>
                    <?php if (!empty($seed['generated_at'])): ?>
                        • Generated: <span id="seedGeneratedAt"><?php echo htmlspecialchars((string)$seed['generated_at']); ?></span>
                    <?php endif; ?>
                </div>
                <?php if (!empty($seed['top_degree']) && is_array($seed['top_degree'])): ?>
                    <div class="small text-muted mb-2">
                        Top connected:
                        <?php
                        $topItems = [];
                        foreach ($seed['top_degree'] as $item) {
                            $label = (string)($item['label'] ?? $item['id'] ?? '');
                            $deg = (int)($item['degree'] ?? 0);
                            if ($label !== '') {
                                $topItems[] = htmlspecialchars($label) . ' (' . $deg . ')';
                            }
                        }
                        echo implode(', ', $topItems);
                        ?>
                    </div>
                <?php endif; ?>

                <?php if (!empty($seed['truncation']['is_truncated'])): ?>
                    <div class="alert alert-warning py-2 small mb-3">
                        Diagram data may be truncated due to limits
                        (MS: <?php echo (int)($seed['limits']['microservices'] ?? 200); ?>,
                        Routes: <?php echo (int)($seed['limits']['routes'] ?? 400); ?>,
                        Controllers: <?php echo (int)($seed['limits']['controllers'] ?? 400); ?>).
                    </div>
                <?php endif; ?>

                <div class="d-flex justify-content-between align-items-center mb-2 gap-3 flex-wrap">
                    <div class="d-flex align-items-center gap-2 flex-wrap">
                        <div class="text-muted small">Nodes: <span id="nodeCount"><?php echo count($seed['nodes'] ?? []); ?></span> • Edges: <span id="edgeCount"><?php echo count($seed['edges'] ?? []); ?></span></div>
                        <div class="text-muted small" id="selectionStatus" style="min-width: 150px;">No node selected</div>
                        <div class="text-muted small" id="perfStatus"></div>
                    </div>
                    <div class="d-flex gap-2 align-items-center flex-wrap">
                        <div class="btn-group btn-group-sm" role="group" aria-label="Zoom controls">
                            <button class="btn btn-outline-secondary" id="zoomIn" title="Zoom in" aria-label="Zoom in"><i class="bi bi-zoom-in"></i></button>
                            <button class="btn btn-outline-secondary" id="zoomOut" title="Zoom out" aria-label="Zoom out"><i class="bi bi-zoom-out"></i></button>
                            <button class="btn btn-outline-secondary" id="zoomReset" title="Reset view" aria-label="Reset view"><i class="bi bi-aspect-ratio"></i></button>
                        </div>
                        <div class="btn-group btn-group-sm" role="group" aria-label="Export controls">
                            <button class="btn btn-outline-secondary" id="exportPng" aria-label="Export PNG"><i class="bi bi-download"></i> PNG</button>
                            <button class="btn btn-outline-secondary" id="exportSvg" aria-label="Export SVG">SVG</button>
                            <button class="btn btn-outline-secondary" id="exportJson" aria-label="Export JSON">JSON</button>
                            <button class="btn btn-outline-secondary" id="exportCsv" aria-label="Export CSV">CSV</button>
                        </div>
                    </div>
                </div>
                <canvas id="diagramCanvas" class="border rounded w-100 mb-3" style="min-height:480px;"></canvas>
                <details>
                    <summary class="small text-muted">Raw data</summary>
                    <pre class="small mb-0" id="diagramSeed" style="max-height: 200px; overflow:auto;"><?php echo htmlspecialchars(json_encode($seed, JSON_PRETTY_PRINT)); ?></pre>
                </details>
            </div>
        </div>
    </div>

    <div class="col-lg-3">
        <div class="card h-100">
            <div class="card-body">
                <h2 class="h6 mb-3">Details</h2>
                <div id="metaDefault" class="text-muted small">
                    Select a node to see its details and connections.
                    <div class="mt-2">
                        <span class="diag-legend-dot diag-legend-ms" aria-hidden="true"></span><span class="text-muted">Microservice</span><br>
                        <span class="diag-legend-dot diag-legend-route" aria-hidden="true"></span><span class="text-muted">Route</span><br>
                        <span class="diag-legend-dot diag-legend-controller" aria-hidden="true"></span><span class="text-muted">Controller</span><br>
                        <span class="text-muted">Dashed edge = inferred link</span>
                    </div>
                </div>
                <div id="metaDetails" class="d-none">
                    <div class="fw-semibold" id="metaTitle"></div>
                    <div class="text-muted small mb-2" id="metaType"></div>
                    <div class="small mb-1"><span class="text-muted">Connected nodes:</span> <span id="metaDegree"></span></div>
                    <div class="small mb-1"><span class="text-muted">ID:</span> <code id="metaId"></code></div>
                    <div class="small mb-1 d-none" id="metaUidRow"><span class="text-muted">UID:</span> <a href="#" id="metaUidLink" target="_blank"></a></div>
                    <div class="small mb-1 d-none" id="metaScopeRow"><span class="text-muted">Scope:</span> <span id="metaScope"></span></div>
                    <div class="small mb-1 d-none" id="metaAccessRow"><span class="text-muted">Access:</span> <span id="metaAccess"></span></div>
                    <div class="small mb-1 d-none" id="metaEntityScopeRow"><span class="text-muted">Entity Scope:</span> <span id="metaEntityScope"></span></div>
                    <div class="small mb-1 d-none" id="metaDegreeRow"><span class="text-muted">Degree:</span> <span id="metaDegreeVal"></span></div>
                    <div class="small mb-1 d-none" id="metaCtrlTypeRow"><span class="text-muted">Controller Type:</span> <span id="metaCtrlType"></span></div>
                </div>
            </div>
        </div>
    </div>
</div>

<script><?php include __DIR__ . '/devguide-diagram-utils.js.php'; ?></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const diagramUtils = window.RadDiagramUtils || {};
    const data = <?php echo json_encode($seed); ?>;
    const allNodes = Array.isArray(data.nodes) ? data.nodes : [];
    const allEdges = Array.isArray(data.edges) ? data.edges : [];
    const canvas = document.getElementById('diagramCanvas');
    const redrawBtn = document.getElementById('redrawDiagram');
    const exportPngBtn = document.getElementById('exportPng');
    const exportSvgBtn = document.getElementById('exportSvg');
    const exportJsonBtn = document.getElementById('exportJson');
    const exportCsvBtn = document.getElementById('exportCsv');
    const zoomInBtn = document.getElementById('zoomIn');
    const zoomOutBtn = document.getElementById('zoomOut');
    const zoomResetBtn = document.getElementById('zoomReset');
    const typeChecks = document.querySelectorAll('.filter-type');
    const msSelect = document.getElementById('filterMsSelect');
    const depthRange = document.getElementById('depthRange');
    const depthLabel = document.getElementById('depthLabel');
    const orphansOnly = document.getElementById('orphansOnly');
    const neighborMode = document.getElementById('neighborMode');
    const showInferred = document.getElementById('showInferred');
    const searchInput = document.getElementById('nodeSearch');
    const focusNodeBtn = document.getElementById('focusNodeBtn');
    const searchFeedback = document.getElementById('searchFeedback');
    const presetSelect = document.getElementById('diagPresetSelect');
    const presetName = document.getElementById('diagPresetName');
    const presetSaveBtn = document.getElementById('diagPresetSave');
    const presetLoadBtn = document.getElementById('diagPresetLoad');
    const presetDeleteBtn = document.getElementById('diagPresetDelete');
    const nodeCount = document.getElementById('nodeCount');
    const edgeCount = document.getElementById('edgeCount');
    const sumMs = document.getElementById('sumMs');
    const sumRoute = document.getElementById('sumRoute');
    const sumController = document.getElementById('sumController');
    const sumOrphans = document.getElementById('sumOrphans');
    const sumComponents = document.getElementById('sumComponents');
    const sumInferred = document.getElementById('sumInferred');
    const selectionStatus = document.getElementById('selectionStatus');
    const perfStatus = document.getElementById('perfStatus');
    const metaDefault = document.getElementById('metaDefault');
    const metaDetails = document.getElementById('metaDetails');
    const metaTitle = document.getElementById('metaTitle');
    const metaType = document.getElementById('metaType');
    const metaDegree = document.getElementById('metaDegree');
    const metaId = document.getElementById('metaId');
    if (!canvas || !allNodes.length) return;

    const ctx = canvas.getContext('2d');
    const tooltip = document.createElement('div');
    tooltip.style.position = 'absolute';
    tooltip.style.padding = '4px 8px';
    tooltip.style.background = 'rgba(15, 23, 42, 0.9)';
    tooltip.style.color = '#fff';
    tooltip.style.fontSize = '12px';
    tooltip.style.borderRadius = '4px';
    tooltip.style.pointerEvents = 'none';
    tooltip.style.display = 'none';
    canvas.parentElement.style.position = 'relative';
    canvas.parentElement.appendChild(tooltip);

    const baseUrl = "<?php echo $this->runData['route']['rad_admin_url'] ?? ''; ?>";
    const nodeRadius = 18;
    const state = {
        view: { scale: 1, tx: 0, ty: 0 },
        selectedId: '',
        hoveredId: '',
        isPanning: false,
        movedWhilePanning: false,
        lastMouse: null,
    };

    const palette = {
        ms: '#0d6efd',
        route: '#6f42c1',
        controller: '#198754',
        edge: '#d0d7de',
        nodeDim: 'rgba(108,117,125,0.28)',
    };

    let currentGraph = {
        nodes: [],
        edges: [],
        degreeMap: {},
        nodeById: {},
        adj: {},
        stats: {
            typeCounts: { ms: 0, route: 0, controller: 0 },
            orphanCount: 0,
            componentCount: 0,
            inferredEdgeCount: 0,
        }
    };
    let drawnNodes = [];
    const PERF_NODE_THRESHOLD = 140;
    const PERF_EDGE_THRESHOLD = 260;
    const PERF_LABEL_THRESHOLD = 180;
    const PERF_EDGE_TARGET = 240;
    const PROGRESSIVE_EDGE_THRESHOLD = 180;
    const PROGRESSIVE_EDGE_BATCH = 56;
    const WORKER_EDGE_THRESHOLD = 260;
    const PRESET_STORAGE_KEY = 'rad.devguide.diagrams.presets.v1';
    let presets = [];
    let renderSeq = 0;
    let edgeWorker = null;
    let edgeWorkerReqId = 0;
    const edgeWorkerPending = {};
    if (diagramUtils.createEdgePlannerWorker) {
        edgeWorker = diagramUtils.createEdgePlannerWorker();
    }
    if (edgeWorker) {
        edgeWorker.onmessage = function(ev) {
            const data = ev && ev.data ? ev.data : {};
            if (data.type !== 'planResult') return;
            const reqId = Number(data.reqId || 0);
            const resolver = edgeWorkerPending[reqId];
            if (typeof resolver === 'function') {
                delete edgeWorkerPending[reqId];
                resolver(Array.isArray(data.jobs) ? data.jobs : []);
            }
        };
    }

    function resizeCanvas() {
        const rect = canvas.getBoundingClientRect();
        canvas.width = rect.width;
        canvas.height = rect.height;
        if (!state.view.tx && !state.view.ty) {
            state.view.tx = canvas.width / 2;
            state.view.ty = canvas.height / 2;
        }
    }

    function getAllowedTypes() {
        return Array.from(typeChecks).filter(c => c.checked).map(c => c.value);
    }

    function requestEdgePlanFromWorker(payload) {
        if (!edgeWorker) {
            return Promise.resolve(null);
        }
        const reqId = ++edgeWorkerReqId;
        return new Promise(resolve => {
            edgeWorkerPending[reqId] = resolve;
            edgeWorker.postMessage({
                type: 'plan',
                reqId,
                edges: payload.edges || [],
                positions: payload.positions || {},
                highlightIds: payload.highlightIds || [],
            });
            setTimeout(() => {
                if (edgeWorkerPending[reqId]) {
                    delete edgeWorkerPending[reqId];
                    resolve(null);
                }
            }, 1200);
        });
    }

    function buildGraph() {
        const allowedTypes = getAllowedTypes();
        const msFilter = msSelect.value;
        const wantOrphans = !!orphansOnly.checked;

        let nodes = allNodes.filter(n => allowedTypes.includes(n.type));
        const nodeById = {};
        nodes.forEach(n => { nodeById[n.id] = n; });

        let edges = allEdges.filter(e => nodeById[e.from] && nodeById[e.to]);
        if (!showInferred.checked) {
            edges = edges.filter(e => !(e && e.meta && e.meta.inferred));
        }

        if (msFilter && nodeById[msFilter]) {
            const depth = parseInt(depthRange.value, 10) || 1;
            const neighbors = new Set([msFilter]);
            const queue = [{ id: msFilter, d: 0 }];
            while (queue.length > 0) {
                const item = queue.shift();
                if (!item || item.d >= depth) {
                    continue;
                }
                edges.forEach(e => {
                    if (e.from === item.id && !neighbors.has(e.to)) {
                        neighbors.add(e.to);
                        queue.push({ id: e.to, d: item.d + 1 });
                    } else if (e.to === item.id && !neighbors.has(e.from)) {
                        neighbors.add(e.from);
                        queue.push({ id: e.from, d: item.d + 1 });
                    }
                });
            }
            nodes = nodes.filter(n => neighbors.has(n.id));
            const visible = new Set(nodes.map(n => n.id));
            edges = edges.filter(e => visible.has(e.from) && visible.has(e.to));
        }

        const degreeMap = {};
        const adj = {};
        nodes.forEach(n => {
            degreeMap[n.id] = 0;
            adj[n.id] = new Set();
        });
        edges.forEach(e => {
            if (degreeMap[e.from] != null) {
                degreeMap[e.from] += 1;
                adj[e.from].add(e.to);
            }
            if (degreeMap[e.to] != null) {
                degreeMap[e.to] += 1;
                adj[e.to].add(e.from);
            }
        });

        if (wantOrphans) {
            nodes = nodes.filter(n => (degreeMap[n.id] || 0) === 0);
            const visible = new Set(nodes.map(n => n.id));
            edges = edges.filter(e => visible.has(e.from) && visible.has(e.to));
        }

        const visibleById = {};
        nodes.forEach(n => { visibleById[n.id] = n; });
        const orphanCount = nodes.filter(n => (degreeMap[n.id] || 0) === 0).length;

        let componentCount = 0;
        const visited = new Set();
        nodes.forEach(n => {
            if (visited.has(n.id)) {
                return;
            }
            componentCount += 1;
            const queue = [n.id];
            visited.add(n.id);
            while (queue.length > 0) {
                const current = queue.shift();
                if (!current || !adj[current]) {
                    continue;
                }
                adj[current].forEach(next => {
                    if (!visited.has(next) && visibleById[next]) {
                        visited.add(next);
                        queue.push(next);
                    }
                });
            }
        });

        const filteredTypeCounts = { ms: 0, route: 0, controller: 0 };
        nodes.forEach(n => {
            if (Object.prototype.hasOwnProperty.call(filteredTypeCounts, n.type)) {
                filteredTypeCounts[n.type] += 1;
            }
        });
        const inferredEdgeCount = edges.filter(e => !!(e && e.meta && e.meta.inferred)).length;

        return {
            nodes,
            edges,
            degreeMap,
            nodeById: visibleById,
            adj,
            stats: {
                typeCounts: filteredTypeCounts,
                orphanCount,
                componentCount,
                inferredEdgeCount,
            }
        };
    }

    function layoutNodes(nodes) {
        const centerX = 0;
        const centerY = 0;
        const msNodes = nodes.filter(n => n.type === 'ms');
        const routeNodes = nodes.filter(n => n.type === 'route');
        const ctlNodes = nodes.filter(n => n.type === 'controller');

        const layers = [
            { list: msNodes, radius: Math.min(canvas.width, canvas.height) * 0.18 },
            { list: routeNodes, radius: Math.min(canvas.width, canvas.height) * 0.30 },
            { list: ctlNodes, radius: Math.min(canvas.width, canvas.height) * 0.42 },
        ];

        const laidOut = [];
        layers.forEach(layer => {
            const count = Math.max(1, layer.list.length);
            layer.list.forEach((node, idx) => {
                const angle = (idx / count) * Math.PI * 2;
                const x = centerX + layer.radius * Math.cos(angle);
                const y = centerY + layer.radius * Math.sin(angle);
                laidOut.push({ ...node, x, y });
            });
        });

        return laidOut;
    }

    function shortLabel(text, maxLen) {
        const t = String(text || '');
        if (t.length <= maxLen) {
            return t;
        }
        return t.slice(0, Math.max(1, maxLen - 1)) + '…';
    }

    function getHighlightSet() {
        const mode = neighborMode.value;
        if (mode !== '1hop' || !state.selectedId || !currentGraph.adj[state.selectedId]) {
            return null;
        }
        const set = new Set([state.selectedId]);
        currentGraph.adj[state.selectedId].forEach(id => set.add(id));
        return set;
    }

    function syncUrlState() {
        const params = new URLSearchParams(window.location.search || '');
        const types = getAllowedTypes();
        if (types.length && types.length < 3) {
            params.set('types', types.join(','));
        } else {
            params.delete('types');
        }
        if (msSelect.value) {
            params.set('ms', msSelect.value);
        } else {
            params.delete('ms');
        }
        params.set('depth', String(depthRange.value || '2'));
        if (orphansOnly.checked) {
            params.set('orphans', '1');
        } else {
            params.delete('orphans');
        }
        if (neighborMode.value && neighborMode.value !== 'none') {
            params.set('nmode', neighborMode.value);
        } else {
            params.delete('nmode');
        }
        if (showInferred.checked) {
            params.set('inferred', '1');
        } else {
            params.delete('inferred');
        }
        const q = String(searchInput.value || '').trim();
        if (q) {
            params.set('q', q);
        } else {
            params.delete('q');
        }
        if (state.selectedId) {
            params.set('sel', state.selectedId);
        } else {
            params.delete('sel');
        }
        const query = params.toString();
        const nextUrl = `${window.location.pathname}${query ? `?${query}` : ''}${window.location.hash || ''}`;
        window.history.replaceState(null, '', nextUrl);
    }

    function applyUrlState() {
        const params = new URLSearchParams(window.location.search || '');
        const typeParam = String(params.get('types') || '').trim();
        if (typeParam) {
            const set = new Set(typeParam.split(',').map(v => v.trim()).filter(Boolean));
            typeChecks.forEach(c => {
                c.checked = set.has(c.value);
            });
        }
        const msParam = String(params.get('ms') || '').trim();
        if (msParam && Array.from(msSelect.options).some(o => o.value === msParam)) {
            msSelect.value = msParam;
        }
        const depthParam = parseInt(String(params.get('depth') || ''), 10);
        if (!Number.isNaN(depthParam) && depthParam >= 1 && depthParam <= 3) {
            depthRange.value = String(depthParam);
            depthLabel.textContent = String(depthParam);
        }
        orphansOnly.checked = String(params.get('orphans') || '') === '1';
        const modeParam = String(params.get('nmode') || '').trim();
        if (modeParam && Array.from(neighborMode.options).some(o => o.value === modeParam)) {
            neighborMode.value = modeParam;
        }
        showInferred.checked = String(params.get('inferred') || '') === '1';
        const qParam = String(params.get('q') || '').trim();
        if (qParam) {
            searchInput.value = qParam;
        }
        const selParam = String(params.get('sel') || '').trim();
        if (selParam) {
            state.selectedId = selParam;
        }
    }

    function draw(syncUrl = true) {
        currentGraph = buildGraph();
        if (state.selectedId && !currentGraph.nodeById[state.selectedId]) {
            state.selectedId = '';
            selectionStatus.textContent = 'No node selected';
            metaDetails.classList.add('d-none');
            metaDefault.classList.remove('d-none');
        }

        nodeCount.textContent = currentGraph.nodes.length;
        edgeCount.textContent = currentGraph.edges.length;
        if (sumMs) sumMs.textContent = String(currentGraph.stats.typeCounts.ms || 0);
        if (sumRoute) sumRoute.textContent = String(currentGraph.stats.typeCounts.route || 0);
        if (sumController) sumController.textContent = String(currentGraph.stats.typeCounts.controller || 0);
        if (sumOrphans) sumOrphans.textContent = String(currentGraph.stats.orphanCount || 0);
        if (sumComponents) sumComponents.textContent = String(currentGraph.stats.componentCount || 0);
        if (sumInferred) sumInferred.textContent = String(currentGraph.stats.inferredEdgeCount || 0);

        drawnNodes = layoutNodes(currentGraph.nodes);
        const drawnMap = {};
        drawnNodes.forEach(n => { drawnMap[n.id] = n; });

        if (state.selectedId && currentGraph.nodeById[state.selectedId]) {
            populateDetail(currentGraph.nodeById[state.selectedId]);
        }

        const highlightSet = getHighlightSet();
        const renderPlan = (function() {
            const totalNodes = currentGraph.nodes.length;
            const totalEdges = currentGraph.edges.length;
            const performanceMode = totalNodes >= PERF_NODE_THRESHOLD || totalEdges >= PERF_EDGE_THRESHOLD;
            let sampledEdges = currentGraph.edges;
            let edgeStep = 1;
            if (performanceMode && totalEdges > PERF_EDGE_TARGET) {
                edgeStep = Math.ceil(totalEdges / PERF_EDGE_TARGET);
                sampledEdges = currentGraph.edges.filter((edge, idx) => {
                    if (idx % edgeStep === 0) return true;
                    if (!state.selectedId) return false;
                    return edge.from === state.selectedId || edge.to === state.selectedId;
                });
            }
            const hideLabels = performanceMode && (totalNodes >= PERF_LABEL_THRESHOLD || state.view.scale < 0.82);
            return { performanceMode, hideLabels, sampledEdges, edgeStep };
        })();
        if (perfStatus) {
            if (renderPlan.performanceMode) {
                perfStatus.textContent = `Performance mode: ${renderPlan.sampledEdges.length}/${currentGraph.edges.length} edges${renderPlan.hideLabels ? ', labels simplified' : ''}`;
            } else {
                perfStatus.textContent = '';
            }
        }

        const edgesToDraw = renderPlan.sampledEdges;
        const token = ++renderSeq;

        function renderScene(edgeJobs, edgeLimit, progressMode) {
            if (token !== renderSeq) {
                return;
            }
            const totalEdges = edgeJobs.length;
            ctx.clearRect(0, 0, canvas.width, canvas.height);
            ctx.save();
            ctx.translate(state.view.tx, state.view.ty);
            ctx.scale(state.view.scale, state.view.scale);

            for (let i = 0; i < edgeLimit; i++) {
                const edge = edgeJobs[i];
                if (!edge) continue;
                ctx.strokeStyle = edge.isActive ? palette.edge : 'rgba(208, 215, 222, 0.3)';
                ctx.lineWidth = (edge.isActive ? 1.2 : 0.9) / state.view.scale;
                ctx.setLineDash(edge.isInferred ? [5 / state.view.scale, 4 / state.view.scale] : []);
                ctx.beginPath();
                ctx.moveTo(edge.x1, edge.y1);
                ctx.lineTo(edge.x2, edge.y2);
                ctx.stroke();
                ctx.setLineDash([]);
            }

            drawnNodes.forEach(node => {
                const isSelected = state.selectedId === node.id;
                const isActive = !highlightSet || highlightSet.has(node.id);
                const fill = isActive ? (palette[node.type] || '#6c757d') : palette.nodeDim;
                ctx.beginPath();
                ctx.fillStyle = fill;
                ctx.arc(node.x, node.y, isSelected ? nodeRadius + 2 : nodeRadius, 0, Math.PI * 2);
                ctx.fill();
                if (isSelected) {
                    ctx.strokeStyle = '#0f172a';
                    ctx.lineWidth = 1.5 / state.view.scale;
                    ctx.stroke();
                }
                if (!renderPlan.hideLabels) {
                    ctx.fillStyle = '#fff';
                    ctx.font = `${12 / state.view.scale}px sans-serif`;
                    const label = shortLabel(node.label || node.id, 14);
                    const textWidth = ctx.measureText(label).width;
                    ctx.fillText(label, node.x - textWidth / 2, node.y + 4 / state.view.scale);
                }
            });

            ctx.restore();
            if (perfStatus && progressMode) {
                const pct = totalEdges ? Math.round((edgeLimit / totalEdges) * 100) : 100;
                perfStatus.textContent = `Performance mode: ${edgeLimit}/${totalEdges} edges rendered (${pct}%)${renderPlan.hideLabels ? ', labels simplified' : ''}`;
            }
        }

        function startEdgeRender(edgeJobs) {
            if (token !== renderSeq) return;
            const totalEdges = edgeJobs.length;
            if (renderPlan.performanceMode && totalEdges > PROGRESSIVE_EDGE_THRESHOLD) {
                let edgeCursor = 0;
                const drawBatch = () => {
                    if (token !== renderSeq) return;
                    edgeCursor = Math.min(totalEdges, edgeCursor + PROGRESSIVE_EDGE_BATCH);
                    renderScene(edgeJobs, edgeCursor, true);
                    if (edgeCursor < totalEdges) {
                        window.requestAnimationFrame(drawBatch);
                        return;
                    }
                    if (perfStatus) {
                        perfStatus.textContent = `Performance mode: ${totalEdges}/${currentGraph.edges.length} edges${renderPlan.hideLabels ? ', labels simplified' : ''}`;
                    }
                    if (syncUrl) syncUrlState();
                };
                drawBatch();
                return;
            }
            renderScene(edgeJobs, totalEdges, false);
            if (syncUrl) syncUrlState();
        }

        const useWorker = !!(edgeWorker && renderPlan.performanceMode && edgesToDraw.length > WORKER_EDGE_THRESHOLD);
        if (useWorker) {
            const positions = {};
            Object.keys(drawnMap).forEach(id => {
                const n = drawnMap[id];
                positions[id] = { x: n.x, y: n.y };
            });
            const plannedEdges = edgesToDraw.map(edge => ({
                from: edge.from,
                to: edge.to,
                isInferred: !!(edge && edge.meta && edge.meta.inferred),
            }));
            const highlightIds = highlightSet ? Array.from(highlightSet) : [];
            requestEdgePlanFromWorker({ edges: plannedEdges, positions, highlightIds }).then(jobs => {
                if (token !== renderSeq) return;
                if (Array.isArray(jobs) && jobs.length) {
                    startEdgeRender(jobs);
                    return;
                }
                const fallbackJobs = edgesToDraw.map(edge => {
                    const from = drawnMap[edge.from];
                    const to = drawnMap[edge.to];
                    if (!from || !to) return null;
                    return {
                        x1: from.x,
                        y1: from.y,
                        x2: to.x,
                        y2: to.y,
                        isInferred: !!(edge && edge.meta && edge.meta.inferred),
                        isActive: !highlightSet || (highlightSet.has(edge.from) && highlightSet.has(edge.to)),
                    };
                }).filter(Boolean);
                startEdgeRender(fallbackJobs);
            });
            return;
        }

        const edgeJobs = edgesToDraw.map(edge => {
            const from = drawnMap[edge.from];
            const to = drawnMap[edge.to];
            if (!from || !to) return null;
            return {
                x1: from.x,
                y1: from.y,
                x2: to.x,
                y2: to.y,
                isInferred: !!(edge && edge.meta && edge.meta.inferred),
                isActive: !highlightSet || (highlightSet.has(edge.from) && highlightSet.has(edge.to)),
            };
        }).filter(Boolean);
        startEdgeRender(edgeJobs);
    }

    function loadPresets() {
        presets = diagramUtils.readPresets ? diagramUtils.readPresets(PRESET_STORAGE_KEY) : [];
    }

    function persistPresets() {
        if (diagramUtils.writePresets) {
            diagramUtils.writePresets(PRESET_STORAGE_KEY, presets);
            return;
        }
        localStorage.setItem(PRESET_STORAGE_KEY, JSON.stringify(presets));
    }

    function renderPresetOptions() {
        if (!presetSelect) return;
        presetSelect.innerHTML = '';
        const base = document.createElement('option');
        base.value = '';
        base.textContent = 'Select saved view';
        presetSelect.appendChild(base);
        presets.forEach(p => {
            const opt = document.createElement('option');
            opt.value = p.id;
            opt.textContent = p.name;
            presetSelect.appendChild(opt);
        });
    }

    function capturePresetState() {
        return {
            types: getAllowedTypes(),
            ms: msSelect.value || '',
            depth: parseInt(depthRange.value || '2', 10) || 2,
            orphans: !!orphansOnly.checked,
            nmode: neighborMode.value || 'none',
            inferred: !!showInferred.checked,
            q: String(searchInput.value || '').trim(),
            sel: state.selectedId || '',
            view: { scale: Number(state.view.scale || 1), tx: Number(state.view.tx || 0), ty: Number(state.view.ty || 0) },
        };
    }

    function applyPresetState(payload) {
        const wanted = new Set(Array.isArray(payload.types) ? payload.types : []);
        typeChecks.forEach(c => { c.checked = wanted.size ? wanted.has(c.value) : true; });
        if (payload.ms && Array.from(msSelect.options).some(o => o.value === payload.ms)) msSelect.value = payload.ms;
        if (Number.isFinite(payload.depth) && payload.depth >= 1 && payload.depth <= 3) {
            depthRange.value = String(payload.depth);
            depthLabel.textContent = String(payload.depth);
        }
        orphansOnly.checked = !!payload.orphans;
        if (payload.nmode && Array.from(neighborMode.options).some(o => o.value === payload.nmode)) neighborMode.value = payload.nmode;
        showInferred.checked = !!payload.inferred;
        searchInput.value = String(payload.q || '');
        state.selectedId = String(payload.sel || '');
        if (payload.view && Number.isFinite(payload.view.scale) && Number.isFinite(payload.view.tx) && Number.isFinite(payload.view.ty)) {
            state.view.scale = Math.min(3, Math.max(0.3, Number(payload.view.scale)));
            state.view.tx = Number(payload.view.tx);
            state.view.ty = Number(payload.view.ty);
        }
        draw();
    }

    function toCanvasCoords(evt) {
        const rect = canvas.getBoundingClientRect();
        return {
            x: (evt.clientX - rect.left - state.view.tx) / state.view.scale,
            y: (evt.clientY - rect.top - state.view.ty) / state.view.scale,
        };
    }

    function hitNode(pt) {
        return drawnNodes.find(n => {
            const dx = pt.x - n.x;
            const dy = pt.y - n.y;
            return Math.sqrt(dx * dx + dy * dy) <= nodeRadius + 2;
        });
    }

    function detailUrlFor(type, uid) {
        if (!uid) return '#';
        switch (type) {
            case 'ms':
                return `${baseUrl}/microservice/detail/${uid}`;
            case 'route':
                return `${baseUrl}/route/detail/${uid}`;
            case 'controller':
                return `${baseUrl}/controller/detail/${uid}`;
            default:
                return '#';
        }
    }

    function populateDetail(node) {
        const degree = currentGraph.degreeMap[node.id] || 0;
        const label = node.label || node.id;
        const typeLabel = node.type === 'ms' ? 'Microservice' : (node.type === 'route' ? 'Route' : 'Controller');
        selectionStatus.textContent = label;
        metaTitle.textContent = label;
        metaType.textContent = `Type: ${typeLabel}`;
        metaDegree.textContent = String(degree);
        metaId.textContent = node.id;

        ['metaUidRow','metaScopeRow','metaAccessRow','metaEntityScopeRow','metaDegreeRow','metaCtrlTypeRow'].forEach(id => {
            const el = document.getElementById(id);
            if (el) el.classList.add('d-none');
        });

        const meta = node.meta || {};
        if (meta.uid) {
            const link = document.getElementById('metaUidLink');
            link.textContent = meta.uid;
            link.href = detailUrlFor(node.type, meta.uid);
            document.getElementById('metaUidRow').classList.remove('d-none');
        }
        if (meta.scope) {
            document.getElementById('metaScope').textContent = meta.scope;
            document.getElementById('metaScopeRow').classList.remove('d-none');
        }
        if (meta.access) {
            document.getElementById('metaAccess').textContent = meta.access;
            document.getElementById('metaAccessRow').classList.remove('d-none');
        }
        if (meta.entity_scope) {
            document.getElementById('metaEntityScope').textContent = meta.entity_scope;
            document.getElementById('metaEntityScopeRow').classList.remove('d-none');
        }
        if (meta.degree) {
            document.getElementById('metaDegreeVal').textContent = meta.degree;
            document.getElementById('metaDegreeRow').classList.remove('d-none');
        }
        if (meta.controller_type) {
            document.getElementById('metaCtrlType').textContent = meta.controller_type;
            document.getElementById('metaCtrlTypeRow').classList.remove('d-none');
        }

        metaDefault.classList.add('d-none');
        metaDetails.classList.remove('d-none');
    }

    function selectNodeById(nodeId, centerNode) {
        const node = drawnNodes.find(n => n.id === nodeId);
        if (!node) {
            return false;
        }
        state.selectedId = node.id;
        populateDetail(node);
        if (centerNode) {
            state.view.tx = (canvas.width / 2) - (node.x * state.view.scale);
            state.view.ty = (canvas.height / 2) - (node.y * state.view.scale);
        }
        draw();
        return true;
    }

    function focusSearchNode() {
        const q = String(searchInput.value || '').trim().toLowerCase();
        if (!q) {
            searchFeedback.textContent = 'Enter a node label, ID, or UID.';
            searchFeedback.className = 'small text-muted mt-1';
            return;
        }

        const rank = (node) => {
            const id = String(node.id || '').toLowerCase();
            const label = String(node.label || '').toLowerCase();
            const uid = String((node.meta && node.meta.uid) || '').toLowerCase();
            if (id === q || uid === q || label === q) return 0;
            if (id.startsWith(q) || uid.startsWith(q) || label.startsWith(q)) return 1;
            if (id.includes(q) || uid.includes(q) || label.includes(q)) return 2;
            return 9;
        };

        const matches = drawnNodes
            .map(node => ({ node, score: rank(node) }))
            .filter(item => item.score < 9)
            .sort((a, b) => a.score - b.score || String(a.node.label || '').localeCompare(String(b.node.label || '')));

        if (!matches.length) {
            searchFeedback.textContent = 'No match in current filtered graph.';
            searchFeedback.className = 'small text-danger mt-1';
            return;
        }

        const focused = matches[0].node;
        searchFeedback.textContent = `Focused: ${focused.label || focused.id}`;
        searchFeedback.className = 'small text-success mt-1';
        selectNodeById(focused.id, true);
    }

    function initMicroserviceFilter() {
        const msNames = {};
        allNodes.forEach(n => {
            if (n.type === 'ms') {
                msNames[n.id] = n.label || n.id;
            }
        });
        Object.keys(msNames).sort().forEach(msId => {
            const opt = document.createElement('option');
            opt.value = msId;
            opt.textContent = msNames[msId];
            msSelect.appendChild(opt);
        });
        if (!msSelect.value && Object.keys(msNames).length) {
            msSelect.value = Object.keys(msNames)[0];
        }
    }

    function downloadBlob(filename, content, type) {
        if (diagramUtils.downloadBlob) {
            diagramUtils.downloadBlob(filename, content, type);
            return;
        }
        const blob = new Blob([content], { type });
        const url = URL.createObjectURL(blob);
        const link = document.createElement('a');
        link.href = url;
        link.download = filename;
        link.click();
        setTimeout(() => URL.revokeObjectURL(url), 400);
    }

    function exportFilteredJson() {
        const payload = {
            generated_at: new Date().toISOString(),
            filters: {
                q: String(searchInput.value || '').trim(),
                node_types: getAllowedTypes(),
                microservice: msSelect.value || '',
                depth: parseInt(depthRange.value || '2', 10) || 2,
                orphans_only: orphansOnly.checked ? 1 : 0,
                neighbor_mode: neighborMode.value || 'none',
                inferred_links: showInferred.checked ? 1 : 0,
                selected_id: state.selectedId || '',
            },
            summary: {
                node_total: currentGraph.nodes.length,
                edge_total: currentGraph.edges.length,
                type_counts: currentGraph.stats.typeCounts,
                orphan_count: currentGraph.stats.orphanCount,
                component_count: currentGraph.stats.componentCount,
                inferred_edge_count: currentGraph.stats.inferredEdgeCount,
            },
            nodes: currentGraph.nodes,
            edges: currentGraph.edges,
        };
        downloadBlob('architecture-diagram.filtered.json', `${JSON.stringify(payload, null, 2)}\n`, 'application/json;charset=utf-8');
    }

    function csvEscape(value) {
        if (diagramUtils.csvEscape) {
            return diagramUtils.csvEscape(value);
        }
        const s = String(value === undefined || value === null ? '' : value);
        return `"${s.replace(/"/g, '""')}"`;
    }

    function exportFilteredCsv() {
        const nodeHeader = ['id', 'label', 'type', 'uid', 'scope', 'access', 'entity_scope', 'controller_type'];
        const edgeHeader = ['from', 'to', 'label', 'inferred', 'method', 'confidence'];
        const nodeRows = currentGraph.nodes.map(n => {
            const m = n.meta || {};
            return [n.id, n.label || '', n.type || '', m.uid || '', m.scope || '', m.access || '', m.entity_scope || '', m.controller_type || ''];
        });
        const edgeRows = currentGraph.edges.map(e => {
            const m = e.meta || {};
            return [e.from || '', e.to || '', e.label || '', m.inferred ? '1' : '0', m.method || '', m.confidence || ''];
        });
        const nodeCsv = [nodeHeader, ...nodeRows].map(row => row.map(csvEscape).join(',')).join('\n');
        const edgeCsv = [edgeHeader, ...edgeRows].map(row => row.map(csvEscape).join(',')).join('\n');
        downloadBlob('architecture-diagram.filtered.csv', `# nodes\n${nodeCsv}\n\n# edges\n${edgeCsv}\n`, 'text/csv;charset=utf-8');
    }

    function xmlEscape(value) {
        if (diagramUtils.xmlEscape) {
            return diagramUtils.xmlEscape(value);
        }
        return String(value || '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&apos;');
    }

    function exportSvg() {
        if (!drawnNodes.length) {
            return;
        }
        const map = {};
        drawnNodes.forEach(n => { map[n.id] = n; });
        let minX = Infinity;
        let minY = Infinity;
        let maxX = -Infinity;
        let maxY = -Infinity;
        drawnNodes.forEach(n => {
            minX = Math.min(minX, n.x);
            minY = Math.min(minY, n.y);
            maxX = Math.max(maxX, n.x);
            maxY = Math.max(maxY, n.y);
        });
        const pad = nodeRadius + 70;
        minX -= pad;
        minY -= pad;
        maxX += 260;
        maxY += pad;
        const width = Math.max(420, Math.ceil(maxX - minX));
        const height = Math.max(300, Math.ceil(maxY - minY));

        const highlightNodes = new Set();
        if (neighborMode.value === '1hop' && state.selectedId && currentGraph.adj[state.selectedId]) {
            highlightNodes.add(state.selectedId);
            currentGraph.adj[state.selectedId].forEach(id => highlightNodes.add(id));
        }
        const hasHighlight = highlightNodes.size > 0;

        const edgeMarkup = currentGraph.edges.map(e => {
            const from = map[e.from];
            const to = map[e.to];
            if (!from || !to) return '';
            const active = !hasHighlight || (highlightNodes.has(e.from) && highlightNodes.has(e.to));
            const stroke = active ? '#d0d7de' : '#d8dde3';
            const dash = (e.meta && e.meta.inferred) ? ' stroke-dasharray="6 4"' : '';
            return `<line x1="${(from.x - minX).toFixed(1)}" y1="${(from.y - minY).toFixed(1)}" x2="${(to.x - minX).toFixed(1)}" y2="${(to.y - minY).toFixed(1)}" stroke="${stroke}" stroke-width="1.3"${dash} />`;
        }).join('\n');

        const nodeMarkup = drawnNodes.map(node => {
            const selected = state.selectedId === node.id;
            const active = !hasHighlight || highlightNodes.has(node.id);
            const fill = active ? (palette[node.type] || '#6c757d') : palette.nodeDim;
            const stroke = selected ? '#111827' : '#ffffff';
            const strokeWidth = selected ? '2.8' : '1.1';
            const x = (node.x - minX).toFixed(1);
            const y = (node.y - minY).toFixed(1);
            const label = xmlEscape(String(node.label || node.id));
            return `<g><circle cx="${x}" cy="${y}" r="${nodeRadius}" fill="${fill}" stroke="${stroke}" stroke-width="${strokeWidth}" /><text x="${(Number(x) + nodeRadius + 6).toFixed(1)}" y="${(Number(y) + 4).toFixed(1)}" font-size="12" font-family="system-ui, sans-serif" fill="#2b2f33">${label}</text></g>`;
        }).join('\n');

        const svg = `<?xml version="1.0" encoding="UTF-8"?>\n<svg xmlns="http://www.w3.org/2000/svg" width="${width}" height="${height}" viewBox="0 0 ${width} ${height}">\n<rect width="100%" height="100%" fill="#ffffff"/>\n${edgeMarkup}\n${nodeMarkup}\n</svg>\n`;
        downloadBlob('architecture-diagram.filtered.svg', svg, 'image/svg+xml;charset=utf-8');
    }

    resizeCanvas();
    initMicroserviceFilter();
    applyUrlState();
    loadPresets();
    renderPresetOptions();
    draw();

    window.addEventListener('resize', () => {
        resizeCanvas();
        draw(false);
    });
    window.addEventListener('beforeunload', () => {
        if (edgeWorker) {
            try { edgeWorker.terminate(); } catch (e) {}
            edgeWorker = null;
        }
        Object.keys(edgeWorkerPending).forEach(k => { delete edgeWorkerPending[k]; });
    });

    redrawBtn?.addEventListener('click', draw);
    typeChecks.forEach(c => c.addEventListener('change', draw));
    msSelect?.addEventListener('change', draw);
    orphansOnly?.addEventListener('change', draw);
    neighborMode?.addEventListener('change', draw);
    showInferred?.addEventListener('change', draw);
    depthRange?.addEventListener('input', () => {
        depthLabel.textContent = depthRange.value;
        draw();
    });

    searchInput?.addEventListener('keydown', (e) => {
        if (e.key === 'Enter') {
            e.preventDefault();
            focusSearchNode();
        }
    });
    focusNodeBtn?.addEventListener('click', focusSearchNode);

    canvas.addEventListener('mousedown', (e) => {
        state.isPanning = true;
        state.movedWhilePanning = false;
        state.lastMouse = { x: e.clientX, y: e.clientY };
    });

    canvas.addEventListener('mouseup', () => {
        state.isPanning = false;
        state.lastMouse = null;
    });

    canvas.addEventListener('mouseleave', () => {
        state.isPanning = false;
        state.lastMouse = null;
        tooltip.style.display = 'none';
    });

    canvas.addEventListener('mousemove', (e) => {
        if (state.isPanning && state.lastMouse) {
            const dx = e.clientX - state.lastMouse.x;
            const dy = e.clientY - state.lastMouse.y;
            if (Math.abs(dx) + Math.abs(dy) > 1) {
                state.movedWhilePanning = true;
            }
            state.view.tx += dx;
            state.view.ty += dy;
            state.lastMouse = { x: e.clientX, y: e.clientY };
            tooltip.style.display = 'none';
            draw(false);
            return;
        }

        const pt = toCanvasCoords(e);
        const node = hitNode(pt);
        if (node) {
            selectionStatus.textContent = node.label || node.id;
            tooltip.style.display = 'block';
            tooltip.textContent = `${node.label || node.id} (${node.type})`;
            tooltip.style.left = `${e.offsetX + 12}px`;
            tooltip.style.top = `${e.offsetY + 12}px`;
        } else {
            if (!state.selectedId) {
                selectionStatus.textContent = 'No node selected';
            }
            tooltip.style.display = 'none';
        }
    });

    canvas.addEventListener('click', (e) => {
        if (state.movedWhilePanning) {
            state.movedWhilePanning = false;
            return;
        }
        const pt = toCanvasCoords(e);
        const node = hitNode(pt);
        if (!node) {
            state.selectedId = '';
            metaDefault.classList.remove('d-none');
            metaDetails.classList.add('d-none');
            selectionStatus.textContent = 'No node selected';
            draw();
            return;
        }
        selectNodeById(node.id, false);
    });

    canvas.addEventListener('wheel', (e) => {
        e.preventDefault();
        const scaleFactor = e.deltaY < 0 ? 1.1 : 0.9;
        state.view.scale = Math.min(3, Math.max(0.3, state.view.scale * scaleFactor));
        draw(false);
    }, { passive: false });

    zoomInBtn?.addEventListener('click', () => {
        state.view.scale = Math.min(3, state.view.scale * 1.2);
        draw(false);
    });

    zoomOutBtn?.addEventListener('click', () => {
        state.view.scale = Math.max(0.3, state.view.scale / 1.2);
        draw(false);
    });

    zoomResetBtn?.addEventListener('click', () => {
        state.view.scale = 1;
        state.view.tx = canvas.width / 2;
        state.view.ty = canvas.height / 2;
        draw(false);
    });

    exportPngBtn?.addEventListener('click', () => {
        const link = document.createElement('a');
        link.download = 'architecture-diagram.filtered.png';
        link.href = canvas.toDataURL('image/png');
        link.click();
    });
    exportSvgBtn?.addEventListener('click', exportSvg);
    exportJsonBtn?.addEventListener('click', exportFilteredJson);
    exportCsvBtn?.addEventListener('click', exportFilteredCsv);
    presetSaveBtn?.addEventListener('click', () => {
        const name = String((presetName && presetName.value) || '').trim();
        if (!name) return;
        const id = diagramUtils.makePresetId ? diagramUtils.makePresetId() : `p_${Date.now()}_${Math.random().toString(36).slice(2, 7)}`;
        presets = presets.filter(p => p.name !== name);
        presets.push({ id, name, payload: capturePresetState() });
        persistPresets();
        renderPresetOptions();
        presetSelect.value = id;
    });
    presetLoadBtn?.addEventListener('click', () => {
        const id = String((presetSelect && presetSelect.value) || '');
        const p = presets.find(x => x.id === id);
        if (!p || !p.payload) return;
        applyPresetState(p.payload);
    });
    presetDeleteBtn?.addEventListener('click', () => {
        const id = String((presetSelect && presetSelect.value) || '');
        if (!id) return;
        presets = presets.filter(p => p.id !== id);
        persistPresets();
        renderPresetOptions();
    });
});
</script>
