<?php
$radAdminUrl = $this->runData['route']['rad_admin_url'] ?? '';
$filters = $this->runData['data']['filters'] ?? [];
$rows = $this->runData['data']['rows'] ?? [];
$summary = $this->runData['data']['summary'] ?? ['total' => 0, 'active' => 0, 'used' => 0, 'expired' => 0, 'archived' => 0];
$pagination = $this->runData['data']['pagination'] ?? ['page' => 1, 'per_page' => 25, 'total' => 0, 'total_pages' => 1];

if (!function_exists('forgotpassword_query')) {
    function forgotpassword_query(array $filters, int $page, int $perPage): string {
        $payload = [
            'status' => $filters['status'] ?? '',
            'date_from' => $filters['date_from'] ?? '',
            'date_to' => $filters['date_to'] ?? '',
            'q' => $filters['q'] ?? '',
            'ip' => $filters['ip'] ?? '',
            'page' => $page,
            'per_page' => $perPage,
        ];
        return http_build_query($payload);
    }
}
?>

<div class="card border-0 shadow-sm mb-4">
    <div class="card-body">
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-3">
            <div>
                <h2 class="h5 mb-1">Password Reset Activity</h2>
                <p class="text-muted mb-0">Review password reset requests and recent access/error log context.</p>
            </div>
            <span class="badge rounded-pill text-bg-light"><?php echo (int)$summary['total']; ?> total</span>
        </div>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-md-3">
        <div class="border rounded p-3 h-100">
            <div class="text-muted small">Active</div>
            <div class="h4 mb-0"><?php echo (int)$summary['active']; ?></div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="border rounded p-3 h-100">
            <div class="text-muted small">Used</div>
            <div class="h4 mb-0"><?php echo (int)$summary['used']; ?></div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="border rounded p-3 h-100">
            <div class="text-muted small">Expired</div>
            <div class="h4 mb-0"><?php echo (int)$summary['expired']; ?></div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="border rounded p-3 h-100">
            <div class="text-muted small">Archived</div>
            <div class="h4 mb-0"><?php echo (int)$summary['archived']; ?></div>
        </div>
    </div>
</div>

<div class="card mb-3">
    <div class="card-body">
        <form class="row g-3 align-items-end" method="get" action="<?php echo $radAdminUrl; ?>/governance/forgotpasswordlog">
            <div class="col-md-2">
                <label class="form-label small text-muted">Status</label>
                <select name="status" class="form-select form-select-sm">
                    <option value="">All statuses</option>
                    <?php foreach (['active' => 'Active','used' => 'Used','expired' => 'Expired','archived' => 'Archived','inactive' => 'Inactive','suspended' => 'Suspended'] as $value => $label) { ?>
                        <option value="<?php echo $value; ?>" <?php echo (($filters['status'] ?? '') === $value) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($label); ?>
                        </option>
                    <?php } ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label small text-muted">From</label>
                <input type="date" name="date_from" class="form-control form-control-sm" value="<?php echo htmlspecialchars($filters['date_from'] ?? ''); ?>">
            </div>
            <div class="col-md-2">
                <label class="form-label small text-muted">To</label>
                <input type="date" name="date_to" class="form-control form-control-sm" value="<?php echo htmlspecialchars($filters['date_to'] ?? ''); ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label small text-muted">Search</label>
                <input type="search" name="q" class="form-control form-control-sm" placeholder="Name, email, or token UID" value="<?php echo htmlspecialchars($filters['q'] ?? ''); ?>">
            </div>
            <div class="col-md-2">
                <label class="form-label small text-muted">IP address</label>
                <input type="text" name="ip" class="form-control form-control-sm" placeholder="e.g. 127.0.0.1" value="<?php echo htmlspecialchars($filters['ip'] ?? ''); ?>">
            </div>
            <div class="col-md-1">
                <label class="form-label small text-muted">Per page</label>
                <select name="per_page" class="form-select form-select-sm">
                    <?php foreach ([10, 25, 50, 100] as $value) { ?>
                        <option value="<?php echo $value; ?>" <?php echo ((int)($pagination['per_page'] ?? 25) === $value) ? 'selected' : ''; ?>>
                            <?php echo $value; ?>
                        </option>
                    <?php } ?>
                </select>
            </div>
            <div class="col-12">
                <button type="submit" class="btn btn-primary btn-sm">Apply</button>
                <?php if (!empty(array_filter($filters))) { ?>
                    <a href="<?php echo $radAdminUrl; ?>/governance/forgotpasswordlog" class="btn btn-outline-secondary btn-sm">Reset</a>
                <?php } ?>
            </div>
        </form>
    </div>
</div>

