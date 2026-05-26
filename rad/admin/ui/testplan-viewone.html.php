<?php
$plan = $this->runData['data']['plan'] ?? [];
$items = $this->runData['data']['items'] ?? [];
$runs = $this->runData['data']['runs'] ?? [];
$maps = $this->runData['data']['scope_maps'] ?? ['ms' => [], 'route' => [], 'api' => []];

function tpScopeLabelDetail($plan, $maps) {
    $scope = $plan['s_scope'] ?? '';
    if ($scope === 'microservice') {
        $name = $maps['ms'][$plan['s_ms_id']] ?? '';
        return $name ? "Microservicelet: {$name}" : 'Microservicelet';
    }
    if ($scope === 'route') {
        $name = $maps['route'][$plan['s_route_id']] ?? '';
        return $name ? "Route: {$name}" : 'Route';
    }
    if ($scope === 'api') {
        $name = $maps['api'][$plan['s_apiendpoint_id']] ?? '';
        return $name ? "API: {$name}" : 'API';
    }
    return ucfirst($scope);
}
?>

<div class="card mb-3">
    <div class="card-body">
        <h4 class="mb-1"><?php echo htmlspecialchars($plan['s_name'] ?? 'Test Plan'); ?></h4>
        <div class="text-muted mb-2"><?php echo htmlspecialchars($plan['s_description'] ?? ''); ?></div>
        <div class="small text-muted">Scope: <?php echo htmlspecialchars(tpScopeLabelDetail($plan, $maps)); ?></div>
    </div>
</div>

<div class="row g-3">
    <div class="col-lg-7">
        <div class="card">
            <div class="card-body">
                <h5 class="card-title">Test Items</h5>
                <?php if (empty($items)) { ?>
                    <div class="text-muted">No items defined.</div>
                <?php } else { ?>
                    <div class="table-responsive">
                        <table class="table align-middle">
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Type</th>
                                    <th>URL</th>
                                    <th>Expected</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($items as $item) { ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($item['s_name']); ?></td>
                                        <td><?php echo htmlspecialchars($item['s_type']); ?></td>
                                        <td class="small text-muted"><?php echo htmlspecialchars($item['s_url'] ?? ''); ?></td>
                                        <td class="small text-muted"><?php echo htmlspecialchars($item['s_expected'] ?? ''); ?></td>
                                    </tr>
                                <?php } ?>
                            </tbody>
                        </table>
                    </div>
                <?php } ?>
            </div>
        </div>
    </div>
    <div class="col-lg-5">
        <div class="card h-100">
            <div class="card-body">
                <h5 class="card-title">Recent Runs</h5>
                <?php if (empty($runs)) { ?>
                    <div class="text-muted">No runs yet.</div>
                <?php } else { ?>
                    <div class="table-responsive">
                        <table class="table align-middle">
                            <thead>
                                <tr>
                                    <th>Started</th>
                                    <th>Status</th>
                                    <th>Passed</th>
                                    <th>Failed</th>
                                    <th>Blocked</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($runs as $run) { ?>
                                    <tr>
                                        <td class="small text-muted"><?php echo htmlspecialchars($run['createstamp']); ?></td>
                                        <td><?php echo htmlspecialchars($run['s_status']); ?></td>
                                        <td><?php echo (int)($run['passed_count'] ?? 0); ?></td>
                                        <td><?php echo (int)($run['failed_count'] ?? 0); ?></td>
                                        <td><?php echo (int)($run['blocked_count'] ?? 0); ?></td>
                                    </tr>
                                <?php } ?>
                            </tbody>
                        </table>
                    </div>
                <?php } ?>
            </div>
        </div>
    </div>
</div>
