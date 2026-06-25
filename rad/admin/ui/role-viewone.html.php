<?php
$profile = $this->runData['data']['role_profile'] ?? [];
$assignments = $this->runData['data']['role_assignments'] ?? [];
$detailStats = $this->runData['data']['role_detail_stats'] ?? [];
$activity = $this->runData['data']['role_activity'] ?? [];
$diagnostics = $this->runData['data']['role_diagnostics'] ?? [];
$charts = $this->runData['data']['role_charts'] ?? ['scope' => ['labels' => [], 'values' => []], 'spaces' => ['labels' => [], 'values' => []]];
$assignmentPagination = $this->runData['data']['role_assignment_pagination'] ?? ['page' => 1, 'per_page' => 25, 'total' => 0, 'total_pages' => 1];
$assignmentTotal = (int)($assignmentPagination['total'] ?? 0);
$assignmentPage = (int)($assignmentPagination['page'] ?? 1);
$assignmentPages = max(1, (int)($assignmentPagination['total_pages'] ?? 1));
$assignmentPerPage = (int)($assignmentPagination['per_page'] ?? 25);
$radAdminUrl = $this->runData['route']['rad_admin_url'];
$uid = htmlspecialchars($profile['uid'] ?? '');
$roleId = (int)($profile['id'] ?? 0);
$archiveContext = $this->runData['data']['role_archive'] ?? ['assignments' => [], 'assignments_total' => 0, 'replacement_roles' => [], 'can_archive' => false];
$canArchive = !empty($archiveContext['can_archive']);
$archiveAssignments = $archiveContext['assignments'] ?? [];
$archiveAssignmentTotal = (int)($archiveContext['assignments_total'] ?? 0);
$replacementRoles = $archiveContext['replacement_roles'] ?? [];
$statusMeta = $profile['status_meta'] ?? ['label' => 'Status', 'badge' => 'secondary'];
$scopeMeta = $profile['scope_meta'] ?? ['label' => 'Scope', 'badge' => 'secondary'];
$baseAssignmentsUrl = $radAdminUrl . '/role/viewone/' . $uid;
?>

<div class="card mb-3 shadow-sm">
    <div class="card-body d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center gap-3">
        <div>
            <h2 class="h4 mb-1"><?php echo htmlspecialchars($profile['name'] ?? 'Role'); ?></h2>
            <div class="text-muted small">UID:
                <span class="role-uid"><?php echo $uid; ?></span>
                <button class="btn btn-link btn-sm p-0 ms-1 copy-uid" data-uid="<?php echo $uid; ?>">
                    <i class="bi bi-clipboard"></i> Copy
                </button>
            </div>
            <?php if ($roleId > 0): ?>
                <div class="text-muted small">ID: <?php echo $roleId; ?></div>
            <?php endif; ?>
            <?php if (!empty($profile['code'])): ?>
                <div class="text-muted small">Code: <?php echo htmlspecialchars($profile['code']); ?></div>
            <?php endif; ?>
        </div>
        <div class="d-flex flex-wrap gap-2 align-items-center">
            <span class="badge bg-<?php echo htmlspecialchars($statusMeta['badge']); ?>">
                <?php echo htmlspecialchars($statusMeta['label']); ?>
            </span>
            <span class="badge bg-<?php echo htmlspecialchars($profile['saas_badge'] ?? 'secondary'); ?>">
                <?php echo htmlspecialchars($profile['saas_label'] ?? 'Role'); ?>
            </span>
            <span class="badge bg-<?php echo htmlspecialchars($scopeMeta['badge']); ?>">
                <?php echo htmlspecialchars($scopeMeta['label']); ?>
            </span>
        </div>
        <div class="btn-group">
            <a href="<?php echo $radAdminUrl; ?>/role/sniff/<?php echo $uid; ?>" class="btn btn-outline-secondary">
                <i class="bi bi-clipboard-data me-1"></i>Meta Sniff
            </a>
            <a href="<?php echo $radAdminUrl; ?>/role/edit/<?php echo $uid; ?>" class="btn btn-primary">
                <i class="bi bi-pencil-square me-1"></i>Edit Role
            </a>
            <a href="<?php echo $radAdminUrl; ?>/user/view" class="btn btn-outline-secondary">
                <i class="bi bi-people me-1"></i>Users
            </a>
            <?php if ($canArchive): ?>
                <button type="button" class="btn btn-outline-danger" data-bs-toggle="modal" data-bs-target="#archiveRoleModal">
                    <i class="bi bi-archive me-1"></i>Archive Role
                </button>
            <?php endif; ?>
        </div>
    </div>
</div>

