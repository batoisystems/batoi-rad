<?php
$radAdminUrl = $this->runData['route']['rad_admin_url'] ?? '';
$entries = $this->runData['data']['changelog'] ?? [];
$tables = $this->runData['data']['tables'] ?? [];
$actors = $this->runData['data']['actors'] ?? [];
$filters = $this->runData['data']['filters'] ?? [];
$pagination = $this->runData['data']['pagination'] ?? ['page' => 1, 'per_page' => 25, 'total' => count($entries), 'total_pages' => 1];
$timezone = $this->runData['entity']['timezone'] ?? ($this->runData['config']['sys']['timezone_default'] ?? 'UTC');
$now = new DateTimeImmutable('now', new DateTimeZone($timezone));

function changelogQuery(array $filters, int $page, int $perPage): string {
    $params = [
        'table' => $filters['table'] ?? '',
        'actor' => $filters['actor'] ?? '',
        'date_from' => $filters['date_from'] ?? '',
        'date_to' => $filters['date_to'] ?? '',
        'search' => $filters['search'] ?? '',
        'change_type' => $filters['change_type'] ?? '',
        'code_source' => $filters['code_source'] ?? '',
        'code_only' => !empty($filters['code_only']) ? '1' : '',
        'page' => $page,
        'per_page' => $perPage,
    ];
    return http_build_query(array_filter($params, fn($v) => $v !== '' && $v !== null));
}
?>

<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
    <div>
        <h1 class="h4 mb-0">Changelog</h1>
        <p class="text-muted mb-0">Recent changes captured via version history, including code-related objects.</p>
    </div>
    <div class="d-flex align-items-center gap-2">
        <a class="btn btn-outline-primary btn-sm" href="<?php echo $radAdminUrl; ?>/governance/insights">
            <i class="bi bi-graph-up-arrow"></i> Code Insights
        </a>
        <?php if (!empty(array_filter($filters))) { ?>
            <a href="<?php echo $radAdminUrl; ?>/governance/changelog" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-arrow-counterclockwise"></i> Reset
            </a>
        <?php } ?>
    </div>
</div>

<div class="row g-3 mb-3">
    <div class="col-md-4">
        <div class="card shadow-sm h-100">
            <div class="card-body">
                <div class="text-muted small">Total entries</div>
                <div class="h4 mb-0"><?php echo (int)($pagination['total'] ?? 0); ?></div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card shadow-sm h-100">
            <div class="card-body">
                <div class="text-muted small">Tables tracked</div>
                <div class="h4 mb-0"><?php echo count($tables); ?></div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card shadow-sm h-100">
            <div class="card-body">
                <div class="text-muted small">Actors</div>
                <div class="h4 mb-0"><?php echo count(array_filter($actors)); ?></div>
            </div>
        </div>
    </div>
</div>

