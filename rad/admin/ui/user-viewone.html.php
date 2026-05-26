<?php
$profile = $this->runData['data']['user_profile'] ?? [];
$spaces = $this->runData['data']['user_spaces'] ?? [];
$nonSaasRole = $this->runData['data']['non_saas_role'] ?? null;
$nonSaasRoleDetail = $this->runData['data']['non_saas_role_detail'] ?? null;
$nonSaasRoleIssues = $this->runData['data']['non_saas_role_issues'] ?? [];
$adminMfaMissingContact = !empty($this->runData['data']['admin_mfa_missing_contact']);
$platformRoles = $this->runData['data']['platform_roles'] ?? [];
$platformRoleConflict = !empty($this->runData['data']['platform_role_conflict']);
$detailStats = $this->runData['data']['user_detail_stats'] ?? ['spaces' => 0, 'roles' => 0];
$activity = $this->runData['data']['user_activity'] ?? [];
$workspaceConflicts = $this->runData['data']['workspace_role_conflicts'] ?? [];
$roleCount = (int)($detailStats['roles'] ?? 0);
$workspaceRoleCount = (int)($detailStats['workspace_roles'] ?? 0);
$spaceCount = (int)($detailStats['spaces'] ?? 0);
$roleDiscrepancy = $workspaceRoleCount > $spaceCount && $spaceCount > 0;
$debugEnabled = !empty($this->runData['data']['debug_priv_enabled']);
$debugPriv = $this->runData['data']['debug_priv'] ?? [];
$radAdminUrl = $this->runData['route']['rad_admin_url'];
$uid = htmlspecialchars($profile['uid'] ?? '');
$entityId = isset($profile['id']) ? (int)$profile['id'] : 0;
$statusMeta = $profile['status_meta'] ?? ['label' => 'Unknown', 'badge' => 'secondary'];
$canManage = !empty($this->runData['data']['can_idm_manage']);
$statusSlug = $statusMeta['slug'] ?? '';
?>

