<?php
$workspaces = $this->runData['data']['workspaces'] ?? [];
$filters = $this->runData['data']['filters'] ?? ['q' => '', 'status' => '', 'missing_owner' => 0, 'missing_slug' => 0, 'no_members' => 0];
$count = count($workspaces);
$pagination = $this->runData['data']['pagination'] ?? ['page' => 1, 'per_page' => 25, 'total' => $count, 'pages' => 1, 'sort' => 'name_asc'];
$radAdminUrl = $this->runData['route']['rad_admin_url'];
$canManage = !empty($this->runData['data']['can_idm_manage']);
$stats = $this->runData['data']['workspace_stats'] ?? [
    'total' => $count,
    'active' => 0,
    'inactive' => 0,
    'archived' => 0,
    'suspended' => 0,
    'missing_owner' => 0,
    'missing_slug' => 0,
    'no_members' => 0,
];
$totalCount = (int)($pagination['total'] ?? $count);
$page = (int)($pagination['page'] ?? 1);
$pages = (int)($pagination['pages'] ?? 1);
$perPage = (int)($pagination['per_page'] ?? 25);
$offset = max(0, ($page - 1) * $perPage);
$rangeStart = $totalCount > 0 ? $offset + 1 : 0;
$rangeEnd = min($totalCount, $offset + $count);
$hasFilters = !empty(array_filter($filters)) || ($pagination['sort'] ?? '') !== 'name_asc' || $perPage !== 25;
$requestQuery = $this->runData['request']->get ?? [];
$buildUrl = function (array $overrides = [], array $removals = []) use ($radAdminUrl, $requestQuery): string {
    $query = $requestQuery;
    foreach ($removals as $key) {
        unset($query[$key]);
    }
    foreach ($overrides as $key => $value) {
        if ($value === null || $value === '') {
            unset($query[$key]);
            continue;
        }
        $query[$key] = $value;
    }
    $queryString = http_build_query($query);
    return $radAdminUrl . '/space/view' . ($queryString !== '' ? ('?' . $queryString) : '');
};
$statusCards = [
    ['label' => 'All Workspaces', 'value' => (int)($stats['total'] ?? 0), 'status' => '', 'tone' => 'primary', 'icon' => 'bi-buildings'],
    ['label' => 'Active', 'value' => (int)($stats['active'] ?? 0), 'status' => '1', 'tone' => 'success', 'icon' => 'bi-check-circle'],
    ['label' => 'Archived', 'value' => (int)($stats['archived'] ?? 0), 'status' => '2', 'tone' => 'secondary', 'icon' => 'bi-archive'],
    ['label' => 'Suspended', 'value' => (int)($stats['suspended'] ?? 0), 'status' => '3', 'tone' => 'warning', 'icon' => 'bi-pause-circle'],
];
$issueChips = [
    ['label' => 'Missing owner', 'count' => (int)($stats['missing_owner'] ?? 0), 'key' => 'missing_owner'],
    ['label' => 'Missing slug', 'count' => (int)($stats['missing_slug'] ?? 0), 'key' => 'missing_slug'],
    ['label' => 'No members', 'count' => (int)($stats['no_members'] ?? 0), 'key' => 'no_members'],
];
$sortOptions = [
    'name_asc' => 'Name A to Z',
    'name_desc' => 'Name Z to A',
    'updated_desc' => 'Recently updated',
    'updated_asc' => 'Least recently updated',
    'created_desc' => 'Newest created',
    'created_asc' => 'Oldest created',
    'members_desc' => 'Most members',
    'members_asc' => 'Fewest members',
    'issues_desc' => 'Most issues',
    'issues_asc' => 'Fewest issues',
];
?>

<div class="card border-0 shadow-sm mb-4 bg-body-tertiary">
    <div class="card-body p-4">
        <div class="d-flex flex-wrap justify-content-between align-items-start gap-3">
            <div class="me-3">
                <div class="text-uppercase text-muted small fw-semibold mb-2">Workspace Operations</div>
                <h2 class="h3 mb-2">Manage workspace inventory with cleaner diagnostics</h2>
                <p class="text-muted mb-0">Review health, ownership, membership coverage, and lifecycle state without drilling into each record first.</p>
            </div>
            <?php if ($canManage) { ?>
                <div class="d-flex flex-wrap gap-2">
                    <a href="<?php echo htmlspecialchars($radAdminUrl . '/space/add'); ?>" class="btn btn-primary">
                        <i class="bi bi-plus-circle me-1"></i>New Workspace
                    </a>
                </div>
            <?php } ?>
        </div>
    </div>
