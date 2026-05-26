<?php
$users = $this->runData['data']['users'];
$numOfUsers = count($users);
$stats = $this->runData['data']['user_stats'] ?? ['total' => 0, 'active' => 0, 'mfa' => 0, 'saas' => 0, 'api' => 0];
$canManage = !empty($this->runData['data']['can_idm_manage']);
$filters = $this->runData['data']['filters'] ?? ['q' => '', 'status' => '', 'mfa' => '', 'agreement' => ''];
$pagination = $this->runData['data']['pagination'] ?? ['page' => 1, 'per_page' => 25, 'total' => $numOfUsers, 'total_pages' => 1];
$currentPage = (int)($pagination['page'] ?? 1);
$perPage = (int)($pagination['per_page'] ?? 25);
$totalUsers = (int)($pagination['total'] ?? $numOfUsers);
$totalPages = max(1, (int)($pagination['total_pages'] ?? 1));
$radAdminUrl = $this->runData['route']['rad_admin_url'];
$queryBase = array_filter([
    'q' => $filters['q'] ?? '',
    'status' => $filters['status'] ?? '',
    'mfa' => $filters['mfa'] ?? '',
    'agreement' => $filters['agreement'] ?? '',
    'per_page' => $perPage,
]);
$prevQuery = http_build_query(array_merge($queryBase, ['page' => max(1, $currentPage - 1)]));
$nextQuery = http_build_query(array_merge($queryBase, ['page' => min($totalPages, $currentPage + 1)]));
?>

<?php if ($numOfUsers === 0): ?>
    <div class="text-center py-5">
        <i class="bi bi-people text-muted" style="font-size: 6rem;"></i>
        <p class="lead mt-3">No users found. Start by adding your first teammate or application identity.</p>
        <?php if ($canManage) { ?>
            <a href="<?php echo $this->runData['route']['rad_admin_url']; ?>/user/add" class="btn btn-primary">
                <i class="bi bi-person-plus-fill me-1"></i>Add User
            </a>
        <?php } ?>
    </div>
