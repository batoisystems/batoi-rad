<?php
$seed = $this->runData['data']['request_flow_seed'] ?? [
    'generated_at' => '',
    'modes' => [],
];
?>

<style>
.rf-step-chip {
    border: 1px solid #e2e8f0;
    background: #f8fafc;
    border-radius: 999px;
    font-size: 0.75rem;
    padding: 0.2rem 0.55rem;
}
.rf-step-item.active {
    border-color: #0d6efd;
    background: #eef4ff;
}
</style>

<div class="alert alert-info d-flex align-items-start">
    <div class="me-2"><i class="bi bi-diagram-2" aria-hidden="true"></i></div>
    <div class="small">
        Sequence view of request lifecycle across Web, API, and RAD Admin gateways. Select a flow mode and click any step for details.
    </div>
</div>

<div class="row g-3">
    <div class="col-lg-3">
        <div class="card h-100">
            <div class="card-body">
                <h2 class="h6 mb-3">Controls</h2>
                <div class="mb-3">
                    <label class="form-label form-label-sm" for="rfMode">Flow mode</label>
                    <select id="rfMode" class="form-select form-select-sm" aria-label="Select request flow mode"></select>
                </div>
                <div class="mb-3">
                    <label class="form-label form-label-sm" for="rfSearch">Find step</label>
                    <div class="input-group input-group-sm">
                        <input id="rfSearch" type="search" class="form-control" placeholder="Search step or file" aria-label="Search step">
                        <button class="btn btn-outline-secondary" id="rfSearchBtn" type="button" aria-label="Find step"><i class="bi bi-search"></i></button>
                    </div>
                    <div id="rfSearchFeedback" class="small text-muted mt-1">Press Enter to focus.</div>
                </div>
                <div class="small text-muted mb-2" id="rfModeNote">Mode notes appear here.</div>
                <?php if (!empty($seed['generated_at'])): ?>
                    <div class="small text-muted">Generated: <?php echo htmlspecialchars((string)$seed['generated_at']); ?></div>
                <?php endif; ?>
                <hr>
                <h2 class="h6 mb-2">Saved Views</h2>
                <div class="mb-2">
                    <select id="rfPresetSelect" class="form-select form-select-sm" aria-label="Saved views"></select>
                </div>
                <div class="input-group input-group-sm">
                    <input id="rfPresetName" type="text" class="form-control" placeholder="Preset name" aria-label="Preset name">
                    <button class="btn btn-outline-primary" id="rfPresetSave" type="button">Save</button>
                </div>
                <div class="d-flex gap-2 mt-2">
                    <button class="btn btn-outline-secondary btn-sm w-100" id="rfPresetLoad" type="button">Load</button>
                    <button class="btn btn-outline-danger btn-sm w-100" id="rfPresetDelete" type="button">Delete</button>
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-2 gap-3 flex-wrap">
                    <div class="d-flex align-items-center gap-2 flex-wrap">
                        <div class="text-muted small">Steps: <span id="rfStepCount">0</span></div>
                        <div class="text-muted small">Selected: <span id="rfSelectedLabel">None</span></div>
                        <div class="text-muted small" id="rfPerfStatus"></div>
                    </div>
                    <div class="d-flex gap-2">
                        <button class="btn btn-outline-secondary btn-sm" id="rfZoomIn" aria-label="Zoom in"><i class="bi bi-zoom-in"></i></button>
                        <button class="btn btn-outline-secondary btn-sm" id="rfZoomOut" aria-label="Zoom out"><i class="bi bi-zoom-out"></i></button>
                        <button class="btn btn-outline-secondary btn-sm" id="rfZoomReset" aria-label="Reset zoom"><i class="bi bi-aspect-ratio"></i></button>
                        <div class="btn-group btn-group-sm" role="group" aria-label="Export controls">
                            <button class="btn btn-outline-secondary" id="rfExportPng" aria-label="Export PNG"><i class="bi bi-download"></i> PNG</button>
                            <button class="btn btn-outline-secondary" id="rfExportSvg" aria-label="Export SVG">SVG</button>
                            <button class="btn btn-outline-secondary" id="rfExportJson" aria-label="Export JSON">JSON</button>
                            <button class="btn btn-outline-secondary" id="rfExportCsv" aria-label="Export CSV">CSV</button>
                        </div>
                    </div>
                </div>
                <canvas id="rfCanvas" class="border rounded w-100 mb-3" style="min-height:460px;"></canvas>
                <div id="rfStepsList" class="d-flex flex-wrap gap-2"></div>
            </div>
        </div>
    </div>

    <div class="col-lg-3">
        <div class="card h-100">
            <div class="card-body">
                <h2 class="h6 mb-3">Step Details</h2>
                <div id="rfMetaDefault" class="small text-muted">
                    Select a step in the diagram or list to inspect implementation details.
                </div>
                <div id="rfMetaDetails" class="d-none">
                    <div class="fw-semibold" id="rfMetaTitle"></div>
                    <div class="text-muted small mb-2" id="rfMetaId"></div>
                    <div class="small mb-2" id="rfMetaDetail"></div>
                    <div class="small text-muted">File:</div>
                    <div class="small"><code id="rfMetaFile"></code></div>
                    <div class="mt-3 d-none d-flex gap-2 flex-wrap" id="rfMetaToolRow">
                        <a href="#" class="btn btn-sm btn-outline-primary" id="rfMetaToolLink" target="_blank" rel="noopener">Open related tool</a>
                        <a href="#" class="btn btn-sm btn-outline-secondary" id="rfMetaDocsLink" target="_blank" rel="noopener">Open docs</a>
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
    const seed = <?php echo json_encode($seed); ?>;
    const modes = Array.isArray(seed.modes) ? seed.modes : [];
    const modeSelect = document.getElementById('rfMode');
    const modeNote = document.getElementById('rfModeNote');
    const searchInput = document.getElementById('rfSearch');
    const searchBtn = document.getElementById('rfSearchBtn');
    const searchFeedback = document.getElementById('rfSearchFeedback');
    const presetSelect = document.getElementById('rfPresetSelect');
    const presetName = document.getElementById('rfPresetName');
    const presetSaveBtn = document.getElementById('rfPresetSave');
    const presetLoadBtn = document.getElementById('rfPresetLoad');
    const presetDeleteBtn = document.getElementById('rfPresetDelete');
    const stepCount = document.getElementById('rfStepCount');
    const selectedLabel = document.getElementById('rfSelectedLabel');
    const perfStatus = document.getElementById('rfPerfStatus');
    const stepsList = document.getElementById('rfStepsList');

    const metaDefault = document.getElementById('rfMetaDefault');
    const metaDetails = document.getElementById('rfMetaDetails');
    const metaTitle = document.getElementById('rfMetaTitle');
    const metaId = document.getElementById('rfMetaId');
    const metaDetail = document.getElementById('rfMetaDetail');
    const metaFile = document.getElementById('rfMetaFile');
    const metaToolRow = document.getElementById('rfMetaToolRow');
    const metaToolLink = document.getElementById('rfMetaToolLink');
    const metaDocsLink = document.getElementById('rfMetaDocsLink');
    const radAdminUrl = <?php echo json_encode($this->runData['route']['rad_admin_url'] ?? ''); ?>;

    const canvas = document.getElementById('rfCanvas');
    if (!canvas || !modes.length) return;
    const ctx = canvas.getContext('2d');

    const zoomIn = document.getElementById('rfZoomIn');
    const zoomOut = document.getElementById('rfZoomOut');
    const zoomReset = document.getElementById('rfZoomReset');
    const exportPngBtn = document.getElementById('rfExportPng');
    const exportSvgBtn = document.getElementById('rfExportSvg');
    const exportJsonBtn = document.getElementById('rfExportJson');
    const exportCsvBtn = document.getElementById('rfExportCsv');

    const state = {
        modeId: modes[0].id,
        selectedStepId: '',
        view: { scale: 1, tx: 0, ty: 0 },
        isPanning: false,
        movedWhilePanning: false,
        lastMouse: null,
        hasViewportState: false,
    };
    let viewportSyncTimer = null;

    let drawnSteps = [];
    const PERF_STEP_THRESHOLD = 22;
    const PERF_EDGE_TARGET = 40;
    const PRESET_STORAGE_KEY = 'rad.devguide.request_flow.presets.v1';
    let presets = [];

    function currentMode() {
        return modes.find(m => m.id === state.modeId) || modes[0];
    }

    function resizeCanvas() {
        const rect = canvas.getBoundingClientRect();
        canvas.width = rect.width;
        canvas.height = rect.height;
        if (!state.hasViewportState && !state.view.tx && !state.view.ty) {
            state.view.tx = canvas.width * 0.1;
            state.view.ty = canvas.height * 0.5;
        }
    }

    function syncUrlState() {
        const params = new URLSearchParams(window.location.search || '');
        if (state.modeId) {
            params.set('mode', state.modeId);
        } else {
            params.delete('mode');
        }
        const q = String(searchInput.value || '').trim();
        if (q) {
            params.set('q', q);
        } else {
            params.delete('q');
        }
        if (state.selectedStepId) {
            params.set('step', state.selectedStepId);
        } else {
            params.delete('step');
        }
        params.set('z', String(Number(state.view.scale || 1).toFixed(3)));
        params.set('x', String(Math.round(state.view.tx || 0)));
        params.set('y', String(Math.round(state.view.ty || 0)));
        const query = params.toString();
        const nextUrl = `${window.location.pathname}${query ? `?${query}` : ''}${window.location.hash || ''}`;
        window.history.replaceState(null, '', nextUrl);
    }

    function scheduleViewportSync() {
        if (viewportSyncTimer) {
            clearTimeout(viewportSyncTimer);
        }
        viewportSyncTimer = setTimeout(() => {
            syncUrlState();
        }, 180);
    }

    function applyUrlState() {
        const params = new URLSearchParams(window.location.search || '');
        const modeParam = String(params.get('mode') || '').trim();
        if (modeParam && modes.some(m => m.id === modeParam)) {
            state.modeId = modeParam;
        }
        const qParam = String(params.get('q') || '').trim();
        if (qParam) {
            searchInput.value = qParam;
        }
        const stepParam = String(params.get('step') || '').trim();
        if (stepParam) {
            state.selectedStepId = stepParam;
        }
        const zParam = parseFloat(String(params.get('z') || ''));
        const xParam = parseFloat(String(params.get('x') || ''));
        const yParam = parseFloat(String(params.get('y') || ''));
        if (Number.isFinite(zParam) && zParam >= 0.4 && zParam <= 2.5) {
            state.view.scale = zParam;
            state.hasViewportState = true;
        }
        if (Number.isFinite(xParam) && Number.isFinite(yParam)) {
            state.view.tx = xParam;
            state.view.ty = yParam;
            state.hasViewportState = true;
        }
    }

    function renderModeSelect() {
        modeSelect.innerHTML = '';
        modes.forEach(mode => {
            const opt = document.createElement('option');
            opt.value = mode.id;
            opt.textContent = mode.label;
            modeSelect.appendChild(opt);
        });
        modeSelect.value = state.modeId;
    }

    function layoutSteps(mode) {
        const steps = Array.isArray(mode.steps) ? mode.steps : [];
        const gapX = 180;
        const startX = 60;
        const y = 0;
        return steps.map((step, idx) => ({
            ...step,
            x: startX + idx * gapX,
            y,
            w: 130,
            h: 54,
        }));
    }

    function draw(syncUrl = true) {
        const mode = currentMode();
        modeNote.textContent = mode.note || '';
        stepCount.textContent = String((mode.steps || []).length);

        drawnSteps = layoutSteps(mode);
        if (state.selectedStepId && !drawnSteps.find(s => s.id === state.selectedStepId)) {
            state.selectedStepId = '';
        }

        ctx.clearRect(0, 0, canvas.width, canvas.height);
        ctx.save();
        ctx.translate(state.view.tx, state.view.ty);
        ctx.scale(state.view.scale, state.view.scale);

        const stepById = {};
        drawnSteps.forEach(s => { stepById[s.id] = s; });
        const totalSteps = drawnSteps.length;
        const allEdges = Array.isArray(mode.edges) ? mode.edges : [];
        const performanceMode = totalSteps >= PERF_STEP_THRESHOLD || allEdges.length > PERF_EDGE_TARGET;
        let sampledEdges = allEdges;
        if (allEdges.length > PERF_EDGE_TARGET) {
            const step = Math.ceil(allEdges.length / PERF_EDGE_TARGET);
            sampledEdges = allEdges.filter((edge, idx) => {
                if (idx % step === 0) return true;
                if (!state.selectedStepId) return false;
                return edge.from === state.selectedStepId || edge.to === state.selectedStepId;
            });
        }
        const hideLabels = performanceMode && (totalSteps > PERF_STEP_THRESHOLD || state.view.scale < 0.72);
        if (perfStatus) {
            if (performanceMode) {
                perfStatus.textContent = `Performance mode: ${sampledEdges.length}/${allEdges.length} links${hideLabels ? ', labels simplified' : ''}`;
            } else {
                perfStatus.textContent = '';
            }
        }

        sampledEdges.forEach(e => {
            const from = stepById[e.from];
            const to = stepById[e.to];
            if (!from || !to) return;
            const x1 = from.x + from.w;
            const y1 = from.y;
            const x2 = to.x;
            const y2 = to.y;
            ctx.strokeStyle = '#cbd5e1';
            ctx.lineWidth = 1.4 / state.view.scale;
            ctx.beginPath();
            ctx.moveTo(x1, y1);
            ctx.lineTo(x2, y2);
            ctx.stroke();

            ctx.fillStyle = '#94a3b8';
            ctx.beginPath();
            ctx.moveTo(x2, y2);
            ctx.lineTo(x2 - 6 / state.view.scale, y2 - 3 / state.view.scale);
            ctx.lineTo(x2 - 6 / state.view.scale, y2 + 3 / state.view.scale);
            ctx.closePath();
            ctx.fill();
        });

        drawnSteps.forEach(step => {
            const selected = state.selectedStepId === step.id;
            ctx.fillStyle = selected ? '#0d6efd' : '#eef2ff';
            ctx.strokeStyle = selected ? '#0b5ed7' : '#93c5fd';
            ctx.lineWidth = selected ? (2 / state.view.scale) : (1 / state.view.scale);

            const x = step.x - step.w / 2;
            const y = step.y - step.h / 2;
            ctx.beginPath();
            ctx.roundRect(x, y, step.w, step.h, 10 / state.view.scale);
            ctx.fill();
            ctx.stroke();

            if (!hideLabels) {
                ctx.fillStyle = selected ? '#fff' : '#0f172a';
                ctx.font = `${12 / state.view.scale}px sans-serif`;
                ctx.textAlign = 'center';
                ctx.textBaseline = 'middle';
                ctx.fillText(step.label, step.x, step.y);
            }
        });

        ctx.restore();
        renderStepsList();
        updateSelectedMeta();
        if (syncUrl) {
            syncUrlState();
        }
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
            mode: state.modeId || '',
            q: String(searchInput.value || '').trim(),
            step: state.selectedStepId || '',
            view: { scale: Number(state.view.scale || 1), tx: Number(state.view.tx || 0), ty: Number(state.view.ty || 0) },
        };
    }

    function applyPresetState(payload) {
        if (payload.mode && modes.some(m => m.id === payload.mode)) {
            state.modeId = payload.mode;
            modeSelect.value = payload.mode;
        }
        searchInput.value = String(payload.q || '');
        state.selectedStepId = String(payload.step || '');
        if (payload.view && Number.isFinite(payload.view.scale) && Number.isFinite(payload.view.tx) && Number.isFinite(payload.view.ty)) {
            state.view.scale = Math.min(2.5, Math.max(0.4, Number(payload.view.scale)));
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

    function exportCurrentJson() {
        const mode = currentMode();
        const payload = {
            generated_at: new Date().toISOString(),
            mode: {
                id: mode.id || '',
                label: mode.label || '',
                note: mode.note || '',
            },
            state: {
                q: String(searchInput.value || '').trim(),
                selected_step_id: state.selectedStepId || '',
                viewport: {
                    scale: Number(state.view.scale || 1),
                    x: Math.round(state.view.tx || 0),
                    y: Math.round(state.view.ty || 0),
                },
            },
            steps: Array.isArray(mode.steps) ? mode.steps : [],
            edges: Array.isArray(mode.edges) ? mode.edges : [],
        };
        downloadBlob(`request-flow-${state.modeId}.json`, `${JSON.stringify(payload, null, 2)}\n`, 'application/json;charset=utf-8');
    }

    function csvEscape(value) {
        if (diagramUtils.csvEscape) {
            return diagramUtils.csvEscape(value);
        }
        const s = String(value === undefined || value === null ? '' : value);
        return `"${s.replace(/"/g, '""')}"`;
    }

    function exportCurrentCsv() {
        const mode = currentMode();
        const stepHeader = ['id', 'label', 'detail', 'file', 'tool_label', 'tool_path', 'docs_label', 'docs_path'];
        const edgeHeader = ['from', 'to'];
        const stepRows = (Array.isArray(mode.steps) ? mode.steps : []).map(step => [
            step.id || '',
            step.label || '',
            step.detail || '',
            step.file || '',
            (step.tool && step.tool.label) ? step.tool.label : '',
            (step.tool && step.tool.path) ? step.tool.path : '',
            (step.docs && step.docs.label) ? step.docs.label : '',
            (step.docs && step.docs.path) ? step.docs.path : '',
        ]);
        const edgeRows = (Array.isArray(mode.edges) ? mode.edges : []).map(e => [e.from || '', e.to || '']);
        const stepCsv = [stepHeader, ...stepRows].map(row => row.map(csvEscape).join(',')).join('\n');
        const edgeCsv = [edgeHeader, ...edgeRows].map(row => row.map(csvEscape).join(',')).join('\n');
        downloadBlob(`request-flow-${state.modeId}.csv`, `# steps\n${stepCsv}\n\n# edges\n${edgeCsv}\n`, 'text/csv;charset=utf-8');
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

    function exportCurrentSvg() {
        if (!drawnSteps.length) {
            return;
        }
        const mode = currentMode();
        const stepById = {};
        drawnSteps.forEach(s => { stepById[s.id] = s; });

        const minX = Math.min(...drawnSteps.map(s => s.x - 18));
        const minY = Math.min(...drawnSteps.map(s => s.y - 58));
        const maxX = Math.max(...drawnSteps.map(s => s.x + s.w + 110));
        const maxY = Math.max(...drawnSteps.map(s => s.y + s.h + 58));
        const width = Math.max(420, Math.ceil(maxX - minX));
        const height = Math.max(280, Math.ceil(maxY - minY));

        const edgeMarkup = (Array.isArray(mode.edges) ? mode.edges : []).map(e => {
            const from = stepById[e.from];
            const to = stepById[e.to];
            if (!from || !to) return '';
            const x1 = (from.x + from.w - minX).toFixed(1);
            const y1 = (from.y - minY).toFixed(1);
            const x2 = (to.x - minX).toFixed(1);
            const y2 = (to.y - minY).toFixed(1);
            return `<line x1="${x1}" y1="${y1}" x2="${x2}" y2="${y2}" stroke="#cbd5e1" stroke-width="1.4" />`;
        }).join('\n');

        const stepMarkup = drawnSteps.map(step => {
            const selected = step.id === state.selectedStepId;
            const x = (step.x - minX).toFixed(1);
            const y = (step.y - minY).toFixed(1);
            const fill = selected ? '#dbeafe' : '#f8fafc';
            const stroke = selected ? '#2563eb' : '#cbd5e1';
            const label = xmlEscape(step.label || step.id || '');
            return `<g><rect x="${x}" y="${(Number(y) - step.h / 2).toFixed(1)}" rx="10" ry="10" width="${step.w}" height="${step.h}" fill="${fill}" stroke="${stroke}" stroke-width="${selected ? '1.8' : '1.2'}" /><text x="${(Number(x) + 10).toFixed(1)}" y="${(Number(y) + 4).toFixed(1)}" font-size="12" font-family="system-ui, sans-serif" fill="#1f2937">${label}</text></g>`;
        }).join('\n');

        const svg = `<?xml version="1.0" encoding="UTF-8"?>\n<svg xmlns="http://www.w3.org/2000/svg" width="${width}" height="${height}" viewBox="0 0 ${width} ${height}">\n<rect width="100%" height="100%" fill="#ffffff"/>\n${edgeMarkup}\n${stepMarkup}\n</svg>\n`;
        downloadBlob(`request-flow-${state.modeId}.svg`, svg, 'image/svg+xml;charset=utf-8');
    }

    function renderStepsList() {
        const mode = currentMode();
        stepsList.innerHTML = '';
        (mode.steps || []).forEach(step => {
            const btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'btn btn-sm btn-light border rf-step-item' + (state.selectedStepId === step.id ? ' active' : '');
            btn.textContent = step.label;
            btn.addEventListener('click', () => {
                state.selectedStepId = step.id;
                centerSelected();
                draw();
            });
            stepsList.appendChild(btn);
        });
    }

    function updateSelectedMeta() {
        const mode = currentMode();
        const step = (mode.steps || []).find(s => s.id === state.selectedStepId);
        if (!step) {
            selectedLabel.textContent = 'None';
            metaToolRow.classList.add('d-none');
            metaDetails.classList.add('d-none');
            metaDefault.classList.remove('d-none');
            return;
        }
        selectedLabel.textContent = step.label;
        metaTitle.textContent = step.label;
        metaId.textContent = `Step ID: ${step.id}`;
        metaDetail.textContent = step.detail || '';
        metaFile.textContent = step.file || '';
        metaToolRow.classList.add('d-none');
        metaToolLink.classList.add('d-none');
        metaDocsLink.classList.add('d-none');

        if (step.tool && step.tool.path) {
            metaToolLink.textContent = step.tool.label || 'Open related tool';
            metaToolLink.href = `${radAdminUrl}${step.tool.path}`;
            metaToolLink.classList.remove('d-none');
            metaToolRow.classList.remove('d-none');
        }
        if (step.docs && step.docs.path) {
            metaDocsLink.textContent = step.docs.label || 'Open docs';
            metaDocsLink.href = `${radAdminUrl}${step.docs.path}`;
            metaDocsLink.classList.remove('d-none');
            metaToolRow.classList.remove('d-none');
        }
        metaDefault.classList.add('d-none');
        metaDetails.classList.remove('d-none');
    }

    function toCanvasCoords(evt) {
        const rect = canvas.getBoundingClientRect();
        return {
            x: (evt.clientX - rect.left - state.view.tx) / state.view.scale,
            y: (evt.clientY - rect.top - state.view.ty) / state.view.scale,
        };
    }

    function hitStep(pt) {
        return drawnSteps.find(step => {
            const x = step.x - step.w / 2;
            const y = step.y - step.h / 2;
            return pt.x >= x && pt.x <= (x + step.w) && pt.y >= y && pt.y <= (y + step.h);
        });
    }

    function centerSelected() {
        const step = drawnSteps.find(s => s.id === state.selectedStepId);
        if (!step) return;
        state.view.tx = (canvas.width / 2) - (step.x * state.view.scale);
        state.view.ty = (canvas.height / 2) - (step.y * state.view.scale);
    }

    function focusSearch() {
        const q = String(searchInput.value || '').trim().toLowerCase();
        if (!q) {
            searchFeedback.textContent = 'Enter a step label or file path.';
            searchFeedback.className = 'small text-muted mt-1';
            return;
        }
        const mode = currentMode();
        const matches = (mode.steps || []).filter(step => {
            const label = String(step.label || '').toLowerCase();
            const file = String(step.file || '').toLowerCase();
            return label.includes(q) || file.includes(q) || String(step.id || '').toLowerCase().includes(q);
        });
        if (!matches.length) {
            searchFeedback.textContent = 'No matching step in this mode.';
            searchFeedback.className = 'small text-danger mt-1';
            return;
        }
        state.selectedStepId = matches[0].id;
        centerSelected();
        draw();
        searchFeedback.textContent = `Focused: ${matches[0].label}`;
        searchFeedback.className = 'small text-success mt-1';
    }

    renderModeSelect();
    resizeCanvas();
    applyUrlState();
    loadPresets();
    renderPresetOptions();
    modeSelect.value = state.modeId;
    if (!state.selectedStepId) {
        state.selectedStepId = (currentMode().steps || [])[0]?.id || '';
    }
    draw();

    modeSelect.addEventListener('change', () => {
        state.modeId = modeSelect.value;
        state.selectedStepId = (currentMode().steps || [])[0]?.id || '';
        state.view.scale = 1;
        state.view.tx = canvas.width * 0.1;
        state.view.ty = canvas.height * 0.5;
        state.hasViewportState = true;
        draw();
    });

    searchInput.addEventListener('keydown', (e) => {
        if (e.key === 'Enter') {
            e.preventDefault();
            focusSearch();
        }
    });
    searchBtn.addEventListener('click', focusSearch);

    window.addEventListener('resize', () => { resizeCanvas(); draw(false); });

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
        scheduleViewportSync();
    });

    canvas.addEventListener('click', (e) => {
        if (state.movedWhilePanning) {
            state.movedWhilePanning = false;
            return;
        }
        const step = hitStep(toCanvasCoords(e));
        if (!step) return;
        state.selectedStepId = step.id;
        draw();
    });

    canvas.addEventListener('wheel', (e) => {
        e.preventDefault();
        const factor = e.deltaY < 0 ? 1.1 : 0.9;
        state.view.scale = Math.min(2.5, Math.max(0.4, state.view.scale * factor));
        state.hasViewportState = true;
        draw(false);
        scheduleViewportSync();
    }, { passive: false });

    zoomIn.addEventListener('click', () => {
        state.view.scale = Math.min(2.5, state.view.scale * 1.2);
        state.hasViewportState = true;
        draw(false);
        scheduleViewportSync();
    });
    zoomOut.addEventListener('click', () => {
        state.view.scale = Math.max(0.4, state.view.scale / 1.2);
        state.hasViewportState = true;
        draw(false);
        scheduleViewportSync();
    });
    zoomReset.addEventListener('click', () => {
        state.view.scale = 1;
        state.view.tx = canvas.width * 0.1;
        state.view.ty = canvas.height * 0.5;
        state.hasViewportState = true;
        draw(false);
        scheduleViewportSync();
    });

    exportPngBtn.addEventListener('click', () => {
        const link = document.createElement('a');
        link.download = `request-flow-${state.modeId}.png`;
        link.href = canvas.toDataURL('image/png');
        link.click();
    });
    exportSvgBtn.addEventListener('click', exportCurrentSvg);
    exportJsonBtn.addEventListener('click', exportCurrentJson);
    exportCsvBtn.addEventListener('click', exportCurrentCsv);
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
