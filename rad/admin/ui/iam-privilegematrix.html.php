<?php
$radAdminUrl = $this->runData['route']['rad_admin_url'] ?? '';
$rolesByScope = $this->runData['data']['roles_by_scope'] ?? ['platform' => [], 'workspace' => [], 'ms' => []];
$msGroups = $this->runData['data']['ms_groups'] ?? [];
$bindingMap = $this->runData['data']['binding_map'] ?? [];
$activeScope = $this->runData['data']['active_scope'] ?? 'platform';
$perMsLimit = (int)($this->runData['data']['per_ms_limit'] ?? 50);

if (!function_exists('iam_role_label')) {
    function iam_role_label(array $role): string {
        $label = $role['s_role_name'] ?? 'Role';
        $label .= ' · ID: ' . (int)$role['id'];
        return $label;
    }
}
?>

<style>
    .pm-table th,
    .pm-table td { vertical-align: middle; }
    .pm-table th { font-weight: 600; }
    .pm-table .pm-role-header { min-width: 140px; }
    .pm-table .pm-role-header .bi { font-size: 0.9rem; }
    .pm-table .pm-role-header .text-muted { line-height: 1.2; }
    .pm-table .pm-route-row td:first-child { width: 180px; }
    .pm-table .pm-route-row td:nth-child(2) { min-width: 240px; }
    .pm-table .pm-route-row .btn { padding: .25rem .4rem; }
    .pm-table .pm-role-cell span { display: inline-block; width: 20px; text-align: center; }
</style>

<div class="card mb-4">
    <div class="card-body d-flex flex-wrap justify-content-between align-items-center gap-3">
        <div>
            <h2 class="h5 mb-1">Privilege Matrix</h2>
            <p class="text-muted mb-0">Routes grouped by microservicelet with role bindings by scope.</p>
        </div>
        <div class="d-flex flex-wrap gap-2 align-items-center">
            <input type="search" class="form-control form-control-sm" id="pm-search" placeholder="Search by role, route, or microservicelet ID/name">
            <select class="form-select form-select-sm w-auto" id="pm-limit">
                <option value="25" <?php echo $perMsLimit === 25 ? 'selected' : ''; ?>>25 routes per MS</option>
                <option value="50" <?php echo $perMsLimit === 50 ? 'selected' : ''; ?>>50 routes per MS</option>
                <option value="100" <?php echo $perMsLimit === 100 ? 'selected' : ''; ?>>100 routes per MS</option>
                <option value="200" <?php echo $perMsLimit === 200 ? 'selected' : ''; ?>>200 routes per MS</option>
                <option value="0" <?php echo $perMsLimit === 0 ? 'selected' : ''; ?>>All routes</option>
            </select>
        </div>
    </div>
</div>

<ul class="nav nav-tabs mb-3" id="pm-tabs" role="tablist">
    <li class="nav-item" role="presentation">
        <button class="nav-link <?php echo $activeScope === 'platform' ? 'active' : ''; ?>" data-bs-toggle="tab" data-bs-target="#pm-platform" type="button" role="tab" data-scope="platform">Platform</button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link <?php echo $activeScope === 'workspace' ? 'active' : ''; ?>" data-bs-toggle="tab" data-bs-target="#pm-workspace" type="button" role="tab" data-scope="workspace">Workspace</button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link <?php echo $activeScope === 'ms' ? 'active' : ''; ?>" data-bs-toggle="tab" data-bs-target="#pm-ms" type="button" role="tab" data-scope="ms">MS</button>
    </li>
</ul>