<div class="row g-3 mb-3">
    <div class="col-md-4">
        <div class="card h-100 shadow-sm role-detail-card">
            <div class="card-body">
                <div class="text-muted text-uppercase small">Assignments</div>
                <div class="display-6 fw-semibold"><?php echo (int)($detailStats['assignments'] ?? 0); ?></div>
                <div class="text-muted small">Users linked to this role</div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card h-100 shadow-sm role-detail-card">
            <div class="card-body">
                <div class="text-muted text-uppercase small">Spaces</div>
                <div class="display-6 fw-semibold text-primary"><?php echo (int)($detailStats['spaces'] ?? 0); ?></div>
                <div class="text-muted small">Distinct workspaces</div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card h-100 shadow-sm role-detail-card">
            <div class="card-body">
                <div class="text-muted text-uppercase small">Default Route</div>
                <span class="badge bg-<?php echo ($detailStats['has_route'] ?? 'no') === 'yes' ? 'success' : 'warning'; ?>">
                    <?php echo ($detailStats['has_route'] ?? 'no') === 'yes' ? 'Configured' : 'Not Set'; ?>
                </span>
                <div class="text-muted small mt-2">Microservicelet fallback</div>
            </div>
        </div>
    </div>
</div>

<div class="card mb-3">
    <div class="card-header bg-white">
        <h3 class="h6 mb-0">Role Diagnostics</h3>
    </div>
    <div class="card-body">
        <?php if (empty($diagnostics)) { ?>
            <div class="text-muted">No issues detected for this role.</div>
        <?php } else { ?>
            <div class="d-flex flex-wrap gap-2 mb-2">
                <?php if (in_array('missing_default_route', $diagnostics, true)) { ?>
                    <span class="badge bg-warning text-dark">Default route missing</span>
                <?php } ?>
                <?php if (in_array('invalid_scope', $diagnostics, true)) { ?>
                    <span class="badge bg-danger">Invalid scope value</span>
                <?php } ?>
                <?php if (in_array('unused', $diagnostics, true)) { ?>
                    <span class="badge bg-light text-dark">Unused role</span>
                <?php } ?>
            </div>
            <div class="d-flex flex-wrap gap-2">
                <a class="btn btn-sm btn-outline-primary" href="<?php echo $radAdminUrl; ?>/role/edit/<?php echo $uid; ?>">
                    <i class="bi bi-pencil-square me-1"></i>Edit Role
                </a>
                <a class="btn btn-sm btn-outline-secondary" href="<?php echo $radAdminUrl; ?>/role/view">
                    <i class="bi bi-list-ul me-1"></i>Back to Roles
                </a>
            </div>
        <?php } ?>
    </div>
</div>

