<?php
$seed = $this->runData['data']['dbview_seed'] ?? ['nodes' => [], 'edges' => []];
?>

<div class="alert alert-info d-flex align-items-start">
    <div class="me-2"><i class="bi bi-diagram-3" aria-hidden="true"></i></div>
    <div class="small">
        Visualizes database tables and their foreign key relationships. Use the filters to focus or trim the view; click a table to see columns and keys.
    </div>
</div>

<div class="row g-3">
    <div class="col-lg-3">
        <div class="card h-100">
            <div class="card-body">
                <h2 class="h6 mb-3">Filters</h2>
                <div class="mb-3">
                    <label class="form-label form-label-sm" for="filterTableSelect">Filter tables</label>
                    <select id="filterTableSelect" class="form-select form-select-sm">
                        <option value="">All tables</option>
                    </select>
                    <small class="text-muted">Lists a_ tables (shown without the prefix).</small>
                </div>
                <div class="mb-3">
                    <label class="form-label form-label-sm" for="maxNodes">Max tables</label>
                    <input type="number" id="maxNodes" class="form-control form-control-sm" value="<?php echo max(30, min(200, count($seed['nodes'] ?? []))); ?>" min="10" max="400">
                    <small class="text-muted">Limit nodes for readability.</small>
                </div>
                <div class="d-flex gap-2">
                    <button class="btn btn-outline-secondary btn-sm w-100" id="redrawDbview"><i class="bi bi-arrow-clockwise"></i> Redraw</button>
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-2 gap-3 flex-wrap">
                    <div class="d-flex align-items-center gap-2 flex-wrap">
                        <div class="text-muted small">Nodes: <span id="nodeCount"><?php echo count($seed['nodes'] ?? []); ?></span> • Edges: <span id="edgeCount"><?php echo count($seed['edges'] ?? []); ?></span></div>
                        <div class="text-muted small" id="selectionStatus" style="min-width: 150px;">No table selected</div>
                    </div>
                    <div class="d-flex gap-2 align-items-center flex-wrap">
                        <div class="btn-group btn-group-sm" role="group">
                            <button class="btn btn-outline-secondary" id="zoomIn" title="Zoom in"><i class="bi bi-zoom-in"></i></button>
                            <button class="btn btn-outline-secondary" id="zoomOut" title="Zoom out"><i class="bi bi-zoom-out"></i></button>
                            <button class="btn btn-outline-secondary" id="zoomReset" title="Reset view"><i class="bi bi-aspect-ratio"></i></button>
                        </div>
                        <button class="btn btn-outline-secondary btn-sm" id="exportPng"><i class="bi bi-download"></i> PNG</button>
                    </div>
                </div>
                <canvas id="dbviewCanvas" class="border rounded w-100 mb-3" style="min-height:520px;"></canvas>
                <details>
                    <summary class="small text-muted">Raw data</summary>
                    <pre class="small mb-0" id="dbviewSeed" style="max-height: 200px; overflow:auto;"><?php echo htmlspecialchars(json_encode($seed, JSON_PRETTY_PRINT)); ?></pre>
                </details>
            </div>
        </div>
    </div>
    <div class="col-lg-3">
        <div class="card h-100">
            <div class="card-body">
                <h2 class="h6 mb-3">Details</h2>
                <div id="metaDefault" class="text-muted small">
                    Select a table to see its columns and keys.
                    <div class="mt-2">
                        <span class="badge bg-primary me-1">PK</span><span class="text-muted">Primary Key</span><br>
                        <span class="badge bg-success me-1">FK</span><span class="text-muted">Foreign Key</span>
                    </div>
                </div>
                <div id="metaDetails" class="d-none">
                    <div class="fw-semibold" id="metaTitle"></div>
                    <div class="text-muted small mb-2" id="metaSchema"></div>
                    <div class="small mb-1"><span class="text-muted">Columns:</span> <span id="metaColCount"></span></div>
                    <div class="small mb-1"><span class="text-muted">FKs:</span> <span id="metaFkCount"></span></div>
                    <div class="small mb-1"><span class="text-muted">Rows (est):</span> <span id="metaRowCount"></span></div>
                    <div class="small fw-semibold mt-2 mb-1">Columns</div>
                    <div class="small" id="metaColumns"></div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const data = <?php echo json_encode($seed); ?>;
    const canvas = document.getElementById('dbviewCanvas');
    const redrawBtn = document.getElementById('redrawDbview');
    const exportBtn = document.getElementById('exportPng');
    const zoomInBtn = document.getElementById('zoomIn');
    const zoomOutBtn = document.getElementById('zoomOut');
    const zoomResetBtn = document.getElementById('zoomReset');
    const tableSelect = document.getElementById('filterTableSelect');
    const maxNodesInput = document.getElementById('maxNodes');
    const nodeCount = document.getElementById('nodeCount');
    const edgeCount = document.getElementById('edgeCount');
    const selectionStatus = document.getElementById('selectionStatus');
    const metaDefault = document.getElementById('metaDefault');
    const metaDetails = document.getElementById('metaDetails');
    const metaTitle = document.getElementById('metaTitle');
    const metaSchema = document.getElementById('metaSchema');
    const metaColCount = document.getElementById('metaColCount');
    const metaFkCount = document.getElementById('metaFkCount');
    const metaRowCount = document.getElementById('metaRowCount');
    const metaColumns = document.getElementById('metaColumns');

    const allNodes = (data.nodes || []);
    const baseNodes = allNodes.filter(n => (n.label || '').startsWith('a_'));
    const allowedIds = new Set(baseNodes.map(n => n.id));
    let nodes = baseNodes;
    let edges = (data.edges || []).filter(e => allowedIds.has(e.from) && allowedIds.has(e.to));
    let filteredNodes = nodes.slice();
    let filteredEdges = edges.slice();
    let positions = {};
    let selectedId = null;
    const view = { tx: 0, ty: 0, scale: 1 };

    function applyFilters() {
        const selected = (tableSelect && tableSelect.value) ? tableSelect.value : '';
        const maxNodes = Math.min(400, Math.max(10, parseInt(maxNodesInput.value || '200', 10)));
        let list = (nodes || []).filter(n => (n.label || '').startsWith('a_'));
        if (selected) {
            list = list.filter(n => (n.label || '') === selected);
        }
        positions = {};
        selectedId = null;
        selectionStatus.textContent = 'No table selected';
        metaDetails.classList.add('d-none');
        metaDefault.classList.remove('d-none');
        filteredNodes = list.slice(0, maxNodes);
        const allowed = new Set(filteredNodes.map(n => n.id));
        filteredEdges = edges.filter(e => allowed.has(e.from) && allowed.has(e.to));
        nodeCount.textContent = filteredNodes.length;
        edgeCount.textContent = filteredEdges.length;
        view.tx = canvas.width * 0.26;
        view.ty = canvas.height * 0.22;
        layout();
        draw();
    }

    function layout() {
        const n = filteredNodes.length || 1;
        const centerX = canvas.width * 0.26;
        const centerY = canvas.height * 0.22;
        if (n === 1 && filteredNodes[0]) {
            positions[filteredNodes[0].id] = { x: centerX, y: centerY };
            return;
        }
        const radius = Math.max(110, Math.min(canvas.width, canvas.height) / 3.4);
        filteredNodes.forEach((node, idx) => {
            const angle = (idx / n) * Math.PI * 2;
            positions[node.id] = {
                x: centerX + radius * Math.cos(angle),
                y: centerY + radius * Math.sin(angle),
            };
        });
    }

    function draw() {
        const ctx = canvas.getContext('2d');
        ctx.clearRect(0, 0, canvas.width, canvas.height);
        ctx.save();
        ctx.translate(view.tx, view.ty);
        ctx.scale(view.scale, view.scale);
        ctx.lineWidth = 1;
        ctx.strokeStyle = '#cbd5e1';
        ctx.fillStyle = '#f8fafc';

        filteredEdges.forEach(edge => {
            const from = positions[edge.from];
            const to = positions[edge.to];
            if (!from || !to) return;
            ctx.beginPath();
            ctx.moveTo(from.x, from.y);
            ctx.lineTo(to.x, to.y);
            ctx.stroke();
        });

        filteredNodes.forEach(node => {
            const pos = positions[node.id];
            if (!pos) return;
            const isSelected = selectedId === node.id;
            const r = isSelected ? 26 : 22;
            ctx.beginPath();
            ctx.fillStyle = isSelected ? '#0d6efd' : '#e9f0ff';
            ctx.strokeStyle = isSelected ? '#0b5ed7' : '#7c8aa5';
            ctx.lineWidth = isSelected ? 2 : 1;
            ctx.arc(pos.x, pos.y, r, 0, Math.PI * 2);
            ctx.fill();
            ctx.stroke();
            ctx.fillStyle = isSelected ? '#fff' : '#0f172a';
            ctx.font = '12px system-ui';
            ctx.textAlign = 'center';
            ctx.textBaseline = 'middle';
            ctx.fillText((node.label || '').slice(0, 12), pos.x, pos.y);
        });
        ctx.restore();
    }

    function selectNode(id) {
        selectedId = id;
        const node = filteredNodes.find(n => n.id === id);
        if (!node) {
            selectionStatus.textContent = 'No table selected';
            metaDetails.classList.add('d-none');
            metaDefault.classList.remove('d-none');
            draw();
            return;
        }
        selectionStatus.textContent = node.label;
        metaDetails.classList.remove('d-none');
        metaDefault.classList.add('d-none');
        const meta = node.meta || {};
        metaTitle.textContent = node.label;
        metaSchema.textContent = meta.schema || '';
        metaColCount.textContent = meta.col_count ?? (meta.columns ? meta.columns.length : '');
        metaFkCount.textContent = meta.fk_count ?? 0;
        metaRowCount.textContent = meta.rows != null ? meta.rows : '—';
        const cols = meta.columns || [];
        if (cols.length) {
            const html = cols.map(c => {
                const pk = c.key === 'PRI' ? '<span class="badge bg-primary ms-1">PK</span>' : '';
                const fk = c.key === 'MUL' ? '<span class="badge bg-success ms-1">FK</span>' : '';
                return `<div class="mb-1"><code>${c.name}</code> <span class="text-muted">${c.type}</span>${pk}${fk}${c.nullable ? '' : '<span class="text-muted ms-1">NOT NULL</span>'}</div>`;
            }).join('');
            metaColumns.innerHTML = html;
        } else {
            metaColumns.innerHTML = '<span class="text-muted">No columns found.</span>';
        }
        draw();
    }

    function hitTest(x, y) {
        const rect = canvas.getBoundingClientRect();
        const px = (x - rect.left - view.tx) / view.scale;
        const py = (y - rect.top - view.ty) / view.scale;
        return filteredNodes.find(n => {
            const pos = positions[n.id];
            if (!pos) return false;
            const dx = px - pos.x;
            const dy = py - pos.y;
            return Math.sqrt(dx*dx + dy*dy) <= 24;
        });
    }

    canvas.addEventListener('click', (e) => {
        const node = hitTest(e.clientX, e.clientY);
        if (node) {
            selectNode(node.id);
        }
    });

    window.addEventListener('resize', () => {
        resizeCanvas();
        layout();
        draw();
    });

    function resizeCanvas() {
        const rect = canvas.getBoundingClientRect();
        canvas.width = rect.width;
        canvas.height = rect.height;
        view.tx = canvas.width / 2;
        view.ty = canvas.height / 2;
    }

    redrawBtn?.addEventListener('click', () => applyFilters());
    tableSelect?.addEventListener('change', () => applyFilters());
    maxNodesInput?.addEventListener('change', () => applyFilters());
    zoomInBtn?.addEventListener('click', () => {
        view.scale = Math.min(3, view.scale * 1.2);
        draw();
    });
    zoomOutBtn?.addEventListener('click', () => {
        view.scale = Math.max(0.3, view.scale / 1.2);
        draw();
    });
    zoomResetBtn?.addEventListener('click', () => {
        view.scale = 1;
        view.tx = canvas.width / 2;
        view.ty = canvas.height / 2;
        draw();
    });
    exportBtn?.addEventListener('click', () => {
        const link = document.createElement('a');
        link.download = 'dbview.png';
        link.href = canvas.toDataURL('image/png');
        link.click();
    });

    resizeCanvas();
    if (tableSelect) {
        tableSelect.innerHTML = '<option value=\"\">All tables</option>';
        const aTables = nodes || [];
        aTables.forEach(n => {
            const opt = document.createElement('option');
            opt.value = n.label || '';
            opt.textContent = (n.label || '').replace(/^a_/, '');
            tableSelect.appendChild(opt);
        });
    }
    applyFilters();
});
</script>
