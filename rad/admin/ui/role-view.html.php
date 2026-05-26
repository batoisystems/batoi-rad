<?php
$roles = $this->runData['data']['roles'];
$stats = $this->runData['data']['role_stats'] ?? ['total' => 0, 'saas' => 0, 'non_saas' => 0, 'with_route' => 0];
$numOfRoles = count($roles);
$radAdminUrl = $this->runData['route']['rad_admin_url'];
$canManage = !empty($this->runData['data']['can_idm_manage']);
$filters = $this->runData['data']['filters'] ?? ['q' => '', 'status' => '', 'scope' => '', 'has_route' => '', 'saas' => '', 'page' => 1, 'per_page' => 25];
$pagination = $this->runData['data']['pagination'] ?? ['page' => 1, 'per_page' => 25, 'total' => $numOfRoles, 'total_pages' => 1];
$currentPage = (int)($pagination['page'] ?? 1);
$totalPages = (int)($pagination['total_pages'] ?? 1);
?>

<?php if ($numOfRoles === 0): ?>
    <div class="text-center py-5">
        <i class="bi bi-shield-slash text-muted" style="font-size: 6rem;"></i>
        <p class="lead mt-3">No roles defined yet. Create your first role to manage permissions.</p>
        <?php if ($canManage) { ?>
            <a href="<?php echo $radAdminUrl; ?>/role/add" class="btn btn-primary">
                <i class="bi bi-plus-circle me-1"></i>Add Role
            </a>
        <?php } ?>
    </div>