<?php else: ?>
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
        <div class="text-muted small">
            Showing <span id="user-visible-count"><?php echo $numOfUsers; ?></span> of <?php echo $totalUsers; ?> users
        </div>
        <div class="btn-group">
            <a href="<?php echo $this->runData['route']['rad_admin_url']; ?>/role/view" class="btn btn-outline-secondary">
                <i class="bi bi-diagram-3 me-1"></i>Roles
            </a>
            <a href="<?php echo $this->runData['route']['rad_admin_url']; ?>/user/diagnostics" class="btn btn-outline-secondary">
                <i class="bi bi-activity me-1"></i>Role Diagnostics
            </a>
            <?php if ($canManage) { ?>
                <a href="<?php echo $this->runData['route']['rad_admin_url']; ?>/user/add" class="btn btn-primary">
                    <i class="bi bi-person-plus-fill me-1"></i>Add User
                </a>
            <?php } ?>
        </div>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-md-3">
            <div class="card h-100 shadow-sm user-metric-card">
                <div class="card-body">
                    <div class="text-muted text-uppercase small">Total</div>
                    <div class="display-6 fw-semibold"><?php echo $stats['total']; ?></div>
                    <div class="text-muted small">Directory entries</div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card h-100 shadow-sm user-metric-card">
                <div class="card-body">
                    <div class="text-muted text-uppercase small">Active</div>
                    <div class="display-6 fw-semibold text-success"><?php echo $stats['active']; ?></div>
                    <div class="text-muted small">Able to sign in</div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card h-100 shadow-sm user-metric-card">
                <div class="card-body">
                    <div class="text-muted text-uppercase small">MFA Enabled</div>
                    <div class="display-6 fw-semibold text-info"><?php echo $stats['mfa']; ?></div>
                    <div class="text-muted small">Using MFA or OTP</div>
                </div>
            </div>
        </div>
    </div>

    <form class="card mb-3" method="get" action="<?php echo $radAdminUrl; ?>/user/view">
        <div class="card-body">
            <div class="row g-3 align-items-end">
                <div class="col-lg-4">
                    <label class="form-label">Search</label>
                    <input type="text" class="form-control" id="user-filter-search" name="q" value="<?php echo htmlspecialchars($filters['q'] ?? ''); ?>" placeholder="Name, username, UID, email...">
                </div>
                <div class="col-md-2 col-lg-2">
                    <label class="form-label">Status</label>
                    <select class="form-select" id="user-filter-status" name="status">
                        <option value="">All</option>
                        <option value="1" <?php echo ($filters['status'] ?? '') === '1' ? 'selected' : ''; ?>>Active</option>
                        <option value="0" <?php echo ($filters['status'] ?? '') === '0' ? 'selected' : ''; ?>>Inactive</option>
                        <option value="2" <?php echo ($filters['status'] ?? '') === '2' ? 'selected' : ''; ?>>Archived</option>
                        <option value="3" <?php echo ($filters['status'] ?? '') === '3' ? 'selected' : ''; ?>>Suspended</option>
                    </select>
                </div>
                <div class="col-md-2 col-lg-2">
                    <label class="form-label">MFA</label>
                    <select class="form-select" id="user-filter-mfa" name="mfa">
                        <option value="">All</option>
                        <option value="enabled" <?php echo ($filters['mfa'] ?? '') === 'enabled' ? 'selected' : ''; ?>>Enabled</option>
                        <option value="disabled" <?php echo ($filters['mfa'] ?? '') === 'disabled' ? 'selected' : ''; ?>>Disabled</option>
                    </select>
                </div>
                <div class="col-md-2 col-lg-2">
                    <label class="form-label">Agreement</label>
                    <select class="form-select" id="user-filter-agreement" name="agreement">
                        <option value="">All</option>
                        <option value="signed" <?php echo ($filters['agreement'] ?? '') === 'signed' ? 'selected' : ''; ?>>Signed</option>
                        <option value="pending" <?php echo ($filters['agreement'] ?? '') === 'pending' ? 'selected' : ''; ?>>Pending</option>
                    </select>
                </div>
                <div class="col-md-2 col-lg-2">
                    <label class="form-label">Per page</label>
                    <select class="form-select" id="user-filter-per-page" name="per_page">
                        <?php foreach ([25, 50, 100, 200] as $size) { ?>
                            <option value="<?php echo $size; ?>" <?php echo $perPage === $size ? 'selected' : ''; ?>><?php echo $size; ?></option>
                        <?php } ?>
                    </select>
                </div>
                <div class="col-md-2 col-lg-2 text-lg-end">
                    <button class="btn btn-primary w-100" type="submit">
                        <i class="bi bi-funnel me-1"></i>Apply
                    </button>
                </div>
                <div class="col-md-2 col-lg-2 text-lg-end">
                    <?php if (!empty($filters['q']) || ($filters['status'] ?? '') !== '' || !empty($filters['mfa']) || !empty($filters['agreement'])) { ?>
                        <a class="btn btn-outline-secondary w-100" id="user-filter-reset" href="<?php echo $this->runData['route']['rad_admin_url']; ?>/user/view">
                            <i class="bi bi-arrow-counterclockwise me-1"></i>Reset
                        </a>
                    <?php } ?>
                </div>
            </div>
        </div>
    </form>

    <div class="toast align-items-center text-bg-success border-0" role="alert" aria-live="assertive" aria-atomic="true" id="user-toast">
        <div class="d-flex">
            <div class="toast-body">
                UID copied to clipboard.
            </div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
        </div>
    </div>

    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table align-middle user-table" id="user-table">
                    <thead>
                        <tr>
                            <th>User</th>
                            <th>Contact</th>
                            <th>Access</th>
                            <th>Agreements</th>
                            <th>Workspaces</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($users as $user): ?>
                        <?php
                            $statusMeta = $user['status_meta'];
                            $uid = htmlspecialchars($user['uid'] ?? '');
                            $email = htmlspecialchars($user['primary_email'] ?: '—');
                            $mobile = htmlspecialchars($user['primary_mobile'] ?: '—');
                            $username = htmlspecialchars($user['s_identity'] ?? '');
                            $displayName = htmlspecialchars($user['s_name'] ?? 'Unnamed');
                            $accessLabel = $user['access_ips'] ? htmlspecialchars($user['access_ips']) : '—';
                            $editDisabledClass = $user['is_protected'] ? ' disabled' : '';
                            $editDisabledAttrs = $user['is_protected'] ? ' tabindex="-1" aria-disabled="true"' : '';
                            $entityId = (int)($user['id'] ?? 0);
                        ?>
                        <tr
                            data-search="<?php echo htmlspecialchars($user['search_blob']); ?>"
                            data-status="<?php echo htmlspecialchars($user['status_slug']); ?>"
                            data-mfa="<?php echo htmlspecialchars($user['mfa_slug']); ?>"
                            data-agreement="<?php echo htmlspecialchars($user['agreement_slug']); ?>"
                        >
                            <td>
                                <div class="fw-semibold"><?php echo $displayName; ?></div>
                                <div class="text-muted small">@<?php echo $username; ?></div>
                                <div class="text-muted small">ID: <?php echo $entityId; ?> · UID: <span class="user-uid"><?php echo $uid; ?></span>
                                    <button type="button" class="btn btn-link btn-sm p-0 ms-1 copy-uid" data-uid="<?php echo $uid; ?>">
                                        <i class="bi bi-clipboard"></i>
                                    </button>
                                </div>
                            </td>
                            <td>
                                <div class="text-muted small">Email</div>
                                <div><?php echo $email; ?></div>
                                <div class="text-muted small mt-1">Mobile</div>
                                <div><?php echo $mobile; ?></div>
                            </td>
                            <td>
                                <span class="badge bg-<?php echo htmlspecialchars($statusMeta['badge']); ?> me-1">
                                    <?php echo htmlspecialchars($statusMeta['label']); ?>
                                </span>
                                <span class="badge bg-light text-dark">
                                    <?php echo htmlspecialchars($user['login_mode_label']); ?>
                                </span>
                                <div class="text-muted small mt-2">
                                    IP Restriction: <?php echo $accessLabel; ?>
                                </div>
                            </td>
                            <td>
                                <span class="badge bg-<?php echo $user['mfa_slug'] === 'enabled' ? 'success' : 'secondary'; ?> me-1">
                                    MFA <?php echo htmlspecialchars($user['mfa_label']); ?>
                                </span>
                                <span class="badge bg-<?php echo $user['agreement_slug'] === 'signed' ? 'success' : 'warning'; ?>">
                                    Agreement <?php echo htmlspecialchars($user['agreement_label']); ?>
                                </span>
                            </td>
                            <td>
                                <div class="fw-semibold"><?php echo (int)$user['spaces_count']; ?> space(s)</div>
                                <div class="text-muted small">Membership footprint</div>
                            </td>
                            <td class="text-end">
                                <div class="btn-group user-actions">
                                    <a href="<?php echo $this->runData['route']['rad_admin_url']; ?>/user/viewone/<?php echo $uid; ?>" class="btn btn-outline-primary btn-sm">
                                        <i class="bi bi-eye"></i>
                                    </a>
                                    <?php if ($canManage) { ?>
                                        <a href="<?php echo $this->runData['route']['rad_admin_url']; ?>/user/edit/<?php echo $uid; ?>" class="btn btn-outline-secondary btn-sm<?php echo $editDisabledClass; ?>"<?php echo $editDisabledAttrs; ?>>
                                            <i class="bi bi-pencil"></i>
                                        </a>
                                    <?php } ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mt-3">
                <div class="text-muted small" id="user-page-summary">
                    <?php
                        $start = $totalUsers === 0 ? 0 : (($currentPage - 1) * $perPage + 1);
                        $end = min($totalUsers, $currentPage * $perPage);
                        echo $totalUsers === 0 ? 'No users match the selected filters.' : "Showing {$start}–{$end} of {$totalUsers}";
                    ?>
                </div>
                <div class="btn-group" role="group">
                    <a class="btn btn-outline-secondary btn-sm<?php echo $currentPage <= 1 ? ' disabled' : ''; ?>" id="user-page-prev"
                       href="<?php echo $currentPage <= 1 ? '#' : ($radAdminUrl . '/user/view?' . $prevQuery); ?>"<?php echo $currentPage <= 1 ? ' tabindex="-1" aria-disabled="true"' : ''; ?>>
                        <i class="bi bi-arrow-left"></i>
                    </a>
                    <a class="btn btn-outline-secondary btn-sm<?php echo $currentPage >= $totalPages ? ' disabled' : ''; ?>" id="user-page-next"
                       href="<?php echo $currentPage >= $totalPages ? '#' : ($radAdminUrl . '/user/view?' . $nextQuery); ?>"<?php echo $currentPage >= $totalPages ? ' tabindex="-1" aria-disabled="true"' : ''; ?>>
                        <i class="bi bi-arrow-right"></i>
                    </a>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>
