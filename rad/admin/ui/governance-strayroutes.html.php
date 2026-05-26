<?php
$entries = $this->runData['data']['entries'] ?? [];
$filters = $this->runData['data']['filters'] ?? ['ms' => '', 'type' => '', 'status' => '', 'q' => ''];
$pagination = $this->runData['data']['pagination'] ?? ['page' => 1, 'per_page' => 25, 'total' => 0, 'total_pages' => 1];
$canRemediate = !empty($this->runData['data']['can_remediate']);
$radAdminUrl = $this->runData['route']['rad_admin_url'];
?>

<div class="card border-0 shadow-sm mb-4">
    <div class="card-body d-flex flex-wrap align-items-center justify-content-between gap-3">
        <div>
            <h2 class="h5 mb-1">Stray Routes &amp; Controllers</h2>
            <p class="text-muted mb-0">Find filesystem route/controller files missing from system tables and DB rows with missing files.</p>
        </div>
        <a class="btn btn-outline-secondary" href="<?php echo $radAdminUrl; ?>/governance/strayroutes">
            <i class="bi bi-arrow-repeat me-1"></i>Run Scan
        </a>
    </div>
</div>

<div class="card shadow-sm mb-4">
    <div class="card-header bg-white">
        <h3 class="h6 mb-0">Filters</h3>
    </div>
    <div class="card-body">
        <form method="get" action="<?php echo $radAdminUrl; ?>/governance/strayroutes" class="row g-2 align-items-end">
            <div class="col-12 col-lg-4">
                <label class="form-label">Search</label>
                <input type="text" class="form-control" name="q" value="<?php echo htmlspecialchars($filters['q'] ?? ''); ?>" placeholder="ms name, id, uid, file">
            </div>
            <div class="col-12 col-lg-3">
                <label class="form-label">Microservicelet</label>
                <input type="text" class="form-control" name="ms" value="<?php echo htmlspecialchars($filters['ms'] ?? ''); ?>" placeholder="ms name">
            </div>
            <div class="col-6 col-lg-2">
                <label class="form-label">Type</label>
                <select name="type" class="form-select">
                    <option value="">All</option>
                    <option value="route" <?php echo ($filters['type'] === 'route') ? 'selected' : ''; ?>>Route</option>
                    <option value="controller" <?php echo ($filters['type'] === 'controller') ? 'selected' : ''; ?>>Controller</option>
                </select>
            </div>
            <div class="col-6 col-lg-2">
                <label class="form-label">Status</label>
                <select name="status" class="form-select">
                    <option value="">All</option>
                    <option value="file_only" <?php echo ($filters['status'] === 'file_only') ? 'selected' : ''; ?>>File only</option>
                    <option value="db_only" <?php echo ($filters['status'] === 'db_only') ? 'selected' : ''; ?>>DB only</option>
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
    </div>
</div>

