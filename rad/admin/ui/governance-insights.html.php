<?php
$radAdminUrl = $this->runData['route']['rad_admin_url'] ?? '';
$defaultDays = (int)($this->runData['data']['default_days'] ?? 30);
$filters = $this->runData['data']['filters'] ?? ['q' => ''];
$fsBootstrap = $this->runData['data']['insights_bootstrap']['fs'] ?? null;
?>

    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
        <div>
            <p class="text-muted mb-0">DB-backed version history for app objects plus filesystem tracking for code files (themes, ms, assets).</p>
        </div>
        <div class="d-flex align-items-center gap-2">
            <a class="btn btn-outline-secondary btn-sm" href="<?php echo $radAdminUrl; ?>/governance/changelog">
                <i class="bi bi-arrow-left-circle"></i> Back to Changelog
            </a>
    </div>
</div>

<div class="card mb-3">
    <div class="card-body">
        <div class="row g-2 align-items-end">
            <div class="col-md-9">
                <label class="form-label small text-muted">Search insights (client-side)</label>
                <input type="text" id="insightsSearch" class="form-control form-control-sm" placeholder="Filter by keyword across insights" value="<?php echo htmlspecialchars($filters['q']); ?>">
            </div>
            <div class="col-md-3 d-grid gap-2">
                <button type="button" class="btn btn-primary btn-sm" id="insightsApplyFilter">Apply</button>
                <?php if ($filters['q'] !== '') { ?>
                    <a href="<?php echo $radAdminUrl; ?>/governance/insights" class="btn btn-outline-secondary btn-sm">Reset</a>
                <?php } ?>
            </div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
            <div>
                <h2 class="h5 mb-1">Summary</h2>
                <div class="text-muted small">
                    Sources:
                    <span class="badge bg-light text-dark border">DB: s_ms, routes, controllers, nav, themes, content</span>
                    <span class="badge bg-light text-dark border">FS scan: ms/, theme/, assets/ (ext: <?php echo htmlspecialchars(isset($fsBootstrap['extensions']) ? implode(', ', $fsBootstrap['extensions']) : '—'); ?>)</span>
                    <span class="badge bg-light text-dark border">Versioned manifests: data/versions/theme, data/versions/route</span>
                </div>
            </div>
            <div class="d-flex align-items-center gap-2">
                <select class="form-select form-select-sm" id="insightsDays" style="max-width: 140px;">
                    <option value="7" <?php echo $defaultDays === 7 ? 'selected' : ''; ?>>Last 7 days</option>
                    <option value="30" <?php echo $defaultDays === 30 ? 'selected' : ''; ?>>Last 30 days</option>
                    <option value="90" <?php echo $defaultDays === 90 ? 'selected' : ''; ?>>Last 90 days</option>
                </select>
                <button type="button" class="btn btn-outline-secondary btn-sm" id="insightsRefresh">
                    <i class="bi bi-arrow-clockwise"></i> Refresh
                </button>
            </div>
        </div>

        <div id="insightsStatus" class="text-muted small py-2">Loading...</div>
        <div id="insightsNotice" class="alert alert-warning py-2 px-3 d-none small mb-3"></div>
        <div id="insightsGrid" class="insights-grid d-none">
            <div class="section-label">DB-backed version history</div>
            <div class="insight-card">
                <div class="insight-label">Code changes</div>
                <div class="insight-value" id="insightsTotal">—</div>
                <div class="insight-sub">Tables: <span id="insightsTables">—</span> • Actors: <span id="insightsActors">—</span></div>
                <div class="text-muted small">Window: last <span id="insightsPeriod"><?php echo $defaultDays; ?></span> days</div>
            </div>
            <div class="insight-card">
                <div class="insight-label">Top tables</div>
                <div class="insight-list" id="insightsByTable"></div>
            </div>
            <div class="insight-card">
                <div class="insight-label">Top actors</div>
                <div class="insight-list" id="insightsByActor"></div>
            </div>
            <div class="insight-card">
                <div class="insight-label">Most touched fields</div>
                <div class="insight-list" id="insightsFields"></div>
            </div>
            <div class="insight-card span-2">
                <div class="insight-label">Latest changes</div>
                <div class="insight-table table-responsive">
                    <table class="table table-sm mb-0 align-middle">
                        <thead>
                            <tr>
                                <th>When</th>
                                <th>Table</th>
                                <th>Record</th>
                                <th>Version</th>
                                <th>Actor</th>
                            </tr>
                        </thead>
                        <tbody id="insightsLatest"></tbody>
                    </table>
                </div>
            </div>
            <div class="insight-card span-2">
                <div class="insight-label">Touches over time</div>
                <div id="insightsTimeline" class="insight-timeline text-muted small">—</div>
            </div>

            <div class="section-label">Filesystem (scan + manifests)</div>
            <div class="insight-card">
                <div class="insight-label">Files scanned</div>
                <div class="insight-value" id="insightsFiles"><?php echo $fsBootstrap['files_scanned'] ?? '—'; ?></div>
                <div class="insight-sub small">Roots: <span title="<?php echo htmlspecialchars(json_encode($fsBootstrap['roots'] ?? [])); ?>"><?php echo isset($fsBootstrap['roots']) ? count($fsBootstrap['roots']) : '—'; ?></span></div>
            </div>
            <div class="insight-card">
                <div class="insight-label">Versioned files</div>
                <div class="insight-value" id="insightsVersioned"><?php echo $this->runData['data']['insights_bootstrap']['fs_versions']['count'] ?? '—'; ?></div>
                <div class="text-muted small">Roots: <?php echo htmlspecialchars(isset($this->runData['data']['insights_bootstrap']['fs_versions']['roots']) ? implode(', ', $this->runData['data']['insights_bootstrap']['fs_versions']['roots']) : '—'); ?></div>
            </div>
            <div class="insight-card span-2">
                <div class="insight-label">Filesystem hotspots (bytes)</div>
                <div class="insight-list" id="insightsFsDirs"></div>
                <div class="insight-list mt-2" id="insightsFsExts"></div>
                <div class="text-muted small mt-1">Roots: <?php echo htmlspecialchars(isset($fsBootstrap['roots']) ? implode(', ', array_map('basename', (array)$fsBootstrap['roots'])) : '—'); ?></div>
            </div>
            <div class="insight-card span-2">
                <div class="insight-label">Recent files</div>
                <div class="insight-list" id="insightsFsRecent"></div>
                <div class="text-muted small mt-1">Click a theme template name in Theme > View to see its stored versions.</div>
            </div>
            <div class="insight-card span-2">
                <div class="insight-label">Versioned templates/routes</div>
                <div class="insight-list" id="insightsFsVersioned"></div>
            </div>
        </div>
    </div>