<div class="card mb-3 shadow-sm user-hero">
    <div class="card-body">
        <div class="d-flex flex-column flex-lg-row align-items-start gap-3">
            <div class="flex-grow-1">
                <div class="d-flex flex-wrap align-items-center gap-2 mb-1">
                    <h2 class="h4 mb-0"><?php echo htmlspecialchars($profile['name'] ?? 'User'); ?></h2>
                    <span class="badge bg-<?php echo htmlspecialchars($statusMeta['badge'] ?? 'secondary'); ?>">
                        <?php echo htmlspecialchars($statusMeta['label'] ?? 'Status'); ?>
                    </span>
                    <span class="badge bg-<?php echo ($profile['mfa_slug'] ?? '') === 'enabled' ? 'success' : 'secondary'; ?>">
                        MFA <?php echo htmlspecialchars($profile['mfa_label'] ?? 'Unknown'); ?>
                    </span>
                </div>
                <div class="text-muted small">@<?php echo htmlspecialchars($profile['username'] ?? ''); ?></div>
                <div class="user-meta text-muted small d-flex align-items-center flex-wrap gap-3 mt-2">
                    <span>ID: <?php echo $entityId; ?></span>
                    <span class="d-flex align-items-center">UID:
                        <span class="user-uid ms-1"><?php echo $uid; ?></span>
                        <button class="btn btn-link btn-sm p-0 ms-1 copy-uid" data-uid="<?php echo $uid; ?>" title="Copy UID">
                            <i class="bi bi-clipboard"></i>
                        </button>
                    </span>
                </div>
                <div class="user-meta-strip text-muted small d-flex flex-wrap gap-3 mt-2">
                    <span>Created: <?php echo htmlspecialchars($activity['created']['timestamp'] ?? '—'); ?></span>
                    <span>Updated: <?php echo htmlspecialchars($activity['updated']['timestamp'] ?? '—'); ?></span>
                    <span>Last login: <?php echo htmlspecialchars($activity['last_login']['timestamp'] ?? '—'); ?></span>
                </div>
            </div>
            <?php if ($canManage) { ?>
                <div class="user-actions d-flex flex-wrap gap-2">
                    <a href="<?php echo $radAdminUrl; ?>/user/edit/<?php echo $uid; ?>" class="btn btn-primary">
                        <i class="bi bi-pencil-square me-1"></i>Edit User
                    </a>
                    <a href="<?php echo $radAdminUrl; ?>/user/resetPassword/<?php echo $uid; ?>" class="btn btn-outline-primary">
                        <i class="bi bi-key me-1"></i>Reset Password
                    </a>
                    <a href="<?php echo $radAdminUrl; ?>/user/managePlatformRole/<?php echo $uid; ?>" class="btn btn-outline-secondary">
                        <i class="bi bi-shield-lock me-1"></i>Non-SaaS Role
                    </a>
                    <a href="<?php echo $radAdminUrl; ?>/user/manageWorkspaceRole/<?php echo $uid; ?>" class="btn btn-outline-secondary">
                        <i class="bi bi-diagram-3 me-1"></i>Workspace Roles
                    </a>
                    <?php if ($entityId !== 1): ?>
                        <?php if ($statusSlug !== 'inactive'): ?>
                            <a href="<?php echo $radAdminUrl; ?>/user/deactivate/<?php echo $uid; ?>" class="btn btn-outline-warning">
                                <i class="bi bi-pause-circle me-1"></i>Deactivate
                            </a>
                        <?php endif; ?>
                        <?php if ($statusSlug !== 'suspended'): ?>
                            <a href="<?php echo $radAdminUrl; ?>/user/suspend/<?php echo $uid; ?>" class="btn btn-outline-warning">
                                <i class="bi bi-slash-circle me-1"></i>Suspend
                            </a>
                        <?php endif; ?>
                        <?php if ($statusSlug !== 'archived'): ?>
                            <a href="<?php echo $radAdminUrl; ?>/user/archive/<?php echo $uid; ?>" class="btn btn-outline-danger">
                                <i class="bi bi-archive me-1"></i>Archive
                            </a>
                        <?php endif; ?>
                        <?php if ($statusSlug !== 'active'): ?>
                            <a href="<?php echo $radAdminUrl; ?>/user/activate/<?php echo $uid; ?>" class="btn btn-outline-success">
                                <i class="bi bi-check-circle me-1"></i>Activate
                            </a>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            <?php } ?>
        </div>
    </div>
</div>

<?php if ($debugEnabled) { ?>
    <div class="alert alert-warning">
        <div class="fw-semibold mb-2">User View Debug (dev_debug_flag=Y)</div>
        <pre class="mb-0 small"><?php echo htmlspecialchars(json_encode($debugPriv, JSON_PRETTY_PRINT), ENT_QUOTES, 'UTF-8'); ?></pre>
    </div>
<?php } ?>

