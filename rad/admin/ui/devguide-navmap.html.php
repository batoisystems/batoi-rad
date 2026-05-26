<?php
$seed = $this->runData['data']['navmap_seed'] ?? [
    'nodes' => [],
    'edges' => [],
    'summary' => [
        'node_total' => 0,
        'edge_total' => 0,
        'counts' => ['navset' => 0, 'navitem' => 0, 'role' => 0, 'object' => 0],
    ],
    'generated_at' => '',
];
?>

<style>
:root {
    --nm-navset: #0d6efd;
    --nm-navitem: #198754;
    --nm-role: #6f42c1;
    --nm-object: #fd7e14;
    --nm-edge: #d0d7de;
}
.nm-dot { display:inline-block; width:.75rem; height:.75rem; border-radius:50%; margin-right:.35rem; }
.nm-navset { background:var(--nm-navset); }
.nm-navitem { background:var(--nm-navitem); }
.nm-role { background:var(--nm-role); }
.nm-object { background:var(--nm-object); }
.nm-chip { border:1px solid #e3e8ef; border-radius:999px; font-size:.75rem; padding:.2rem .55rem; background:#f8fafc; }
</style>

<div class="alert alert-info d-flex align-items-start">
    <div class="me-2"><i class="bi bi-menu-button-wide" aria-hidden="true"></i></div>
    <div class="small">
        Maps navigation structures to authorization: navsets and menu items, role assignments, and role permission bindings to route or microservice objects.
    </div>
</div>

<div class="row g-3">
    <div class="col-lg-3">
        <div class="card h-100">
            <div class="card-body">
                <h2 class="h6 mb-3">Filters</h2>

                <div class="mb-3">
                    <label class="form-label form-label-sm" for="nmSearch">Find node</label>
                    <div class="input-group input-group-sm">
                        <input id="nmSearch" type="search" class="form-control" placeholder="Search label, ID, UID, href" aria-label="Search node">
                        <button class="btn btn-outline-secondary" id="nmFocus" type="button" aria-label="Focus matching node"><i class="bi bi-search"></i></button>
                    </div>
                    <div id="nmSearchFeedback" class="small text-muted mt-1">Press Enter to focus.</div>
                </div>

                <div class="mb-3">
                    <label class="form-label form-label-sm">Node types</label>
                    <div class="d-flex flex-wrap gap-2">
                        <div class="form-check form-check-inline"><input class="form-check-input nm-type" type="checkbox" value="navset" id="nmTypeNavset" checked><label class="form-check-label" for="nmTypeNavset">Navset</label></div>
                        <div class="form-check form-check-inline"><input class="form-check-input nm-type" type="checkbox" value="navitem" id="nmTypeNavitem" checked><label class="form-check-label" for="nmTypeNavitem">Nav item</label></div>
                        <div class="form-check form-check-inline"><input class="form-check-input nm-type" type="checkbox" value="role" id="nmTypeRole" checked><label class="form-check-label" for="nmTypeRole">Role</label></div>
                        <div class="form-check form-check-inline"><input class="form-check-input nm-type" type="checkbox" value="object" id="nmTypeObject" checked><label class="form-check-label" for="nmTypeObject">Object</label></div>
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label form-label-sm" for="nmObjectType">Object type</label>
                    <select id="nmObjectType" class="form-select form-select-sm" aria-label="Filter object type">
                        <option value="all">All</option>
                        <option value="ms">Microservice objects</option>
                        <option value="route">Route objects</option>
                    </select>
                </div>

                <div class="mb-3">
                    <label class="form-label form-label-sm" for="nmRelation">Edge relation</label>
                    <select id="nmRelation" class="form-select form-select-sm" aria-label="Filter relation type">
                        <option value="all">All relations</option>
                        <option value="contains">contains</option>
                        <option value="parent_of">parent_of</option>
                        <option value="nav_role">nav_role</option>
                        <option value="scoped_to">scoped_to</option>
                        <option value="bound_to">bound_to</option>
                    </select>
                </div>

                <div class="mb-3">
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" id="nmIsolated" aria-label="Show isolated nodes only">
                        <label class="form-check-label" for="nmIsolated">Show isolated nodes only</label>
                    </div>
                </div>

                <button class="btn btn-outline-secondary btn-sm w-100" id="nmRedraw"><i class="bi bi-arrow-clockwise"></i> Redraw</button>
                <hr>
                <h2 class="h6 mb-2">Saved Views</h2>
                <div class="mb-2">
                    <select class="form-select form-select-sm" id="nmPresetSelect" aria-label="Saved views"></select>
                </div>
                <div class="input-group input-group-sm">
                    <input type="text" class="form-control" id="nmPresetName" placeholder="Preset name" aria-label="Preset name">
                    <button class="btn btn-outline-primary" id="nmPresetSave" type="button">Save</button>
                </div>
                <div class="d-flex gap-2 mt-2">
                    <button class="btn btn-outline-secondary btn-sm w-100" id="nmPresetLoad" type="button">Load</button>
                    <button class="btn btn-outline-danger btn-sm w-100" id="nmPresetDelete" type="button">Delete</button>
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-body">
                <div class="d-flex gap-2 flex-wrap mb-2">
                    <span class="nm-chip">Navset: <span id="nmSumNavset"><?php echo (int)($seed['summary']['counts']['navset'] ?? 0); ?></span></span>
                    <span class="nm-chip">Nav item: <span id="nmSumNavitem"><?php echo (int)($seed['summary']['counts']['navitem'] ?? 0); ?></span></span>
                    <span class="nm-chip">Role: <span id="nmSumRole"><?php echo (int)($seed['summary']['counts']['role'] ?? 0); ?></span></span>
                    <span class="nm-chip">Object: <span id="nmSumObject"><?php echo (int)($seed['summary']['counts']['object'] ?? 0); ?></span></span>
                </div>

                <?php if (!empty($seed['generated_at'])): ?>
                    <div class="small text-muted mb-2">Generated: <?php echo htmlspecialchars((string)$seed['generated_at']); ?></div>
                <?php endif; ?>

                <div class="d-flex justify-content-between align-items-center mb-2 gap-3 flex-wrap">
                    <div class="text-muted small">Nodes: <span id="nmNodeCount"><?php echo count($seed['nodes'] ?? []); ?></span> • Edges: <span id="nmEdgeCount"><?php echo count($seed['edges'] ?? []); ?></span></div>
                    <div class="text-muted small" id="nmPerfStatus"></div>
                    <div class="d-flex gap-2 align-items-center">
                        <div class="btn-group btn-group-sm" role="group" aria-label="Zoom controls">
                            <button class="btn btn-outline-secondary" id="nmZoomIn" aria-label="Zoom in"><i class="bi bi-zoom-in"></i></button>
                            <button class="btn btn-outline-secondary" id="nmZoomOut" aria-label="Zoom out"><i class="bi bi-zoom-out"></i></button>
                            <button class="btn btn-outline-secondary" id="nmZoomReset" aria-label="Reset view"><i class="bi bi-aspect-ratio"></i></button>
                        </div>
                        <div class="btn-group btn-group-sm" role="group" aria-label="Export controls">
                            <button class="btn btn-outline-secondary" id="nmExportPng" aria-label="Export PNG"><i class="bi bi-download"></i> PNG</button>
                            <button class="btn btn-outline-secondary" id="nmExportSvg" aria-label="Export SVG">SVG</button>
                            <button class="btn btn-outline-secondary" id="nmExportJson" aria-label="Export JSON">JSON</button>
                            <button class="btn btn-outline-secondary" id="nmExportCsv" aria-label="Export CSV">CSV</button>
                        </div>
                    </div>
                </div>

                <canvas id="nmCanvas" class="border rounded w-100 mb-3" style="min-height:540px;"></canvas>
                <details>
                    <summary class="small text-muted">Raw data</summary>
                    <pre class="small mb-0" style="max-height:220px; overflow:auto;"><?php echo htmlspecialchars(json_encode($seed, JSON_PRETTY_PRINT)); ?></pre>
                </details>
            </div>
        </div>
    </div>

    <div class="col-lg-3">
        <div class="card h-100">
            <div class="card-body">
                <h2 class="h6 mb-3">Details</h2>
                <div id="nmMetaDefault" class="text-muted small">
                    Select a node to inspect metadata and immediate relationships.
                    <div class="mt-2">
                        <span class="nm-dot nm-navset"></span><span class="text-muted">Navset</span><br>
                        <span class="nm-dot nm-navitem"></span><span class="text-muted">Nav item</span><br>
                        <span class="nm-dot nm-role"></span><span class="text-muted">Role</span><br>
                        <span class="nm-dot nm-object"></span><span class="text-muted">Object (MS/Route)</span>
                    </div>
                </div>
                <div id="nmMetaDetails" class="d-none">
                    <div class="fw-semibold" id="nmMetaTitle"></div>
                    <div class="text-muted small mb-2" id="nmMetaType"></div>
                    <div class="mb-2"><span class="badge text-bg-light border" id="nmMetaTypeBadge">Type</span></div>
                    <div class="small mb-1"><span class="text-muted">ID:</span> <code id="nmMetaId"></code></div>
                    <div class="small mb-1 d-none" id="nmMetaUidRow"><span class="text-muted">UID:</span> <span id="nmMetaUid"></span></div>
                    <div class="small mb-1 d-none" id="nmMetaHrefRow"><span class="text-muted">Href:</span> <code id="nmMetaHref"></code></div>
                    <div class="small mb-1 d-none" id="nmMetaObjTypeRow"><span class="text-muted">Object Type:</span> <span id="nmMetaObjType"></span></div>
                    <div class="small mb-1 d-none" id="nmMetaScopeRow"><span class="text-muted">Scope:</span> <span id="nmMetaScope"></span></div>
                    <div class="small mb-1"><span class="text-muted">Degree:</span> <span id="nmMetaDegree"></span></div>
                    <div class="small text-muted mt-2 mb-1">Relations</div>
                    <div class="small mb-1" id="nmMetaRelations"><span class="text-muted">No related edges.</span></div>
                    <div class="mt-3 d-none d-flex gap-2 flex-wrap" id="nmMetaActionRow">
                        <a href="#" target="_blank" rel="noopener" class="btn btn-sm btn-outline-primary d-none" id="nmMetaOpenDetail">Open details</a>
                        <a href="#" target="_blank" rel="noopener" class="btn btn-sm btn-outline-secondary d-none" id="nmMetaOpenHref">Open href</a>
                    </div>
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
    const nodesAll = Array.isArray(data.nodes) ? data.nodes : [];
    const edgesAll = Array.isArray(data.edges) ? data.edges : [];

    const canvas = document.getElementById('nmCanvas');
    if (!canvas) return;
    const ctx = canvas.getContext('2d');

    const redrawBtn = document.getElementById('nmRedraw');
    const exportPngBtn = document.getElementById('nmExportPng');
    const exportSvgBtn = document.getElementById('nmExportSvg');
    const exportJsonBtn = document.getElementById('nmExportJson');
    const exportCsvBtn = document.getElementById('nmExportCsv');
    const zoomInBtn = document.getElementById('nmZoomIn');
    const zoomOutBtn = document.getElementById('nmZoomOut');
    const zoomResetBtn = document.getElementById('nmZoomReset');

    const typeChecks = document.querySelectorAll('.nm-type');
    const objectTypeSel = document.getElementById('nmObjectType');
    const relationSel = document.getElementById('nmRelation');
    const isolatedToggle = document.getElementById('nmIsolated');
    const searchInput = document.getElementById('nmSearch');
    const focusBtn = document.getElementById('nmFocus');
    const searchFeedback = document.getElementById('nmSearchFeedback');
    const presetSelect = document.getElementById('nmPresetSelect');
    const presetName = document.getElementById('nmPresetName');
    const presetSaveBtn = document.getElementById('nmPresetSave');
    const presetLoadBtn = document.getElementById('nmPresetLoad');
    const presetDeleteBtn = document.getElementById('nmPresetDelete');

    const nodeCount = document.getElementById('nmNodeCount');
    const edgeCount = document.getElementById('nmEdgeCount');
    const perfStatus = document.getElementById('nmPerfStatus');
    const sumNavset = document.getElementById('nmSumNavset');
    const sumNavitem = document.getElementById('nmSumNavitem');
    const sumRole = document.getElementById('nmSumRole');
    const sumObject = document.getElementById('nmSumObject');

    const metaDefault = document.getElementById('nmMetaDefault');
    const metaDetails = document.getElementById('nmMetaDetails');
    const metaTitle = document.getElementById('nmMetaTitle');
    const metaType = document.getElementById('nmMetaType');
    const metaTypeBadge = document.getElementById('nmMetaTypeBadge');
    const metaId = document.getElementById('nmMetaId');
    const metaUid = document.getElementById('nmMetaUid');
    const metaDegree = document.getElementById('nmMetaDegree');
    const metaHref = document.getElementById('nmMetaHref');
    const metaObjType = document.getElementById('nmMetaObjType');
    const metaScope = document.getElementById('nmMetaScope');
    const metaRelations = document.getElementById('nmMetaRelations');
    const metaUidRow = document.getElementById('nmMetaUidRow');
    const metaHrefRow = document.getElementById('nmMetaHrefRow');
    const metaObjTypeRow = document.getElementById('nmMetaObjTypeRow');
    const metaScopeRow = document.getElementById('nmMetaScopeRow');
    const metaActionRow = document.getElementById('nmMetaActionRow');
    const metaOpenDetail = document.getElementById('nmMetaOpenDetail');
    const metaOpenHref = document.getElementById('nmMetaOpenHref');
    const radAdminUrl = <?php echo json_encode($this->runData['route']['rad_admin_url'] ?? ''); ?>;

    const palette = {
        navset: '#0d6efd',
        navitem: '#198754',
        role: '#6f42c1',
        object: '#fd7e14',
        edge: '#d0d7de',
        dim: 'rgba(108,117,125,0.30)',
        text: '#2b2f33',
    };

    const state = {
        view: { scale: 1, tx: 0, ty: 0 },
        selectedId: '',
        isPanning: false,
        movedWhilePanning: false,
        lastMouse: null,
        hasViewportState: false,
    };
    let viewportSyncTimer = null;

    let graph = { nodes: [], edges: [], degree: {}, adj: {}, nodeById: {}, stats: { navset: 0, navitem: 0, role: 0, object: 0 } };
    let drawnNodes = [];
    const r = 15;
    const PERF_NODE_THRESHOLD = 180;
    const PERF_EDGE_THRESHOLD = 320;
    const PERF_LABEL_THRESHOLD = 220;
    const PERF_EDGE_TARGET = 300;
    const PROGRESSIVE_EDGE_THRESHOLD = 200;
    const PROGRESSIVE_EDGE_BATCH = 64;
    const WORKER_EDGE_THRESHOLD = 260;
    const PRESET_STORAGE_KEY = 'rad.devguide.navmap.presets.v1';
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
        if (!state.hasViewportState && !state.view.tx && !state.view.ty) {
            state.view.tx = canvas.width * 0.12;
            state.view.ty = canvas.height * 0.12;
        }
    }

    function allowedTypes() {
        return Array.from(typeChecks).filter(c => c.checked).map(c => c.value);
    }

    function buildGraph() {
        const types = new Set(allowedTypes());
        let nodes = nodesAll.filter(n => types.has(n.type));

        if (objectTypeSel.value !== 'all') {
            nodes = nodes.filter(n => n.type !== 'object' || String((n.meta || {}).object_type || '') === objectTypeSel.value);
        }

        const nodeById = {};
        nodes.forEach(n => { nodeById[n.id] = n; });

        let edges = edgesAll.filter(e => nodeById[e.from] && nodeById[e.to]);
        if (relationSel.value !== 'all') {
            edges = edges.filter(e => String(e.label || '') === relationSel.value);
        }

        const degree = {};
        const adj = {};
        nodes.forEach(n => {
            degree[n.id] = 0;
            adj[n.id] = new Set();
        });

        edges.forEach(e => {
            degree[e.from] = (degree[e.from] || 0) + 1;
            degree[e.to] = (degree[e.to] || 0) + 1;
            if (adj[e.from]) adj[e.from].add(e.to);
            if (adj[e.to]) adj[e.to].add(e.from);
        });

        if (isolatedToggle.checked) {
            nodes = nodes.filter(n => (degree[n.id] || 0) === 0);
            const isolatedById = {};
            nodes.forEach(n => { isolatedById[n.id] = n; });
            edges = [];
            Object.keys(degree).forEach(id => {
                if (!isolatedById[id]) delete degree[id];
            });
        }

        const stats = { navset: 0, navitem: 0, role: 0, object: 0 };
        nodes.forEach(n => {
            if (stats[n.type] !== undefined) stats[n.type] += 1;
        });

        graph = {
            nodes,
            edges,
            degree,
            adj,
            nodeById,
            stats,
        };

        if (state.selectedId && !graph.nodeById[state.selectedId]) {
            state.selectedId = '';
        }

        nodeCount.textContent = String(nodes.length);
        edgeCount.textContent = String(edges.length);
        sumNavset.textContent = String(stats.navset);
        sumNavitem.textContent = String(stats.navitem);
        sumRole.textContent = String(stats.role);
        sumObject.textContent = String(stats.object);
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
                activeMode: payload.activeMode || 'both_highlight',
                selectedId: payload.selectedId || '',
            });
            setTimeout(() => {
                if (edgeWorkerPending[reqId]) {
                    delete edgeWorkerPending[reqId];
                    resolve(null);
                }
            }, 1200);
        });
    }

    function layout() {
        const groups = { navset: [], navitem: [], role: [], object: [] };
        graph.nodes.forEach(n => {
            if (!groups[n.type]) groups[n.type] = [];
            groups[n.type].push(n);
        });

        Object.keys(groups).forEach(k => {
            groups[k].sort((a, b) => String(a.label || '').localeCompare(String(b.label || '')));
        });

        const col = { navset: 0, navitem: 1, role: 2, object: 3 };
        const xGap = 230;
        const yGap = 74;
        const top = 40;
        drawnNodes = [];

        Object.keys(groups).forEach(type => {
            const arr = groups[type];
            arr.forEach((n, idx) => {
                drawnNodes.push({
                    ...n,
                    x: 60 + (col[type] || 0) * xGap,
                    y: top + idx * yGap,
                });
            });
        });
    }

    function shortLabel(text, max) {
        const s = String(text || '');
        return s.length > max ? (s.slice(0, max - 1) + '…') : s;
    }

    function drawEdge(from, to, isMuted) {
        const dx = to.x - from.x;
        const dy = to.y - from.y;
        const len = Math.sqrt(dx * dx + dy * dy) || 1;
        const ux = dx / len;
        const uy = dy / len;

        const x1 = from.x + ux * r;
        const y1 = from.y + uy * r;
        const x2 = to.x - ux * r;
        const y2 = to.y - uy * r;

        ctx.strokeStyle = isMuted ? palette.dim : palette.edge;
        ctx.lineWidth = 1.4 / state.view.scale;
        ctx.beginPath();
        ctx.moveTo(x1, y1);
        ctx.lineTo(x2, y2);
        ctx.stroke();

        const ah = 7 / state.view.scale;
        const aw = 4 / state.view.scale;
        ctx.fillStyle = isMuted ? palette.dim : '#94a3b8';
        ctx.beginPath();
        ctx.moveTo(x2, y2);
        ctx.lineTo(x2 - ux * ah + -uy * aw, y2 - uy * ah + ux * aw);
        ctx.lineTo(x2 - ux * ah - -uy * aw, y2 - uy * ah - ux * aw);
        ctx.closePath();
        ctx.fill();
    }

    function draw(syncUrl = true) {
        const drawnById = {};
        drawnNodes.forEach(n => { drawnById[n.id] = n; });

        const selectedNeighbors = new Set();
        if (state.selectedId && graph.adj[state.selectedId]) {
            graph.adj[state.selectedId].forEach(id => selectedNeighbors.add(id));
        }
        const renderPlan = (function() {
            const totalNodes = graph.nodes.length;
            const totalEdges = graph.edges.length;
            const performanceMode = totalNodes >= PERF_NODE_THRESHOLD || totalEdges >= PERF_EDGE_THRESHOLD;
            let sampledEdges = graph.edges;
            if (performanceMode && totalEdges > PERF_EDGE_TARGET) {
                const step = Math.ceil(totalEdges / PERF_EDGE_TARGET);
                sampledEdges = graph.edges.filter((edge, idx) => {
                    if (idx % step === 0) return true;
                    if (!state.selectedId) return false;
                    return edge.from === state.selectedId || edge.to === state.selectedId;
                });
            }
            const hideLabels = performanceMode && (totalNodes >= PERF_LABEL_THRESHOLD || state.view.scale < 0.9);
            return { performanceMode, sampledEdges, hideLabels };
        })();
        if (perfStatus) {
            if (renderPlan.performanceMode) {
                perfStatus.textContent = `Performance mode: ${renderPlan.sampledEdges.length}/${graph.edges.length} edges${renderPlan.hideLabels ? ', labels simplified' : ''}`;
            } else {
                perfStatus.textContent = '';
            }
        }

        const edgesToDraw = renderPlan.sampledEdges;
        const token = ++renderSeq;

        function renderScene(edgeJobs, edgeLimit, progressMode) {
            if (token !== renderSeq) return;
            const totalEdges = edgeJobs.length;
            ctx.clearRect(0, 0, canvas.width, canvas.height);
            ctx.save();
            ctx.translate(state.view.tx, state.view.ty);
            ctx.scale(state.view.scale, state.view.scale);

            for (let i = 0; i < edgeLimit; i++) {
                const e = edgeJobs[i];
                if (!e) continue;
                drawEdge({ x: e.x1, y: e.y1 }, { x: e.x2, y: e.y2 }, !e.isActive);
            }

            drawnNodes.forEach(n => {
                const selected = state.selectedId === n.id;
                const muted = state.selectedId && !selected && !selectedNeighbors.has(n.id);
                const fill = muted ? palette.dim : (palette[n.type] || '#6c757d');
                ctx.beginPath();
                ctx.arc(n.x, n.y, r, 0, Math.PI * 2);
                ctx.fillStyle = fill;
                ctx.fill();
                ctx.lineWidth = selected ? (3 / state.view.scale) : (1 / state.view.scale);
                ctx.strokeStyle = selected ? '#111827' : '#ffffff';
                ctx.stroke();
                if (!renderPlan.hideLabels) {
                    ctx.fillStyle = muted ? '#98a2b3' : palette.text;
                    ctx.font = `${Math.max(11, 12 / state.view.scale)}px system-ui`;
                    ctx.fillText(shortLabel(n.label || n.id, 28), n.x + r + 6, n.y + 4);
                }
            });

            const headings = [
                { label: 'Navsets', x: 40 },
                { label: 'Nav Items', x: 270 },
                { label: 'Roles', x: 500 },
                { label: 'Objects', x: 730 },
            ];
            if (!renderPlan.hideLabels) {
                ctx.fillStyle = '#64748b';
                ctx.font = `${Math.max(10, 11 / state.view.scale)}px system-ui`;
                headings.forEach(h => { ctx.fillText(h.label, h.x, 16); });
            }

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
                        perfStatus.textContent = `Performance mode: ${totalEdges}/${graph.edges.length} edges${renderPlan.hideLabels ? ', labels simplified' : ''}`;
                    }
                    if (syncUrl) scheduleViewportSync();
                };
                drawBatch();
                return;
            }
            renderScene(edgeJobs, totalEdges, false);
            if (syncUrl) scheduleViewportSync();
        }

        const useWorker = !!(edgeWorker && renderPlan.performanceMode && edgesToDraw.length > WORKER_EDGE_THRESHOLD);
        if (useWorker) {
            const positions = {};
            Object.keys(drawnById).forEach(id => {
                const n = drawnById[id];
                positions[id] = { x: n.x, y: n.y };
            });
            const plannedEdges = edgesToDraw.map(edge => ({ from: edge.from, to: edge.to, isInferred: false }));
            requestEdgePlanFromWorker({
                edges: plannedEdges,
                positions,
                activeMode: 'edge_to_selected',
                selectedId: state.selectedId || '',
                highlightIds: [],
            }).then(jobs => {
                if (token !== renderSeq) return;
                if (Array.isArray(jobs) && jobs.length) {
                    startEdgeRender(jobs);
                    return;
                }
                const fallbackJobs = edgesToDraw.map(edge => {
                    const from = drawnById[edge.from];
                    const to = drawnById[edge.to];
                    if (!from || !to) return null;
                    return {
                        x1: from.x,
                        y1: from.y,
                        x2: to.x,
                        y2: to.y,
                        isInferred: false,
                        isActive: !state.selectedId || state.selectedId === edge.from || state.selectedId === edge.to,
                    };
                }).filter(Boolean);
                startEdgeRender(fallbackJobs);
            });
            return;
        }

        const edgeJobs = edgesToDraw.map(edge => {
            const from = drawnById[edge.from];
            const to = drawnById[edge.to];
            if (!from || !to) return null;
            return {
                x1: from.x,
                y1: from.y,
                x2: to.x,
                y2: to.y,
                isInferred: false,
                isActive: !state.selectedId || state.selectedId === edge.from || state.selectedId === edge.to,
            };
        }).filter(Boolean);
        startEdgeRender(edgeJobs);
    }

    function toWorld(mx, my) {
        return {
            x: (mx - state.view.tx) / state.view.scale,
            y: (my - state.view.ty) / state.view.scale,
        };
    }

    function pickNode(mx, my) {
        const p = toWorld(mx, my);
        for (let i = drawnNodes.length - 1; i >= 0; i--) {
            const n = drawnNodes[i];
            const dx = p.x - n.x;
            const dy = p.y - n.y;
            if ((dx * dx + dy * dy) <= (r + 3) * (r + 3)) {
                return n;
            }
        }
        return null;
    }

    function setRowValue(row, el, value) {
        if (!row || !el) return;
        const txt = String(value || '').trim();
        if (txt) {
            el.textContent = txt;
            row.classList.remove('d-none');
        } else {
            row.classList.add('d-none');
        }
    }

    function updateMeta() {
        const node = graph.nodeById[state.selectedId] || null;
        if (!node) {
            metaDefault.classList.remove('d-none');
            metaDetails.classList.add('d-none');
            metaActionRow.classList.add('d-none');
            syncUrlState();
            return;
        }

        metaDefault.classList.add('d-none');
        metaDetails.classList.remove('d-none');

        metaTitle.textContent = String(node.label || node.id);
        metaType.textContent = String(node.type || '');
        metaTypeBadge.textContent = String(node.type || 'node');
        metaId.textContent = String(node.id || '');
        metaDegree.textContent = String(graph.degree[node.id] || 0);

        const m = node.meta || {};
        setRowValue(metaUidRow, metaUid, m.uid || '');
        setRowValue(metaHrefRow, metaHref, m.href || '');
        setRowValue(metaObjTypeRow, metaObjType, m.object_type || '');
        setRowValue(metaScopeRow, metaScope, m.scope || '');

        const relations = graph.edges.filter(e => e.from === node.id || e.to === node.id).slice(0, 10);
        if (!relations.length) {
            metaRelations.innerHTML = '<span class="text-muted">No related edges.</span>';
        } else {
            metaRelations.innerHTML = relations.map(e => {
                const other = e.from === node.id ? e.to : e.from;
                const direction = e.from === node.id ? '->' : '<-';
                return `<div><code>${e.label || 'rel'}</code> ${direction} <code>${other}</code></div>`;
            }).join('');
        }

        metaActionRow.classList.add('d-none');
        metaOpenDetail.classList.add('d-none');
        metaOpenHref.classList.add('d-none');

        const detailPath = String(m.detail_path || '').trim();
        if (detailPath) {
            metaOpenDetail.href = `${radAdminUrl}${detailPath}`;
            metaOpenDetail.classList.remove('d-none');
            metaActionRow.classList.remove('d-none');
        }

        const href = String(m.href || '').trim();
        if (href) {
            metaOpenHref.href = href;
            metaOpenHref.classList.remove('d-none');
            metaActionRow.classList.remove('d-none');
        }

        syncUrlState();
    }

    function focusMatch() {
        const q = String(searchInput.value || '').trim().toLowerCase();
        if (!q) {
            searchFeedback.textContent = 'Type a search term.';
            return;
        }

        const match = graph.nodes.find(n => {
            const m = n.meta || {};
            const hay = [n.label, n.id, m.uid, m.href, m.object_type, m.scope].join(' ').toLowerCase();
            return hay.indexOf(q) >= 0;
        });

        if (!match) {
            searchFeedback.textContent = 'No matching node in current filter.';
            return;
        }

        state.selectedId = match.id;
        const placed = drawnNodes.find(n => n.id === match.id);
        if (placed) {
            state.view.tx = canvas.width * 0.45 - placed.x * state.view.scale;
            state.view.ty = canvas.height * 0.45 - placed.y * state.view.scale;
            state.hasViewportState = true;
        }
        searchFeedback.textContent = `Focused: ${match.label || match.id}`;
        draw();
        updateMeta();
    }

    function syncUrlState() {
        const params = new URLSearchParams(window.location.search || '');
        const q = String(searchInput.value || '').trim();
        if (q) params.set('q', q); else params.delete('q');

        const types = allowedTypes();
        if (types.length && types.length !== 4) params.set('types', types.join(',')); else params.delete('types');

        if (objectTypeSel.value !== 'all') params.set('obj', objectTypeSel.value); else params.delete('obj');
        if (relationSel.value !== 'all') params.set('rel', relationSel.value); else params.delete('rel');
        if (isolatedToggle.checked) params.set('iso', '1'); else params.delete('iso');
        if (state.selectedId) params.set('node', state.selectedId); else params.delete('node');

        params.set('z', String(Number(state.view.scale || 1).toFixed(3)));
        params.set('x', String(Math.round(state.view.tx || 0)));
        params.set('y', String(Math.round(state.view.ty || 0)));

        const query = params.toString();
        const nextUrl = `${window.location.pathname}${query ? `?${query}` : ''}${window.location.hash || ''}`;
        window.history.replaceState(null, '', nextUrl);
    }

    function scheduleViewportSync() {
        if (viewportSyncTimer) clearTimeout(viewportSyncTimer);
        viewportSyncTimer = setTimeout(syncUrlState, 180);
    }

    function applyUrlState() {
        const params = new URLSearchParams(window.location.search || '');
        const q = String(params.get('q') || '').trim();
        if (q) searchInput.value = q;

        const t = String(params.get('types') || '').trim();
        if (t) {
            const wanted = new Set(t.split(',').map(v => v.trim()).filter(Boolean));
            Array.from(typeChecks).forEach(c => {
                c.checked = wanted.has(c.value);
            });
        }

        const obj = String(params.get('obj') || '').trim();
        if (obj === 'ms' || obj === 'route') objectTypeSel.value = obj;

        const rel = String(params.get('rel') || '').trim();
        if (rel) {
            const hasOpt = Array.from(relationSel.options).some(o => o.value === rel);
            if (hasOpt) relationSel.value = rel;
        }

        isolatedToggle.checked = String(params.get('iso') || '') === '1';

        const node = String(params.get('node') || '').trim();
        if (node) state.selectedId = node;

        const z = parseFloat(String(params.get('z') || ''));
        const x = parseFloat(String(params.get('x') || ''));
        const y = parseFloat(String(params.get('y') || ''));

        if (Number.isFinite(z) && z >= 0.35 && z <= 2.8) {
            state.view.scale = z;
            state.hasViewportState = true;
        }
        if (Number.isFinite(x) && Number.isFinite(y)) {
            state.view.tx = x;
            state.view.ty = y;
            state.hasViewportState = true;
        }
    }

    function redraw(syncUrl = true) {
        buildGraph();
        layout();
        draw(syncUrl);
        updateMeta();
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
            q: String(searchInput.value || '').trim(),
            types: allowedTypes(),
            obj: objectTypeSel.value || 'all',
            rel: relationSel.value || 'all',
            iso: !!isolatedToggle.checked,
            node: state.selectedId || '',
            view: { scale: Number(state.view.scale || 1), tx: Number(state.view.tx || 0), ty: Number(state.view.ty || 0) },
        };
    }

    function applyPresetState(payload) {
        const wanted = new Set(Array.isArray(payload.types) ? payload.types : []);
        Array.from(typeChecks).forEach(c => { c.checked = wanted.size ? wanted.has(c.value) : true; });
        searchInput.value = String(payload.q || '');
        if (payload.obj && Array.from(objectTypeSel.options).some(o => o.value === payload.obj)) objectTypeSel.value = payload.obj;
        if (payload.rel && Array.from(relationSel.options).some(o => o.value === payload.rel)) relationSel.value = payload.rel;
        isolatedToggle.checked = !!payload.iso;
        state.selectedId = String(payload.node || '');
        if (payload.view && Number.isFinite(payload.view.scale) && Number.isFinite(payload.view.tx) && Number.isFinite(payload.view.ty)) {
            state.view.scale = Math.min(2.8, Math.max(0.35, Number(payload.view.scale)));
            state.view.tx = Number(payload.view.tx);
            state.view.ty = Number(payload.view.ty);
            state.hasViewportState = true;
        }
        redraw();
    }

    function downloadBlob(filename, content, type) {
        if (diagramUtils.downloadBlob) {
            diagramUtils.downloadBlob(filename, content, type);
            return;
        }
        const blob = new Blob([content], { type });
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = filename;
        a.click();
        setTimeout(() => URL.revokeObjectURL(url), 400);
    }

    function currentFilterState() {
        return {
            q: String(searchInput.value || '').trim(),
            node_types: allowedTypes(),
            object_type: objectTypeSel.value,
            relation: relationSel.value,
            isolated_only: isolatedToggle.checked ? 1 : 0,
            selected_id: state.selectedId || '',
            viewport: {
                scale: Number(state.view.scale || 1),
                x: Math.round(state.view.tx || 0),
                y: Math.round(state.view.ty || 0),
            },
        };
    }

    function exportFilteredJson() {
        const payload = {
            generated_at: new Date().toISOString(),
            filters: currentFilterState(),
            summary: {
                node_total: graph.nodes.length,
                edge_total: graph.edges.length,
                counts: graph.stats,
            },
            nodes: graph.nodes,
            edges: graph.edges,
        };
        downloadBlob('navmap.filtered.json', `${JSON.stringify(payload, null, 2)}\n`, 'application/json;charset=utf-8');
    }

    function csvEscape(value) {
        if (diagramUtils.csvEscape) {
            return diagramUtils.csvEscape(value);
        }
        const s = String(value === null || value === undefined ? '' : value);
        return `"${s.replace(/"/g, '""')}"`;
    }

    function exportFilteredCsv() {
        const nodeHeader = ['id', 'label', 'type', 'uid', 'object_type', 'scope', 'href', 'detail_path'];
        const edgeHeader = ['from', 'to', 'label', 'relation'];

        const nodeRows = graph.nodes.map(n => {
            const m = n.meta || {};
            return [
                n.id,
                n.label || '',
                n.type || '',
                m.uid || '',
                m.object_type || '',
                m.scope || '',
                m.href || '',
                m.detail_path || '',
            ];
        });

        const edgeRows = graph.edges.map(e => [
            e.from || '',
            e.to || '',
            e.label || '',
            (e.meta && e.meta.relation) ? e.meta.relation : '',
        ]);

        const nodeCsv = [nodeHeader, ...nodeRows].map(row => row.map(csvEscape).join(',')).join('\n');
        const edgeCsv = [edgeHeader, ...edgeRows].map(row => row.map(csvEscape).join(',')).join('\n');
        const content = `# navmap nodes\n${nodeCsv}\n\n# navmap edges\n${edgeCsv}\n`;
        downloadBlob('navmap.filtered.csv', content, 'text/csv;charset=utf-8');
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

    function layoutBounds() {
        if (!drawnNodes.length) {
            return { minX: 0, minY: 0, maxX: 980, maxY: 680 };
        }
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
        const pad = 80;
        return {
            minX: minX - pad,
            minY: minY - pad,
            maxX: maxX + 280,
            maxY: maxY + pad,
        };
    }

    function exportSvg() {
        const byId = {};
        drawnNodes.forEach(n => { byId[n.id] = n; });
        const b = layoutBounds();
        const width = Math.max(420, Math.ceil(b.maxX - b.minX));
        const height = Math.max(320, Math.ceil(b.maxY - b.minY));

        const selectedNeighbors = new Set();
        if (state.selectedId && graph.adj[state.selectedId]) {
            graph.adj[state.selectedId].forEach(id => selectedNeighbors.add(id));
        }

        const edgeSvg = graph.edges.map(e => {
            const from = byId[e.from];
            const to = byId[e.to];
            if (!from || !to) return '';
            const active = !state.selectedId || state.selectedId === from.id || state.selectedId === to.id;
            const stroke = active ? '#cbd5e1' : '#c7ced6';
            const x1 = Math.round((from.x - b.minX) * 10) / 10;
            const y1 = Math.round((from.y - b.minY) * 10) / 10;
            const x2 = Math.round((to.x - b.minX) * 10) / 10;
            const y2 = Math.round((to.y - b.minY) * 10) / 10;
            return `<line x1="${x1}" y1="${y1}" x2="${x2}" y2="${y2}" stroke="${stroke}" stroke-width="1.3" />`;
        }).join('\n');

        const nodeSvg = drawnNodes.map(n => {
            const selected = state.selectedId === n.id;
            const muted = state.selectedId && !selected && !selectedNeighbors.has(n.id);
            const fill = muted ? '#c7ced6' : (palette[n.type] || '#6c757d');
            const stroke = selected ? '#111827' : '#ffffff';
            const strokeWidth = selected ? '2.8' : '1.1';
            const x = Math.round((n.x - b.minX) * 10) / 10;
            const y = Math.round((n.y - b.minY) * 10) / 10;
            const label = xmlEscape(shortLabel(n.label || n.id, 30));
            return `<g><circle cx="${x}" cy="${y}" r="${r}" fill="${fill}" stroke="${stroke}" stroke-width="${strokeWidth}" /><text x="${x + r + 7}" y="${y + 4}" font-size="12" font-family="system-ui, sans-serif" fill="#2b2f33">${label}</text></g>`;
        }).join('\n');

        const svg = `<?xml version="1.0" encoding="UTF-8"?>\n<svg xmlns="http://www.w3.org/2000/svg" width="${width}" height="${height}" viewBox="0 0 ${width} ${height}">\n<rect width="100%" height="100%" fill="#ffffff"/>\n${edgeSvg}\n${nodeSvg}\n</svg>\n`;
        downloadBlob('navmap.filtered.svg', svg, 'image/svg+xml;charset=utf-8');
    }

    canvas.addEventListener('mousedown', ev => {
        state.isPanning = true;
        state.movedWhilePanning = false;
        state.lastMouse = { x: ev.clientX, y: ev.clientY };
    });

    canvas.addEventListener('mousemove', ev => {
        if (!state.isPanning || !state.lastMouse) return;
        const dx = ev.clientX - state.lastMouse.x;
        const dy = ev.clientY - state.lastMouse.y;
        if (Math.abs(dx) > 1 || Math.abs(dy) > 1) state.movedWhilePanning = true;
        state.view.tx += dx;
        state.view.ty += dy;
        state.lastMouse = { x: ev.clientX, y: ev.clientY };
        state.hasViewportState = true;
        draw();
    });

    function stopPan() {
        state.isPanning = false;
        state.lastMouse = null;
    }
    canvas.addEventListener('mouseup', stopPan);
    canvas.addEventListener('mouseleave', stopPan);

    canvas.addEventListener('click', ev => {
        if (state.movedWhilePanning) {
            state.movedWhilePanning = false;
            return;
        }
        const rect = canvas.getBoundingClientRect();
        const node = pickNode(ev.clientX - rect.left, ev.clientY - rect.top);
        state.selectedId = node ? node.id : '';
        draw();
        updateMeta();
    });

    canvas.addEventListener('wheel', ev => {
        ev.preventDefault();
        const rect = canvas.getBoundingClientRect();
        const mx = ev.clientX - rect.left;
        const my = ev.clientY - rect.top;
        const before = toWorld(mx, my);
        const factor = ev.deltaY < 0 ? 1.1 : 0.9;
        state.view.scale = Math.min(2.8, Math.max(0.35, state.view.scale * factor));
        state.view.tx = mx - before.x * state.view.scale;
        state.view.ty = my - before.y * state.view.scale;
        state.hasViewportState = true;
        draw();
    }, { passive: false });

    redrawBtn.addEventListener('click', () => redraw());
    exportPngBtn.addEventListener('click', () => {
        const a = document.createElement('a');
        a.href = canvas.toDataURL('image/png');
        a.download = 'navmap.filtered.png';
        a.click();
    });
    exportSvgBtn.addEventListener('click', exportSvg);
    exportJsonBtn.addEventListener('click', exportFilteredJson);
    exportCsvBtn.addEventListener('click', exportFilteredCsv);
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

    zoomInBtn.addEventListener('click', () => {
        state.view.scale = Math.min(2.8, state.view.scale * 1.12);
        state.hasViewportState = true;
        draw();
    });
    zoomOutBtn.addEventListener('click', () => {
        state.view.scale = Math.max(0.35, state.view.scale / 1.12);
        state.hasViewportState = true;
        draw();
    });
    zoomResetBtn.addEventListener('click', () => {
        state.view.scale = 1;
        state.view.tx = canvas.width * 0.12;
        state.view.ty = canvas.height * 0.12;
        state.hasViewportState = true;
        draw();
    });

    Array.from(typeChecks).forEach(c => c.addEventListener('change', () => redraw()));
    objectTypeSel.addEventListener('change', () => redraw());
    relationSel.addEventListener('change', () => redraw());
    isolatedToggle.addEventListener('change', () => redraw());

    focusBtn.addEventListener('click', focusMatch);
    searchInput.addEventListener('keydown', ev => {
        if (ev.key === 'Enter') {
            ev.preventDefault();
            focusMatch();
        }
    });

    document.addEventListener('keydown', ev => {
        const target = ev.target;
        if (target && (target.tagName === 'INPUT' || target.tagName === 'TEXTAREA' || target.tagName === 'SELECT')) {
            return;
        }
        if (!drawnNodes.length) return;

        if (ev.key === 'j' || ev.key === 'k') {
            const ids = drawnNodes.map(n => n.id);
            let idx = ids.indexOf(state.selectedId);
            if (idx < 0) idx = 0;
            if (ev.key === 'j') idx = Math.min(ids.length - 1, idx + 1);
            if (ev.key === 'k') idx = Math.max(0, idx - 1);
            state.selectedId = ids[idx] || '';
            draw();
            updateMeta();
        } else if (ev.key === 'Enter') {
            const node = graph.nodeById[state.selectedId] || null;
            const detail = node && node.meta ? String(node.meta.detail_path || '') : '';
            if (detail) {
                window.open(`${radAdminUrl}${detail}`, '_blank', 'noopener');
            }
        }
    });

    window.addEventListener('resize', () => {
        resizeCanvas();
        redraw(false);
    });
    window.addEventListener('beforeunload', () => {
        if (edgeWorker) {
            try { edgeWorker.terminate(); } catch (e) {}
            edgeWorker = null;
        }
        Object.keys(edgeWorkerPending).forEach(k => { delete edgeWorkerPending[k]; });
    });

    applyUrlState();
    loadPresets();
    renderPresetOptions();
    resizeCanvas();
    redraw(false);
    syncUrlState();
});
</script>