<?php else: ?>
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
        <div class="text-muted small">
            Showing <?php echo number_format($pagination['total']); ?> role(s)
        </div>
        <div class="btn-group">
            <?php
                $sniffQuery = http_build_query(array_filter([
                    'q' => $filters['q'],
                    'status' => $filters['status'],
                    'scope' => $filters['scope'],
                    'has_route' => $filters['has_route'],
                    'saas' => $filters['saas'],
                ], function ($value) {
                    return $value !== '';
                }));
                $sniffUrl = $radAdminUrl . '/role/sniff' . ($sniffQuery !== '' ? '?' . $sniffQuery : '');
            ?>
            <a href="<?php echo $sniffUrl; ?>" class="btn btn-outline-secondary">
                <i class="bi bi-clipboard-data me-1"></i>Meta Sniff
            </a>
            <a href="<?php echo $radAdminUrl; ?>/user/view" class="btn btn-outline-secondary">
                <i class="bi bi-people me-1"></i>Users
            </a>
            <?php if ($canManage) { ?>
                <a href="<?php echo $radAdminUrl; ?>/role/add" class="btn btn-primary">
                    <i class="bi bi-plus-circle me-1"></i>Add Role
                </a>
            <?php } ?>
        </div>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-md-3">
            <div class="card h-100 shadow-sm role-metric-card">
                <div class="card-body">
                    <div class="text-muted text-uppercase small">Total</div>
                    <div class="display-6 fw-semibold"><?php echo $stats['total']; ?></div>
                    <div class="text-muted small">Defined roles</div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card h-100 shadow-sm role-metric-card">
                <div class="card-body">
                    <div class="text-muted text-uppercase small">SaaS Ready</div>
                    <div class="display-6 fw-semibold text-info"><?php echo $stats['saas']; ?></div>
                    <div class="text-muted small">Available to workspaces</div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card h-100 shadow-sm role-metric-card">
                <div class="card-body">
                    <div class="text-muted text-uppercase small">Non-SaaS</div>
                    <div class="display-6 fw-semibold text-secondary"><?php echo $stats['non_saas']; ?></div>
                    <div class="text-muted small">Platform/API roles</div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card h-100 shadow-sm role-metric-card">
                <div class="card-body">
                    <div class="text-muted text-uppercase small">Default Route</div>
                    <div class="display-6 fw-semibold text-success"><?php echo $stats['with_route']; ?></div>
                    <div class="text-muted small">Roles mapped to routes</div>
                </div>
            </div>
        </div>
    </div>

    <form class="card mb-3" method="get" action="<?php echo $radAdminUrl; ?>/role/view">
        <div class="card-body">
            <div class="row g-3 align-items-end">
                <div class="col-lg-4">
                    <label class="form-label">Search</label>
                    <input type="text" class="form-control" id="role-filter-search" name="q" placeholder="Title, scope, code, UID..." value="<?php echo htmlspecialchars($filters['q']); ?>">
                </div>
                <div class="col-md-2 col-lg-2">
                    <label class="form-label">Status</label>
                    <select class="form-select" id="role-filter-status" name="status">
                        <option value="">All</option>
                        <option value="1" <?php echo $filters['status'] === '1' ? 'selected' : ''; ?>>Active</option>
                        <option value="0" <?php echo $filters['status'] === '0' ? 'selected' : ''; ?>>Inactive</option>
                        <option value="2" <?php echo $filters['status'] === '2' ? 'selected' : ''; ?>>Archived</option>
                        <option value="3" <?php echo $filters['status'] === '3' ? 'selected' : ''; ?>>Suspended</option>
                    </select>
                </div>
                <div class="col-md-2 col-lg-2">
                    <label class="form-label">SaaS</label>
                    <select class="form-select" id="role-filter-saas" name="saas">
                        <option value="">All</option>
                        <option value="saas" <?php echo $filters['saas'] === 'saas' ? 'selected' : ''; ?>>SaaS</option>
                        <option value="non_saas" <?php echo $filters['saas'] === 'non_saas' ? 'selected' : ''; ?>>Non-SaaS</option>
                    </select>
                </div>
                <div class="col-md-2 col-lg-2">
                    <label class="form-label">Scope</label>
                    <select class="form-select" id="role-filter-scope" name="scope">
                        <option value="">All</option>
                        <option value="global" <?php echo $filters['scope'] === 'global' ? 'selected' : ''; ?>>Global</option>
                        <option value="platform" <?php echo $filters['scope'] === 'platform' ? 'selected' : ''; ?>>Platform</option>
                        <option value="workspace" <?php echo $filters['scope'] === 'workspace' ? 'selected' : ''; ?>>Workspace</option>
                    </select>
                </div>
                <div class="col-md-2 col-lg-2">
                    <label class="form-label">Default Route</label>
                    <select class="form-select" id="role-filter-route" name="has_route">
                        <option value="">All</option>
                        <option value="yes" <?php echo $filters['has_route'] === 'yes' ? 'selected' : ''; ?>>Has Route</option>
                        <option value="no" <?php echo $filters['has_route'] === 'no' ? 'selected' : ''; ?>>Not Set</option>
                    </select>
                </div>
                <div class="col-md-2 col-lg-2 text-lg-end">
                    <button class="btn btn-primary w-100" type="submit">
                        <i class="bi bi-funnel me-1"></i>Apply
                    </button>
                </div>
                <div class="col-md-2 col-lg-2 text-lg-end">
                    <?php if (!empty($filters['q']) || !empty($filters['status']) || !empty($filters['scope']) || !empty($filters['has_route']) || !empty($filters['saas'])): ?>
                        <a class="btn btn-outline-secondary w-100" id="role-filter-reset" href="<?php echo $radAdminUrl; ?>/role/view">
                            <i class="bi bi-arrow-counterclockwise me-1"></i>Reset
                        </a>
                    <?php endif; ?>
                </div>
                <div class="col-md-2 col-lg-2">
                    <label class="form-label">Per page</label>
                    <select class="form-select" name="per_page">
                        <?php foreach ([25, 50, 100, 200] as $size) { ?>
                            <option value="<?php echo $size; ?>" <?php echo (int)$filters['per_page'] === $size ? 'selected' : ''; ?>><?php echo $size; ?></option>
                        <?php } ?>
                    </select>
                </div>
            </div>
        </div>
    </form>

    <div class="toast align-items-center text-bg-success border-0" role="alert" aria-live="assertive" aria-atomic="true" id="role-toast">
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
                <table class="table align-middle role-table" id="role-table">
                    <thead>
                        <tr>
                            <th>Role</th>
                            <th>Scope & Status</th>
                            <th>Default Route</th>
                            <th>Description</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($roles as $role): ?>
                        <?php
                            $uid = htmlspecialchars($role['uid'] ?? '');
                            $statusMeta = $role['status_meta'] ?? ['label' => 'Status', 'badge' => 'secondary'];
                            $scopeMeta = $role['scope_meta'] ?? ['label' => 'Scope', 'badge' => 'secondary'];
                            $routeInfo = $role['default_route'] ?? null;
                            $routeLabel = $routeInfo ? ($routeInfo['ms_name'] ? $routeInfo['ms_name'] . ' · ' : '') . $routeInfo['name'] : 'Not configured';
                            $routeUrl = ($routeInfo && !empty($routeInfo['uid'])) ? $radAdminUrl . '/route/detail/' . htmlspecialchars($routeInfo['uid']) : '';
                            $canEdit = (int)($role['id'] ?? 0) !== 1;
                            $diagnostics = $role['diagnostics'] ?? [];
                        ?>
                        <tr
                            data-search="<?php echo htmlspecialchars($role['search_blob']); ?>"
                            data-status="<?php echo htmlspecialchars($role['status_slug']); ?>"
                            data-saas="<?php echo htmlspecialchars($role['saas_slug']); ?>"
                            data-scope="<?php echo htmlspecialchars($role['scope_slug']); ?>"
                            data-route="<?php echo htmlspecialchars($role['has_route']); ?>"
                        >
                            <td>
                                <div class="fw-semibold">
                                    <a href="<?php echo $radAdminUrl; ?>/role/viewone/<?php echo $uid; ?>">
                                        <?php echo htmlspecialchars($role['s_role_name'] ?? 'Role'); ?>
                                    </a>
                                </div>
                                <div class="text-muted small">ID: <?php echo (int)($role['id'] ?? 0); ?></div>
                                <div class="text-muted small">UID:
                                    <span class="role-uid"><?php echo $uid; ?></span>
                                    <button type="button" class="btn btn-link btn-sm p-0 ms-1 copy-uid" data-uid="<?php echo $uid; ?>">
                                        <i class="bi bi-clipboard"></i>
                                    </button>
                                </div>
                                <?php if (!empty($role['s_code'])): ?>
                                    <div class="text-muted small">Code: <?php echo htmlspecialchars($role['s_code']); ?></div>
                                <?php endif; ?>
                                <?php if (!empty($diagnostics)): ?>
                                    <div class="mt-1 d-flex flex-wrap gap-1">
                                        <?php if (in_array('missing_default_route', $diagnostics, true)) { ?>
                                            <span class="badge bg-warning text-dark">Default route missing</span>
                                        <?php } ?>
                                        <?php if (in_array('unused', $diagnostics, true)) { ?>
                                            <span class="badge bg-light text-dark">Unused</span>
                                        <?php } ?>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="badge bg-<?php echo htmlspecialchars($statusMeta['badge']); ?> me-1">
                                    <?php echo htmlspecialchars($statusMeta['label']); ?>
                                </span>
                                <span class="badge bg-<?php echo htmlspecialchars($role['saas_badge']); ?> me-1">
                                    <?php echo htmlspecialchars($role['saas_label']); ?>
                                </span>
                                <span class="badge bg-<?php echo htmlspecialchars($scopeMeta['badge']); ?>">
                                    <?php echo htmlspecialchars($scopeMeta['label']); ?>
                                </span>
                            </td>
                            <td>
                                <?php if ($routeInfo && $routeUrl): ?>
                                    <a href="<?php echo $routeUrl; ?>" class="text-decoration-none">
                                        <?php echo htmlspecialchars($routeLabel); ?>
                                    </a>
                                <?php else: ?>
                                    <span class="text-muted">Not configured</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if (!empty($role['description_excerpt'])): ?>
                                    <div class="text-muted"><?php echo htmlspecialchars($role['description_excerpt']); ?></div>
                                <?php else: ?>
                                    <span class="text-muted small">No description.</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-end">
                                <div class="btn-group role-actions">
                                    <a href="<?php echo $radAdminUrl; ?>/role/viewone/<?php echo $uid; ?>" class="btn btn-outline-primary btn-sm">
                                        <i class="bi bi-eye"></i>
                                    </a>
                                    <a href="<?php echo $radAdminUrl; ?>/role/edit/<?php echo $uid; ?>" class="btn btn-outline-secondary btn-sm<?php echo $canEdit ? '' : ' disabled'; ?>"<?php echo $canEdit ? '' : ' tabindex="-1" aria-disabled="true"'; ?>>
                                        <i class="bi bi-pencil"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mt-3">
                <div class="text-muted small">
                    Page <?php echo $currentPage; ?> of <?php echo $totalPages; ?>
                </div>
                <div class="btn-group" role="group">
                    <?php
                        $queryBase = [
                            'q' => $filters['q'],
                            'status' => $filters['status'],
                            'scope' => $filters['scope'],
                            'has_route' => $filters['has_route'],
                            'saas' => $filters['saas'],
                            'per_page' => $filters['per_page'],
                        ];
                        $prevQuery = http_build_query(array_merge($queryBase, ['page' => max(1, $currentPage - 1)]));
                        $nextQuery = http_build_query(array_merge($queryBase, ['page' => min($totalPages, $currentPage + 1)]));
                    ?>
                    <a class="btn btn-outline-secondary btn-sm<?php echo $currentPage <= 1 ? ' disabled' : ''; ?>" href="<?php echo $radAdminUrl . '/role/view?' . $prevQuery; ?>">
                        <i class="bi bi-arrow-left"></i>
                    </a>
                    <a class="btn btn-outline-secondary btn-sm<?php echo $currentPage >= $totalPages ? ' disabled' : ''; ?>" href="<?php echo $radAdminUrl . '/role/view?' . $nextQuery; ?>">
                        <i class="bi bi-arrow-right"></i>
                    </a>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>