<div class="row g-3 mb-3">
    <div class="col-lg-6">
        <?php if ($platformRoleConflict): ?>
            <div class="alert alert-warning d-flex align-items-start gap-2">
                <i class="bi bi-exclamation-triangle-fill mt-1"></i>
                <div>
                    <div class="fw-semibold">Multiple platform roles detected</div>
                    <div class="small mb-2">Select a single Non-SaaS role to keep and remove the rest.</div>
                    <?php if ($canManage): ?>
                        <a class="btn btn-sm btn-outline-primary" href="<?php echo $radAdminUrl; ?>/user/managePlatformRole/<?php echo $uid; ?>">Resolve Roles</a>
                    <?php endif; ?>
                </div>
            </div>
        <?php elseif ($adminMfaMissingContact): ?>
            <div class="alert alert-danger d-flex align-items-start gap-2">
                <i class="bi bi-shield-exclamation mt-1"></i>
                <div>
                    <div class="fw-semibold">Admin MFA needs contact info</div>
                    <div class="small mb-2">Administrator login requires an email or mobile number to deliver MFA codes.</div>
                    <?php if ($canManage): ?>
                        <a class="btn btn-sm btn-outline-danger" href="<?php echo $radAdminUrl; ?>/user/edit/<?php echo $uid; ?>">Add contact</a>
                    <?php endif; ?>
                </div>
            </div>
        <?php elseif (!empty($nonSaasRoleIssues)): ?>
            <div class="alert alert-warning d-flex align-items-start gap-2">
                <i class="bi bi-exclamation-triangle-fill mt-1"></i>
                <div>
                    <div class="fw-semibold">Platform role needs attention</div>
                    <div class="small mb-2"><?php echo htmlspecialchars(implode('. ', $nonSaasRoleIssues)); ?></div>
                    <?php if ($canManage): ?>
                        <a class="btn btn-sm btn-outline-primary" href="<?php echo $radAdminUrl; ?>/user/managePlatformRole/<?php echo $uid; ?>">Fix role</a>
                    <?php endif; ?>
                </div>
            </div>
        <?php elseif (!$nonSaasRole): ?>
            <div class="alert alert-warning d-flex align-items-start gap-2">
                <i class="bi bi-exclamation-triangle-fill mt-1"></i>
                <div>
                    <div class="fw-semibold">Platform role not set</div>
                    <div class="small mb-2">Assign a platform role to enable non-SaaS access.</div>
                    <?php if ($canManage): ?>
                        <a class="btn btn-sm btn-outline-primary" href="<?php echo $radAdminUrl; ?>/user/managePlatformRole/<?php echo $uid; ?>">Add Non-SaaS Role</a>
                    <?php endif; ?>
                </div>
            </div>
        <?php else: ?>
            <div class="card shadow-sm mb-0">
                <div class="card-body d-flex align-items-center gap-3">
                    <i class="bi bi-shield-lock text-primary fs-3"></i>
                    <div>
                        <div class="text-muted text-uppercase small">Platform Role</div>
                        <div class="fw-semibold"><?php echo htmlspecialchars($nonSaasRole['name'] ?? 'Role'); ?></div>
                        <div class="text-muted small">ID <?php echo (int)($nonSaasRole['id'] ?? 0); ?></div>
                        <?php if ($nonSaasRoleDetail): ?>
                            <div class="text-muted small mt-1">
                                Scope: <?php echo htmlspecialchars($nonSaasRoleDetail['scope'] ?? 'platform'); ?>
                            </div>
                            <div class="text-muted small">
                                Default route:
                                <?php if (!empty($nonSaasRoleDetail['default_route'])): ?>
                                    <?php echo htmlspecialchars($nonSaasRoleDetail['default_route']['ms_name'] ?? ''); ?>
                                    <?php if (!empty($nonSaasRoleDetail['default_route']['ms_name'])): ?> / <?php endif; ?>
                                    <?php echo htmlspecialchars($nonSaasRoleDetail['default_route']['name'] ?? ''); ?>
                                <?php else: ?>
                                    Not set
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                    <?php if ($canManage): ?>
                        <div class="ms-auto">
                            <a class="btn btn-sm btn-outline-secondary" href="<?php echo $radAdminUrl; ?>/user/managePlatformRole/<?php echo $uid; ?>"><i class="bi bi-pencil"></i> Change</a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
    <div class="col-lg-6">
        <div class="card shadow-sm h-100 workspace-summary">
            <div class="card-body d-flex flex-column gap-2">
                <div class="d-flex align-items-center gap-2">
                    <i class="bi bi-diagram-3 text-primary fs-4"></i>
                    <div>
                        <div class="text-muted text-uppercase small">Workspace Access</div>
                        <div class="fw-semibold"><?php echo (int)($detailStats['spaces'] ?? 0); ?> workspace(s)</div>
                    </div>
                </div>
                <div class="text-muted small">Total workspace roles: <?php echo $workspaceRoleCount; ?></div>
                <?php if (!empty($workspaceConflicts)): ?>
                    <div class="alert alert-warning py-2 px-2 mb-0">
                        <div class="small fw-semibold">Multiple roles in one or more workspaces</div>
                        <div class="small text-muted">Resolve to keep one role per workspace.</div>
                        <a class="btn btn-sm btn-outline-warning mt-2" href="<?php echo $radAdminUrl; ?>/user/diagnostics?only_issues=Y&issue_type=workspace_conflict&user_uid=<?php echo $uid; ?>">
                            <i class="bi bi-tools me-1"></i>Open Role Diagnostics
                        </a>
                    </div>
                <?php elseif ($roleDiscrepancy): ?>
                    <div class="alert alert-warning py-2 px-2 mb-0">
                        <div class="small fw-semibold">Workspace roles exceed workspace count</div>
                        <div class="small text-muted">Review diagnostics to keep one role per workspace.</div>
                        <a class="btn btn-sm btn-outline-warning mt-2" href="<?php echo $radAdminUrl; ?>/user/diagnostics?only_issues=Y&issue_type=workspace_conflict&user_uid=<?php echo $uid; ?>">
                            <i class="bi bi-tools me-1"></i>Open Role Diagnostics
                        </a>
                    </div>
                <?php endif; ?>
                <?php if ($canManage): ?>
                    <div class="mt-auto">
                        <a href="<?php echo $radAdminUrl; ?>/user/manageWorkspaceRole/<?php echo $uid; ?>" class="btn btn-outline-primary btn-sm">
                            <i class="bi bi-diagram-3 me-1"></i>Manage Workspace Roles
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<div class="row g-3 mb-3">
    <div class="col-md-3">
        <div class="card h-100 shadow-sm user-detail-card">
            <div class="card-body">
                <div class="text-muted text-uppercase small">Spaces</div>
                <div class="display-6 fw-semibold"><?php echo (int)($detailStats['spaces'] ?? 0); ?></div>
                <div class="text-muted small">Active workspace assignments</div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card h-100 shadow-sm user-detail-card">
            <div class="card-body">
                <div class="text-muted text-uppercase small">Roles</div>
                <div class="display-6 fw-semibold"><?php echo (int)($detailStats['roles'] ?? 0); ?></div>
                <div class="text-muted small">Total mapped roles</div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card h-100 shadow-sm user-detail-card">
            <div class="card-body">
                <div class="text-muted text-uppercase small">Agreement</div>
                <span class="badge bg-<?php echo ($detailStats['agreement_slug'] ?? '') === 'signed' ? 'success' : 'warning'; ?>">
                    <?php echo htmlspecialchars($detailStats['agreement_label'] ?? 'Pending'); ?>
                </span>
                <div class="text-muted small mt-2">Platform agreement status</div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card h-100 shadow-sm user-detail-card">
            <div class="card-body">
                <div class="text-muted text-uppercase small">MFA</div>
                <span class="badge bg-<?php echo ($detailStats['mfa_slug'] ?? '') === 'enabled' ? 'success' : 'secondary'; ?>">
                    <?php echo htmlspecialchars($detailStats['mfa_label'] ?? 'Disabled'); ?>
                </span>
                <div class="text-muted small mt-2">Multi-factor enforcement</div>
            </div>
        </div>
    </div>