<div class="card mb-3">
    <div class="card-body">
        <form class="row g-2 align-items-end" method="get">
            <div class="col-12">
                <div class="btn-group btn-group-sm mb-2" role="group" aria-label="Quick ranges">
                    <a class="btn btn-outline-secondary" href="<?php echo $radAdminUrl; ?>/governance/changelog?<?php echo changelogQuery(array_merge($filters, ['date_from' => $now->modify('-7 days')->format('Y-m-d'), 'date_to' => $now->format('Y-m-d')]), 1, (int)$pagination['per_page']); ?>">Last 7 days</a>
                    <a class="btn btn-outline-secondary" href="<?php echo $radAdminUrl; ?>/governance/changelog?<?php echo changelogQuery(array_merge($filters, ['date_from' => $now->modify('-30 days')->format('Y-m-d'), 'date_to' => $now->format('Y-m-d')]), 1, (int)$pagination['per_page']); ?>">Last 30 days</a>
                    <a class="btn btn-outline-secondary" href="<?php echo $radAdminUrl; ?>/governance/changelog?<?php echo changelogQuery(array_merge($filters, ['date_from' => $now->modify('-90 days')->format('Y-m-d'), 'date_to' => $now->format('Y-m-d')]), 1, (int)$pagination['per_page']); ?>">Last 90 days</a>
                </div>
            </div>
            <div class="col-md-3">
                <label class="form-label">Table</label>
                <select name="table" class="form-select form-select-sm">
                    <option value="">All</option>
                    <?php foreach ($tables as $tbl): ?>
                        <option value="<?php echo htmlspecialchars($tbl); ?>" <?php echo ($filters['table'] ?? '') === $tbl ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($tbl); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">Actor</label>
                <select name="actor" class="form-select form-select-sm">
                    <option value="">All</option>
                    <?php foreach ($actors as $actor): ?>
                        <?php if (!$actor) continue; ?>
                        <option value="<?php echo (int)$actor; ?>" <?php echo ((string)($filters['actor'] ?? '') === (string)$actor) ? 'selected' : ''; ?>>
                            <?php echo (int)$actor; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">From</label>
                <input type="date" name="date_from" class="form-control form-control-sm" value="<?php echo htmlspecialchars($filters['date_from'] ?? ''); ?>">
            </div>
            <div class="col-md-2">
                <label class="form-label">To</label>
                <input type="date" name="date_to" class="form-control form-control-sm" value="<?php echo htmlspecialchars($filters['date_to'] ?? ''); ?>">
            </div>
            <div class="col-md-2">
                <label class="form-label">Type</label>
                <select name="change_type" class="form-select form-select-sm">
                    <option value="">All</option>
                    <option value="db" <?php echo ($filters['change_type'] ?? '') === 'db' ? 'selected' : ''; ?>>DB changes (s_*)</option>
                    <option value="code" <?php echo ($filters['change_type'] ?? '') === 'code' ? 'selected' : ''; ?>>Code changes</option>
                    <option value="fs" <?php echo ($filters['change_type'] ?? '') === 'fs' ? 'selected' : ''; ?>>Filesystem only</option>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">Source</label>
                <select name="code_source" class="form-select form-select-sm">
                    <option value="">All</option>
                    <option value="ms" <?php echo ($filters['code_source'] ?? '') === 'ms' ? 'selected' : ''; ?>>Microservicelets</option>
                    <option value="theme" <?php echo ($filters['code_source'] ?? '') === 'theme' ? 'selected' : ''; ?>>Themes</option>
                    <option value="vendor" <?php echo ($filters['code_source'] ?? '') === 'vendor' ? 'selected' : ''; ?>>Vendor</option>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">Search</label>
                <input type="text" name="search" class="form-control form-control-sm" placeholder="table/id" value="<?php echo htmlspecialchars($filters['search'] ?? ''); ?>">
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-primary btn-sm w-100 mt-4"><i class="bi bi-funnel me-1"></i>Filter</button>
            </div>
            <div class="col-md-2">
                <label class="form-label">Per page</label>
                <select name="per_page" class="form-select form-select-sm">
                    <?php foreach ([10, 25, 50, 100, 200] as $size) { ?>
                        <option value="<?php echo $size; ?>" <?php echo (int)$pagination['per_page'] === $size ? 'selected' : ''; ?>><?php echo $size; ?></option>
                    <?php } ?>
                </select>
            </div>
        </form>
    </div>
    <div class="card-footer bg-white">
        <div class="text-muted small">Showing up to <?php echo (int)$pagination['per_page']; ?> changes per page.</div>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <?php if (empty($entries)): ?>
            <p class="text-muted mb-0">No changes found for the selected filters.</p>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-sm align-middle">
                    <thead>
                        <tr>
                            <th>When</th>
                            <th>Actor</th>
                            <th>Target</th>
                            <th>Record / File</th>
                            <th>Version</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($entries as $row): ?>
                        <tr>
                            <td class="text-muted small"><?php echo htmlspecialchars($row['s_modified_timestamp'] ?? ''); ?></td>
                            <td><?php echo htmlspecialchars($row['actor_name'] ?? $row['s_modified_by'] ?? '—'); ?></td>
                            <td>
                                <?php if (!empty($row['is_fs'])): ?>
                                    <div class="fw-semibold"><?php echo htmlspecialchars($row['fs_source'] ?? 'filesystem'); ?></div>
                                    <div class="text-muted small"><?php echo htmlspecialchars($row['s_db_table']); ?></div>
                                <?php else: ?>
                                    <?php echo htmlspecialchars($row['s_db_table']); ?>
                                    <?php if (!empty($row['is_code'])): ?>
                                        <span class="badge bg-info-subtle text-info ms-1">Code</span>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if (!empty($row['is_fs'])): ?>
                                    <div class="text-monospace small"><?php echo htmlspecialchars($row['fs_path'] ?? ''); ?></div>
                                    <div class="text-muted small">SHA1: <?php echo htmlspecialchars($row['fs_hash'] ?? ''); ?></div>
                                <?php else: ?>
                                    <div>ID: <?php echo htmlspecialchars($row['s_data_record_id']); ?></div>
                                    <div class="text-muted small">UID: <?php echo htmlspecialchars($row['s_record_uid'] ?? ''); ?></div>
                                <?php endif; ?>
                            </td>
                            <td><span class="badge bg-light text-dark"><?php echo htmlspecialchars($row['s_version_number'] ?? ''); ?></span></td>
                            <td class="text-end">
                                <div class="btn-group btn-group-sm">
                                    <button type="button" class="btn btn-outline-secondary" data-changelog-action="snapshot" data-id="<?php echo (int)$row['id']; ?>">
                                        <i class="bi bi-file-earmark-text"></i> Snapshot
                                    </button>
                                    <button type="button" class="btn btn-outline-primary" data-changelog-action="diff" data-id="<?php echo (int)$row['id']; ?>">
                                        <i class="bi bi-code-square"></i> Diff
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mt-2">
                <div class="text-muted small">
                    Showing page <?php echo (int)$pagination['page']; ?> of <?php echo (int)$pagination['total_pages']; ?> (<?php echo (int)$pagination['total']; ?> entries)
                </div>
                <nav aria-label="Changelog pagination">
                    <ul class="pagination pagination-sm mb-0">
                        <?php $prevPage = max(1, (int)$pagination['page'] - 1); ?>
                        <?php $nextPage = min((int)$pagination['total_pages'], (int)$pagination['page'] + 1); ?>
                        <li class="page-item <?php echo $pagination['page'] <= 1 ? 'disabled' : ''; ?>">
                            <a class="page-link" href="?<?php echo changelogQuery($filters, $prevPage, (int)$pagination['per_page']); ?>" aria-label="Previous">Prev</a>
                        </li>
                        <li class="page-item active"><span class="page-link"><?php echo (int)$pagination['page']; ?></span></li>
                        <li class="page-item <?php echo $pagination['page'] >= $pagination['total_pages'] ? 'disabled' : ''; ?>">
                            <a class="page-link" href="?<?php echo changelogQuery($filters, $nextPage, (int)$pagination['per_page']); ?>" aria-label="Next">Next</a>
                        </li>
                    </ul>
                </nav>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Right sidebar panel -->
