<?php
$report = $this->runData['data']['audit_report'] ?? [];
$summary = $report['summary'] ?? ['ms_total' => 0, 'file_total' => 0, 'issue_total' => 0];
$issues = $report['issues'] ?? [];
$filters = $this->runData['data']['filters'] ?? ['q' => '', 'ms' => '', 'route_id' => '', 'route_uid' => '', 'source' => ''];
$pagination = $this->runData['data']['pagination'] ?? ['page' => 1, 'per_page' => 25, 'total' => 0, 'total_pages' => 1];
$radAdminUrl = $this->runData['route']['rad_admin_url'];
?>

<div class="card border-0 shadow-sm mb-4">
    <div class="card-body d-flex flex-wrap align-items-center justify-content-between gap-3">
        <div>
            <h2 class="h5 mb-1">Workspace SQL Audit</h2>
            <p class="text-muted mb-0">Scans workspace-scoped microservicelets for SQL calls that appear to miss space_id scoping.</p>
        </div>
        <form method="post" action="<?php echo $radAdminUrl; ?>/governance/workspace-audit">
            <input type="hidden" name="action" value="workspace_audit_scan">
            <button type="submit" class="btn btn-primary">
                <i class="bi bi-lightning-charge me-1"></i>Run Scan
            </button>
        </form>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-md-4">
        <div class="card shadow-sm h-100">
            <div class="card-body">
                <div class="text-muted small">Workspace microservicelets</div>
                <div class="h4 mb-0"><?php echo (int)($summary['ms_total'] ?? 0); ?></div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card shadow-sm h-100">
            <div class="card-body">
                <div class="text-muted small">Files scanned</div>
                <div class="h4 mb-0"><?php echo (int)($summary['file_total'] ?? 0); ?></div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card shadow-sm h-100">
            <div class="card-body">
                <div class="text-muted small">Potential issues</div>
                <div class="h4 mb-0"><?php echo (int)($summary['issue_total'] ?? 0); ?></div>
            </div>
        </div>
    </div>
</div>