</div>

<div class="row g-3 mb-3">
    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-header bg-white">
                <h3 class="h6 mb-0">Profile & Contact</h3>
            </div>
            <div class="card-body">
                <dl class="row mb-0">
                    <dt class="col-sm-4 text-muted">Full Name</dt>
                    <dd class="col-sm-8"><?php echo htmlspecialchars($profile['name'] ?? '—'); ?></dd>

                    <dt class="col-sm-4 text-muted">Email</dt>
                    <dd class="col-sm-8"><?php echo htmlspecialchars($profile['email'] ?? '—'); ?></dd>

                    <dt class="col-sm-4 text-muted">Mobile</dt>
                    <dd class="col-sm-8"><?php echo htmlspecialchars($profile['mobile'] ?? '—'); ?></dd>

                    <dt class="col-sm-4 text-muted">Login Mode</dt>
                    <dd class="col-sm-8">
                        <span class="badge bg-light text-dark">
                            <?php echo htmlspecialchars($profile['login_mode_label'] ?? 'Human'); ?>
                        </span>
                    </dd>

                    <dt class="col-sm-4 text-muted">IP Restriction</dt>
                    <dd class="col-sm-8"><?php echo htmlspecialchars($profile['access_ips'] ?: 'Not set'); ?></dd>
                </dl>
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-header bg-white">
                <h3 class="h6 mb-0">Security & Activity</h3>
            </div>
            <div class="card-body">
                <ul class="list-group list-group-flush">
                    <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                        <div>
                            <div class="fw-semibold">Created</div>
                            <div class="text-muted small">by <?php echo htmlspecialchars($activity['created']['actor'] ?? 'System'); ?></div>
                        </div>
                        <div class="text-muted"><?php echo htmlspecialchars($activity['created']['timestamp'] ?? '—'); ?></div>
                    </li>
                    <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                        <div>
                            <div class="fw-semibold">Last Updated</div>
                            <div class="text-muted small">by <?php echo htmlspecialchars($activity['updated']['actor'] ?? 'System'); ?></div>
                        </div>
                        <div class="text-muted"><?php echo htmlspecialchars($activity['updated']['timestamp'] ?? '—'); ?></div>
                    </li>
                    <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                        <div>
                            <div class="fw-semibold">Last Login</div>
                            <div class="text-muted small">Latest session created</div>
                        </div>
                        <div class="text-muted"><?php echo htmlspecialchars($activity['last_login']['timestamp'] ?? '—'); ?></div>
                    </li>
                </ul>
            </div>
        </div>
    </div>