</div>

<div class="row g-3 mb-4">
    <?php foreach ($statusCards as $card) {
        $isActive = ($filters['status'] ?? '') === $card['status'] || ($card['status'] === '' && ($filters['status'] ?? '') === '');
        $href = $card['status'] === ''
            ? $buildUrl(['status' => null, 'page' => null])
            : $buildUrl(['status' => $card['status'], 'page' => 1]);
    ?>
        <div class="col-12 col-sm-6 col-xl-3">
            <a href="<?php echo htmlspecialchars($href); ?>" class="card border-0 shadow-sm h-100 text-decoration-none <?php echo $isActive ? 'ring ring-' . $card['tone'] : ''; ?>">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start mb-3">
                        <span class="badge text-bg-<?php echo htmlspecialchars($card['tone']); ?>"><?php echo htmlspecialchars($card['label']); ?></span>
                        <i class="bi <?php echo htmlspecialchars($card['icon']); ?> text-muted"></i>
                    </div>
                    <div class="display-6 mb-1 text-body"><?php echo number_format($card['value']); ?></div>
                    <div class="text-muted small"><?php echo $card['status'] === '' ? 'Across all lifecycle states' : 'Filter to this lifecycle state'; ?></div>
                </div>
            </a>
        </div>
    <?php } ?>
</div>

<div class="card border-0 shadow-sm mb-4">
    <div class="card-body">
        <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-3">
            <div>
                <h3 class="h5 mb-1">Workspace Inventory</h3>
                <div class="text-muted small">
                    <?php if ($totalCount > 0) { ?>
                        Showing <?php echo number_format($rangeStart); ?>-<?php echo number_format($rangeEnd); ?> of <?php echo number_format($totalCount); ?> matching workspaces
                    <?php } else { ?>
                        No workspaces match the current filter set
                    <?php } ?>
                </div>
            </div>
            <div class="d-flex flex-wrap align-items-center gap-2">
                <?php foreach ($issueChips as $chip) {
                    $active = !empty($filters[$chip['key']]);
                    $chipUrl = $buildUrl([$chip['key'] => $active ? null : 1, 'page' => 1]);
                ?>
                    <a href="<?php echo htmlspecialchars($chipUrl); ?>" class="btn btn-sm <?php echo $active ? 'btn-dark' : 'btn-outline-secondary'; ?>">
                        <?php echo htmlspecialchars($chip['label']); ?>
                        <span class="ms-1 badge <?php echo $active ? 'text-bg-light' : 'text-bg-secondary'; ?>"><?php echo number_format($chip['count']); ?></span>
                    </a>
                <?php } ?>
                <?php if ($hasFilters) { ?>
                    <a class="btn btn-link btn-sm text-decoration-none" href="<?php echo htmlspecialchars($radAdminUrl . '/space/view'); ?>">Reset all</a>
                <?php } ?>
            </div>
        </div>

        <form class="row g-2 align-items-end" method="get" action="<?php echo htmlspecialchars($radAdminUrl . '/space/view'); ?>">
            <div class="col-12 col-lg-4">
                <label for="space_q" class="form-label small text-muted mb-1">Search</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="bi bi-search"></i></span>
                    <input type="text" id="space_q" name="q" class="form-control" placeholder="Name, UID, slug, owner, description" value="<?php echo htmlspecialchars($filters['q'] ?? ''); ?>">
                </div>
            </div>
            <div class="col-6 col-lg-2">
                <label for="space_status" class="form-label small text-muted mb-1">Status</label>
                <select id="space_status" name="status" class="form-select">
                    <option value="">All statuses</option>
                    <option value="0" <?php echo ($filters['status'] ?? '') === '0' ? 'selected' : ''; ?>>Inactive</option>
                    <option value="1" <?php echo ($filters['status'] ?? '') === '1' ? 'selected' : ''; ?>>Active</option>
                    <option value="2" <?php echo ($filters['status'] ?? '') === '2' ? 'selected' : ''; ?>>Archived</option>
                    <option value="3" <?php echo ($filters['status'] ?? '') === '3' ? 'selected' : ''; ?>>Suspended</option>
                </select>
            </div>
            <div class="col-6 col-lg-2">
                <label for="space_sort" class="form-label small text-muted mb-1">Sort</label>
                <select id="space_sort" name="sort" class="form-select">
                    <?php foreach ($sortOptions as $key => $label) { ?>
                        <option value="<?php echo htmlspecialchars($key); ?>" <?php echo ($pagination['sort'] ?? '') === $key ? 'selected' : ''; ?>><?php echo htmlspecialchars($label); ?></option>
                    <?php } ?>
                </select>
            </div>
            <div class="col-6 col-lg-2">
                <label for="space_per_page" class="form-label small text-muted mb-1">Page size</label>
                <select id="space_per_page" name="per_page" class="form-select">
                    <?php foreach ([20, 25, 50, 100, 200] as $pp) { ?>
                        <option value="<?php echo $pp; ?>" <?php echo $perPage === $pp ? 'selected' : ''; ?>><?php echo $pp; ?>/page</option>
                    <?php } ?>
                </select>
            </div>
            <div class="col-6 col-lg-2 d-grid">
                <button type="submit" class="btn btn-outline-primary">Apply filters</button>
            </div>
            <div class="col-12">
                <div class="d-flex flex-wrap gap-3 pt-2">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" value="1" id="missing_owner" name="missing_owner" <?php echo !empty($filters['missing_owner']) ? 'checked' : ''; ?>>
                        <label class="form-check-label small" for="missing_owner">Only workspaces missing an owner</label>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" value="1" id="missing_slug" name="missing_slug" <?php echo !empty($filters['missing_slug']) ? 'checked' : ''; ?>>
                        <label class="form-check-label small" for="missing_slug">Only workspaces missing a slug</label>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" value="1" id="no_members" name="no_members" <?php echo !empty($filters['no_members']) ? 'checked' : ''; ?>>
                        <label class="form-check-label small" for="no_members">Only workspaces with no members</label>
                    </div>
                </div>
            </div>
        </form>
    </div>