<div class="card shadow-sm mb-4">
    <div class="card-header bg-white d-flex align-items-center justify-content-between">
        <h3 class="h6 mb-0">Findings</h3>
        <span class="text-muted small"><?php echo htmlspecialchars($report['generated_at'] ?? 'No report yet'); ?></span>
    </div>
    <div class="card-body">
        <form method="get" action="<?php echo $radAdminUrl; ?>/governance/workspace-audit" class="row g-2 align-items-end mb-3">
            <div class="col-12 col-lg-3">
                <label class="form-label">Search</label>
                <input type="text" class="form-control" name="q" value="<?php echo htmlspecialchars($filters['q'] ?? ''); ?>" placeholder="File, snippet, route">
            </div>
            <div class="col-12 col-lg-3">
                <label class="form-label">Microservicelet</label>
                <input type="text" class="form-control" name="ms" value="<?php echo htmlspecialchars($filters['ms'] ?? ''); ?>" placeholder="ms name">
            </div>
            <div class="col-6 col-lg-2">
                <label class="form-label">Route ID</label>
                <input type="text" class="form-control" name="route_id" value="<?php echo htmlspecialchars($filters['route_id'] ?? ''); ?>">
            </div>
            <div class="col-6 col-lg-2">
                <label class="form-label">Route UID</label>
                <input type="text" class="form-control" name="route_uid" value="<?php echo htmlspecialchars($filters['route_uid'] ?? ''); ?>">
            </div>
            <div class="col-6 col-lg-2">
                <label class="form-label">Source</label>
                <select name="source" class="form-select">
                    <option value="">All</option>
                    <option value="route" <?php echo ($filters['source'] === 'route') ? 'selected' : ''; ?>>Route</option>
                    <option value="template" <?php echo ($filters['source'] === 'template') ? 'selected' : ''; ?>>Template</option>
                </select>
            </div>
            <div class="col-6 col-lg-1">
                <label class="form-label">Per page</label>
                <select name="per_page" class="form-select">
                    <?php foreach ([10,25,50,100,200] as $opt): ?>
                        <option value="<?php echo $opt; ?>" <?php echo ($pagination['per_page'] == $opt) ? 'selected' : ''; ?>><?php echo $opt; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-6 col-lg-1 d-grid">
                <button type="submit" class="btn btn-outline-secondary">Apply</button>
            </div>
        </form>
        <?php if (empty($issues)): ?>
            <div class="alert alert-success mb-0">No issues found in the latest scan.</div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table align-middle">
                    <thead>
                        <tr>
                            <th>Microservicelet</th>
                            <th>Location</th>
                            <th>Call</th>
                            <th>Tables</th>
                            <th>Snippet</th>
                            <th class="text-end">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($issues as $issue): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($issue['ms_name'] ?? ''); ?></td>
                                <td>
                                    <?php if (($issue['source'] ?? '') === 'template'): ?>
                                        <div class="small text-muted">Template</div>
                                        <div class="fw-semibold"><?php echo htmlspecialchars($issue['template'] ?? ''); ?></div>
                                        <div class="text-muted small"><?php echo htmlspecialchars($issue['file'] ?? ''); ?></div>
                                    <?php else: ?>
                                        <div class="small text-muted">Route</div>
                                        <div class="fw-semibold">ID: <?php echo htmlspecialchars($issue['route_id'] ?? ''); ?></div>
                                        <div class="text-muted small">UID: <?php echo htmlspecialchars($issue['route_uid'] ?? ''); ?></div>
                                        <?php if (!empty($issue['route_name'])): ?>
                                            <div class="text-muted small"><?php echo htmlspecialchars($issue['route_name']); ?></div>
                                        <?php endif; ?>
                                        <div class="text-muted small"><?php echo htmlspecialchars($issue['file'] ?? ''); ?></div>
                                    <?php endif; ?>
                                </td>
                                <td><span class="badge text-bg-warning"><?php echo htmlspecialchars($issue['call_type'] ?? ''); ?></span></td>
                                <td class="text-muted small">
                                    <?php echo htmlspecialchars(implode(', ', $issue['tables'] ?? [])); ?>
                                </td>
                                <td class="text-muted small"><?php echo htmlspecialchars($issue['snippet'] ?? ''); ?></td>
                                <td class="text-end">
                                    <form method="post" action="<?php echo $radAdminUrl; ?>/governance/workspace-audit" class="d-inline">
                                        <input type="hidden" name="action" value="workspace_audit_ignore">
                                        <input type="hidden" name="ignore_key" value="<?php echo htmlspecialchars($issue['key'] ?? ''); ?>">
                                        <button type="submit" class="btn btn-outline-secondary btn-sm">Ignore</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php if (($pagination['total_pages'] ?? 1) > 1): ?>
<nav class="d-flex justify-content-between align-items-center">
    <div class="text-muted small">
        Showing page <?php echo (int)$pagination['page']; ?> of <?php echo (int)$pagination['total_pages']; ?>
        (<?php echo (int)$pagination['total']; ?> issues)
    </div>
    <ul class="pagination mb-0">
        <?php
        $baseParams = $filters;
        $baseParams['per_page'] = $pagination['per_page'];
        $baseQuery = http_build_query(array_filter($baseParams, fn($v) => $v !== '' && $v !== null));
        $baseUrl = $radAdminUrl . '/governance/workspace-audit' . ($baseQuery ? '?' . $baseQuery . '&' : '?');
        $prevPage = max(1, (int)$pagination['page'] - 1);
        $nextPage = min((int)$pagination['total_pages'], (int)$pagination['page'] + 1);
        ?>
        <li class="page-item <?php echo ($pagination['page'] <= 1) ? 'disabled' : ''; ?>">
            <a class="page-link" href="<?php echo $baseUrl . 'page=' . $prevPage; ?>">Prev</a>
        </li>
        <li class="page-item <?php echo ($pagination['page'] >= $pagination['total_pages']) ? 'disabled' : ''; ?>">
            <a class="page-link" href="<?php echo $baseUrl . 'page=' . $nextPage; ?>">Next</a>
        </li>
    </ul>
</nav>
<?php endif; ?>
