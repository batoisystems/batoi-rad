<?php
$plans = $this->runData['data']['plans'] ?? [];
$maps = $this->runData['data']['scope_maps'] ?? ['ms' => [], 'route' => [], 'api' => []];
function tpScopeLabel($plan, $maps) {
    $scope = $plan['s_scope'] ?? '';
    if ($scope === 'microservice') {
        $name = $maps['ms'][$plan['s_ms_id']] ?? '';
        return 'Microservicelet' . ($name ? ': ' . $name : '');
    }
    if ($scope === 'route') {
        $name = $maps['route'][$plan['s_route_id']] ?? '';
        return 'Route' . ($name ? ': ' . $name : '');
    }
    if ($scope === 'api') {
        $name = $maps['api'][$plan['s_apiendpoint_id']] ?? '';
        return 'API' . ($name ? ': ' . $name : '');
    }
    return ucfirst($scope);
}
?>
<div class="card mb-3">
    <div class="card-body">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
            <div>
                <p class="text-muted mb-0">Plans linked to microservicelets, routes, and API endpoints.</p>
            </div>
            <div class="d-flex flex-wrap gap-2">
                <a href="<?php echo $this->runData['route']['rad_admin_url']; ?>/testplan/add" class="btn btn-primary btn-sm">
                    <i class="bi bi-plus-circle me-1"></i>New Plan
                </a>
                <a href="<?php echo $this->runData['route']['rad_admin_url']; ?>/testplan/report" class="btn btn-outline-secondary btn-sm">
                    <i class="bi bi-bar-chart-line me-1"></i>Reports
                </a>
            </div>
        </div>
        <form class="row row-cols-1 row-cols-lg-4 g-2 align-items-end mt-3" method="post" action="<?php echo $this->runData['route']['rad_admin_url']; ?>/testplan/generate">
            <div class="col">
                <label class="form-label small text-muted mb-1">Auto-generate scope</label>
                <select class="form-select form-select-sm" name="target_key" required>
                    <option value="">Select target</option>
                    <?php if (!empty($maps['ms'])) { ?>
                        <optgroup label="Microservicelets">
                            <?php foreach ($maps['ms'] as $id => $name) { ?>
                                <option value="microservice:<?php echo (int)$id; ?>"><?php echo htmlspecialchars($name); ?></option>
                            <?php } ?>
                        </optgroup>
                    <?php } ?>
                    <?php if (!empty($maps['route'])) { ?>
                        <optgroup label="Routes">
                            <?php foreach ($maps['route'] as $id => $name) { ?>
                                <option value="route:<?php echo (int)$id; ?>"><?php echo htmlspecialchars($name); ?></option>
                            <?php } ?>
                        </optgroup>
                    <?php } ?>
                    <?php if (!empty($maps['api'])) { ?>
                        <optgroup label="API Endpoints">
                            <?php foreach ($maps['api'] as $id => $name) { ?>
                                <option value="api:<?php echo (int)$id; ?>"><?php echo htmlspecialchars($name); ?></option>
                            <?php } ?>
                        </optgroup>
                    <?php } ?>
                </select>
            </div>
            <div class="col">
                <label class="form-label small text-muted mb-1">Plan name (optional)</label>
                <input type="text" class="form-control form-control-sm" name="override_name" placeholder="Auto: My plan name">
            </div>
            <div class="col">
                <label class="form-label small text-muted mb-1">Description (optional)</label>
                <input type="text" class="form-control form-control-sm" name="override_desc" placeholder="Smoke coverage, happy path">
            </div>
            <div class="col d-grid">
                <button type="submit" class="btn btn-sm btn-outline-primary">
                    <i class="bi bi-stars me-1"></i>Generate &amp; open
                </button>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <?php if (empty($plans)) { ?>
            <div class="text-muted">No test plans found.</div>
        <?php } else { ?>
            <div class="table-responsive">
                <table class="table align-middle">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Scope</th>
                            <th>Items</th>
                            <th>Runs</th>
                            <th>Last run</th>
                            <th>Status</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($plans as $plan) { ?>
                            <tr>
                                <td><?php echo htmlspecialchars($plan['s_name']); ?></td>
                                <td><?php echo htmlspecialchars(tpScopeLabel($plan, $maps) ?? ''); ?></td>
                                <td><?php echo (int)($plan['item_count'] ?? 0); ?></td>
                                <td><?php echo (int)($plan['run_count'] ?? 0); ?></td>
                                <td class="text-muted small"><?php echo $plan['last_run_at'] ?? '—'; ?></td>
                                <td><?php echo $plan['last_status'] ? htmlspecialchars($plan['last_status']) : '—'; ?></td>
                                <td><a class="btn btn-sm btn-outline-primary" href="<?php echo $this->runData['route']['rad_admin_url']; ?>/testplan/viewone/<?php echo (int)$plan['id']; ?>">Open</a></td>
                            </tr>
                        <?php } ?>
                    </tbody>
                </table>
            </div>
        <?php } ?>
    </div>
</div>
