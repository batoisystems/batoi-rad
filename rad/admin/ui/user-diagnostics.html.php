<?php
$diag = $this->runData['data']['diagnostics'] ?? ['total_users' => 0, 'issues' => [], 'roles' => []];
$issues = $diag['issues'] ?? [];
$roles = $diag['roles'] ?? [];
$radAdminUrl = $this->runData['route']['rad_admin_url'];
$pagination = $diag['pagination'] ?? ['page' => 1, 'per_page' => 25, 'total' => 0, 'total_pages' => 1];
$currentPage = (int)($pagination['page'] ?? 1);
$perPage = (int)($pagination['per_page'] ?? 25);
$totalUsers = (int)($pagination['total'] ?? 0);
$totalPages = max(1, (int)($pagination['total_pages'] ?? 1));
$filters = $diag['filters'] ?? ['only_issues' => 'Y', 'issue_type' => '', 'user_uid' => '', 'dry_run' => 'N'];
$onlyIssues = ($filters['only_issues'] ?? 'Y') === 'Y';
$issueType = $filters['issue_type'] ?? '';
$filterUid = $filters['user_uid'] ?? '';
$dryRun = ($filters['dry_run'] ?? 'N') === 'Y';
$issueCount = 0;
foreach ($issues as $rowCount) {
    if (!empty($rowCount['issues'])) {
        $issueCount++;
    }
}
$normalizeReport = $_SESSION['normalize_report'] ?? null;
if ($normalizeReport) {
    unset($_SESSION['normalize_report']);
}
$queryBase = [
    'per_page' => $perPage,
    'only_issues' => $onlyIssues ? 'Y' : 'N',
    'issue_type' => $issueType,
    'user_uid' => $filterUid,
    'dry_run' => $dryRun ? 'Y' : 'N',
];
$prevQuery = http_build_query(array_merge($queryBase, ['page' => max(1, $currentPage - 1)]));
$nextQuery = http_build_query(array_merge($queryBase, ['page' => min($totalPages, $currentPage + 1)]));
?>

<div class="card mb-3">
    <div class="card-body d-flex flex-wrap justify-content-between align-items-center gap-3">
        <div>
            <h5 class="mb-1">Role Diagnostics</h5>
            <div class="text-muted small">
                Scanned <?php echo (int)$diag['total_users']; ?> users; found <?php echo $issueCount; ?> user(s) needing attention on this page.
            </div>
        </div>
        <form class="d-flex flex-wrap align-items-center gap-2" method="get" action="<?php echo $radAdminUrl; ?>/user/diagnostics">
            <label class="small text-muted">Per page</label>
            <select class="form-select form-select-sm w-auto" name="per_page">
                <?php foreach ([25, 50, 100, 200] as $size) { ?>
                    <option value="<?php echo $size; ?>" <?php echo $perPage === $size ? 'selected' : ''; ?>><?php echo $size; ?></option>
                <?php } ?>
            </select>
            <label class="small text-muted">Show</label>
            <select class="form-select form-select-sm w-auto" name="only_issues">
                <option value="Y" <?php echo $onlyIssues ? 'selected' : ''; ?>>Only users with issues</option>
                <option value="N" <?php echo !$onlyIssues ? 'selected' : ''; ?>>All users</option>
            </select>
            <label class="small text-muted">Issue type</label>
            <select class="form-select form-select-sm w-auto" name="issue_type">
                <option value="" <?php echo $issueType === '' ? 'selected' : ''; ?>>All issues</option>
                <option value="workspace_conflict" <?php echo $issueType === 'workspace_conflict' ? 'selected' : ''; ?>>Workspace role conflicts</option>
                <option value="ms_conflict" <?php echo $issueType === 'ms_conflict' ? 'selected' : ''; ?>>Microservice role conflicts</option>
            </select>
            <label class="small text-muted">User UID</label>
            <input type="text" class="form-control form-control-sm w-auto" name="user_uid" value="<?php echo htmlspecialchars($filterUid); ?>" placeholder="Optional">
            <label class="small text-muted">Mode</label>
            <select class="form-select form-select-sm w-auto" name="dry_run">
                <option value="N" <?php echo !$dryRun ? 'selected' : ''; ?>>Apply</option>
                <option value="Y" <?php echo $dryRun ? 'selected' : ''; ?>>Dry run</option>
            </select>
            <button type="submit" class="btn btn-outline-primary btn-sm">Apply</button>
            <a href="<?php echo $radAdminUrl; ?>/user/view" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-arrow-left-circle me-1"></i>Back to Users
            </a>
        </form>
        <form class="d-flex align-items-center gap-2" method="post" action="<?php echo $radAdminUrl; ?>/user/normalizeAll">
            <?php if ($dryRun) { ?>
                <input type="hidden" name="dry_run" value="Y">
            <?php } ?>
            <button type="submit" name="export" value="csv" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-download me-1"></i>CSV
            </button>
            <button type="submit" name="export" value="json" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-code-slash me-1"></i>JSON
            </button>
        </form>
    </div>