</div>

<?php if ($count > 0) { ?>
    <div class="row g-3">
        <?php foreach ($workspaces as $workspace) {
            $status = (int)($workspace['livestatus'] ?? 1);
            $statusMap = [
                1 => ['label' => 'Active', 'class' => 'text-bg-success'],
                2 => ['label' => 'Archived', 'class' => 'text-bg-secondary'],
                3 => ['label' => 'Suspended', 'class' => 'text-bg-warning'],
                0 => ['label' => 'Inactive', 'class' => 'text-bg-light'],
            ];
            $badge = $statusMap[$status] ?? ['label' => 'Unknown', 'class' => 'text-bg-light'];
            $memberCount = (int)($workspace['member_count'] ?? 0);
            $bindingCount = (int)($workspace['binding_count'] ?? 0);
            $missingOwner = empty($workspace['s_owner_entity_id']);
            $missingSlug = empty($workspace['s_slug']);
            $noMembers = $memberCount === 0;
            $issueCount = (int)($workspace['issue_count'] ?? 0);
            $lastActivity = $workspace['last_member_activity'] ?? null;
            $lastActivityLabel = $lastActivity ? (new DateTime($lastActivity))->format('M j, Y g:i a') : 'No member activity yet';
            $updatedRaw = $workspace['updatestamp'] ?? $workspace['createstamp'] ?? null;
            $updatedLabel = $updatedRaw ? (new DateTime($updatedRaw))->format('M j, Y g:i a') : 'Unknown';
            $createdLabel = !empty($workspace['createstamp']) ? (new DateTime($workspace['createstamp']))->format('M j, Y') : 'Unknown';
            $ownerLabel = trim((string)($workspace['owner_name'] ?? ''));
            if ($ownerLabel === '') {
                $ownerLabel = $missingOwner ? 'Owner not assigned' : 'Entity #' . (int)($workspace['s_owner_entity_id'] ?? 0);
            } elseif (!empty($workspace['owner_identity'])) {
                $ownerLabel .= ' (@' . $workspace['owner_identity'] . ')';
            }
        ?>
            <div class="col-12 col-xl-6">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body d-flex flex-column gap-3">
                        <div class="d-flex justify-content-between align-items-start gap-3">
                            <div class="flex-grow-1">
                                <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
                                    <span class="badge <?php echo htmlspecialchars($badge['class']); ?>"><?php echo htmlspecialchars($badge['label']); ?></span>
                                    <?php if ($issueCount > 0) { ?>
                                        <span class="badge rounded-pill text-bg-dark"><?php echo number_format($issueCount); ?> issue<?php echo $issueCount === 1 ? '' : 's'; ?></span>
                                    <?php } else { ?>
                                        <span class="badge rounded-pill text-bg-success">Healthy</span>
                                    <?php } ?>
                                </div>
                                <h4 class="h5 mb-1"><?php echo htmlspecialchars($workspace['s_name'] ?? 'Workspace'); ?></h4>
                                <?php if (!empty($workspace['s_description'])) { ?>
                                    <p class="text-muted mb-2"><?php echo htmlspecialchars($workspace['s_description']); ?></p>
                                <?php } ?>
                                <div class="small text-muted d-flex flex-wrap gap-3">
                                    <span>UID: <?php echo htmlspecialchars($workspace['uid']); ?></span>
                                    <span>Slug: <?php echo htmlspecialchars($workspace['s_slug'] ?? '—'); ?></span>
                                    <span>Owner: <?php echo htmlspecialchars($ownerLabel); ?></span>
                                </div>
                            </div>
                            <div class="dropdown">
                                <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                                    More
                                </button>
                                <ul class="dropdown-menu dropdown-menu-end">
                                    <li><a class="dropdown-item" href="<?php echo htmlspecialchars($radAdminUrl . '/space/viewone/' . urlencode($workspace['uid'])); ?>">Overview</a></li>
                                    <li><a class="dropdown-item" href="<?php echo htmlspecialchars($radAdminUrl . '/membership/view?space_id=' . (int)$workspace['id']); ?>">Memberships</a></li>
                                    <li><a class="dropdown-item" href="<?php echo htmlspecialchars($radAdminUrl . '/permissionbindings/view?space_id=' . (int)$workspace['id']); ?>">Permissions</a></li>
                                    <?php if ($canManage) { ?>
                                        <li><hr class="dropdown-divider"></li>
                                        <li><a class="dropdown-item" href="<?php echo htmlspecialchars($radAdminUrl . '/space/edit/' . urlencode($workspace['uid'])); ?>">Edit workspace</a></li>
                                        <li><a class="dropdown-item" href="<?php echo htmlspecialchars($radAdminUrl . '/space/setowner/' . urlencode($workspace['uid'])); ?>">Set owner</a></li>
                                        <li><a class="dropdown-item" href="<?php echo htmlspecialchars($radAdminUrl . '/space/adduser/' . urlencode($workspace['uid'])); ?>">Add member</a></li>
                                        <?php if ($status !== 1) { ?>
                                            <li><a class="dropdown-item" href="<?php echo htmlspecialchars($radAdminUrl . '/space/activate/' . urlencode($workspace['uid'])); ?>" onclick="return confirm('Activate this workspace?');">Activate</a></li>
                                        <?php } ?>
                                        <?php if ($status === 1) { ?>
                                            <li><a class="dropdown-item" href="<?php echo htmlspecialchars($radAdminUrl . '/space/suspend/' . urlencode($workspace['uid'])); ?>" onclick="return confirm('Suspend this workspace?');">Suspend</a></li>
                                        <?php } ?>
                                        <li><a class="dropdown-item text-danger" href="<?php echo htmlspecialchars($radAdminUrl . '/space/archive/' . urlencode($workspace['uid'])); ?>" onclick="return confirm('Archive this workspace?');">Archive</a></li>
                                    <?php } ?>
                                </ul>
                            </div>
                        </div>

                        <div class="row g-3">
                            <div class="col-4">
                                <div class="border rounded-3 p-3 h-100">
                                    <div class="text-uppercase small text-muted mb-1">Members</div>
                                    <div class="fs-3 fw-semibold"><?php echo number_format($memberCount); ?></div>
                                    <div class="small text-muted">Assigned memberships</div>
                                </div>
                            </div>
                            <div class="col-4">
                                <div class="border rounded-3 p-3 h-100">
                                    <div class="text-uppercase small text-muted mb-1">Bindings</div>
                                    <div class="fs-3 fw-semibold"><?php echo number_format($bindingCount); ?></div>
                                    <div class="small text-muted">Permission records</div>
                                </div>
                            </div>
                            <div class="col-4">
                                <div class="border rounded-3 p-3 h-100">
                                    <div class="text-uppercase small text-muted mb-1">Updated</div>
                                    <div class="fw-semibold small"><?php echo htmlspecialchars($updatedLabel); ?></div>
                                    <div class="small text-muted">Created <?php echo htmlspecialchars($createdLabel); ?></div>
                                </div>
                            </div>
                        </div>

                        <div class="d-flex flex-wrap gap-2">
                            <?php if ($missingOwner) { ?>
                                <a href="<?php echo htmlspecialchars($radAdminUrl . '/space/setowner/' . urlencode($workspace['uid'])); ?>" class="badge rounded-pill text-bg-warning text-decoration-none">Missing owner</a>
                            <?php } ?>
                            <?php if ($missingSlug) { ?>
                                <a href="<?php echo htmlspecialchars($radAdminUrl . '/space/edit/' . urlencode($workspace['uid'])); ?>" class="badge rounded-pill text-bg-warning text-decoration-none">Missing slug</a>
                            <?php } ?>
                            <?php if ($noMembers) { ?>
                                <a href="<?php echo htmlspecialchars($radAdminUrl . '/space/adduser/' . urlencode($workspace['uid'])); ?>" class="badge rounded-pill text-bg-light text-decoration-none">No members</a>
                            <?php } ?>
                            <?php if ($status === 2) { ?>
                                <span class="badge rounded-pill text-bg-secondary">Archived workspace</span>
                            <?php } elseif ($status === 3) { ?>
                                <span class="badge rounded-pill text-bg-warning">Suspended workspace</span>
                            <?php } elseif ($status === 0) { ?>
                                <span class="badge rounded-pill text-bg-light">Inactive workspace</span>
                            <?php } ?>
                        </div>

                        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 pt-1">
                            <div class="small text-muted">
                                <i class="bi bi-clock-history me-1"></i>Last member activity: <?php echo htmlspecialchars($lastActivityLabel); ?>
                            </div>
                            <div class="d-flex flex-wrap gap-2">
                                <a href="<?php echo htmlspecialchars($radAdminUrl . '/space/viewone/' . urlencode($workspace['uid'])); ?>" class="btn btn-primary btn-sm">
                                    <i class="bi bi-eye me-1"></i>Overview
                                </a>
                                <a href="<?php echo htmlspecialchars($radAdminUrl . '/membership/view?space_id=' . (int)$workspace['id']); ?>" class="btn btn-outline-secondary btn-sm">
                                    <i class="bi bi-people me-1"></i>Memberships
                                </a>
                                <?php if ($canManage) { ?>
                                    <a href="<?php echo htmlspecialchars($radAdminUrl . '/space/edit/' . urlencode($workspace['uid'])); ?>" class="btn btn-outline-secondary btn-sm">
                                        <i class="bi bi-pencil-square me-1"></i>Edit
                                    </a>
                                <?php } ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        <?php } ?>
    </div>
<?php } else { ?>
    <div class="card border-0 shadow-sm">
        <div class="card-body text-center py-5">
            <i class="bi bi-buildings text-muted" style="font-size:4.5rem;"></i>
            <?php if ($hasFilters) { ?>
                <h2 class="h4 mt-3">No workspaces match these filters</h2>
                <p class="text-muted">Adjust the search, status, or issue filters to broaden the result set.</p>
                <a href="<?php echo htmlspecialchars($radAdminUrl . '/space/view'); ?>" class="btn btn-outline-secondary">Clear filters</a>
            <?php } else { ?>
                <h2 class="h4 mt-3">No workspaces yet</h2>
                <p class="text-muted">Create the first workspace to start assigning members, permissions, and microservicelets.</p>
                <?php if ($canManage) { ?>
                    <a href="<?php echo htmlspecialchars($radAdminUrl . '/space/add'); ?>" class="btn btn-primary">
                        <i class="bi bi-plus-circle me-1"></i>Create Workspace
                    </a>
                <?php } ?>
            <?php } ?>
        </div>
    </div>
<?php } ?>

