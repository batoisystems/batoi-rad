<?php
$route = $this->runData['data']['route'];
$microservice = $this->runData['data']['microservice'];
$history = $this->runData['data']['history'] ?? [];
$hasBindings = $this->runData['data']['route_has_bindings'] ?? false;
$inheritsBindings = $this->runData['data']['route_inherits_bindings'] ?? false;
$permissionLocked = $this->runData['data']['route_permission_locked'] ?? false;
$effectiveBindingSource = $this->runData['data']['route_effective_binding_source'] ?? 'none';
$effectiveBindingGroups = $this->runData['data']['route_effective_binding_role_groups'] ?? ['platform' => [], 'workspace' => []];
$platformBindingRoles = $effectiveBindingGroups['platform'] ?? [];
$workspaceBindingRoles = $effectiveBindingGroups['workspace'] ?? [];
$createdBy = $this->runData['data']['route_created_by'] ?? 'System';
$updatedBy = $this->runData['data']['route_updated_by'] ?? 'System';

$statusMeta = [
    '0' => ['label' => 'Inactive', 'badge' => 'info'],
    '1' => ['label' => 'Active', 'badge' => 'success'],
    '2' => ['label' => 'Archived', 'badge' => 'danger'],
    '3' => ['label' => 'Suspended', 'badge' => 'warning'],
];
$status = $statusMeta[$route['livestatus']] ?? $statusMeta['0'];
$msUid = $microservice['uid'];
$help = $this->runData['data']['route_help'] ?? [];

$codeUrl = $this->runData['route']['rad_admin_url'] . '/route/code/' . $route['uid'] . '/' . $msUid;
$editUrl = $this->runData['route']['rad_admin_url'] . '/route/edit/' . $route['uid'] . '/' . $msUid;
$helpUrl = $help['view_url'] ?? ($this->runData['route']['rad_admin_url'] . '/route/help/' . $route['uid']);
$helpEditUrl = $help['edit_url'] ?? ($this->runData['route']['rad_admin_url'] . '/route/helpedit/' . $route['uid']);
$bindingsUrl = $this->runData['route']['rad_admin_url'] . '/permissionbindings/view?object_type=route&object_id=' . $route['id'];
$toggleUrl = $this->runData['route']['rad_admin_url'] . '/route/' . ($route['livestatus'] == '1' ? 'archive' : 'activate') . '/' . $route['uid'];
?>

<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <a href="<?php echo htmlspecialchars($this->runData['route']['backlink']); ?>" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left-circle"></i> Back to Microservicelet
    </a>
    <div class="btn-group" role="group" aria-label="Route actions">
        <a href="<?php echo htmlspecialchars($codeUrl); ?>" class="btn btn-outline-secondary"><i class="bi bi-code"></i> Code</a>
        <a href="<?php echo htmlspecialchars($helpUrl); ?>" class="btn btn-outline-dark"><i class="bi bi-journal-text"></i> Help</a>
        <a href="<?php echo htmlspecialchars($editUrl); ?>" class="btn btn-outline-primary"><i class="bi bi-pencil-square"></i> Edit</a>
        <?php if (!$permissionLocked): ?>
            <a href="<?php echo htmlspecialchars($bindingsUrl); ?>" class="btn btn-outline-info"><i class="bi bi-key"></i> Permissions</a>
        <?php endif; ?>
        <a href="<?php echo htmlspecialchars($toggleUrl); ?>" class="btn btn-outline-<?php echo $route['livestatus'] == '1' ? 'danger' : 'success'; ?>">
            <i class="bi bi-<?php echo $route['livestatus'] == '1' ? 'archive' : 'check-circle'; ?>"></i>
            <?php echo $route['livestatus'] == '1' ? 'Archive' : 'Activate'; ?>
        </a>
    </div>
</div>

<div class="card mb-3">
    <div class="card-body">
        <?php
            $testHooks = $this->runData['data']['test_hooks'] ?? [];
            $this->runData['data']['test_hook_scope'] = 'route';
            $this->runData['data']['test_hook_ref'] = $route['id'];
            include $this->runData['config']['dir']['admin'].'/ui/partials/test-hooks.html.php';
        ?>
    </div>
</div>