</div>

<?php if (empty($issues)) { ?>
    <div class="alert alert-success">
        <i class="bi bi-check-circle me-1"></i> No role conflicts detected.
    </div>
<?php } elseif (!empty($normalizeReport)) { ?>
    <div class="card mb-3">
        <div class="card-header bg-white">
            <h3 class="h6 mb-0">Normalization Report</h3>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-sm align-middle">
                    <thead>
                        <tr>
                            <th>User ID</th>
                            <th>Kept</th>
                            <th>Removed</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($normalizeReport as $entry) {
                            $removed = $entry['removed'] ?? [];
                            $kept = $entry['kept'] ?? [];
                            if (empty($removed)) {
                                continue;
                            }
                            ?>
                            <tr>
                                <td><?php echo (int)($entry['user_id'] ?? 0); ?></td>
                                <td>
                                    <?php foreach ($kept as $row) {
                                        $spaceId = (int)($row['space_id'] ?? 0);
                                        $roleId = (int)($row['s_role_id'] ?? 0);
                                        $spaceLabel = $row['space_name'] ?? ('Space ' . $spaceId);
                                        $roleLabel = $row['role_name'] ?? ('Role ' . $roleId);
                                        ?>
                                        <span class="badge bg-light text-dark me-1">
                                            <a class="text-decoration-none" href="<?php echo $radAdminUrl; ?>/space/viewone/<?php echo $spaceId; ?>">
                                                <?php echo htmlspecialchars($spaceLabel); ?>
                                            </a>
                                            ·
                                            <a class="text-decoration-none" href="<?php echo $radAdminUrl; ?>/role/viewone/<?php echo $roleId; ?>">
                                                <?php echo htmlspecialchars($roleLabel); ?>
                                            </a>
                                        </span>
                                    <?php } ?>
                                </td>
                                <td>
                                    <?php foreach ($removed as $row) {
                                        $spaceId = (int)($row['space_id'] ?? 0);
                                        $roleId = (int)($row['s_role_id'] ?? 0);
                                        $spaceLabel = $row['space_name'] ?? ('Space ' . $spaceId);
                                        $roleLabel = $row['role_name'] ?? ('Role ' . $roleId);
                                        ?>
                                        <span class="badge bg-warning text-dark me-1">
                                            <a class="text-decoration-none" href="<?php echo $radAdminUrl; ?>/space/viewone/<?php echo $spaceId; ?>">
                                                <?php echo htmlspecialchars($spaceLabel); ?>
                                            </a>
                                            ·
                                            <a class="text-decoration-none" href="<?php echo $radAdminUrl; ?>/role/viewone/<?php echo $roleId; ?>">
                                                <?php echo htmlspecialchars($roleLabel); ?>
                                            </a>
                                        </span>
                                    <?php } ?>
                                </td>
                            </tr>
                        <?php } ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
<?php } else { ?>
    <?php foreach ($issues as $row) {
        $user = $row['user'];
        $auth = $row['auth'] ?? [];
        $saasConflicts = $row['saas_conflicts'] ?? [];
        $msConflicts = $row['ms_conflicts'] ?? [];
        $invalidRoles = $row['invalid_roles'] ?? [];
        $invalidWorkspaceRoles = $row['invalid_workspace_roles'] ?? [];
        $invalidMsRoles = $row['invalid_ms_roles'] ?? [];
        $currentNonSaas = (int)($auth['role_id'] ?? 0);
        ?>
        <div class="card mb-3">
            <div class="card-body">
                <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-2">
                    <div>
                        <h6 class="mb-1"><?php echo htmlspecialchars($user['s_name'] ?? 'User'); ?></h6>
                        <div class="text-muted small">ID: <?php echo (int)$user['id']; ?> · UID: <?php echo htmlspecialchars($user['uid']); ?> · @<?php echo htmlspecialchars($user['s_identity']); ?></div>
                    </div>
                    <div class="text-muted small">
                        <?php echo empty($row['issues']) ? 'No role issues detected' : implode(' · ', array_map('htmlspecialchars', $row['issues'])); ?>
                    </div>
                </div>

                <?php if (empty($row['issues'])) { ?>
                    <div class="d-flex justify-content-end">
                        <a class="btn btn-outline-secondary btn-sm" href="<?php echo $radAdminUrl; ?>/user/viewone/<?php echo htmlspecialchars($user['uid']); ?>">
                            <i class="bi bi-eye me-1"></i>View User
                        </a>
                    </div>
                <?php } else { ?>
                    <form method="post" action="<?php echo $radAdminUrl; ?>/user/normalize" class="border rounded p-3">
                        <input type="hidden" name="user_uid" value="<?php echo htmlspecialchars($user['uid']); ?>">
                        <input type="hidden" name="only_issues" value="<?php echo $onlyIssues ? 'Y' : 'N'; ?>">
                        <input type="hidden" name="issue_type" value="<?php echo htmlspecialchars($issueType); ?>">
                        <input type="hidden" name="user_uid_filter" value="<?php echo htmlspecialchars($filterUid); ?>">
                        <input type="hidden" name="per_page" value="<?php echo (int)$perPage; ?>">
                        <input type="hidden" name="page" value="<?php echo (int)$currentPage; ?>">

                        <div class="mb-3">
                            <label class="form-label">Non-SaaS Role (keep one)</label>
                            <select name="non_saas_role" class="form-select">
                                <option value="">-- Keep current or skip --</option>
                                <?php foreach ($roles as $rid => $role) {
                                    if (($role['s_scope'] ?? 'platform') === 'workspace') { continue; }
                                    $ridInt = (int)$rid;
                                    $selected = $currentNonSaas === $ridInt ? 'selected' : '';
                                    ?>
                                    <option value="<?php echo $ridInt; ?>" <?php echo $selected; ?>>
                                        <?php echo htmlspecialchars($role['s_role_name'] ?? ('Role #' . $ridInt)); ?>
                                    </option>
                                <?php } ?>
                            </select>
                            <div class="form-text">Selecting a non-SaaS role updates platform access only; workspace assignments are preserved.</div>
                        </div>

                        <?php if (!empty($saasConflicts)) { ?>
                            <div class="mb-3">
                                <label class="form-label">SaaS Role per Workspace (keep one per space)</label>
                                <?php foreach ($saasConflicts as $spaceId => $roleIds) { ?>
                                    <div class="mb-2">
                                        <div class="text-muted small mb-1">Workspace ID: <?php echo (int)$spaceId; ?></div>
                                        <select name="saas_choice[<?php echo (int)$spaceId; ?>]" class="form-select">
                                            <?php foreach ($roleIds as $rid) {
                                                $label = $roles[$rid]['s_role_name'] ?? ('Role #' . $rid);
                                                ?>
                                                <option value="<?php echo (int)$rid; ?>"><?php echo htmlspecialchars($label); ?></option>
                                            <?php } ?>
                                        </select>
                                    </div>
                                <?php } ?>
                                <div class="form-text">Only the selected role per workspace will be kept; others will be removed.</div>
                            </div>
                        <?php } ?>

                        <?php if (!empty($msConflicts)) { ?>
                            <div class="mb-3">
                                <label class="form-label">MS Role per Microservice (keep one per microservice)</label>
                                <?php foreach ($msConflicts as $spaceId => $msRoles) { ?>
                                    <?php foreach ($msRoles as $msId => $roleIds) { ?>
                                        <div class="mb-2">
                                            <div class="text-muted small mb-1">Workspace ID: <?php echo (int)$spaceId; ?> · MS ID: <?php echo (int)$msId; ?></div>
                                            <select name="ms_choice[<?php echo (int)$spaceId; ?>][<?php echo (int)$msId; ?>]" class="form-select form-select-sm">
                                                <option value="">-- Keep latest --</option>
                                                <?php foreach ($roleIds as $rid) {
                                                    $label = $roles[$rid]['s_role_name'] ?? ('Role #' . $rid);
                                                    ?>
                                                    <option value="<?php echo (int)$rid; ?>"><?php echo htmlspecialchars($label); ?></option>
                                                <?php } ?>
                                            </select>
                                        </div>
                                    <?php } ?>
                                <?php } ?>
                                <div class="form-text">Only the selected role per microservice will be kept; others will be removed.</div>
                            </div>
                        <?php } ?>

                        <?php if (!empty($invalidWorkspaceRoles)) { ?>
                            <div class="alert alert-warning">
                                <i class="bi bi-exclamation-triangle me-1"></i> Invalid workspace roles detected:
                                <?php foreach ($invalidWorkspaceRoles as $bad) { ?>
                                    <span class="badge bg-light text-dark me-1">Space <?php echo (int)$bad['space_id']; ?> · Role <?php echo (int)$bad['role_id']; ?></span>
                                <?php } ?>
                            </div>
                        <?php } ?>

                        <?php if (!empty($invalidMsRoles)) { ?>
                            <div class="alert alert-warning">
                                <i class="bi bi-exclamation-triangle me-1"></i> Invalid microservice roles detected:
                                <?php foreach ($invalidMsRoles as $bad) { ?>
                                    <span class="badge bg-light text-dark me-1">Space <?php echo (int)$bad['space_id']; ?> · MS <?php echo (int)$bad['ms_id']; ?> · Role <?php echo (int)$bad['role_id']; ?></span>
                                <?php } ?>
                            </div>
                        <?php } ?>

                        <?php if (!empty($invalidRoles)) { ?>
                            <div class="alert alert-warning">
                                <i class="bi bi-exclamation-triangle me-1"></i> Invalid role references detected (IDs: <?php echo implode(', ', array_map('intval', $invalidRoles)); ?>). They will be removed by normalization.
                            </div>
                        <?php } ?>

                        <?php if (!empty($saasConflicts) || !empty($msConflicts)) { ?>
                            <div class="d-flex flex-wrap justify-content-end gap-2">
                                <?php if (!empty($saasConflicts)) { ?>
                                    <button type="submit" name="remove_scope" value="workspace" class="btn btn-outline-primary" onclick="return confirm('Remove extra workspace roles for this user?');">
                                        <i class="bi bi-trash3 me-1"></i>Remove Extra Workspace Roles
                                    </button>
                                <?php } ?>
                                <?php if (!empty($msConflicts)) { ?>
                                    <button type="submit" name="remove_scope" value="ms" class="btn btn-outline-primary" onclick="return confirm('Remove extra microservice roles for this user?');">
                                        <i class="bi bi-trash3 me-1"></i>Remove Extra MS Roles
                                    </button>
                                <?php } ?>
                                <button type="submit" name="remove_scope" value="all" class="btn btn-primary" onclick="return confirm('Remove extra workspace and microservice roles for this user?');">
                                    <i class="bi bi-trash3 me-1"></i>Remove Extra Memberships
                                </button>
                            </div>
                        <?php } else { ?>
                            <div class="text-muted small text-end">No conflicts to resolve for this user.</div>
                        <?php } ?>
                    </form>
                <?php } ?>
            </div>
        </div>
    <?php } ?>
<?php } ?>

