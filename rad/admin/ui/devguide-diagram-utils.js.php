window.RadDiagramUtils = window.RadDiagramUtils || (function() {
    function downloadBlob(filename, content, type) {
        const blob = new Blob([content], { type: type || 'application/octet-stream' });
        const url = URL.createObjectURL(blob);
        const link = document.createElement('a');
        link.href = url;
        link.download = filename;
        link.click();
        setTimeout(() => URL.revokeObjectURL(url), 400);
    }

    function csvEscape(value) {
        const s = String(value === undefined || value === null ? '' : value);
        return `"${s.replace(/"/g, '""')}"`;
    }

    function xmlEscape(value) {
        return String(value || '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&apos;');
    }

    function readPresets(storageKey) {
        try {
            const raw = localStorage.getItem(String(storageKey || '')) || '[]';
            const parsed = JSON.parse(raw);
            return Array.isArray(parsed) ? parsed : [];
        } catch (e) {
            return [];
        }
    }

    function writePresets(storageKey, presets) {
        localStorage.setItem(String(storageKey || ''), JSON.stringify(Array.isArray(presets) ? presets : []));
    }

    function makePresetId() {
        return `p_${Date.now()}_${Math.random().toString(36).slice(2, 7)}`;
    }

    function createEdgePlannerWorker() {
        if (typeof Worker === 'undefined' || typeof URL === 'undefined' || typeof Blob === 'undefined') {
            return null;
        }
        const workerCode = `
self.onmessage = function(ev) {
  const d = ev && ev.data ? ev.data : {};
  if (d.type !== 'plan') return;
  const edges = Array.isArray(d.edges) ? d.edges : [];
  const positions = d.positions || {};
  const ids = Array.isArray(d.highlightIds) ? d.highlightIds : [];
  const hasHighlight = ids.length > 0;
  const highlight = hasHighlight ? new Set(ids) : null;
  const activeMode = String(d.activeMode || 'both_highlight');
  const selectedId = String(d.selectedId || '');
  const jobs = [];
  for (let i = 0; i < edges.length; i++) {
    const e = edges[i] || {};
    const from = positions[e.from];
    const to = positions[e.to];
    if (!from || !to) continue;
    let isActive = true;
    if (activeMode === 'edge_to_selected') {
      isActive = !selectedId || e.from === selectedId || e.to === selectedId;
    } else {
      isActive = !hasHighlight || (highlight.has(e.from) && highlight.has(e.to));
    }
    jobs.push({
      x1: from.x, y1: from.y, x2: to.x, y2: to.y,
      isInferred: !!e.isInferred,
      isActive: !!isActive
    });
  }
  self.postMessage({ type: 'planResult', reqId: d.reqId, jobs: jobs });
};`;
        try {
            const blob = new Blob([workerCode], { type: 'application/javascript' });
            const url = URL.createObjectURL(blob);
            const worker = new Worker(url);
            setTimeout(() => URL.revokeObjectURL(url), 800);
            return worker;
        } catch (e) {
            return null;
        }
    }

    return {
        downloadBlob,
        csvEscape,
        xmlEscape,
        readPresets,
        writePresets,
        makePresetId,
        createEdgePlannerWorker,
    };
})();