<div class="card mb-4">
    <div class="card-body">
        <div class="d-flex justify-content-between flex-wrap gap-3">
            <div>
                <h2 class="h4 mb-1"><?php echo htmlspecialchars($route['s_name']); ?></h2>
                <p class="text-muted mb-2"><?php echo $route['s_description'] ? htmlspecialchars($route['s_description']) : 'No description provided.'; ?></p>
                <div class="d-flex flex-wrap gap-2">
                    <span class="badge bg-<?php echo $status['badge']; ?>"><?php echo $status['label']; ?></span>
                    <span class="badge bg-light text-dark"><i class="bi bi-diagram-3 me-1"></i><?php echo htmlspecialchars($microservice['s_name']); ?> (ID: <?php echo (int)$microservice['id']; ?>, UID: <?php echo htmlspecialchars($microservice['uid']); ?>)</span>
                    <span class="badge bg-light text-dark"><i class="bi bi-shield-lock me-1"></i><?php echo strtoupper($route['s_entity_scope']); ?> scope</span>
                    <?php if ($permissionLocked): ?>
                        <span class="badge bg-light text-dark">Public access</span>
                    <?php elseif ($hasBindings): ?>
                        <span class="badge bg-info text-dark">Route bindings active</span>
                    <?php elseif ($inheritsBindings): ?>
                        <span class="badge bg-primary text-white">Inherits microservice bindings</span>
                    <?php else: ?>
                        <span class="badge bg-warning text-dark">No bindings</span>
                    <?php endif; ?>
                    <?php if (empty($route['s_ms_id'])) { ?>
                        <span class="badge bg-danger">Unbound to microservicelet</span>
                    <?php } ?>
                </div>
                <?php if ($permissionLocked): ?>
                    <div class="alert alert-warning mt-3 mb-0">
                        This route belongs to a public microservicelet and remains accessible to everyone. Permission bindings are disabled.
                    </div>
                <?php endif; ?>
            </div>
            <div class="text-end">
                <div class="small text-muted">Route UID</div>
                <code><?php echo htmlspecialchars($route['uid']); ?></code>
                <div class="mt-2 small text-muted">Route ID</div>
                <span class="fw-semibold"><?php echo $route['id']; ?></span>
            </div>
        </div>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-md-4">
        <div class="card h-100">
            <div class="card-body">
                <div class="text-muted text-uppercase small mb-1">Created</div>
                <div class="fw-semibold"><?php echo htmlspecialchars($createdBy); ?></div>
                <div class="small text-muted"><?php echo htmlspecialchars($route['createstamp']); ?></div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card h-100">
            <div class="card-body">
                <div class="text-muted text-uppercase small mb-1">Last updated</div>
                <div class="fw-semibold"><?php echo htmlspecialchars($updatedBy); ?></div>
                <div class="small text-muted"><?php echo htmlspecialchars($route['updatestamp']); ?></div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card h-100">
            <div class="card-body">
                <div class="text-muted text-uppercase small mb-1">Template</div>
                <div class="fw-semibold"><?php echo htmlspecialchars($microservice['s_tpl_name']); ?></div>
                <div class="small text-muted">Inherits from microservice</div>
            </div>
        </div>
    </div>
</div>

<?php if (!$permissionLocked): ?>
<div class="card mb-4">
    <div class="card-header d-flex justify-content-between align-items-center">
        <strong>Permission Bindings</strong>
        <div class="d-flex align-items-center gap-2">
            <a href="<?php echo $this->runData['route']['rad_admin_url'] . '/permissionbindings/view?object_type=route&object_id=' . (int)$route['id']; ?>" class="btn btn-sm btn-outline-secondary">
                <i class="bi bi-key"></i> Manage
            </a>
            <span class="badge bg-<?php echo $effectiveBindingSource === 'direct' ? 'info' : ($effectiveBindingSource === 'inherited' ? 'primary' : 'secondary'); ?>">
                Effective source: <?php echo htmlspecialchars($effectiveBindingSource); ?>
            </span>
        </div>
    </div>
    <div class="card-body">
        <div class="mb-3">
            <div class="text-muted small mb-1">Platform roles</div>
            <?php if (empty($platformBindingRoles)) { ?>
                <div class="text-muted small">No platform roles are bound.</div>
            <?php } else { ?>
                <div class="d-flex flex-wrap gap-2">
                    <?php foreach ($platformBindingRoles as $role) { ?>
                        <span class="badge bg-primary">
                            <?php echo htmlspecialchars($role['role_name']); ?> (ID: <?php echo (int)$role['role_id']; ?>)
                        </span>
                    <?php } ?>
                </div>
            <?php } ?>
        </div>

        <?php if (strtolower((string)($microservice['s_scope'] ?? 'platform')) === 'workspace') { ?>
            <div class="mb-2">
                <div class="text-muted small mb-1">Workspace roles</div>
                <?php if (empty($workspaceBindingRoles)) { ?>
                    <div class="text-muted small">No workspace roles are bound.</div>
                <?php } else { ?>
                    <div class="d-flex flex-wrap gap-2">
                        <?php foreach ($workspaceBindingRoles as $role) { ?>
                            <span class="badge bg-success">
                                <?php echo htmlspecialchars($role['role_name']); ?> (ID: <?php echo (int)$role['role_id']; ?>)
                            </span>
                        <?php } ?>
                    </div>
                <?php } ?>
            </div>
        <?php } ?>

        <div class="small text-muted mt-3">
            <?php if ($effectiveBindingSource === 'direct') { ?>
                Effective access is using direct route bindings.
            <?php } elseif ($effectiveBindingSource === 'inherited') { ?>
                Effective access is inherited from microservicelet bindings.
            <?php } else { ?>
                No effective bindings were found for this route.
            <?php } ?>
        </div>
    </div>
</div>
<?php endif; ?>