</div>

<style>
.insights-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
    gap: 1rem;
}
.insight-card {
    border: 1px solid #e9ecef;
    border-radius: .5rem;
    padding: .75rem;
    background: #fff;
}
.insight-card .insight-label {
    font-size: .85rem;
    text-transform: uppercase;
    letter-spacing: .05em;
    color: #6c757d;
    margin-bottom: .35rem;
}
.insight-card .insight-value {
    font-size: 1.6rem;
    font-weight: 600;
}
.insight-card .insight-sub {
    color: #6c757d;
    font-size: .9rem;
}
.insight-list div {
    display: flex;
    justify-content: space-between;
    font-size: .95rem;
    padding: .15rem 0;
    border-bottom: 1px dashed #e9ecef;
}
.insight-list div:last-child {
    border-bottom: 0;
}
.section-label {
    grid-column: 1 / -1;
    font-size: .8rem;
    text-transform: uppercase;
    letter-spacing: .08em;
    color: #6c757d;
    margin-top: .5rem;
}
.insight-table {
    max-height: 260px;
    overflow-y: auto;
}
.insight-timeline {
    min-height: 160px;
    white-space: pre-wrap;
}
.insight-bar {
    display: inline-block;
    background: #0d6efd;
    height: 8px;
    border-radius: 3px;
    vertical-align: middle;
}
.span-2 {
    grid-column: span 2;
}
@media (max-width: 992px) {
    .span-2 {
        grid-column: span 1;
    }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const bootstrap = <?php echo json_encode($this->runData['data']['insights_bootstrap'] ?? null, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); ?>;
    const insightsDays = document.getElementById('insightsDays');
    const insightsRefresh = document.getElementById('insightsRefresh');
    const insightsStatus = document.getElementById('insightsStatus');
    const insightsGrid = document.getElementById('insightsGrid');
    const insightsNotice = document.getElementById('insightsNotice');
    let currentInsights = null;
    const insightsPeriod = document.getElementById('insightsPeriod');
    const insightsTotal = document.getElementById('insightsTotal');
    const insightsTables = document.getElementById('insightsTables');
    const insightsActors = document.getElementById('insightsActors');
    const insightsByTable = document.getElementById('insightsByTable');
    const insightsByActor = document.getElementById('insightsByActor');
    const insightsFields = document.getElementById('insightsFields');
    const insightsLatest = document.getElementById('insightsLatest');
    const insightsTimeline = document.getElementById('insightsTimeline');
    const insightsFiles = document.getElementById('insightsFiles');
    const insightsVersioned = document.getElementById('insightsVersioned');
    const insightsFsDirs = document.getElementById('insightsFsDirs');
    const insightsFsExts = document.getElementById('insightsFsExts');
    const insightsFsRecent = document.getElementById('insightsFsRecent');

    function applyInsights(insights) {
        try {
            if (!insights) {
                insightsStatus.textContent = 'Unable to load insights: no data';
                insightsStatus.classList.remove('d-none');
                insightsGrid.classList.add('d-none');
                insightsNotice.classList.add('d-none');
                return;
            }
            currentInsights = insights;
            const term = (document.getElementById('insightsSearch')?.value || '').toLowerCase();
            const usedFallback = !!insights.used_fallback;

            if (usedFallback) {
                insightsNotice.textContent = 'No entries found in the selected window. Showing the most recent version history instead.';
                insightsNotice.classList.remove('d-none');
            } else if ((insights.counts?.total || 0) === 0) {
                insightsNotice.textContent = 'No version history records were found in this window.';
                insightsNotice.classList.remove('d-none');
            } else {
                insightsNotice.classList.add('d-none');
            }

            insightsPeriod.textContent = insights.period_days;
            insightsTotal.textContent = insights.counts.total ?? '0';
            insightsTables.textContent = insights.counts.tables ?? '0';
            insightsActors.textContent = insights.counts.actors ?? '0';
            insightsFiles.textContent = insights.counts.files_scanned ?? '0';
            insightsVersioned.textContent = insights.counts.versioned_files ?? '0';

            insightsByTable.innerHTML = renderKV(filterKV(insights.by_table, term), 'touches');
            insightsByActor.innerHTML = renderKV(filterKV(insights.by_actor, term), 'touches');
            insightsFields.innerHTML = renderKV(filterKV(insights.fields_touched, term), 'hits');
            insightsFsDirs.innerHTML = renderKV(filterKV((insights.fs && insights.fs.by_directory_bytes) || {}, term), 'bytes');
            insightsFsExts.innerHTML = renderKV(filterKV((insights.fs && insights.fs.by_extension_bytes) || {}, term), 'bytes');
            insightsFsRecent.innerHTML = renderRecent((insights.fs && insights.fs.recent_files) || []);
            renderVersioned(insights.fs_versions || {});

            insightsLatest.innerHTML = '';
            filterLatest(insights.latest_changes || [], term).forEach(row => {
                const tr = document.createElement('tr');
                tr.innerHTML = `
                    <td class="text-muted small">${escapeHtml(row.timestamp || '')}</td>
                    <td>${escapeHtml(row.table || '')}</td>
                    <td>${escapeHtml(row.record_id || '')}</td>
                    <td>${escapeHtml(row.version || '')}</td>
                    <td>${escapeHtml(row.actor || '—')}</td>
                `;
                insightsLatest.appendChild(tr);
            });

            insightsTimeline.textContent = renderTimeline(insights.touches_over_time || {});
            insightsStatus.classList.add('d-none');
            insightsGrid.classList.remove('d-none');
        } catch (err) {
            insightsStatus.textContent = 'Unable to render insights: ' + (err?.message || '');
            insightsStatus.classList.remove('d-none');
            insightsGrid.classList.add('d-none');
            console.error('Insights render error', err);
        }
    }

    function loadInsights() {
        const days = parseInt(insightsDays.value, 10) || 30;
        insightsStatus.textContent = 'Loading...';
        insightsStatus.classList.remove('d-none');
        insightsGrid.classList.add('d-none');

        fetch(`<?php echo $radAdminUrl; ?>/governance/insightsData?days=${days}`)
            .then(resp => {
                if (!resp.ok) {
                    throw new Error(resp.statusText || 'Request failed');
                }
                return resp.json();
            })
            .then(({ insights }) => applyInsights(insights))
            .catch((err) => {
                insightsStatus.textContent = 'Unable to load insights: ' + (err?.message || '');
                insightsStatus.classList.remove('d-none');
                insightsGrid.classList.add('d-none');
            });
    }

    function renderKV(map, label) {
        if (!map || Object.keys(map).length === 0) {
            return '<div class="text-muted small">No data in this window</div>';
        }
        return Object.keys(map).map(key => {
            const val = map[key];
            const bar = val ? `<span class="insight-bar" style="width:${Math.min(100, 6 + val)}px;"></span>` : '';
            return `<div class="d-flex justify-content-between align-items-center gap-2"><span class="text-truncate" title="${escapeHtml(key)}">${escapeHtml(key)}</span><span class="text-muted">${val} ${label} ${bar}</span></div>`;
        }).join('');
    }

    function filterKV(map, term) {
        if (!term || !map) return map || {};
        const result = {};
        Object.keys(map).forEach(key => {
            if (key.toLowerCase().includes(term)) {
                result[key] = map[key];
            }
        });
        return result;
    }

    function filterLatest(list, term) {
        if (!term) return list;
        return list.filter(row => {
            const blob = [
                row.table || '',
                row.record_id || '',
                row.version || '',
                row.actor || '',
                row.timestamp || ''
            ].join(' ').toLowerCase();
            return blob.includes(term);
        });
    }

    function renderTimeline(series) {
        const entries = Object.entries(series);
        if (!entries.length) return 'No change events in this window';
        const max = Math.max(...entries.map(([, v]) => v || 0));
        return entries.map(([date, val]) => {
            const width = max ? Math.max(6, Math.round((val / max) * 100)) : 0;
            return `<div class="d-flex justify-content-between align-items-center"><span class="text-muted small">${escapeHtml(date)}</span><span>${val} <span class="insight-bar" style="width:${width}%;"></span></span></div>`;
        }).join('');
    }

    function renderRecent(list) {
        if (!Array.isArray(list) || list.length === 0) {
            return '<div class="text-muted small">No files</div>';
        }
        return list.map(item => {
            const dt = item.mtime ? new Date(item.mtime * 1000).toLocaleString() : '';
            const path = escapeHtml(item.path || '');
            const fileName = (item.path || '').split('/').pop();
            const templateMatch = fileName.endsWith('.tpl.php') ? fileName.replace('.tpl.php','') : '';
            const rel = path.replace(/^.*\\/rad\\//, 'rad/');
            const maybeLink = templateMatch ? `<a href="<?php echo $radAdminUrl; ?>/theme/viewone/${templateMatch}" title="Open template">${escapeHtml(rel)}</a>` : `<span class="text-truncate" style="max-width:240px;" title="${path}">${escapeHtml(rel)}</span>`;
            return `<div class="d-flex justify-content-between gap-2"><span>${maybeLink}</span><span class="text-muted">${dt}</span></div>`;
        }).join('');
    }

    function renderVersioned(fsVersions) {
        const list = document.getElementById('insightsFsVersioned');
        if (!list) return;
        const entries = fsVersions.entries || [];
        if (entries.length === 0) {
            list.innerHTML = '<div class="text-muted small">No versioned files detected</div>';
            return;
        }
        list.innerHTML = entries.map(e => {
            const when = e.latest_timestamp ? new Date(e.latest_timestamp * 1000).toLocaleString() : '';
            const link = `<a href="<?php echo $radAdminUrl; ?>/theme/viewone/${e.template}" title="Open template">${escapeHtml(e.template)}</a>`;
            return `<div class="d-flex justify-content-between gap-2"><span>${link}</span><span class="text-muted">${when}</span></div>`;
        }).join('');
    }

    function escapeHtml(str) {
        if (str === null || str === undefined) return '';
        return String(str).replace(/[&<>"']/g, m => ({
            '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'
        })[m]);
    }

    insightsRefresh.addEventListener('click', loadInsights);
    document.getElementById('insightsApplyFilter')?.addEventListener('click', () => {
        if (currentInsights) {
            applyInsights(currentInsights);
        } else if (bootstrap?.counts) {
            applyInsights(bootstrap);
        }
    });
    insightsDays.addEventListener('change', loadInsights);
    if (bootstrap) {
        applyInsights(bootstrap);
    } else {
        loadInsights();
    }
});
</script>