<div class="tab-content">
    <?php foreach (['platform' => 'Platform', 'workspace' => 'Workspace', 'ms' => 'MS'] as $scopeKey => $scopeLabel) { ?>
        <?php $roles = $rolesByScope[$scopeKey] ?? []; ?>
        <div class="tab-pane fade <?php echo $activeScope === $scopeKey ? 'show active' : ''; ?>" id="pm-<?php echo $scopeKey; ?>" role="tabpanel">
            <?php if (empty($roles)) { ?>
                <div class="alert alert-info">No <?php echo htmlspecialchars($scopeLabel); ?> roles found.</div>
                <?php continue; ?>
            <?php } ?>

            <?php foreach ($msGroups as $group) { ?>
                <?php
                $msName = $group['ms_name'] ?? 'Unknown';
                $msId = (int)($group['ms_id'] ?? 0);
                $routes = $group['routes'] ?? [];
                if (empty($routes)) {
                    continue;
                }
                ?>
                <div class="card mb-4 pm-group" data-ms="<?php echo htmlspecialchars(strtolower($msName)); ?>" data-ms-id="<?php echo $msId; ?>">
                    <div class="card-header bg-white d-flex justify-content-between align-items-center">
                        <div>
                            <strong><?php echo htmlspecialchars($msName); ?></strong>
                            <span class="text-muted small">ID: <?php echo $msId; ?></span>
                        </div>
                        <span class="badge text-bg-light">
                            <?php echo count($routes); ?> of <?php echo (int)($group['routes_total'] ?? count($routes)); ?> routes
                        </span>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-sm align-middle mb-0 pm-table">
                            <thead class="table-light">
                                <tr>
                                    <th class="text-nowrap">Microservicelet</th>
                                    <th class="text-nowrap">Route</th>
                                    <?php foreach ($roles as $role) { ?>
                                        <th class="text-center pm-role-header" data-role="<?php echo htmlspecialchars(strtolower($role['s_role_name'] ?? '')); ?>" data-role-id="<?php echo (int)$role['id']; ?>">
                                            <div class="d-flex flex-column align-items-center">
                                                <div class="d-flex align-items-center gap-1">
                                                    <span><?php echo htmlspecialchars($role['s_role_name'] ?? 'Role'); ?></span>
                                                    <?php if (!empty($role['uid'])) { ?>
                                                        <a href="<?php echo $radAdminUrl; ?>/role/edit/<?php echo $role['uid']; ?>" class="text-decoration-none text-muted" title="Edit role">
                                                            <i class="bi bi-pencil-square"></i>
                                                        </a>
                                                    <?php } ?>
                                                </div>
                                                <span class="text-muted small">ID: <?php echo (int)$role['id']; ?></span>
                                            </div>
                                        </th>
                                    <?php } ?>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($routes as $index => $route) { ?>
                                    <?php
                                    $routeId = (int)($route['id'] ?? 0);
                                    $routeName = $route['s_name'] ?? $route['s_href'] ?? ('Route #' . $routeId);
                                    $routeUid = $route['uid'] ?? '';
                                    ?>
                                    <tr class="pm-route-row" data-route="<?php echo htmlspecialchars(strtolower($routeName)); ?>" data-route-id="<?php echo $routeId; ?>">
                                        <td class="text-nowrap">
                                            <?php if ($index === 0) { ?>
                                                <strong><?php echo htmlspecialchars($msName); ?></strong>
                                                <div class="text-muted small">ID: <?php echo $msId; ?></div>
                                            <?php } ?>
                                        </td>
                                        <td>
                                            <div class="d-flex align-items-center gap-2">
                                                <div>
                                                    <div class="fw-semibold"><?php echo htmlspecialchars($routeName); ?></div>
                                                    <div class="text-muted small">ID: <?php echo $routeId; ?></div>
                                                </div>
                                                <?php if ($routeId > 0) { ?>
                                                    <a class="btn btn-sm btn-outline-secondary" href="<?php echo $radAdminUrl; ?>/permissionbindings/view?object_type=route&object_id=<?php echo $routeId; ?>" title="Edit bindings">
                                                        <i class="bi bi-key"></i>
                                                    </a>
                                                <?php } ?>
                                            </div>
                                        </td>
                                        <?php foreach ($roles as $role) { ?>
                                            <?php $hasBinding = !empty($bindingMap[$routeId][(int)$role['id']]); ?>
                                            <td class="text-center pm-role-cell" data-role-id="<?php echo (int)$role['id']; ?>">
                                                <?php if ($hasBinding) { ?>
                                                    <span class="text-success fw-semibold">✓</span>
                                                <?php } else { ?>
                                                    <span class="text-muted">✕</span>
                                                <?php } ?>
                                            </td>
                                        <?php } ?>
                                    </tr>
                                <?php } ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php } ?>
        </div>
    <?php } ?>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    var searchInput = document.getElementById('pm-search');
    var limitSelect = document.getElementById('pm-limit');
    var activeScope = '<?php echo htmlspecialchars($activeScope); ?>';

    function applySearch() {
        var q = (searchInput ? searchInput.value.trim().toLowerCase() : '');
        document.querySelectorAll('.pm-group').forEach(function (group) {
            var msText = (group.getAttribute('data-ms') || '') + ' ' + (group.getAttribute('data-ms-id') || '');
            var anyRowVisible = false;
            group.querySelectorAll('.pm-route-row').forEach(function (row) {
                var routeText = (row.getAttribute('data-route') || '') + ' ' + (row.getAttribute('data-route-id') || '');
                var showRow = q === '' || msText.indexOf(q) !== -1 || routeText.indexOf(q) !== -1;
                row.style.display = showRow ? '' : 'none';
                if (showRow) {
                    anyRowVisible = true;
                }
            });

            var matchingRoleColumns = [];
            group.querySelectorAll('.pm-role-header').forEach(function (header) {
                var roleText = (header.getAttribute('data-role') || '') + ' ' + (header.getAttribute('data-role-id') || '');
                var match = q === '' || roleText.indexOf(q) !== -1;
                header.style.display = match ? '' : 'none';
                if (match) {
                    matchingRoleColumns.push(header.getAttribute('data-role-id'));
                }
            });
            group.querySelectorAll('.pm-role-cell').forEach(function (cell) {
                var roleId = cell.getAttribute('data-role-id');
                if (q === '' || matchingRoleColumns.indexOf(roleId) !== -1) {
                    cell.style.display = '';
                } else {
                    cell.style.display = 'none';
                }
            });

            group.style.display = anyRowVisible ? '' : 'none';
        });
    }

    if (searchInput) {
        searchInput.addEventListener('input', applySearch);
    }

    document.querySelectorAll('#pm-tabs [data-bs-toggle="tab"]').forEach(function (tab) {
        tab.addEventListener('shown.bs.tab', function (event) {
            var scope = event.target.getAttribute('data-scope') || 'platform';
            activeScope = scope;
            var url = new URL(window.location.href);
            url.searchParams.set('scope', scope);
            url.searchParams.set('per_ms_limit', limitSelect ? (limitSelect.value || '50') : '50');
            window.history.replaceState({}, '', url.toString());
        });
    });

    if (limitSelect) {
        limitSelect.addEventListener('change', function () {
            var url = new URL(window.location.href);
            url.searchParams.set('per_ms_limit', limitSelect.value);
            url.searchParams.set('scope', activeScope);
            window.location.href = url.toString();
        });
    }

});
</script>