<div class="card shadow-sm mb-4">
    <div class="card-header bg-white d-flex flex-wrap align-items-center justify-content-between gap-2">
        <h3 class="h6 mb-0">Findings</h3>
        <div class="d-flex align-items-center gap-2">
            <span class="text-muted small"><?php echo (int)$pagination['total']; ?> records</span>
            <?php if ($canRemediate && !empty($entries)): ?>
                <form method="post" action="<?php echo $radAdminUrl; ?>/governance/strayroutes" class="d-flex align-items-center gap-2">
                    <input type="hidden" name="action" value="bulk_delete_files">
                    <?php foreach ($entries as $row): ?>
                        <?php if (($row['status'] ?? '') === 'file_only'): ?>
                            <input type="hidden" name="files[]" value="<?php echo htmlspecialchars((string)($row['file'] ?? '')); ?>">
                            <input type="hidden" name="ms_names[]" value="<?php echo htmlspecialchars((string)($row['ms_name'] ?? '')); ?>">
                        <?php endif; ?>
                    <?php endforeach; ?>
                    <input type="hidden" name="delete_mode" value="trash">
                    <button type="submit" class="btn btn-outline-danger btn-sm">Trash all file-only</button>
                </form>
                <form method="post" action="<?php echo $radAdminUrl; ?>/governance/strayroutes" class="d-flex align-items-center gap-2">
                    <input type="hidden" name="action" value="bulk_generate_files">
                    <?php foreach ($entries as $idx => $row): ?>
                        <?php if (($row['status'] ?? '') === 'db_only'): ?>
                            <input type="hidden" name="items[<?php echo (int)$idx; ?>][ms_id]" value="<?php echo htmlspecialchars((string)($row['ms_id'] ?? '')); ?>">
                            <input type="hidden" name="items[<?php echo (int)$idx; ?>][ms_name]" value="<?php echo htmlspecialchars((string)($row['ms_name'] ?? '')); ?>">
                            <input type="hidden" name="items[<?php echo (int)$idx; ?>][type]" value="<?php echo htmlspecialchars((string)($row['type'] ?? '')); ?>">
                            <input type="hidden" name="items[<?php echo (int)$idx; ?>][key]" value="<?php echo htmlspecialchars((string)($row['key'] ?? '')); ?>">
                        <?php endif; ?>
                    <?php endforeach; ?>
                    <button type="submit" class="btn btn-outline-primary btn-sm">Generate files for DB-only</button>
                </form>
            <?php endif; ?>
        </div>
    </div>
    <div class="card-body">
        <?php if (empty($entries)): ?>
            <div class="alert alert-success mb-0">No stray files or missing records found.</div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table align-middle">
                    <thead>
                        <tr>
                            <th>Microservicelet</th>
                            <th>Type</th>
                            <th>Key</th>
                            <th>File</th>
                            <th>Status</th>
                            <?php if ($canRemediate): ?>
                                <th class="text-end">Action</th>
                            <?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($entries as $row): ?>
                            <tr>
                                <td>
                                    <div class="fw-semibold"><?php echo htmlspecialchars($row['ms_name'] ?? ''); ?></div>
                                    <div class="text-muted small">ID: <?php echo htmlspecialchars((string)($row['ms_id'] ?? '')); ?></div>
                                    <div class="text-muted small">UID: <?php echo htmlspecialchars((string)($row['ms_uid'] ?? '')); ?></div>
                                </td>
                                <td><span class="badge text-bg-light"><?php echo htmlspecialchars($row['type'] ?? ''); ?></span></td>
                                <td class="text-muted small"><?php echo htmlspecialchars((string)($row['key'] ?? '')); ?></td>
                                <td class="text-muted small"><?php echo htmlspecialchars((string)($row['file'] ?? '')); ?></td>
                                <td>
                                    <?php if (($row['status'] ?? '') === 'file_only'): ?>
                                        <span class="badge text-bg-warning">File only</span>
                                    <?php elseif (($row['status'] ?? '') === 'db_only'): ?>
                                        <span class="badge text-bg-danger">DB only</span>
                                    <?php else: ?>
                                        <span class="badge text-bg-secondary"><?php echo htmlspecialchars((string)($row['status'] ?? '')); ?></span>
                                    <?php endif; ?>
                                </td>
                                <?php if ($canRemediate): ?>
                                    <td class="text-end">
                                        <?php if (($row['status'] ?? '') === 'file_only'): ?>
                                            <form method="post" action="<?php echo $radAdminUrl; ?>/governance/strayroutes" class="d-inline-flex align-items-center gap-2">
                                                <input type="hidden" name="action" value="delete_file">
                                                <input type="hidden" name="file" value="<?php echo htmlspecialchars((string)($row['file'] ?? '')); ?>">
                                                <input type="hidden" name="ms_name" value="<?php echo htmlspecialchars((string)($row['ms_name'] ?? '')); ?>">
                                                <input type="hidden" name="delete_mode" value="trash">
                                                <button type="submit" class="btn btn-outline-danger btn-sm">Delete File</button>
                                                <label class="form-check form-check-inline mb-0 text-muted small">
                                                    <input class="form-check-input" type="checkbox" name="delete_mode" value="permanent">
                                                    <span class="form-check-label">Permanent</span>
                                                </label>
                                            </form>
                                        <?php elseif (($row['status'] ?? '') === 'db_only'): ?>
                                            <form method="post" action="<?php echo $radAdminUrl; ?>/governance/strayroutes" class="d-inline-flex align-items-center gap-2">
                                                <input type="hidden" name="action" value="generate_file">
                                                <input type="hidden" name="ms_id" value="<?php echo htmlspecialchars((string)($row['ms_id'] ?? '')); ?>">
                                                <input type="hidden" name="ms_name" value="<?php echo htmlspecialchars((string)($row['ms_name'] ?? '')); ?>">
                                                <input type="hidden" name="type" value="<?php echo htmlspecialchars((string)($row['type'] ?? '')); ?>">
                                                <input type="hidden" name="key" value="<?php echo htmlspecialchars((string)($row['key'] ?? '')); ?>">
                                                <button type="submit" class="btn btn-outline-primary btn-sm">Generate File</button>
                                            </form>
                                        <?php endif; ?>
                                    </td>
                                <?php endif; ?>
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
    </div>
    <ul class="pagination mb-0">
        <?php
        $baseParams = $filters;
        $baseParams['per_page'] = $pagination['per_page'];
        $baseQuery = http_build_query(array_filter($baseParams, fn($v) => $v !== '' && $v !== null));
        $baseUrl = $radAdminUrl . '/governance/strayroutes' . ($baseQuery ? '?' . $baseQuery . '&' : '?');
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
