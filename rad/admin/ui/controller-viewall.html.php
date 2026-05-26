<?php
$controllers = $this->runData['data']['controllers'] ?? [];
$filters = $this->runData['data']['filters'] ?? ['search' => '', 'scope' => '', 'livestatus' => ''];
$radUrl = $this->runData['route']['rad_admin_url'];
?>

<div class="card mb-3">
    <div class="card-body">
        <form class="row g-2 align-items-end" method="get">
            <div class="col-md-4">
                <label class="form-label">Search</label>
                <input type="text" name="search" class="form-control" value="<?php echo htmlspecialchars($filters['search'] ?? ''); ?>" placeholder="Business class, data model, or microservice name">
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
                    <option value="3" <?php echo (($filters['livestatus'] ?? '') === '3') ? 'selected' : ''; ?>>Suspended</option>
                </select>
            </div>
            <div class="col-md-2 d-grid gap-2">
                <button type="submit" class="btn btn-primary"><i class="bi bi-funnel me-1"></i>Filter</button>
                <?php if (($filters['search'] ?? '') !== '' || ($filters['scope'] ?? '') !== '' || ($filters['livestatus'] ?? '') !== '') { ?>
                    <a href="<?php echo $radUrl; ?>/controller/viewall" class="btn btn-outline-secondary">Reset</a>
                <?php } ?>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <?php if (empty($controllers)) { ?>
            <p class="text-muted mb-0">No controllers found.</p>
        <?php } else { ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>Business Class / Data Model</th>
                            <th>Microservicelet</th>
                            <th>Type</th>
                            <th>Scope</th>
                            <th>Status</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($controllers as $ctrl) {
                        $scope = strtolower($ctrl['s_scope'] ?? ($ctrl['ms_scope'] ?? ''));
                        $status = (string)($ctrl['livestatus'] ?? '0');
                        $type = strtoupper($ctrl['s_type'] ?? '');
                        ?>
                        <tr>
                            <td>
                                <div class="fw-semibold"><?php echo htmlspecialchars($ctrl['s_name'] ?? ''); ?></div>
                                <div class="text-muted small">ID: <?php echo (int)$ctrl['id']; ?> · UID: <?php echo htmlspecialchars($ctrl['uid'] ?? ''); ?></div>
                            </td>
                            <td>
                                <div class="fw-semibold">
                                    <?php if (!empty($ctrl['s_ms_uid'])) { ?>
                                        <a href="<?php echo $radUrl; ?>/microservice/detail/<?php echo htmlspecialchars($ctrl['s_ms_uid']); ?>" class="link-primary text-decoration-none">
                                            <?php echo htmlspecialchars($ctrl['ms_name'] ?? ''); ?>
                                        </a>
                                    <?php } else { ?>
                                        <?php echo htmlspecialchars($ctrl['ms_name'] ?? ''); ?>
                                    <?php } ?>
                                    <?php if (empty($ctrl['s_ms_id'])) { ?>
                                        <span class="badge bg-danger ms-1">Unbound</span>
                                    <?php } ?>
                                </div>
                                <div class="text-muted small">
                                    <?php echo $ctrl['s_ms_id'] ? 'ID: ' . (int)$ctrl['s_ms_id'] . ' · UID: ' . htmlspecialchars($ctrl['s_ms_uid'] ?? '') : 'No microservice linked'; ?>
                                </div>
                            </td>
                            <td><span class="badge bg-secondary"><?php echo $type === 'BL' ? 'Business Class' : ($type === 'DM' ? 'Data Model' : '—'); ?></span></td>
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
                            <td class="text-end">
                                <div class="btn-group btn-group-sm">
                                    <?php if (!empty($ctrl['s_ms_id'])) { ?>
                                        <a class="btn btn-outline-secondary" href="<?php echo $radUrl; ?>/controller/edit/<?php echo htmlspecialchars($ctrl['uid'] ?? $ctrl['id']); ?>/<?php echo htmlspecialchars($ctrl['s_ms_uid'] ?? $ctrl['s_ms_id']); ?>">
                                            Edit
                                        </a>
                                        <a class="btn btn-outline-primary" href="<?php echo $radUrl; ?>/microservice/detail/<?php echo htmlspecialchars($ctrl['s_ms_uid'] ?? $ctrl['s_ms_id']); ?>">
                                            Microservicelet
                                        </a>
                                    <?php } else { ?>
                                        <button class="btn btn-outline-secondary" disabled title="Link to a microservicelet to edit">Edit</button>
                                    <?php } ?>
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