<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mt-3">
    <div class="text-muted small">
        <?php
            $start = $totalUsers === 0 ? 0 : (($currentPage - 1) * $perPage + 1);
            $end = min($totalUsers, $currentPage * $perPage);
            echo $totalUsers === 0 ? 'No users to scan.' : "Showing {$start}–{$end} of {$totalUsers}";
        ?>
    </div>
    <div class="btn-group" role="group">
        <a class="btn btn-outline-secondary btn-sm<?php echo $currentPage <= 1 ? ' disabled' : ''; ?>"
           href="<?php echo $currentPage <= 1 ? '#' : ($radAdminUrl . '/user/diagnostics?' . $prevQuery); ?>"
           <?php echo $currentPage <= 1 ? ' tabindex="-1" aria-disabled="true"' : ''; ?>>
            <i class="bi bi-arrow-left"></i>
        </a>
        <a class="btn btn-outline-secondary btn-sm<?php echo $currentPage >= $totalPages ? ' disabled' : ''; ?>"
           href="<?php echo $currentPage >= $totalPages ? '#' : ($radAdminUrl . '/user/diagnostics?' . $nextQuery); ?>"
           <?php echo $currentPage >= $totalPages ? ' tabindex="-1" aria-disabled="true"' : ''; ?>>
            <i class="bi bi-arrow-right"></i>
        </a>
    </div>
</div>