<?php if ($canArchive): ?>
<div class="modal fade" id="archiveRoleModal" tabindex="-1" aria-labelledby="archiveRoleModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <form method="post" action="<?php echo $radAdminUrl; ?>/role/archive/<?php echo $uid; ?>">
                <div class="modal-header">
                    <h5 class="modal-title" id="archiveRoleModalLabel">Archive Role</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p class="mb-2">
                        This will archive <strong><?php echo htmlspecialchars($profile['name'] ?? 'Role'); ?></strong>.
                        <?php if ($archiveAssignmentTotal > 0): ?>
                            <?php echo $archiveAssignmentTotal; ?> user assignment(s) must be reassigned first.
                        <?php else: ?>
                            No users are currently assigned to this role.
                        <?php endif; ?>
                    </p>

                    <?php if ($archiveAssignmentTotal > 0): ?>
                        <?php if (empty($replacementRoles)): ?>
                            <div class="alert alert-warning">
                                No replacement role found for this scope. Create a role with the same scope to continue.
                            </div>
                        <?php endif; ?>
                        <div class="mb-3">
                            <label class="form-label">Replacement role (same scope)</label>
                            <select name="replacement_role_id" class="form-select" <?php echo empty($replacementRoles) ? 'disabled' : 'required'; ?>>
                                <option value="">Select a replacement role</option>
                                <?php foreach ($replacementRoles as $r): ?>
                                    <option value="<?php echo (int)($r['id'] ?? 0); ?>">
                                        <?php echo htmlspecialchars(($r['s_role_name'] ?? 'Role') . ' · ID: ' . ($r['id'] ?? '')); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <div class="form-text">All users assigned to this role will be moved to the selected role.</div>
                        </div>

                        <div class="table-responsive border rounded">
                            <table class="table table-sm mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>User</th>
                                        <th>Username</th>
                                        <th>Workspace</th>
                                        <th>MS</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($archiveAssignments as $row): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($row['user_name'] ?? ''); ?> (ID: <?php echo (int)($row['user_id'] ?? 0); ?>)</td>
                                            <td><?php echo htmlspecialchars($row['username'] ?? ($row['email'] ?? '')); ?></td>
                                            <td><?php echo htmlspecialchars($row['space_name'] ?? ''); ?></td>
                                            <td><?php echo htmlspecialchars($row['ms_name'] ?? ''); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-warning mb-0">
                            Archiving this role will make it unavailable for assignment.
                        </div>
                    <?php endif; ?>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger" <?php echo ($archiveAssignmentTotal > 0 && empty($replacementRoles)) ? 'disabled' : ''; ?>>Archive Role</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<div class="row g-3 mb-3">
    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-header bg-white">
                <h3 class="h6 mb-0">Role Summary</h3>
            </div>
            <div class="card-body">
                <dl class="row mb-0">
                    <dt class="col-sm-4 text-muted">Scope</dt>
                    <dd class="col-sm-8">
                        <span class="badge bg-<?php echo htmlspecialchars($scopeMeta['badge']); ?>">
                            <?php echo htmlspecialchars($scopeMeta['label']); ?>
                        </span>
                    </dd>

                    <dt class="col-sm-4 text-muted">SaaS Availability</dt>
                    <dd class="col-sm-8">
                        <span class="badge bg-<?php echo htmlspecialchars($profile['saas_badge'] ?? 'secondary'); ?>">
                            <?php echo htmlspecialchars($profile['saas_label'] ?? 'Unknown'); ?>
                        </span>
                    </dd>

                    <dt class="col-sm-4 text-muted">Default Route</dt>
                    <dd class="col-sm-8">
                        <?php if (!empty($profile['default_route']['uid'])): ?>
                            <a href="<?php echo $radAdminUrl; ?>/route/detail/<?php echo htmlspecialchars($profile['default_route']['uid']); ?>">
                                <?php echo htmlspecialchars(($profile['default_route']['ms_name'] ? $profile['default_route']['ms_name'] . ' · ' : '') . $profile['default_route']['name']); ?>
                            </a>
                        <?php else: ?>
                            <span class="text-muted">Not configured</span>
                        <?php endif; ?>
                    </dd>

                    <dt class="col-sm-4 text-muted">Assignments by Scope</dt>
                    <dd class="col-sm-8">
                        <span class="badge bg-secondary me-1">Workspace: <?php echo (int)($detailStats['workspace_assignments'] ?? 0); ?></span>
                        <span class="badge bg-info text-dark">MS: <?php echo (int)($detailStats['ms_assignments'] ?? 0); ?></span>
                    </dd>

                    <dt class="col-sm-4 text-muted">Created</dt>
                    <dd class="col-sm-8">
                        <?php echo htmlspecialchars($activity['created']['timestamp'] ?? '—'); ?>
                        <span class="text-muted small">by <?php echo htmlspecialchars($activity['created']['actor'] ?? 'System'); ?></span>
                    </dd>

                    <dt class="col-sm-4 text-muted">Updated</dt>
                    <dd class="col-sm-8">
                        <?php echo htmlspecialchars($activity['updated']['timestamp'] ?? '—'); ?>
                        <span class="text-muted small">by <?php echo htmlspecialchars($activity['updated']['actor'] ?? 'System'); ?></span>
                    </dd>
                </dl>
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-header bg-white">
                <h3 class="h6 mb-0">Description</h3>
            </div>
            <div class="card-body">
                <?php if (!empty($profile['description'])): ?>
                    <p class="mb-0"><?php echo nl2br(htmlspecialchars($profile['description'])); ?></p>
                <?php else: ?>
                    <p class="text-muted mb-0">No description provided.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<div class="card mb-3">
    <div class="card-header bg-white d-flex justify-content-between align-items-center">
        <div>
            <h3 class="h6 mb-0">User Assignments</h3>
            <div class="text-muted small">Total <?php echo $assignmentTotal; ?> record(s)</div>
        </div>
        <form class="d-flex align-items-center gap-2" method="get" action="<?php echo $baseAssignmentsUrl; ?>">
            <input type="hidden" name="assignments_page" value="1">
            <label class="small text-muted">Per page</label>
            <select name="assignments_per_page" class="form-select form-select-sm" onchange="this.form.submit()">
                <?php foreach ([10, 25, 50, 100, 200] as $option) { ?>
                    <option value="<?php echo $option; ?>" <?php echo $assignmentPerPage === $option ? 'selected' : ''; ?>>
                        <?php echo $option; ?>
                    </option>
                <?php } ?>
            </select>
        </form>
    </div>
    <div class="card-body">
        <?php if (empty($assignments)): ?>
            <div class="text-muted">No users currently assigned to this role.</div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table align-middle">
                    <thead>
                        <tr>
                            <th>Workspace</th>
                            <th>Scope</th>
                            <th>User</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($assignments as $assignment): ?>
                            <tr>
                                <td>
                                    <?php if (!empty($assignment['space_uid'])): ?>
                                        <span class="fw-semibold"><?php echo htmlspecialchars($assignment['space_name']); ?></span>
                                    <?php else: ?>
                                        <span class="text-muted">Global</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php
                                        $scopeLevel = strtolower((string)($assignment['scope_level'] ?? 'workspace'));
                                        $scopeLabel = $scopeLevel === 'ms' ? 'MS' : 'Workspace';
                                        $scopeBadge = $scopeLevel === 'ms' ? 'info' : 'secondary';
                                    ?>
                                    <span class="badge bg-<?php echo $scopeBadge; ?>"><?php echo $scopeLabel; ?></span>
                                    <?php if ($scopeLevel === 'ms' && !empty($assignment['ms_name'])) { ?>
                                        <div class="text-muted small"><?php echo htmlspecialchars($assignment['ms_name']); ?></div>
                                    <?php } ?>
                                </td>
                                <td>
                                    <?php echo htmlspecialchars($assignment['user_name']); ?>
                                    <div class="text-muted small">@<?php echo htmlspecialchars($assignment['username']); ?></div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div class="d-flex justify-content-between align-items-center mt-3">
                <div class="text-muted small">Page <?php echo $assignmentPage; ?> of <?php echo $assignmentPages; ?></div>
                <nav aria-label="Assignments pagination">
                    <ul class="pagination pagination-sm mb-0">
                        <?php
                        $prevPage = max(1, $assignmentPage - 1);
                        $nextPage = min($assignmentPages, $assignmentPage + 1);
                        $prevUrl = $baseAssignmentsUrl . '?assignments_page=' . $prevPage . '&assignments_per_page=' . $assignmentPerPage;
                        $nextUrl = $baseAssignmentsUrl . '?assignments_page=' . $nextPage . '&assignments_per_page=' . $assignmentPerPage;
                        ?>
                        <li class="page-item <?php echo $assignmentPage <= 1 ? 'disabled' : ''; ?>">
                            <a class="page-link" href="<?php echo $prevUrl; ?>">Previous</a>
                        </li>
                        <li class="page-item active"><span class="page-link"><?php echo $assignmentPage; ?></span></li>
                        <li class="page-item <?php echo $assignmentPage >= $assignmentPages ? 'disabled' : ''; ?>">
                            <a class="page-link" href="<?php echo $nextUrl; ?>">Next</a>
                        </li>
                    </ul>
                </nav>
            </div>
        <?php endif; ?>
    </div>
