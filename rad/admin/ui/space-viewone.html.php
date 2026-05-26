<?php
$space = $this->runData['data']['space'];
$assignments = $this->runData['data']['assignments'] ?? [];
$owner = $this->runData['data']['owner_entity'] ?? null;
$radAdminUrl = $this->runData['route']['rad_admin_url'];
$definition = json_decode($space['s_definition'] ?? '{}', true);
$ipRule = $this->runData['data']['ip_access_rule'] ?? ['enabled' => false, 'ips' => [], 'raw' => ''];
$status = (string)($space['livestatus'] ?? '0');
$statusMap = [
    '1' => ['label' => 'Active', 'class' => 'bg-success-subtle text-success'],
    '2' => ['label' => 'Archived', 'class' => 'bg-secondary-subtle text-secondary'],
    '3' => ['label' => 'Suspended', 'class' => 'bg-warning-subtle text-warning'],
    '0' => ['label' => 'Inactive', 'class' => 'bg-light text-muted'],
];
$statusBadge = $statusMap[$status] ?? ['label' => 'Unknown', 'class' => 'bg-light text-muted'];
$definitionJson = json_encode($definition, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
?>

<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <div>
        <h1 class="h4 mb-1"><?php echo htmlspecialchars($this->runData['route']['h1']); ?></h1>
        <div class="d-flex flex-wrap align-items-center gap-2 mb-1">
            <span class="badge <?php echo htmlspecialchars($statusBadge['class']); ?>"><?php echo htmlspecialchars($statusBadge['label']); ?></span>
            <span class="text-muted small">Slug: <?php echo htmlspecialchars($space['s_slug'] ?? '—'); ?></span>
        </div>
        <div class="text-muted small">UID: <?php echo htmlspecialchars($space['uid']); ?> · ID: <?php echo (int)$space['id']; ?></div>
    </div>
    <div class="btn-group">
        <a href="<?php echo $radAdminUrl; ?>/space/edit/<?php echo $space['uid']; ?>" class="btn btn-primary btn-sm">
            <i class="bi bi-pencil-square me-1"></i>Edit Workspace
        </a>
        <a href="<?php echo $radAdminUrl; ?>/space/ipaccess/<?php echo $space['uid']; ?>" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-shield-lock me-1"></i>IP Restriction
        </a>
        <a href="<?php echo $radAdminUrl; ?>/space/sniff/<?php echo $space['uid']; ?>" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-clipboard-data me-1"></i>Meta Sniff
        </a>
        <a href="<?php echo $radAdminUrl; ?>/space/adduser/<?php echo $space['uid']; ?>" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-person-plus me-1"></i>Add User
        </a>
        <a href="<?php echo $radAdminUrl; ?>/space/archive/<?php echo $space['uid']; ?>" class="btn btn-outline-danger btn-sm">
            <i class="bi bi-archive me-1"></i>Archive
        </a>
    </div>
</div>

<?php if (!$assignments): ?>
    <div class="alert alert-warning">
        <strong>No memberships found.</strong> Add users to this workspace so SaaS roles can be enforced.
    </div>
<?php endif; ?>

<div class="row g-3">
    <div class="col-lg-5">
        <div class="card h-100 shadow-sm">
            <div class="card-body">
                <h5 class="card-title mb-3">Workspace Details</h5>
                <dl class="row mb-0">
                    <dt class="col-sm-4 text-muted">Name</dt>
                    <dd class="col-sm-8"><?php echo htmlspecialchars($space['s_name']); ?></dd>

                    <dt class="col-sm-4 text-muted">Description</dt>
                    <dd class="col-sm-8"><?php echo htmlspecialchars($space['s_description'] ?? '—'); ?></dd>

                    <dt class="col-sm-4 text-muted">Owner</dt>
                    <dd class="col-sm-8">
                        <?php if ($owner): ?>
                            <?php echo htmlspecialchars($owner['s_name'] ?? $owner['s_identity'] ?? ('Entity #' . (int)$owner['id'])); ?>
                        <?php elseif (!empty($space['s_owner_entity_id'])): ?>
                            Entity #<?php echo (int)$space['s_owner_entity_id']; ?>
                        <?php else: ?>
                            —
                        <?php endif; ?>
                    </dd>

                    <dt class="col-sm-4 text-muted">Created</dt>
                    <dd class="col-sm-8"><?php echo htmlspecialchars($space['createstamp'] ?? '—'); ?></dd>

                    <dt class="col-sm-4 text-muted">DYN IP Restriction</dt>
                    <dd class="col-sm-8">
                        <?php if (!empty($ipRule['enabled'])): ?>
                            <span class="badge bg-danger"><?php echo (int)count($ipRule['ips'] ?? []); ?> allowed IPs</span>
                        <?php else: ?>
                            <span class="badge bg-secondary">Disabled</span>
                        <?php endif; ?>
                        <div class="small text-muted mt-1">Controls workspace-scoped DYN access for this workspace.</div>
                    </dd>

                    <dt class="col-sm-4 text-muted">Definition</dt>
                    <dd class="col-sm-8">
                        <details>
                            <summary class="small text-primary-emphasis">View JSON definition</summary>
                            <pre class="mt-2 mb-0 small bg-light p-2 rounded"><?php echo htmlspecialchars((string)$definitionJson); ?></pre>
                        </details>
                    </dd>
                </dl>
            </div>
        </div>
    </div>
    <div class="col-lg-7">
        <div class="card h-100 shadow-sm">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h5 class="card-title mb-0">Users & Roles</h5>
                    <a href="<?php echo $radAdminUrl; ?>/membership/view?space_id=<?php echo (int)$space['id']; ?>" class="btn btn-sm btn-outline-secondary">
                        View Memberships
                    </a>
                </div>
                <?php if (!empty($assignments)): ?>
                    <div class="table-responsive">
                        <table class="table table-sm align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>User</th>
                                    <th>Role</th>
                                    <th class="text-end">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($assignments as $assignment): ?>
                                    <?php if (empty($assignment['role_id'])) { continue; } ?>
                                    <tr>
                                        <td>
                                            <div class="fw-semibold"><?php echo htmlspecialchars($assignment['user_name'] ?? 'Unknown'); ?></div>
                                            <div class="text-muted small"><?php echo htmlspecialchars($assignment['user_identity'] ?? ''); ?></div>
                                        </td>
                                        <td>
                                            <span class="badge bg-light text-dark">
                                                <?php echo htmlspecialchars($assignment['role_name'] ?? 'Unknown Role'); ?>
                                            </span>
                                        </td>
                                        <td class="text-end">
                                            <a href="<?php echo $radAdminUrl . '/space/removeUser/' . $space['uid'] . '/' . $assignment['role_id'] . '/' . $assignment['user_id']; ?>" class="btn btn-outline-danger btn-sm" title="Remove User from Role">
                                                <i class="bi bi-trash"></i>
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <p class="text-muted mb-0">No users associated with this workspace yet.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<div class="mt-3">
    <a href="<?php echo $radAdminUrl; ?>/space/view" class="btn btn-outline-secondary btn-sm">
        <i class="bi bi-arrow-left-circle"></i> Back to Workspaces
    </a>
</div>