</div>

<div class="card mb-3">
    <div class="card-header bg-white d-flex justify-content-between align-items-center">
        <h3 class="h6 mb-0">Workspace Access</h3>
        <?php if ($canManage): ?>
            <a class="btn btn-sm btn-outline-secondary" href="<?php echo $radAdminUrl; ?>/user/manageWorkspaceRole/<?php echo $uid; ?>">
                <i class="bi bi-pencil"></i> Manage
            </a>
        <?php endif; ?>
    </div>
    <div class="card-body">
        <?php if (!empty($spaces)): ?>
            <div class="table-responsive">
                <table class="table align-middle">
                    <thead>
                        <tr>
                            <th>Workspace</th>
                            <th>Roles</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($spaces as $space): ?>
                            <tr>
                                <td>
                                    <div class="fw-semibold"><?php echo htmlspecialchars($space['name'] ?? 'Space'); ?></div>
                                    <div class="text-muted small">ID: <?php echo (int)($space['id'] ?? 0); ?></div>
                                </td>
                                <td>
                                    <?php if (!empty($space['roles'])): ?>
                                        <?php foreach ($space['roles'] as $role): ?>
                                            <span class="badge bg-light text-dark me-1 mb-1" title="Scope: <?php echo htmlspecialchars($role['scope_level'] ?? 'workspace'); ?>">
                                                <?php echo htmlspecialchars($role['name'] ?? 'Role'); ?>
                                                <?php if (!empty($role['ms_name'])): ?>
                                                    <span class="text-muted">(MS: <?php echo htmlspecialchars($role['ms_name']); ?>)</span>
                                                <?php endif; ?>
                                            </span>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <span class="text-muted">No roles assigned</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="text-muted">No workspace roles assigned yet. Use Manage Workspace Roles to link this user to a workspace.</div>
        <?php endif; ?>
    </div>
</div>

<div class="toast align-items-center text-bg-success border-0" role="alert" aria-live="assertive" aria-atomic="true" id="user-detail-toast">
    <div class="d-flex">
        <div class="toast-body">
            UID copied to clipboard.
        </div>
        <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
    </div>
</div>