</div>

<div class="card mb-3">
    <div class="card-header bg-white d-flex justify-content-between align-items-center">
        <h3 class="h6 mb-0">Assignment Charts</h3>
        <span class="text-muted small">Scope and workspace distribution</span>
    </div>
    <div class="card-body">
        <div class="row g-3">
            <div class="col-lg-6">
                <div class="border rounded p-3 h-100">
                    <div class="small text-muted mb-2">Assignments by scope</div>
                    <canvas id="role-chart-scope" height="180"></canvas>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="border rounded p-3 h-100">
                    <div class="small text-muted mb-2">Top workspaces</div>
                    <canvas id="role-chart-spaces" height="180"></canvas>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="toast align-items-center text-bg-success border-0" role="alert" aria-live="assertive" aria-atomic="true" id="role-detail-toast">
    <div class="d-flex">
        <div class="toast-body">
            UID copied to clipboard.
        </div>
        <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
    </div>
</div>

<script>
(function() {
    const scopeChart = document.getElementById('role-chart-scope');
    const spaceChart = document.getElementById('role-chart-spaces');
    const scopeData = <?php echo json_encode($charts['scope'] ?? ['labels' => [], 'values' => []]); ?>;
    const spaceData = <?php echo json_encode($charts['spaces'] ?? ['labels' => [], 'values' => []]); ?>;

    if (scopeChart && scopeData.labels && scopeData.labels.length) {
        window.RadAdminCharts.render(scopeChart, {
            type: 'bar',
            data: {
                labels: scopeData.labels,
                datasets: [{
                    label: 'Assignments',
                    data: scopeData.values || [],
                    backgroundColor: ['#0d6efd', '#17a2b8']
                }]
            },
            options: {
                responsive: true,
                plugins: { legend: { display: false } },
                scales: { y: { beginAtZero: true } }
            }
        });
    }

    if (spaceChart && spaceData.labels && spaceData.labels.length) {
        window.RadAdminCharts.render(spaceChart, {
            type: 'bar',
            data: {
                labels: spaceData.labels,
                datasets: [{
                    label: 'Assignments',
                    data: spaceData.values || [],
                    backgroundColor: '#6c757d'
                }]
            },
            options: {
                responsive: true,
                plugins: { legend: { display: false } },
                scales: { y: { beginAtZero: true } }
            }
        });
    }
})();
</script>