<?php if ($pages > 1) {
    $windowStart = max(1, $page - 2);
    $windowEnd = min($pages, $page + 2);
?>
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mt-4">
        <div class="small text-muted">
            Page <?php echo number_format($page); ?> of <?php echo number_format($pages); ?>
        </div>
        <nav aria-label="Workspace pagination">
            <ul class="pagination pagination-sm mb-0">
                <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                    <a class="page-link" href="<?php echo htmlspecialchars($buildUrl(['page' => max(1, $page - 1)])); ?>">Prev</a>
                </li>
                <?php for ($i = $windowStart; $i <= $windowEnd; $i++) { ?>
                    <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                        <a class="page-link" href="<?php echo htmlspecialchars($buildUrl(['page' => $i])); ?>"><?php echo number_format($i); ?></a>
                    </li>
                <?php } ?>
                <li class="page-item <?php echo $page >= $pages ? 'disabled' : ''; ?>">
                    <a class="page-link" href="<?php echo htmlspecialchars($buildUrl(['page' => min($pages, $page + 1)])); ?>">Next</a>
                </li>
            </ul>
        </nav>
    </div>
<?php } ?>

<script>
document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(function (el) {
    if (window.bootstrap && window.bootstrap.Tooltip) {
        new window.bootstrap.Tooltip(el);
    }
});
</script>