<div class="card mb-4">
    <div class="card-header bg-white d-flex flex-wrap align-items-center justify-content-between gap-3">
        <div>
            <strong><?php echo (int)$pagination['total']; ?> reset request(s)</strong>
            <div class="text-muted small">Page <?php echo (int)$pagination['page']; ?> of <?php echo (int)$pagination['total_pages']; ?></div>
        </div>
        <form method="post" action="<?php echo $radAdminUrl; ?>/governance/forgotpasswordlog" class="d-flex flex-wrap gap-2 align-items-center">
            <input type="hidden" name="action" value="purge_password_reset">
            <select name="purge_days" class="form-select form-select-sm" required>
                <option value="">Cleanup older than</option>
                <option value="30">30 days</option>
                <option value="90">90 days</option>
                <option value="180">180 days</option>
            </select>
            <button type="submit" class="btn btn-outline-danger btn-sm">
                <i class="bi bi-trash3 me-1"></i>Cleanup
            </button>
        </form>
    </div>
    <div class="table-responsive">
        <table class="table table-sm align-middle mb-0">
            <thead class="table-light">
            <tr>
                <th class="text-nowrap">Created</th>
                <th>User</th>
                <th>Email</th>
                <th class="text-nowrap">IP</th>
                <th class="text-nowrap">Status</th>
                <th class="text-nowrap">Expires</th>
                <th class="text-nowrap">Used at</th>
                <th>Access log</th>
                <th>Error log</th>
            </tr>
            </thead>
            <tbody>
            <?php if (empty($rows)) { ?>
                <tr>
                    <td colspan="9" class="text-center py-4 text-muted">No password reset activity found for the selected filters.</td>
                </tr>
            <?php } ?>
            <?php foreach ($rows as $row) { ?>
                <?php
                $name = $row['s_name'] ?? '';
                $identity = $row['s_identity'] ?? '';
                $email = $row['s_email'] ?? '';
                $userLabel = $name !== '' ? $name : ($identity !== '' ? $identity : ('User #' . (int)$row['s_entity_id']));
                $status = $row['status'] ?? 'unknown';
                $statusBadge = [
                    'active' => 'bg-success',
                    'used' => 'bg-secondary',
                    'expired' => 'bg-warning text-dark',
                    'archived' => 'bg-light text-dark',
                    'inactive' => 'bg-light text-dark',
                    'suspended' => 'bg-danger',
                ][$status] ?? 'bg-light text-dark';
                $accessLog = $row['access_log'] ?? null;
                $errorLog = $row['error_log'] ?? null;
                ?>
                <tr>
                    <td class="text-nowrap"><?php echo htmlspecialchars($row['createstamp'] ?? ''); ?></td>
                    <td>
                        <div class="fw-semibold"><?php echo htmlspecialchars($userLabel); ?></div>
                        <div class="text-muted small">Entity #<?php echo (int)($row['s_entity_id'] ?? 0); ?></div>
                        <div class="text-muted small">Token: <?php echo htmlspecialchars($row['uid'] ?? ''); ?></div>
                    </td>
                    <td><?php echo htmlspecialchars($email !== '' ? $email : $identity); ?></td>
                    <td class="text-nowrap"><?php echo htmlspecialchars($row['s_ip'] ?? ''); ?></td>
                    <td><span class="badge <?php echo $statusBadge; ?>"><?php echo htmlspecialchars(ucfirst($status)); ?></span></td>
                    <td class="text-nowrap"><?php echo htmlspecialchars($row['s_expires_at'] ?? ''); ?></td>
                    <td class="text-nowrap"><?php echo htmlspecialchars($row['s_used_at'] ?? ''); ?></td>
                    <td>
                        <?php if (!empty($accessLog)) { ?>
                            <div class="small">Hits: <?php echo (int)$accessLog['count']; ?></div>
                            <div class="small text-muted">
                                <?php echo htmlspecialchars($accessLog['last_time']); ?>
                                <?php if (!empty($accessLog['last_method'])) { ?>
                                    · <?php echo htmlspecialchars($accessLog['last_method']); ?>
                                <?php } ?>
                            </div>
                            <div class="small text-muted text-truncate" style="max-width:220px;">
                                <?php echo htmlspecialchars($accessLog['last_uri'] ?? ''); ?>
                            </div>
                        <?php } else { ?>
                            <span class="text-muted small">No access log match</span>
                        <?php } ?>
                    </td>
                    <td>
                        <?php if (!empty($errorLog)) { ?>
                            <div class="small">Errors: <?php echo (int)$errorLog['count']; ?></div>
                            <div class="small text-muted"><?php echo htmlspecialchars($errorLog['last_time']); ?></div>
                            <div class="small text-muted text-truncate" style="max-width:220px;">
                                <?php echo htmlspecialchars($errorLog['last_message'] ?? ''); ?>
                            </div>
                        <?php } else { ?>
                            <span class="text-muted small">No error log match</span>
                        <?php } ?>
                    </td>
                </tr>
            <?php } ?>
            </tbody>
        </table>
    </div>
</div>

<?php if (($pagination['total_pages'] ?? 1) > 1) { ?>
    <?php
    $baseQuery = forgotpassword_query($filters, 1, (int)$pagination['per_page']);
    $baseUrl = $radAdminUrl . '/governance/forgotpasswordlog?' . $baseQuery . '&page=';
    $page = (int)$pagination['page'];
    $totalPages = (int)$pagination['total_pages'];
    ?>
    <nav aria-label="Password reset pagination">
        <ul class="pagination">
            <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                <a class="page-link" href="<?php echo $baseUrl . max(1, $page - 1); ?>">Previous</a>
            </li>
            <?php for ($p = 1; $p <= $totalPages; $p++) { ?>
                <?php if ($p === 1 || $p === $totalPages || abs($p - $page) <= 2) { ?>
                    <li class="page-item <?php echo $p === $page ? 'active' : ''; ?>">
                        <a class="page-link" href="<?php echo $baseUrl . $p; ?>"><?php echo $p; ?></a>
                    </li>
                <?php } elseif ($p === 2 || $p === $totalPages - 1) { ?>
                    <li class="page-item disabled"><span class="page-link">…</span></li>
                <?php } ?>
            <?php } ?>
            <li class="page-item <?php echo $page >= $totalPages ? 'disabled' : ''; ?>">
                <a class="page-link" href="<?php echo $baseUrl . min($totalPages, $page + 1); ?>">Next</a>
            </li>
        </ul>
    </nav>
<?php } ?>
