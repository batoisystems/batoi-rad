<?php
$radAdminUrl = $this->runData['route']['rad_admin_url'] ?? '';
$report = $this->runData['data']['ac_report'] ?? ['ms_no_bindings' => [], 'routes_no_bindings' => []];
$msList = $report['ms_no_bindings'] ?? [];
$routeList = $report['routes_no_bindings'] ?? [];
?>
<div class="card border-0 shadow-sm mb-4">
    <div class="card-body d-flex flex-column flex-lg-row align-items-lg-center gap-3">
        <div>
            <p class="text-muted mb-0">Audit of private microservicelets and routes with zero permission bindings.</p>
        </div>
        <div class="ms-lg-auto d-flex gap-2 flex-wrap">
            <a href="<?php echo $radAdminUrl; ?>/techdocs/view" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left me-1"></i>Back to Tech Docs</a>
            <a href="<?php echo $radAdminUrl; ?>/techdocs/accesscontrol" class="btn btn-outline-info btn-sm"><i class="bi bi-shield-lock me-1"></i>Access Control</a>
        </div>
    </div>
</div>

<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-white">
        <h3 class="h6 mb-0">Microservicelets without bindings</h3>
        <p class="text-muted small mb-0">Private microservicelets that have no permission bindings. Add ms-level bindings to grant access.</p>
    </div>
    <div class="card-body">
        <?php if (empty($msList)) { ?>
            <p class="text-success mb-0"><i class="bi bi-check-circle-fill me-2"></i>All private microservicelets have bindings.</p>
        <?php } else { ?>
            <div class="table-responsive">
                <table class="table table-sm table-bordered align-middle mb-0">
                    <thead>
                        <tr>
                            <th scope="col">ID</th>
                            <th scope="col">Name</th>
                            <th scope="col">Bindings</th>
                            <th scope="col">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($msList as $row) { ?>
                            <tr>
                                <td><?php echo (int)$row['id']; ?></td>
                                <td><?php echo htmlspecialchars($row['s_name'] ?? ''); ?></td>
                                <td>0</td>
                                <td><a class="btn btn-outline-primary btn-sm" href="<?php echo $radAdminUrl; ?>/permissionbindings/view?object_type=ms&object_id=<?php echo (int)$row['id']; ?>">Add Binding</a></td>
                            </tr>
                        <?php } ?>
                    </tbody>
                </table>
            </div>
        <?php } ?>
    </div>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-header bg-white">
        <h3 class="h6 mb-0">Routes without bindings</h3>
        <p class="text-muted small mb-0">Routes of private microservicelets that have no permission bindings. Add route-level bindings to grant access.</p>
    </div>
    <div class="card-body">
        <?php if (empty($routeList)) { ?>
            <p class="text-success mb-0"><i class="bi bi-check-circle-fill me-2"></i>All routes of private microservicelets have bindings.</p>
        <?php } else { ?>
            <div class="table-responsive">
                <table class="table table-sm table-bordered align-middle mb-0">
                    <thead>
                        <tr>
                            <th scope="col">Route ID</th>
                            <th scope="col">Route Name</th>
                            <th scope="col">Microservicelet</th>
                            <th scope="col">Bindings</th>
                            <th scope="col">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($routeList as $row) { ?>
                            <tr>
                                <td><?php echo (int)$row['id']; ?></td>
                                <td><?php echo htmlspecialchars($row['s_name'] ?? ''); ?></td>
                                <td><?php echo htmlspecialchars($row['ms_name'] ?? ('MS #' . $row['s_ms_id'])); ?></td>
                                <td>0</td>
                                <td><a class="btn btn-outline-primary btn-sm" href="<?php echo $radAdminUrl; ?>/permissionbindings/view?object_type=route&object_id=<?php echo (int)$row['id']; ?>">Add Binding</a></td>
                            </tr>
                        <?php } ?>
                    </tbody>
                </table>
            </div>
        <?php } ?>
    </div>
</div>
