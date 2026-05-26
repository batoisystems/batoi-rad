<?php
$seed = $this->runData['data']['access_diagram_seed'] ?? [
    'nodes' => [],
    'edges' => [],
    'summary' => [
        'node_total' => 0,
        'edge_total' => 0,
        'counts' => ['entity' => 0, 'membership' => 0, 'role' => 0, 'object' => 0],
    ],
    'truncation' => ['is_truncated' => false],
    'limits' => [],
    'generated_at' => '',
];
?>

<style>
:root {
    --acc-entity: #0d6efd;
    --acc-membership: #198754;
    --acc-role: #6f42c1;
    --acc-object: #fd7e14;
    --acc-edge: #d0d7de;
}
.acc-dot { display:inline-block; width:.75rem; height:.75rem; border-radius:50%; margin-right:.35rem; }
.acc-entity { background:var(--acc-entity); }
.acc-membership { background:var(--acc-membership); }
.acc-role { background:var(--acc-role); }
.acc-object { background:var(--acc-object); }
.acc-chip { border:1px solid #e3e8ef; border-radius:999px; font-size:.75rem; padding:.2rem .55rem; background:#f8fafc; }
</style>

<div class="alert alert-info d-flex align-items-start">
    <div class="me-2"><i class="bi bi-shield-lock" aria-hidden="true"></i></div>
    <div class="small">
        Visualizes how access is resolved: entity/API principal -> space membership -> role -> permission-bound route or microservice.
    </div>
</div>

<div class="row g-3">
    <div class="col-lg-3">
        <div class="card h-100">
            <div class="card-body">
                <h2 class="h6 mb-3">Filters</h2>

                <div class="mb-3">
                    <label class="form-label form-label-sm" for="accSearch">Find node</label>
                    <div class="input-group input-group-sm">
                        <input id="accSearch" type="search" class="form-control" placeholder="Search label, ID, UID" aria-label="Search node">
                        <button class="btn btn-outline-secondary" id="accFocus" type="button" aria-label="Focus matching node"><i class="bi bi-search"></i></button>
                    </div>
                    <div id="accSearchFeedback" class="small text-muted mt-1">Press Enter to focus.</div>
                </div>

                <div class="mb-3">
                    <label class="form-label form-label-sm">Node types</label>
                    <div class="d-flex flex-wrap gap-2">
                        <div class="form-check form-check-inline"><input class="form-check-input acc-type" type="checkbox" value="entity" id="accTypeEntity" checked><label class="form-check-label" for="accTypeEntity">Entity/API</label></div>
                        <div class="form-check form-check-inline"><input class="form-check-input acc-type" type="checkbox" value="membership" id="accTypeMembership" checked><label class="form-check-label" for="accTypeMembership">Membership</label></div>
                        <div class="form-check form-check-inline"><input class="form-check-input acc-type" type="checkbox" value="role" id="accTypeRole" checked><label class="form-check-label" for="accTypeRole">Role</label></div>
                        <div class="form-check form-check-inline"><input class="form-check-input acc-type" type="checkbox" value="object" id="accTypeObject" checked><label class="form-check-label" for="accTypeObject">Object</label></div>
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label form-label-sm" for="accObjectType">Object type</label>
                    <select id="accObjectType" class="form-select form-select-sm" aria-label="Filter object node type">
                        <option value="all">All</option>
                        <option value="ms">Microservice objects</option>
                        <option value="route">Route objects</option>
                    </select>
                </div>

                <div class="mb-3">
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" id="accIsolated" aria-label="Show isolated nodes only">
                        <label class="form-check-label" for="accIsolated">Show isolated nodes only</label>
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label form-label-sm" for="accHighlight">Neighbor highlight</label>
                    <select id="accHighlight" class="form-select form-select-sm" aria-label="Neighbor highlight mode">
                        <option value="none">Off</option>
                        <option value="1hop">Selected + 1 hop</option>
                    </select>
                </div>

                <button class="btn btn-outline-secondary btn-sm w-100" id="accRedraw"><i class="bi bi-arrow-clockwise"></i> Redraw</button>
                <hr>
                <h2 class="h6 mb-2">Saved Views</h2>
                <div class="mb-2">
                    <select class="form-select form-select-sm" id="accPresetSelect" aria-label="Saved views"></select>
                </div>
                <div class="input-group input-group-sm">
                    <input type="text" class="form-control" id="accPresetName" placeholder="Preset name" aria-label="Preset name">
                    <button class="btn btn-outline-primary" id="accPresetSave" type="button">Save</button>
                </div>
                <div class="d-flex gap-2 mt-2">
                    <button class="btn btn-outline-secondary btn-sm w-100" id="accPresetLoad" type="button">Load</button>
                    <button class="btn btn-outline-danger btn-sm w-100" id="accPresetDelete" type="button">Delete</button>
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-body">
                <div class="d-flex gap-2 flex-wrap mb-2">
                    <span class="acc-chip">Entity/API: <span id="accSumEntity"><?php echo (int)($seed['summary']['counts']['entity'] ?? 0); ?></span></span>
                    <span class="acc-chip">Membership: <span id="accSumMembership"><?php echo (int)($seed['summary']['counts']['membership'] ?? 0); ?></span></span>
                    <span class="acc-chip">Role: <span id="accSumRole"><?php echo (int)($seed['summary']['counts']['role'] ?? 0); ?></span></span>
                    <span class="acc-chip">Object: <span id="accSumObject"><?php echo (int)($seed['summary']['counts']['object'] ?? 0); ?></span></span>
                </div>
                <?php if (!empty($seed['generated_at'])): ?>
                    <div class="small text-muted mb-2">Generated: <?php echo htmlspecialchars((string)$seed['generated_at']); ?></div>
                <?php endif; ?>

                <?php if (!empty($seed['truncation']['is_truncated'])): ?>
                    <div class="alert alert-warning py-2 small mb-3">
                        Access graph may be truncated by query limits.
                    </div>
                <?php endif; ?>

                <div class="d-flex justify-content-between align-items-center mb-2 gap-3 flex-wrap">
                    <div class="text-muted small">Nodes: <span id="accNodeCount"><?php echo count($seed['nodes'] ?? []); ?></span> • Edges: <span id="accEdgeCount"><?php echo count($seed['edges'] ?? []); ?></span></div>
                    <div class="text-muted small" id="accPerfStatus"></div>
                    <div class="d-flex gap-2 align-items-center">
                        <div class="btn-group btn-group-sm" role="group" aria-label="Zoom controls">
                            <button class="btn btn-outline-secondary" id="accZoomIn" aria-label="Zoom in"><i class="bi bi-zoom-in"></i></button>
                            <button class="btn btn-outline-secondary" id="accZoomOut" aria-label="Zoom out"><i class="bi bi-zoom-out"></i></button>
                            <button class="btn btn-outline-secondary" id="accZoomReset" aria-label="Reset view"><i class="bi bi-aspect-ratio"></i></button>
                        </div>
                        <div class="btn-group btn-group-sm" role="group" aria-label="Export controls">
                            <button class="btn btn-outline-secondary" id="accExportPng" aria-label="Export PNG"><i class="bi bi-download"></i> PNG</button>
                            <button class="btn btn-outline-secondary" id="accExportSvg" aria-label="Export SVG">SVG</button>
                            <button class="btn btn-outline-secondary" id="accExportJson" aria-label="Export JSON">JSON</button>
                            <button class="btn btn-outline-secondary" id="accExportCsv" aria-label="Export CSV">CSV</button>
                        </div>
                    </div>
                </div>

                <canvas id="accCanvas" class="border rounded w-100 mb-3" style="min-height:520px;"></canvas>
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
                <div id="accMetaDefault" class="text-muted small">
                    Select a node to inspect metadata and edge degree.
                    <div class="mt-2">
                        <span class="acc-dot acc-entity"></span><span class="text-muted">Entity/API</span><br>
                        <span class="acc-dot acc-membership"></span><span class="text-muted">Membership</span><br>
                        <span class="acc-dot acc-role"></span><span class="text-muted">Role</span><br>
                        <span class="acc-dot acc-object"></span><span class="text-muted">Object (MS/Route)</span>
                    </div>
                </div>
                <div id="accMetaDetails" class="d-none">
                    <div class="fw-semibold" id="accMetaTitle"></div>
                    <div class="text-muted small mb-2" id="accMetaType"></div>
                    <div class="mb-2"><span class="badge text-bg-light border" id="accMetaTypeBadge">Type</span></div>
                    <div class="small mb-1"><span class="text-muted">ID:</span> <code id="accMetaId"></code></div>
                    <div class="small mb-1"><span class="text-muted">Degree:</span> <span id="accMetaDegree"></span></div>
                    <div class="small mb-1 d-none" id="accMetaUidRow"><span class="text-muted">UID:</span> <span id="accMetaUid"></span></div>
                    <div class="small mb-1 d-none" id="accMetaScopeRow"><span class="text-muted">Scope:</span> <span id="accMetaScope"></span></div>
                    <div class="small mb-1 d-none" id="accMetaObjTypeRow"><span class="text-muted">Object Type:</span> <span id="accMetaObjType"></span></div>
                    <div class="small mb-1 d-none" id="accMetaSpaceRow"><span class="text-muted">Space ID:</span> <span id="accMetaSpace"></span></div>
                    <div class="small text-muted mt-2 mb-1">Relations</div>
                    <div class="small mb-1" id="accMetaRelations"><span class="text-muted">No related edges.</span></div>
                    <div class="mt-3 d-none d-flex gap-2 flex-wrap" id="accMetaActionRow">
                        <a href="#" target="_blank" rel="noopener" class="btn btn-sm btn-outline-primary d-none" id="accMetaOpenDetail">Open details</a>
                        <a href="#" target="_blank" rel="noopener" class="btn btn-sm btn-outline-secondary d-none" id="accMetaOpenList">Open list</a>
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
    const canvas = document.getElementById('accCanvas');
    if (!canvas) return;

    const ctx = canvas.getContext('2d');
    const redrawBtn = document.getElementById('accRedraw');
    const exportPngBtn = document.getElementById('accExportPng');
    const exportSvgBtn = document.getElementById('accExportSvg');
    const exportJsonBtn = document.getElementById('accExportJson');
    const exportCsvBtn = document.getElementById('accExportCsv');
    const zoomInBtn = document.getElementById('accZoomIn');
    const zoomOutBtn = document.getElementById('accZoomOut');
    const zoomResetBtn = document.getElementById('accZoomReset');
    const typeChecks = document.querySelectorAll('.acc-type');
    const objectTypeSel = document.getElementById('accObjectType');
    const isolatedToggle = document.getElementById('accIsolated');
    const highlightSel = document.getElementById('accHighlight');
    const searchInput = document.getElementById('accSearch');
    const focusBtn = document.getElementById('accFocus');
    const searchFeedback = document.getElementById('accSearchFeedback');
    const presetSelect = document.getElementById('accPresetSelect');
    const presetName = document.getElementById('accPresetName');
    const presetSaveBtn = document.getElementById('accPresetSave');
    const presetLoadBtn = document.getElementById('accPresetLoad');
    const presetDeleteBtn = document.getElementById('accPresetDelete');

    const nodeCount = document.getElementById('accNodeCount');
    const edgeCount = document.getElementById('accEdgeCount');
    const perfStatus = document.getElementById('accPerfStatus');
    const sumEntity = document.getElementById('accSumEntity');
    const sumMembership = document.getElementById('accSumMembership');
    const sumRole = document.getElementById('accSumRole');
    const sumObject = document.getElementById('accSumObject');

    const metaDefault = document.getElementById('accMetaDefault');
    const metaDetails = document.getElementById('accMetaDetails');
    const metaTitle = document.getElementById('accMetaTitle');
    const metaType = document.getElementById('accMetaType');
    const metaTypeBadge = document.getElementById('accMetaTypeBadge');
    const metaId = document.getElementById('accMetaId');
    const metaDegree = document.getElementById('accMetaDegree');
    const metaRelations = document.getElementById('accMetaRelations');
    const metaActionRow = document.getElementById('accMetaActionRow');
    const metaOpenDetail = document.getElementById('accMetaOpenDetail');
    const metaOpenList = document.getElementById('accMetaOpenList');
    const radAdminUrl = <?php echo json_encode($this->runData['route']['rad_admin_url'] ?? ''); ?>;

    const palette = {
        entity: '#0d6efd',
        membership: '#198754',
        role: '#6f42c1',
        object: '#fd7e14',
        edge: '#d0d7de',
        dim: 'rgba(108,117,125,0.28)',
    };

    const state = {
        view: { scale: 1, tx: 0, ty: 0 },
        selectedId: '',
        isPanning: false,
        movedWhilePanning: false,
        lastMouse: null,
        hasViewportState: false,
        relationNodeIds: [],
        relationIndex: -1,
    };
    let viewportSyncTimer = null;

    let graph = { nodes: [], edges: [], degree: {}, adj: {}, nodeById: {}, stats: { entity: 0, membership: 0, role: 0, object: 0 } };
    let drawnNodes = [];
    const r = 16;
    const PERF_NODE_THRESHOLD = 180;
    const PERF_EDGE_THRESHOLD = 320;
    const PERF_LABEL_THRESHOLD = 220;
    const PERF_EDGE_TARGET = 300;
    const PROGRESSIVE_EDGE_THRESHOLD = 200;
    const PROGRESSIVE_EDGE_BATCH = 64;
    const WORKER_EDGE_THRESHOLD = 260;
    const PRESET_STORAGE_KEY = 'rad.devguide.access_diagrams.presets.v1';
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
            state.view.tx = canvas.width / 2;
            state.view.ty = canvas.height / 2;
        }
    }

    function shortLabel(t, n) {
        const s = String(t || '');
        return s.length > n ? (s.slice(0, n - 1) + '…') : s;
    }

    function allowedTypes() {
        return Array.from(typeChecks).filter(c => c.checked).map(c => c.value);
    }

    function buildGraph() {
        const types = new Set(allowedTypes());
        let nodes = nodesAll.filter(n => types.has(n.type));

        if (objectTypeSel.value !== 'all') {
            nodes = nodes.filter(n => n.type !== 'object' || (n.meta && n.meta.object_type === objectTypeSel.value));
        }

        const byId = {};
        nodes.forEach(n => { byId[n.id] = n; });
        let edges = edgesAll.filter(e => byId[e.from] && byId[e.to]);

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
            const visible = new Set(nodes.map(n => n.id));
            edges = edges.filter(e => visible.has(e.from) && visible.has(e.to));
        }

        const stats = { entity: 0, membership: 0, role: 0, object: 0 };
        nodes.forEach(n => {
            if (Object.prototype.hasOwnProperty.call(stats, n.type)) stats[n.type]++;
        });

        const visibleById = {};
        nodes.forEach(n => { visibleById[n.id] = n; });
        return { nodes, edges, degree, adj, nodeById: visibleById, stats };
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

    function layoutNodes(nodes) {
        const groups = {
            entity: nodes.filter(n => n.type === 'entity'),
            membership: nodes.filter(n => n.type === 'membership'),
            role: nodes.filter(n => n.type === 'role'),
            object: nodes.filter(n => n.type === 'object'),
        };
        const layers = [
            { key: 'entity', x: -canvas.width * 0.32 },
            { key: 'membership', x: -canvas.width * 0.10 },
            { key: 'role', x: canvas.width * 0.10 },
            { key: 'object', x: canvas.width * 0.32 },
        ];
        const laid = [];
        layers.forEach(layer => {
            const list = groups[layer.key];
            const total = Math.max(1, list.length);
            list.forEach((node, idx) => {
                const y = ((idx + 1) / (total + 1) - 0.5) * (canvas.height * 0.78);
                laid.push({ ...node, x: layer.x, y });
            });
        });
        return laid;
    }

    function hitNode(pt) {
        return drawnNodes.find(n => {
            const dx = pt.x - n.x;
            const dy = pt.y - n.y;
            return Math.sqrt(dx * dx + dy * dy) <= r + 2;
        });
    }

    function toCanvasCoords(evt) {
        const rect = canvas.getBoundingClientRect();
        return {
            x: (evt.clientX - rect.left - state.view.tx) / state.view.scale,
            y: (evt.clientY - rect.top - state.view.ty) / state.view.scale,
        };
    }

    function highlightSet() {
        if (highlightSel.value !== '1hop' || !state.selectedId || !graph.adj[state.selectedId]) return null;
        const set = new Set([state.selectedId]);
        graph.adj[state.selectedId].forEach(v => set.add(v));
        return set;
    }

    function updateRelationFocusVisuals(focusBtn) {
        const relButtons = Array.from(metaRelations.querySelectorAll('[data-rel-node]'));
        relButtons.forEach((btn, idx) => {
            const active = idx === state.relationIndex;
            btn.classList.toggle('fw-semibold', active);
            btn.classList.toggle('text-primary', active);
            if (active && focusBtn) {
                btn.focus();
            }
        });
    }

    function isTypingContext(target) {
        if (!(target instanceof HTMLElement)) {
            return false;
        }
        const tag = (target.tagName || '').toLowerCase();
        return tag === 'input' || tag === 'textarea' || tag === 'select' || !!target.isContentEditable;
    }

    function syncUrlState() {
        const params = new URLSearchParams(window.location.search || '');
        const types = allowedTypes();
        if (types.length && types.length < 4) {
            params.set('types', types.join(','));
        } else {
            params.delete('types');
        }
        if (objectTypeSel.value && objectTypeSel.value !== 'all') {
            params.set('obj', objectTypeSel.value);
        } else {
            params.delete('obj');
        }
        if (isolatedToggle.checked) {
            params.set('isolated', '1');
        } else {
            params.delete('isolated');
        }
        if (highlightSel.value && highlightSel.value !== 'none') {
            params.set('hl', highlightSel.value);
        } else {
            params.delete('hl');
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
        params.set('z', String(Number(state.view.scale || 1).toFixed(3)));
        params.set('x', String(Math.round(state.view.tx || 0)));
        params.set('y', String(Math.round(state.view.ty || 0)));
        const query = params.toString();
        const nextUrl = `${window.location.pathname}${query ? `?${query}` : ''}${window.location.hash || ''}`;
        window.history.replaceState(null, '', nextUrl);
    }

    function scheduleViewportUrlSync() {
        if (viewportSyncTimer) {
            clearTimeout(viewportSyncTimer);
        }
        viewportSyncTimer = setTimeout(() => {
            syncUrlState();
        }, 180);
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
        const objParam = String(params.get('obj') || '').trim();
        if (objParam && Array.from(objectTypeSel.options).some(o => o.value === objParam)) {
            objectTypeSel.value = objParam;
        }
        isolatedToggle.checked = String(params.get('isolated') || '') === '1';
        const hlParam = String(params.get('hl') || '').trim();
        if (hlParam && Array.from(highlightSel.options).some(o => o.value === hlParam)) {
            highlightSel.value = hlParam;
        }
        const qParam = String(params.get('q') || '').trim();
        if (qParam) {
            searchInput.value = qParam;
        }
        const selParam = String(params.get('sel') || '').trim();
        if (selParam) {
            state.selectedId = selParam;
        }
        const zParam = parseFloat(String(params.get('z') || ''));
        const xParam = parseFloat(String(params.get('x') || ''));
        const yParam = parseFloat(String(params.get('y') || ''));
        if (Number.isFinite(zParam) && zParam >= 0.3 && zParam <= 3) {
            state.view.scale = zParam;
            state.hasViewportState = true;
        }
        if (Number.isFinite(xParam) && Number.isFinite(yParam)) {
            state.view.tx = xParam;
            state.view.ty = yParam;
            state.hasViewportState = true;
        }
    }

    function draw(syncUrl = true) {
        graph = buildGraph();
        if (state.selectedId && !graph.nodeById[state.selectedId]) {
            state.selectedId = '';
            metaDetails.classList.add('d-none');
            metaDefault.classList.remove('d-none');
        }

        nodeCount.textContent = graph.nodes.length;
        edgeCount.textContent = graph.edges.length;
        sumEntity.textContent = graph.stats.entity;
        sumMembership.textContent = graph.stats.membership;
        sumRole.textContent = graph.stats.role;
        sumObject.textContent = graph.stats.object;

        drawnNodes = layoutNodes(graph.nodes);
        const map = {};
        drawnNodes.forEach(n => { map[n.id] = n; });
        const hset = highlightSet();
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
            const hideLabels = performanceMode && (totalNodes >= PERF_LABEL_THRESHOLD || state.view.scale < 0.85);
            return { performanceMode, sampledEdges, hideLabels };
        })();
        if (perfStatus) {
            if (renderPlan.performanceMode) {
                perfStatus.textContent = `Performance mode: ${renderPlan.sampledEdges.length}/${graph.edges.length} edges${renderPlan.hideLabels ? ', labels simplified' : ''}`;
            } else {
                perfStatus.textContent = '';
            }
        }
        if (state.selectedId && graph.nodeById[state.selectedId]) {
            updateMeta(graph.nodeById[state.selectedId]);
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
                ctx.strokeStyle = e.isActive ? palette.edge : 'rgba(208,215,222,0.3)';
                ctx.lineWidth = (e.isActive ? 1.2 : 0.9) / state.view.scale;
                ctx.beginPath();
                ctx.moveTo(e.x1, e.y1);
                ctx.lineTo(e.x2, e.y2);
                ctx.stroke();
            }

            drawnNodes.forEach(n => {
                const active = !hset || hset.has(n.id);
                const selected = state.selectedId === n.id;
                ctx.beginPath();
                ctx.fillStyle = active ? (palette[n.type] || '#6c757d') : palette.dim;
                ctx.arc(n.x, n.y, selected ? r + 2 : r, 0, Math.PI * 2);
                ctx.fill();
                if (selected) {
                    ctx.strokeStyle = '#0f172a';
                    ctx.lineWidth = 1.5 / state.view.scale;
                    ctx.stroke();
                }
                if (!renderPlan.hideLabels) {
                    ctx.fillStyle = '#fff';
                    ctx.font = `${11 / state.view.scale}px sans-serif`;
                    const label = shortLabel(n.label || n.id, 13);
                    const tw = ctx.measureText(label).width;
                    ctx.fillText(label, n.x - tw / 2, n.y + 3 / state.view.scale);
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
                        perfStatus.textContent = `Performance mode: ${totalEdges}/${graph.edges.length} edges${renderPlan.hideLabels ? ', labels simplified' : ''}`;
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
            Object.keys(map).forEach(id => {
                const n = map[id];
                positions[id] = { x: n.x, y: n.y };
            });
            const plannedEdges = edgesToDraw.map(edge => ({ from: edge.from, to: edge.to, isInferred: false }));
            const highlightIds = hset ? Array.from(hset) : [];
            requestEdgePlanFromWorker({ edges: plannedEdges, positions, highlightIds, activeMode: 'both_highlight' }).then(jobs => {
                if (token !== renderSeq) return;
                if (Array.isArray(jobs) && jobs.length) {
                    startEdgeRender(jobs);
                    return;
                }
                const fallbackJobs = edgesToDraw.map(edge => {
                    const from = map[edge.from];
                    const to = map[edge.to];
                    if (!from || !to) return null;
                    return {
                        x1: from.x,
                        y1: from.y,
                        x2: to.x,
                        y2: to.y,
                        isInferred: false,
                        isActive: !hset || (hset.has(edge.from) && hset.has(edge.to)),
                    };
                }).filter(Boolean);
                startEdgeRender(fallbackJobs);
            });
            return;
        }

        const edgeJobs = edgesToDraw.map(edge => {
            const from = map[edge.from];
            const to = map[edge.to];
            if (!from || !to) return null;
            return {
                x1: from.x,
                y1: from.y,
                x2: to.x,
                y2: to.y,
                isInferred: false,
                isActive: !hset || (hset.has(edge.from) && hset.has(edge.to)),
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
            types: allowedTypes(),
            obj: objectTypeSel.value || 'all',
            isolated: !!isolatedToggle.checked,
            hl: highlightSel.value || 'none',
            q: String(searchInput.value || '').trim(),
            sel: state.selectedId || '',
            view: { scale: Number(state.view.scale || 1), tx: Number(state.view.tx || 0), ty: Number(state.view.ty || 0) },
        };
    }

    function applyPresetState(payload) {
        const wanted = new Set(Array.isArray(payload.types) ? payload.types : []);
        typeChecks.forEach(c => { c.checked = wanted.size ? wanted.has(c.value) : true; });
        if (payload.obj && Array.from(objectTypeSel.options).some(o => o.value === payload.obj)) objectTypeSel.value = payload.obj;
        isolatedToggle.checked = !!payload.isolated;
        if (payload.hl && Array.from(highlightSel.options).some(o => o.value === payload.hl)) highlightSel.value = payload.hl;
        searchInput.value = String(payload.q || '');
        state.selectedId = String(payload.sel || '');
        if (payload.view && Number.isFinite(payload.view.scale) && Number.isFinite(payload.view.tx) && Number.isFinite(payload.view.ty)) {
            state.view.scale = Math.min(3, Math.max(0.3, Number(payload.view.scale)));
            state.view.tx = Number(payload.view.tx);
            state.view.ty = Number(payload.view.ty);
            state.hasViewportState = true;
        }
        draw();
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
                node_types: allowedTypes(),
                object_type: objectTypeSel.value,
                isolated_only: isolatedToggle.checked ? 1 : 0,
                highlight_mode: highlightSel.value,
                selected_id: state.selectedId || '',
            },
            summary: {
                node_total: graph.nodes.length,
                edge_total: graph.edges.length,
                counts: graph.stats,
            },
            nodes: graph.nodes,
            edges: graph.edges,
        };
        downloadBlob('access-diagram.filtered.json', `${JSON.stringify(payload, null, 2)}\n`, 'application/json;charset=utf-8');
    }

    function csvEscape(value) {
        if (diagramUtils.csvEscape) {
            return diagramUtils.csvEscape(value);
        }
        const s = String(value === undefined || value === null ? '' : value);
        return `"${s.replace(/"/g, '""')}"`;
    }

    function exportFilteredCsv() {
        const nodeHeader = ['id', 'label', 'type', 'uid', 'scope', 'space_id', 'object_type', 'detail_path', 'list_path'];
        const edgeHeader = ['from', 'to', 'label', 'relation'];
        const nodeRows = graph.nodes.map(n => {
            const m = n.meta || {};
            return [n.id, n.label || '', n.type || '', m.uid || '', m.scope || '', m.space_id || '', m.object_type || '', m.detail_path || '', m.list_path || ''];
        });
        const edgeRows = graph.edges.map(e => [e.from || '', e.to || '', e.label || '', (e.meta && e.meta.relation) ? e.meta.relation : '']);
        const nodeCsv = [nodeHeader, ...nodeRows].map(row => row.map(csvEscape).join(',')).join('\n');
        const edgeCsv = [edgeHeader, ...edgeRows].map(row => row.map(csvEscape).join(',')).join('\n');
        downloadBlob('access-diagram.filtered.csv', `# nodes\n${nodeCsv}\n\n# edges\n${edgeCsv}\n`, 'text/csv;charset=utf-8');
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
        const pad = r + 70;
        minX -= pad;
        minY -= pad;
        maxX += 280;
        maxY += pad;
        const width = Math.max(420, Math.ceil(maxX - minX));
        const height = Math.max(320, Math.ceil(maxY - minY));

        const hset = highlightSet();
        const hasHighlight = !!(hset && hset.size);

        const edgeMarkup = graph.edges.map(e => {
            const from = map[e.from];
            const to = map[e.to];
            if (!from || !to) return '';
            const active = !hasHighlight || (hset.has(e.from) && hset.has(e.to));
            const stroke = active ? '#d0d7de' : '#d8dde3';
            return `<line x1="${(from.x - minX).toFixed(1)}" y1="${(from.y - minY).toFixed(1)}" x2="${(to.x - minX).toFixed(1)}" y2="${(to.y - minY).toFixed(1)}" stroke="${stroke}" stroke-width="1.3" />`;
        }).join('\n');

        const nodeMarkup = drawnNodes.map(n => {
            const active = !hasHighlight || hset.has(n.id);
            const selected = state.selectedId === n.id;
            const fill = active ? (palette[n.type] || '#6c757d') : palette.dim;
            const stroke = selected ? '#111827' : '#ffffff';
            const strokeWidth = selected ? '2.8' : '1.1';
            const x = (n.x - minX).toFixed(1);
            const y = (n.y - minY).toFixed(1);
            const label = xmlEscape(shortLabel(n.label || n.id, 18));
            return `<g><circle cx="${x}" cy="${y}" r="${r}" fill="${fill}" stroke="${stroke}" stroke-width="${strokeWidth}" /><text x="${(Number(x) - 26).toFixed(1)}" y="${(Number(y) + 4).toFixed(1)}" font-size="11" font-family="system-ui, sans-serif" fill="#2b2f33">${label}</text></g>`;
        }).join('\n');

        const svg = `<?xml version="1.0" encoding="UTF-8"?>\n<svg xmlns="http://www.w3.org/2000/svg" width="${width}" height="${height}" viewBox="0 0 ${width} ${height}">\n<rect width="100%" height="100%" fill="#ffffff"/>\n${edgeMarkup}\n${nodeMarkup}\n</svg>\n`;
        downloadBlob('access-diagram.filtered.svg', svg, 'image/svg+xml;charset=utf-8');
    }

    function updateMeta(node) {
        const meta = node.meta || {};
        metaTitle.textContent = node.label || node.id;
        metaType.textContent = `Type: ${node.type}`;
        metaTypeBadge.textContent = node.type === 'entity'
            ? 'Entity/API Principal'
            : (node.type === 'membership'
                ? 'Space Membership'
                : (node.type === 'role'
                    ? 'Role'
                    : 'Protected Object'));
        metaId.textContent = node.id;
        metaDegree.textContent = String(graph.degree[node.id] || 0);

        const rows = ['accMetaUidRow','accMetaScopeRow','accMetaObjTypeRow','accMetaSpaceRow'];
        rows.forEach(id => document.getElementById(id).classList.add('d-none'));
        metaActionRow.classList.add('d-none');
        metaOpenDetail.classList.add('d-none');
        metaOpenList.classList.add('d-none');

        if (meta.uid) {
            document.getElementById('accMetaUid').textContent = meta.uid;
            document.getElementById('accMetaUidRow').classList.remove('d-none');
        }
        if (meta.scope || meta.scope_level) {
            document.getElementById('accMetaScope').textContent = meta.scope || meta.scope_level;
            document.getElementById('accMetaScopeRow').classList.remove('d-none');
        }
        if (meta.object_type) {
            document.getElementById('accMetaObjType').textContent = meta.object_type;
            document.getElementById('accMetaObjTypeRow').classList.remove('d-none');
        }
        if (meta.space_id) {
            document.getElementById('accMetaSpace').textContent = meta.space_id;
            document.getElementById('accMetaSpaceRow').classList.remove('d-none');
        }

        const relationLines = [];
        const maxRelations = 10;
        graph.edges.forEach(edge => {
            if (relationLines.length >= maxRelations) {
                return;
            }
            if (edge.from !== node.id && edge.to !== node.id) {
                return;
            }
            const otherId = edge.from === node.id ? edge.to : edge.from;
            const otherNode = graph.nodeById[otherId];
            const relation = String((edge.meta && edge.meta.relation) || edge.label || 'linked_to');
            const target = otherNode ? (otherNode.label || otherNode.id) : otherId;
            relationLines.push({
                nodeId: otherId,
                text: `${relation} -> ${target}`,
            });
        });
        if (relationLines.length === 0) {
            state.relationNodeIds = [];
            state.relationIndex = -1;
            metaRelations.innerHTML = '<span class="text-muted">No related edges.</span>';
        } else {
            state.relationNodeIds = relationLines.map(item => item.nodeId);
            state.relationIndex = 0;
            metaRelations.innerHTML = relationLines.map(item =>
                `<button type="button" class="btn btn-link btn-sm p-0 text-start" data-rel-node="${item.nodeId}">${item.text}</button>`
            ).join('<br>');
            updateRelationFocusVisuals(false);
        }

        if (meta.detail_path) {
            metaOpenDetail.href = `${radAdminUrl}${meta.detail_path}`;
            metaOpenDetail.classList.remove('d-none');
            metaActionRow.classList.remove('d-none');
        }

        if (node.type === 'entity') {
            metaOpenList.href = `${radAdminUrl}/user/view`;
            metaOpenList.textContent = 'Open users';
            metaOpenList.classList.remove('d-none');
            metaActionRow.classList.remove('d-none');
        } else if (node.type === 'role') {
            metaOpenList.href = `${radAdminUrl}/role/view`;
            metaOpenList.textContent = 'Open roles';
            metaOpenList.classList.remove('d-none');
            metaActionRow.classList.remove('d-none');
        } else if (node.type === 'membership') {
            metaOpenList.href = `${radAdminUrl}/membership/view`;
            metaOpenList.textContent = 'Open memberships';
            metaOpenList.classList.remove('d-none');
            metaActionRow.classList.remove('d-none');
        } else if (node.type === 'object') {
            const objectType = String(meta.object_type || '');
            if (objectType === 'ms') {
                metaOpenList.href = `${radAdminUrl}/microservice/view`;
                metaOpenList.textContent = 'Open microservices';
                metaOpenList.classList.remove('d-none');
                metaActionRow.classList.remove('d-none');
            } else if (objectType === 'route') {
                metaOpenList.href = `${radAdminUrl}/route/viewall`;
                metaOpenList.textContent = 'Open routes';
                metaOpenList.classList.remove('d-none');
                metaActionRow.classList.remove('d-none');
            }
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
        updateMeta(node);
        if (centerNode) {
            state.view.tx = (canvas.width / 2) - (node.x * state.view.scale);
            state.view.ty = (canvas.height / 2) - (node.y * state.view.scale);
        }
        draw(false);
        syncUrlState();
        return true;
    }

    function focusSearch() {
        const q = String(searchInput.value || '').trim().toLowerCase();
        if (!q) {
            searchFeedback.textContent = 'Enter a node label, ID, or UID.';
            searchFeedback.className = 'small text-muted mt-1';
            return;
        }

        const rank = (n) => {
            const id = String(n.id || '').toLowerCase();
            const label = String(n.label || '').toLowerCase();
            const uid = String((n.meta && n.meta.uid) || '').toLowerCase();
            if (id === q || label === q || uid === q) return 0;
            if (id.startsWith(q) || label.startsWith(q) || uid.startsWith(q)) return 1;
            if (id.includes(q) || label.includes(q) || uid.includes(q)) return 2;
            return 9;
        };

        const matches = drawnNodes
            .map(n => ({ n, s: rank(n) }))
            .filter(v => v.s < 9)
            .sort((a, b) => a.s - b.s || String(a.n.label || '').localeCompare(String(b.n.label || '')));

        if (!matches.length) {
            searchFeedback.textContent = 'No match in current graph.';
            searchFeedback.className = 'small text-danger mt-1';
            return;
        }

        const node = matches[0].n;
        selectNodeById(node.id, true);
        searchFeedback.textContent = `Focused: ${node.label || node.id}`;
        searchFeedback.className = 'small text-success mt-1';
    }

    resizeCanvas();
    applyUrlState();
    loadPresets();
    renderPresetOptions();
    draw();

    window.addEventListener('resize', () => { resizeCanvas(); draw(false); });
    window.addEventListener('beforeunload', () => {
        if (edgeWorker) {
            try { edgeWorker.terminate(); } catch (e) {}
            edgeWorker = null;
        }
        Object.keys(edgeWorkerPending).forEach(k => { delete edgeWorkerPending[k]; });
    });
    redrawBtn?.addEventListener('click', draw);
    typeChecks.forEach(c => c.addEventListener('change', draw));
    objectTypeSel?.addEventListener('change', draw);
    isolatedToggle?.addEventListener('change', draw);
    highlightSel?.addEventListener('change', draw);
    searchInput?.addEventListener('keydown', (e) => { if (e.key === 'Enter') { e.preventDefault(); focusSearch(); } });
    focusBtn?.addEventListener('click', focusSearch);

    canvas.addEventListener('mousedown', (e) => {
        state.isPanning = true;
        state.movedWhilePanning = false;
        state.lastMouse = { x: e.clientX, y: e.clientY };
    });
    canvas.addEventListener('mouseup', () => { state.isPanning = false; state.lastMouse = null; });
    canvas.addEventListener('mouseleave', () => { state.isPanning = false; state.lastMouse = null; });
    canvas.addEventListener('mousemove', (e) => {
        if (!state.isPanning || !state.lastMouse) return;
        const dx = e.clientX - state.lastMouse.x;
        const dy = e.clientY - state.lastMouse.y;
        if (Math.abs(dx) + Math.abs(dy) > 1) state.movedWhilePanning = true;
        state.view.tx += dx;
        state.view.ty += dy;
        state.lastMouse = { x: e.clientX, y: e.clientY };
        state.hasViewportState = true;
        draw(false);
        scheduleViewportUrlSync();
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
            metaDetails.classList.add('d-none');
            metaDefault.classList.remove('d-none');
            draw();
            return;
        }
        selectNodeById(node.id, false);
    });

    metaRelations?.addEventListener('click', (e) => {
        const target = e.target;
        if (!(target instanceof HTMLElement)) {
            return;
        }
        const relNodeId = target.getAttribute('data-rel-node');
        if (!relNodeId) {
            return;
        }
        const relButtons = Array.from(metaRelations.querySelectorAll('[data-rel-node]'));
        const idx = relButtons.findIndex(btn => btn.getAttribute('data-rel-node') === relNodeId);
        if (idx >= 0) {
            state.relationIndex = idx;
        }
        selectNodeById(relNodeId, true);
    });

    document.addEventListener('keydown', (e) => {
        if (isTypingContext(e.target)) {
            return;
        }
        if (!state.relationNodeIds.length) {
            return;
        }
        const key = String(e.key || '').toLowerCase();
        if (key === 'j') {
            e.preventDefault();
            const len = state.relationNodeIds.length;
            state.relationIndex = state.relationIndex < 0 ? 0 : ((state.relationIndex + 1) % len);
            updateRelationFocusVisuals(true);
        } else if (key === 'k') {
            e.preventDefault();
            const len = state.relationNodeIds.length;
            state.relationIndex = state.relationIndex < 0 ? 0 : ((state.relationIndex - 1 + len) % len);
            updateRelationFocusVisuals(true);
        } else if (key === 'enter' && state.relationIndex >= 0) {
            const targetId = state.relationNodeIds[state.relationIndex] || '';
            if (targetId) {
                e.preventDefault();
                selectNodeById(targetId, true);
            }
        }
    });

    canvas.addEventListener('wheel', (e) => {
        e.preventDefault();
        const sf = e.deltaY < 0 ? 1.1 : 0.9;
        state.view.scale = Math.min(3, Math.max(0.3, state.view.scale * sf));
        state.hasViewportState = true;
        draw(false);
        scheduleViewportUrlSync();
    }, { passive: false });

    zoomInBtn?.addEventListener('click', () => {
        state.view.scale = Math.min(3, state.view.scale * 1.2);
        state.hasViewportState = true;
        draw(false);
        scheduleViewportUrlSync();
    });
    zoomOutBtn?.addEventListener('click', () => {
        state.view.scale = Math.max(0.3, state.view.scale / 1.2);
        state.hasViewportState = true;
        draw(false);
        scheduleViewportUrlSync();
    });
    zoomResetBtn?.addEventListener('click', () => {
        state.view.scale = 1;
        state.view.tx = canvas.width / 2;
        state.view.ty = canvas.height / 2;
        state.hasViewportState = true;
        draw(false);
        scheduleViewportUrlSync();
    });

    exportPngBtn?.addEventListener('click', () => {
        const link = document.createElement('a');
        link.download = 'access-diagram.filtered.png';
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
