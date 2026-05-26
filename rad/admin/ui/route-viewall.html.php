<?php
$routes = $this->runData['data']['routes'] ?? [];
$filters = $this->runData['data']['filters'] ?? ['search' => '', 'scope' => '', 'livestatus' => '', 'has_bindings' => ''];
$radUrl = $this->runData['route']['rad_admin_url'];
?>

<div class="card mb-3">
    <div class="card-body">
        <form class="row g-2 align-items-end" method="get">
            <div class="col-md-4">
                <label class="form-label">Search</label>
                <input type="text" name="search" class="form-control" value="<?php echo htmlspecialchars($filters['search'] ?? ''); ?>" placeholder="Route or microservice name">
            </div>
            <div class="col-md-3">
                <label class="form-label">Scope</label>
                <select name="scope" class="form-select">
                    <option value="">All</option>
                    <option value="global" <?php echo (($filters['scope'] ?? '') === 'global') ? 'selected' : ''; ?>>Global</option>
                    <option value="platform" <?php echo (($filters['scope'] ?? '') === 'platform') ? 'selected' : ''; ?>>Platform</option>
                    <option value="workspace" <?php echo (($filters['scope'] ?? '') === 'workspace') ? 'selected' : ''; ?>>Workspace</option>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">Status</label>
                <select name="livestatus" class="form-select">
                    <option value="">All</option>
                    <option value="1" <?php echo (($filters['livestatus'] ?? '') === '1') ? 'selected' : ''; ?>>Active</option>
                    <option value="2" <?php echo (($filters['livestatus'] ?? '') === '2') ? 'selected' : ''; ?>>Archived</option>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">Bindings</label>
                <select name="has_bindings" class="form-select">
                    <option value="">All</option>
                    <option value="Y" <?php echo (($filters['has_bindings'] ?? '') === 'Y') ? 'selected' : ''; ?>>Has bindings</option>
                    <option value="N" <?php echo (($filters['has_bindings'] ?? '') === 'N') ? 'selected' : ''; ?>>No bindings</option>
                </select>
            </div>
            <div class="col-md-2 d-grid gap-2">
                <button type="submit" class="btn btn-primary"><i class="bi bi-funnel me-1"></i>Filter</button>
                <?php if (($filters['search'] ?? '') !== '' || ($filters['scope'] ?? '') !== '' || ($filters['livestatus'] ?? '') !== '' || ($filters['has_bindings'] ?? '') !== '') { ?>
                    <a href="<?php echo $radUrl; ?>/route/viewall" class="btn btn-outline-secondary">Reset</a>
                <?php } ?>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <?php if (empty($routes)) { ?>
            <p class="text-muted mb-0">No routes found.</p>
        <?php } else { ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>Route</th>
                            <th>Microservice</th>
                            <th>Scope</th>
                            <th>Status</th>
                            <th>Bindings</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($routes as $route) {
                        $scope = strtolower($route['s_scope'] ?? ($route['ms_scope'] ?? ''));
                        $status = (string)($route['livestatus'] ?? '0');
                        $hasBindings = !empty($route['has_bindings']);
                        ?>
                        <tr>
                            <td>
                                <div class="fw-semibold"><?php echo htmlspecialchars($route['s_name'] ?? ''); ?></div>
                                <div class="text-muted small">#<?php echo (int)$route['id']; ?> · <?php echo htmlspecialchars($route['uid'] ?? ''); ?></div>
                                <?php if (!empty($route['s_notification_template'])) { ?>
                                    <div class="small text-muted">Notif template: <?php echo htmlspecialchars($route['s_notification_template']); ?></div>
                                <?php } ?>
                                <?php if (!empty($route['s_activity_template'])) { ?>
                                    <div class="small text-muted">Activity template: <?php echo htmlspecialchars($route['s_activity_template']); ?></div>
                                <?php } ?>
                            </td>
                            <td>
                                <div class="fw-semibold">
                                    <?php if (!empty($route['s_ms_uid'])) { ?>
                                        <a href="<?php echo $radUrl; ?>/microservice/detail/<?php echo htmlspecialchars($route['s_ms_uid']); ?>" class="link-primary text-decoration-none">
                                            <?php echo htmlspecialchars($route['ms_name'] ?? ''); ?>
                                        </a>
                                    <?php } else { ?>
                                        <?php echo htmlspecialchars($route['ms_name'] ?? ''); ?>
                                    <?php } ?>
                                    <?php if (empty($route['s_ms_id'])) { ?>
                                        <span class="badge bg-danger ms-1">Unbound</span>
                                    <?php } ?>
                                </div>
                                <div class="text-muted small">
                                    <?php echo $route['s_ms_id'] ? 'MS #' . (int)$route['s_ms_id'] : 'No microservice linked'; ?>
                                </div>
                            </td>
                            <td>
                                <span class="badge bg-<?php echo $scope === 'workspace' ? 'info' : ($scope === 'global' ? 'secondary' : 'success'); ?>">
                                    <?php echo $scope ?: '—'; ?>
                                </span>
                            </td>
                            <td>
                                <span class="badge bg-<?php echo $status === '1' ? 'success' : ($status === '2' ? 'danger' : 'secondary'); ?>">
                                    <?php echo $status === '1' ? 'Active' : ($status === '2' ? 'Archived' : 'Inactive'); ?>
                                </span>
                            </td>
                            <td>
                                <?php if ($hasBindings) { ?>
                                    <span class="badge bg-success">Yes</span>
                                <?php } else { ?>
                                    <span class="badge bg-warning text-dark">None</span>
                                <?php } ?>
                            </td>
                            <td class="text-end">
                                <div class="btn-group btn-group-sm">
                                    <?php if (!empty($route['s_ms_id'])) { ?>
                                        <a class="btn btn-outline-secondary" href="<?php echo $radUrl; ?>/route/edit/<?php echo htmlspecialchars($route['uid'] ?? $route['id']); ?>/<?php echo htmlspecialchars($route['s_ms_uid'] ?? $route['s_ms_id']); ?>">
                                            Edit
                                        </a>
                                        <a class="btn btn-outline-primary" href="<?php echo $radUrl; ?>/microservice/detail/<?php echo htmlspecialchars($route['s_ms_uid'] ?? $route['s_ms_id']); ?>">
                                            Microservicelet
                                        </a>
                                    <?php } else { ?>
                                        <button class="btn btn-outline-secondary" disabled title="Link to a microservicelet to edit">Edit</button>
                                    <?php } ?>
                                    <a class="btn btn-outline-primary" href="<?php echo $radUrl; ?>/permissionbindings/view?object_type=route&object_id=<?php echo (int)$route['id']; ?>">
                                        Bindings
                                    </a>
                                </div>
                            </td>
                        </tr>
                    <?php } ?>
                    </tbody>
                </table>
            </div>
        <?php } ?>
    </div>
</div>