<div id="changelogPanel" class="changelog-panel">
    <div class="changelog-panel__header d-flex justify-content-between align-items-center">
        <div>
            <div class="small text-muted" id="changelogPanelType">Snapshot</div>
            <div class="fw-semibold" id="changelogPanelTitle">Loading...</div>
            <div class="text-muted small" id="changelogPanelFormat"></div>
        </div>
        <div class="d-flex align-items-center gap-2">
            <button type="button" class="btn btn-sm btn-outline-secondary d-none" id="changelogPanelDownload">
                <i class="bi bi-download"></i> Raw
            </button>
            <button type="button" class="btn btn-sm btn-outline-secondary" id="changelogPanelClose"><i class="bi bi-x-lg"></i></button>
        </div>
    </div>
    <div class="changelog-panel__body">
        <div id="changelogPanelStatus" class="text-center text-muted small py-3 d-none">Loading...</div>
        <div id="changelogPanelContent" class="changelog-panel__content"></div>
    </div>
</div>

<style>
.changelog-panel {
    position: fixed;
    top: 0;
    right: -480px;
    width: 480px;
    height: 100vh;
    background: #fff;
    box-shadow: -2px 0 12px rgba(0,0,0,0.15);
    z-index: 1200;
    transition: right 0.3s ease;
    display: flex;
    flex-direction: column;
}
.changelog-panel.open {
    right: 0;
}
.changelog-panel__header {
    padding: 1rem;
    border-bottom: 1px solid #e9ecef;
    background: #f8f9fa;
}
.changelog-panel__body {
    padding: 1rem;
    overflow-y: auto;
    flex: 1;
}
.changelog-panel__content table {
    width: 100%;
}
.changelog-panel__content .diff-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 1rem;
}
.changelog-panel__content .diff-grid .card {
    border: 1px solid #dee2e6;
    border-radius: .5rem;
}
.changelog-panel__content .diff-grid .card-header {
    background: #f8f9fa;
    border-bottom: 1px solid #dee2e6;
    padding: .5rem .75rem;
}
.changelog-panel__content dl {
    margin-bottom: 0;
}
.changelog-panel__content dt {
    font-weight: 600;
    font-size: .9rem;
}
.changelog-panel__content dd {
    margin-bottom: .75rem;
    font-size: .9rem;
}
.changelog-panel__content pre {
    white-space: pre-wrap;
    word-break: break-word;
    background: #f8f9fa;
    border: 1px solid #e9ecef;
    padding: .75rem;
    border-radius: .5rem;
}
@media (max-width: 992px) {
    .changelog-panel {
        width: 100%;
    }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const panel = document.getElementById('changelogPanel');
    const panelClose = document.getElementById('changelogPanelClose');
    const panelType = document.getElementById('changelogPanelType');
    const panelTitle = document.getElementById('changelogPanelTitle');
    const panelStatus = document.getElementById('changelogPanelStatus');
    const panelContent = document.getElementById('changelogPanelContent');
    const panelFormat = document.getElementById('changelogPanelFormat');
    const panelDownload = document.getElementById('changelogPanelDownload');

    const fieldLabels = {
        's_ms': {
            's_name': 'Name',
            's_type': 'Type',
            's_scope': 'Scope',
            's_scope': 'Scope',
            's_tpl_name': 'Template',
            'uid': 'UID',
            'id': 'ID'
        },
        's_msroute': {
            's_name': 'Name',
            's_entity_scope': 'Entity Scope',
            's_degree': 'Degree',
            'uid': 'UID',
            'id': 'ID'
        },
        's_mscontroller': {
            's_name': 'Name',
            's_type': 'Type',
            'uid': 'UID',
            'id': 'ID'
        },
        's_role': {
            's_role_name': 'Name',
            's_scope': 'Scope',
            's_default_route_id': 'Default Route ID',
            'id': 'ID',
            'uid': 'UID'
        },
        's_space': {
            's_name': 'Name',
            's_description': 'Description',
            'uid': 'UID',
            'id': 'ID'
        },
        's_permission_binding': {
            's_object_type': 'Object Type',
            's_object_id': 'Object ID',
            's_role_id': 'Role ID',
            'id': 'ID'
        },
        's_nav': {
            's_name': 'Name',
            's_href': 'Href',
            's_location': 'Location',
            'id': 'ID'
        },
        's_navset': {
            's_name': 'Name',
            's_slug': 'Slug',
            'id': 'ID'
        },
        's_content': {
            's_title': 'Title',
            's_meta_title': 'Meta Title',
            's_meta_description': 'Meta Description',
            'uid': 'UID',
            'id': 'ID'
        },
        's_entity': {
            's_name': 'Name',
            's_identity': 'Username',
            's_type': 'Type',
            'id': 'ID',
            'uid': 'UID'
        }
    };

    function openPanel(typeLabel, title) {
        panelType.textContent = typeLabel;
        panelTitle.textContent = title;
        panelFormat.textContent = '';
        panelStatus.classList.add('d-none');
        panelContent.innerHTML = '';
        panelDownload.classList.add('d-none');
        panel.classList.add('open');
    }

    function closePanel() {
        panel.classList.remove('open');
    }

    panelClose.addEventListener('click', closePanel);

    document.querySelectorAll('[data-changelog-action]').forEach(btn => {
        btn.addEventListener('click', () => {
            const action = btn.getAttribute('data-changelog-action');
            const id = btn.getAttribute('data-id');
            openPanel(action === 'snapshot' ? 'Snapshot' : 'Diff', 'Loading...');
            panelStatus.textContent = 'Loading...';
            panelStatus.classList.remove('d-none');

            const url = action === 'snapshot'
                ? `<?php echo $radAdminUrl; ?>/governance/snapshot/${id}`
                : `<?php echo $radAdminUrl; ?>/governance/diff/${id}`;
            fetch(url)
                .then(resp => {
                    if (!resp.ok) {
                        return resp.text().then(txt => { throw new Error(txt || resp.statusText); });
                    }
                    return resp.json().catch(() => { throw new Error('Invalid JSON'); });
                })
                .then(data => {
                    panelStatus.classList.add('d-none');
                    renderContent(action, data);
                })
                .catch((err) => {
                    panelStatus.textContent = 'Unable to load data. ' + (err?.message || '');
                    panelStatus.classList.remove('d-none');
                });
        });
    });

    function renderContent(kind, payload) {
        panelContent.innerHTML = '';
        const data = kind === 'snapshot' ? payload.snapshot : payload.diff;
        const format = payload.format || '';
        panelFormat.textContent = format
            ? `Format: ${typeof format === 'string' ? format : JSON.stringify(format)}`
            : '';
        wireDownload(kind, payload);
        const header = document.createElement('div');
        header.className = 'fw-semibold mb-2';
        header.textContent = kind === 'snapshot' ? 'Snapshot' : 'Diff (current vs previous)';
        panelContent.appendChild(header);

        if (!data) {
            panelContent.appendChild(textNode('No data.'));
            return;
        }

        if (kind === 'diff' && data && data.current !== undefined && data.previous !== undefined) {
            renderDiffColumns(data.previous, data.current);
            return;
        }

        if (kind === 'diff' && data.changed) {
            renderDiffBuckets(data);
            return;
        }

        renderSnapshot(data);
    }

    function textNode(str) {
        const p = document.createElement('p');
        p.textContent = str;
        return p;
    }

    function renderSnapshot(data) {
        if (data && typeof data === 'object' && data.encoding === 'base64') {
            const decoded = tryDecode(data);
            panelContent.appendChild(preBlock(decoded));
            return;
        }
        if (typeof data === 'string') {
            // attempt base64 decode then JSON parse
            const maybeDecoded = tryDecode(data);
            if (maybeDecoded) {
                if (typeof maybeDecoded === 'object') {
                    renderObject(maybeDecoded);
                } else {
                    panelContent.appendChild(preBlock(maybeDecoded));
                }
            } else {
                panelContent.appendChild(preBlock(data));
            }
        } else if (typeof data === 'object') {
            renderObject(data);
        } else {
            panelContent.appendChild(preBlock(String(data)));
        }
    }

    function preBlock(content) {
        const pre = document.createElement('pre');
        pre.textContent = typeof content === 'string' ? content : JSON.stringify(content, null, 2);
        return pre;
    }

    function renderObject(obj) {
        const table = inferTable(obj);
        const labels = fieldLabels[table] || {};
        const dl = document.createElement('dl');
        Object.keys(obj).forEach(key => {
            const dt = document.createElement('dt');
            dt.textContent = labels[key] || key;
            const dd = document.createElement('dd');
            const val = obj[key];
            if (typeof val === 'object' && val !== null) {
                dd.appendChild(preBlock(val));
            } else {
                dd.textContent = val === null || val === undefined ? '—' : String(val);
            }
            dl.appendChild(dt);
            dl.appendChild(dd);
        });
        panelContent.appendChild(dl);
    }

    function renderDiffColumns(prev, curr) {
        const grid = document.createElement('div');
        grid.className = 'diff-grid';

        const left = document.createElement('div');
        left.className = 'card';
        left.innerHTML = '<div class="card-header fw-semibold">Previous</div>';
        const leftBody = document.createElement('div');
        leftBody.className = 'card-body';
        leftBody.appendChild(renderDiffSide(prev, 'prev'));
        left.appendChild(leftBody);

        const right = document.createElement('div');
        right.className = 'card';
        right.innerHTML = '<div class="card-header fw-semibold">Current</div>';
        const rightBody = document.createElement('div');
        rightBody.className = 'card-body';
        rightBody.appendChild(renderDiffSide(curr, 'curr'));
        right.appendChild(rightBody);

        grid.appendChild(left);
        grid.appendChild(right);
        panelContent.appendChild(grid);
    }

    function renderDiffBuckets(diff) {
        const container = document.createElement('div');
        ['added','removed','changed'].forEach(section => {
            if (!diff[section] || Object.keys(diff[section]).length === 0) return;
            const card = document.createElement('div');
            card.className = 'card mb-3';
            const header = document.createElement('div');
            header.className = 'card-header fw-semibold text-capitalize';
            header.textContent = section;
            const body = document.createElement('div');
            body.className = 'card-body';
            const ul = document.createElement('ul');
            ul.className = 'mb-0';
            Object.keys(diff[section]).forEach(k => {
                const li = document.createElement('li');
                li.textContent = `${k}: ${JSON.stringify(diff[section][k])}`;
                ul.appendChild(li);
            });
            body.appendChild(ul);
            card.appendChild(header);
            card.appendChild(body);
            container.appendChild(card);
        });
        panelContent.appendChild(container);
    }

    function renderDiffSide(data, side) {
        if (data && typeof data === 'object' && data.encoding === 'base64') {
            return preBlock(tryDecode(data));
        }
        if (typeof data === 'string') {
            const decoded = tryDecode(data);
            if (decoded && typeof decoded === 'object') {
                return renderObjectFragment(decoded);
            }
            return preBlock(decoded || data);
        }
        if (typeof data === 'object') {
            return renderObjectFragment(data);
        }
        return preBlock(String(data));
    }

    function renderObjectFragment(obj) {
        const table = inferTable(obj);
        const labels = fieldLabels[table] || {};
        const dl = document.createElement('dl');
        Object.keys(obj).forEach(key => {
            const dt = document.createElement('dt');
            dt.textContent = labels[key] || key;
            const dd = document.createElement('dd');
            const val = obj[key];
            if (typeof val === 'object' && val !== null) {
                dd.appendChild(preBlock(val));
            } else {
                dd.textContent = val === null || val === undefined ? '—' : String(val);
            }
            dl.appendChild(dt);
            dl.appendChild(dd);
        });
        return dl;
    }

    function tryDecode(val) {
        if (val && typeof val === 'object' && val.encoding === 'base64') {
            val = val.value || '';
        }
        // base64 decode then JSON parse fallback
        try {
            const decoded = atob(val);
            try {
                return JSON.parse(decoded);
            } catch (e) {
                return decoded;
            }
        } catch (e) {
            try {
                return JSON.parse(val);
            } catch (err) {
                return null;
            }
        }
    }

    function wireDownload(kind, payload) {
        panelDownload.classList.add('d-none');
        const raw = payload.raw_base64 || null;
        if (!raw) return;
        panelDownload.classList.remove('d-none');
        panelDownload.onclick = () => {
            let content = '';
            if (kind === 'snapshot') {
                content = raw;
            } else {
                content = JSON.stringify(raw, null, 2);
            }
            const blob = new Blob([content], { type: 'text/plain' });
            const url = URL.createObjectURL(blob);
            const link = document.createElement('a');
            link.href = url;
            link.download = kind === 'snapshot' ? 'snapshot_raw.txt' : 'diff_raw.json';
            document.body.appendChild(link);
            link.click();
            link.remove();
            URL.revokeObjectURL(url);
        };
    }

    function inferTable(obj) {
        if (obj && typeof obj === 'object') {
            if (obj.hasOwnProperty('s_scope') && obj.hasOwnProperty('s_default_route_id')) return 's_role';
            if (obj.hasOwnProperty('s_entity_scope')) return 's_msroute';
            if (obj.hasOwnProperty('s_type') && obj.hasOwnProperty('s_tpl_name')) return 's_ms';
            if (obj.hasOwnProperty('s_object_type') && obj.hasOwnProperty('s_role_id')) return 's_permission_binding';
            if (obj.hasOwnProperty('s_location') && obj.hasOwnProperty('s_href')) return 's_nav';
            if (obj.hasOwnProperty('s_slug') && obj.hasOwnProperty('s_name')) return 's_navset';
            if (obj.hasOwnProperty('s_identity') && obj.hasOwnProperty('s_type')) return 's_entity';
        }
        return '';
    }
});
</script>