<div class="card mb-4">
    <div class="card-header d-flex justify-content-between align-items-center">
        <strong>Help Content</strong>
        <div class="d-flex gap-2">
            <a href="<?php echo htmlspecialchars($helpUrl); ?>" class="btn btn-sm btn-outline-secondary">
                <i class="bi bi-eye"></i> View Help
            </a>
            <a href="<?php echo htmlspecialchars($helpEditUrl); ?>" class="btn btn-sm btn-outline-primary">
                <i class="bi bi-pencil-square"></i> Edit Help
            </a>
        </div>
    </div>
    <div class="card-body">
        <div class="row g-3">
            <div class="col-md-4">
                <div class="text-muted text-uppercase small mb-1">Status</div>
                <div class="fw-semibold"><?php echo !empty($help['exists']) ? 'Available' : 'Not created'; ?></div>
                <div class="small text-muted mt-2">
                    <?php echo !empty($help['beta_exists']) ? 'Beta Help file available.' : 'No beta Help file detected.'; ?>
                </div>
            </div>
            <div class="col-md-4">
                <div class="text-muted text-uppercase small mb-1">Live File</div>
                <code><?php echo htmlspecialchars($help['live_path'] ?? ''); ?></code>
            </div>
            <div class="col-md-4">
                <div class="text-muted text-uppercase small mb-1">Beta File</div>
                <code><?php echo htmlspecialchars($help['beta_path'] ?? ''); ?></code>
            </div>
        </div>
        <div class="mt-3">
            <div class="text-muted text-uppercase small mb-1">Excerpt</div>
            <p class="mb-0 text-muted"><?php echo htmlspecialchars($help['excerpt'] ?? 'No help content created yet.'); ?></p>
        </div>
    </div>
</div>

<div class="card mb-4">
    <div class="card-header">
        <strong>Route Metadata</strong>
    </div>
    <div class="card-body">
        <div class="row">
            <div class="col-md-6">
                <dl class="row mb-0">
                    <dt class="col-sm-4 text-muted">Entity Scope</dt>
                    <dd class="col-sm-8"><?php echo htmlspecialchars($route['s_entity_scope']); ?></dd>
                    <dt class="col-sm-4 text-muted">Access Roles</dt>
                    <dd class="col-sm-8 text-muted">Deprecated (use Permission Bindings)</dd>
                    <dt class="col-sm-4 text-muted">Service Definition</dt>
                    <dd class="col-sm-8"><code><?php echo $route['s_service_definition'] ? htmlspecialchars($route['s_service_definition']) : '{}'; ?></code></dd>
                </dl>
            </div>
            <div class="col-md-6">
                <dl class="row mb-0">
                    <dt class="col-sm-4 text-muted">Microservicelet</dt>
                    <dd class="col-sm-8">
                        <a href="<?php echo $this->runData['route']['rad_admin_url']; ?>/microservice/detail/<?php echo $microservice['uid']; ?>">
                            <?php echo htmlspecialchars($microservice['s_name']); ?>
                        </a>
                        <div class="text-muted small">ID: <?php echo (int)$microservice['id']; ?> · UID: <?php echo htmlspecialchars($microservice['uid']); ?></div>
                    </dd>
                    <dt class="col-sm-4 text-muted">Livestatus</dt>
                    <dd class="col-sm-8"><?php echo $status['label']; ?></dd>
                    <dt class="col-sm-4 text-muted">Default Route</dt>
                    <dd class="col-sm-8"><?php echo ($microservice['s_default_route_id'] == $route['id']) ? 'Yes' : 'No'; ?></dd>
                </dl>
            </div>
        </div>
    </div>
</div>

<div class="card mb-4">
    <div class="card-header d-flex justify-content-between align-items-center">
        <strong>Update History</strong>
        <span class="text-muted small"><?php echo count($history); ?> entries</span>
    </div>
    <div class="card-body">
        <?php if (empty($history)) { ?>
            <p class="mb-0 text-muted">No version history recorded for this route yet.</p>
        <?php } else { ?>
            <div class="table-responsive">
                <table class="table table-sm align-middle">
                    <thead>
                        <tr>
                            <th>Version</th>
                            <th>Modified By</th>
                            <th>Timestamp</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($history as $entry): ?>
                            <?php $collapseId = 'history-' . $entry['id']; ?>
                            <tr>
                                <td>#<?php echo $entry['s_version_number'] ?? ($entry['id']); ?></td>
                                <td><?php echo htmlspecialchars($entry['modifier_label']); ?></td>
                                <td><span class="text-muted small"><?php echo htmlspecialchars($entry['s_modified_timestamp'] ?? ''); ?></span></td>
                                <td class="text-end">
                                    <?php if (!empty($entry['snapshot'])): ?>
                                        <button class="btn btn-sm btn-outline-secondary" type="button" data-bs-toggle="collapse" data-bs-target="#<?php echo $collapseId; ?>">
                                            View snapshot
                                        </button>
                                    <?php else: ?>
                                        <span class="text-muted small">No snapshot</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php if (!empty($entry['snapshot'])): ?>
                                <tr class="collapse" id="<?php echo $collapseId; ?>">
                                    <td colspan="4">
                                        <pre class="bg-dark text-light rounded p-3 mb-0"><?php echo htmlspecialchars($entry['snapshot']); ?></pre>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php } ?>
    </div>
</div>
