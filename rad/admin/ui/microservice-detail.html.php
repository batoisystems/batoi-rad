<?php
$ms = $this->runData['data']['ms'];
$stats = $this->runData['data']['stats'];
$defaultRoute = $this->runData['data']['default_route'] ?? null;
$recentRoutes = $this->runData['data']['recent_routes'] ?? [];
$recentControllers = $this->runData['data']['recent_controllers'] ?? [];
$hasBindings = $this->runData['data']['has_bindings'] ?? false;
$branchCounts = $this->runData['data']['branch_counts'] ?? ['beta_routes' => 0];
$branchCanManage = !empty($this->runData['data']['branch_can_manage']);
$branchCanMerge = !empty($this->runData['data']['branch_can_merge']);
$testHooks = $this->runData['data']['test_hooks'] ?? [];
$bindingRoleGroups = $this->runData['data']['permission_binding_role_groups'] ?? ['platform' => [], 'workspace' => []];
$platformBindingRoles = $bindingRoleGroups['platform'] ?? [];
$workspaceBindingRoles = $bindingRoleGroups['workspace'] ?? [];
$filesystemAudit = $this->runData['data']['filesystem_audit'] ?? [
    'directory' => '',
    'directory_exists' => false,
    'expected_route_pattern' => '',
    'registered_class_files' => [],
    'unregistered_class_files' => [],
    'cleanup_candidates' => [],
];
$unregisteredClassFiles = $filesystemAudit['unregistered_class_files'] ?? [];
$cleanupCandidates = $filesystemAudit['cleanup_candidates'] ?? [];
$canRegisterClassFiles = !empty($this->runData['data']['can_register_class_files']);
$canCleanupFiles = !empty($this->runData['data']['can_cleanup_files']);
$ipRule = $this->runData['data']['ip_access_rule'] ?? ['enabled' => false, 'ips' => [], 'raw' => ''];
?>

<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <a href="<?php echo $this->runData['route']['backlink']; ?>" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left-circle"></i> Back to Microservicelets
    </a>
    <div class="d-flex flex-wrap gap-2">
        <a href="<?php echo $this->runData['route']['rad_admin_url'] . '/route/view/' . $ms['uid']; ?>" class="btn btn-outline-secondary">
            <i class="bi bi-diagram-3"></i> View Routes
        </a>
        <a href="<?php echo $this->runData['route']['rad_admin_url'] . '/microservice/ipaccess/' . $ms['uid']; ?>" class="btn btn-outline-secondary">
            <i class="bi bi-shield-lock"></i> IP Restriction
        </a>
        <a href="<?php echo $this->runData['route']['rad_admin_url'] . '/microservice/sniff/' . $ms['uid']; ?>" class="btn btn-outline-secondary">
            <i class="bi bi-clipboard-data"></i> Meta Sniff
        </a>
        <a href="<?php echo $this->runData['route']['rad_admin_url'] . '/microservice/edit/' . $ms['uid']; ?>" class="btn btn-outline-primary">
            <i class="bi bi-pencil-square"></i> Edit
        </a>
        <div class="dropdown">
            <button class="btn btn-outline-secondary dropdown-toggle" type="button" id="msActionsMenu" data-bs-toggle="dropdown" aria-expanded="false">
                <i class="bi bi-three-dots-vertical"></i> More actions
            </button>
            <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="msActionsMenu">
                <li><h6 class="dropdown-header">Manage</h6></li>
                <li>
                    <a class="dropdown-item" href="<?php echo $this->runData['route']['rad_admin_url'] . '/controller/view/' . $ms['uid']; ?>">
                        <i class="bi bi-columns-gap me-2"></i> View Business Classes
                    </a>
                </li>
                <li>
                    <a class="dropdown-item" href="<?php echo $this->runData['route']['rad_admin_url'] . '/permissionbindings/view?object_type=ms&object_id=' . $ms['id']; ?>">
                        <i class="bi bi-key me-2"></i> Permission Bindings
                    </a>
                </li>
                <li>
                    <a class="dropdown-item" href="<?php echo $this->runData['route']['rad_admin_url'] . '/microservice/export/' . $ms['uid']; ?>">
                        <i class="bi bi-box-arrow-up me-2"></i> Export
                    </a>
                </li>
                <?php if ((int)($this->runData['entity']['id'] ?? 0) === 1) { ?>
                    <li><hr class="dropdown-divider"></li>
                    <li><h6 class="dropdown-header">Maintenance</h6></li>
                    <?php if (strtoupper($ms['s_type'] ?? '') !== 'DYN') { ?>
                        <li>
                            <button type="button" class="dropdown-item" data-bs-toggle="modal" data-bs-target="#upgradeToDynModal">
                                <i class="bi bi-arrow-up-circle me-2"></i> Upgrade to DYN
                            </button>
                        </li>
                    <?php } ?>
                    <li>
                        <button type="button" class="dropdown-item" data-bs-toggle="modal" data-bs-target="#overwriteBindingsModal">
                            <i class="bi bi-shield-check me-2"></i> Overwrite Route Permission Bindings
                        </button>
                    </li>
                    <li><hr class="dropdown-divider"></li>
                    <li><h6 class="dropdown-header">State</h6></li>
                    <?php if ($ms['livestatus'] == '1') { ?>
                        <li>
                            <a class="dropdown-item text-danger" href="<?php echo $this->runData['route']['rad_admin_url'] . '/microservice/archive/' . $ms['uid']; ?>">
                                <i class="bi bi-archive me-2"></i> Archive
                            </a>
                        </li>
                    <?php } else { ?>
                        <li>
                            <a class="dropdown-item text-success" href="<?php echo $this->runData['route']['rad_admin_url'] . '/microservice/activate/' . $ms['uid']; ?>">
                                <i class="bi bi-check-circle me-2"></i> Activate
                            </a>
                        </li>
                    <?php } ?>
                <?php } ?>
            </ul>
        </div>
    </div>
</div>

<?php if ((int)($this->runData['entity']['id'] ?? 0) === 1 && strtoupper($ms['s_type'] ?? '') !== 'DYN') { ?>
<div class="modal fade" id="upgradeToDynModal" tabindex="-1" aria-labelledby="upgradeToDynLabel" aria-hidden="true" data-bs-backdrop="false">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <form method="post" action="<?php echo $this->runData['route']['rad_admin_url'] . '/microservice/upgradetodyn/' . $ms['uid']; ?>">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($this->runData['request']->csrf_token ?? '', ENT_QUOTES); ?>">
                <div class="modal-header">
                    <h5 class="modal-title" id="upgradeToDynLabel">Upgrade Microservicelet to DYN</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p class="mb-2">This will convert <strong><?php echo htmlspecialchars($ms['s_name']); ?></strong> to DYN and rename route files to <code>route.{route_name}.*</code>.</p>
                    <div class="alert alert-light border mb-3">
                        <div class="fw-semibold mb-1">Link rewrite rules (optional)</div>
                        <div class="small text-muted">
                            Global/Platform: <code>/{ms_name}/{route_id}/...</code> → <code>/{ms_name}/{route_name}/...</code><br>
                            Workspace: <code>/{ms_name}/{route_id}/{spaceUid}/...</code> → <code>/{prefix}/{space_name}/{ms_name}/{route_name}/...</code>
                        </div>
                    </div>
                    <div class="form-check mb-2">
                        <input class="form-check-input" type="checkbox" value="1" id="rewriteRoutesDyn" name="rewrite_routes">
                        <label class="form-check-label" for="rewriteRoutesDyn">Rewrite links inside route code (optional)</label>
                    </div>
                    <div class="form-check mb-2">
                        <input class="form-check-input" type="checkbox" value="1" id="rewriteThemeDyn" name="rewrite_theme">
                        <label class="form-check-label" for="rewriteThemeDyn">Rewrite links inside the linked theme file (optional)</label>
                    </div>
                    <div class="small text-muted">Tip: This operation does not change route names or database records.</div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-warning">Upgrade to DYN</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php } ?>

<?php if ((int)($this->runData['entity']['id'] ?? 0) === 1) { ?>
<div class="modal fade" id="overwriteBindingsModal" tabindex="-1" aria-labelledby="overwriteBindingsLabel" aria-hidden="true" data-bs-backdrop="false">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <form method="post" action="<?php echo $this->runData['route']['rad_admin_url'] . '/microservice/overwritebindings/' . $ms['uid']; ?>">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($this->runData['request']->csrf_token ?? '', ENT_QUOTES); ?>">
                <div class="modal-header">
                    <h5 class="modal-title" id="overwriteBindingsLabel">Overwrite Route Permission Bindings</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p class="mb-2">
                        This will remove existing <strong>route</strong> bindings for all routes in
                        <strong><?php echo htmlspecialchars($ms['s_name']); ?></strong> and replace them
                        with the <strong>microservicelet</strong> bindings.
                    </p>
                    <div class="alert alert-warning mb-0">
                        <div class="fw-semibold mb-1">Warning</div>
                        <div class="small text-muted">This action cannot be undone. Routes with custom bindings will be overwritten.</div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-warning">Overwrite Bindings</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php } ?>

<div class="card mt-3">
    <div class="card-body">
        <h5 class="card-title mb-0">Test Plans</h5>
        <?php
            $this->runData['data']['test_hooks'] = $testHooks;
            $this->runData['data']['test_hook_scope'] = 'microservice';
            $this->runData['data']['test_hook_ref'] = $ms['id'];
            include $this->runData['config']['dir']['admin'].'/ui/partials/test-hooks.html.php';
        ?>
    </div>
</div>
<div class="card mb-4">
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
            <div>
                <h2 class="h4 mb-1"><?php echo htmlspecialchars($ms['s_name']); ?></h2>
                <p class="text-muted mb-2"><?php echo $ms['s_description'] ? htmlspecialchars($ms['s_description']) : 'No description provided.'; ?></p>
                <div class="d-flex flex-wrap gap-2">
                    <span class="badge <?php echo $ms['livestatus'] == '1' ? 'bg-success' : 'bg-secondary'; ?>">
                        <?php echo $ms['livestatus'] == '1' ? 'Active' : ($ms['livestatus'] == '2' ? 'Archived' : 'Inactive'); ?>
                    </span>
                    <span class="badge bg-info text-dark"><?php echo htmlspecialchars($ms['s_type']); ?></span>
                    <?php $accessScope = (strtolower($ms['s_scope'] ?? '') === 'global') ? 'public' : 'private'; ?>
                    <span class="badge bg-<?php echo $accessScope === 'private' ? 'primary' : 'secondary'; ?>">
                        <?php echo ucfirst($accessScope); ?> scope
                    </span>
                    <?php
                        $scope = $ms['s_scope'] ?? 'platform';
                        $isSaas = ($scope === 'workspace');
                    ?>
                    <span class="badge bg-<?php echo $isSaas ? 'success' : 'warning text-dark'; ?>">
                        <?php echo $isSaas ? 'SaaS (' . htmlspecialchars($scope) . ')' : 'Non-SaaS (' . htmlspecialchars($scope) . ')'; ?>
                    </span>
                    <?php if ($hasBindings) { ?>
                        <span class="badge bg-info text-dark">Permission bindings active</span>
                    <?php } else { ?>
                        <span class="badge bg-warning text-dark">Legacy roles in use</span>
                    <?php } ?>
                </div>
            </div>
            <div class="text-end">
                <div class="small text-muted">ID</div>
                <span class="fw-semibold"><?php echo (int)$ms['id']; ?></span>
                <div class="mt-2 small text-muted">UID</div>
                <code><?php echo htmlspecialchars($ms['uid']); ?></code>
                <div class="mt-2 small text-muted">Template</div>
                <span class="badge bg-light text-dark"><?php echo htmlspecialchars($ms['s_tpl_name']); ?></span>
            </div>
        </div>
    </div>
</div>

<div class="card mb-4">
    <div class="card-body">
        <div class="row">
            <div class="col-md-4">
                <div class="text-muted text-uppercase small mb-1">Access Scope</div>
                <div class="fw-semibold">
                    <?php echo ucfirst($accessScope); ?>
                </div>
                <div class="small text-muted">Determines public vs private exposure.</div>
            </div>
            <div class="col-md-4">
                <div class="text-muted text-uppercase small mb-1">Scope</div>
                <div class="fw-semibold"><?php echo htmlspecialchars($scope); ?></div>
                <div class="small text-muted">Platform = non-SaaS; Workspace/App/Member Org = SaaS; Global = public.</div>
            </div>
            <div class="col-md-4">
                <div class="text-muted text-uppercase small mb-1">Permission Bindings</div>
                <div class="fw-semibold">
                    <?php if ($hasBindings) { ?>
                        <span class="badge bg-success">Active</span>
                    <?php } else { ?>
                        <span class="badge bg-warning text-dark">None</span>
                    <?php } ?>
                </div>
                <?php if ($scope === 'global') { ?>
                    <div class="small text-muted">Global scope; bindings are not required.</div>
                <?php } else { ?>
                    <div class="small text-muted">Manage via Permission Bindings.</div>
                <?php } ?>
            </div>
            <div class="col-md-4 mt-3">
                <div class="text-muted text-uppercase small mb-1">Platform IP Restriction</div>
                <div class="fw-semibold">
                    <?php if (strtoupper((string)($ms['s_type'] ?? '')) === 'DYN' && strtolower((string)($ms['s_scope'] ?? 'platform')) === 'platform' && !empty($ipRule['enabled'])) { ?>
                        <span class="badge bg-danger"><?php echo (int)count($ipRule['ips'] ?? []); ?> allowed IPs</span>
                    <?php } elseif (strtoupper((string)($ms['s_type'] ?? '')) === 'DYN' && strtolower((string)($ms['s_scope'] ?? 'platform')) === 'platform') { ?>
                        <span class="badge bg-secondary">Disabled</span>
                    <?php } else { ?>
                        <span class="badge bg-light text-dark border">Not applicable</span>
                    <?php } ?>
                </div>
                <div class="small text-muted">
                    <?php if (strtoupper((string)($ms['s_type'] ?? '')) === 'DYN' && strtolower((string)($ms['s_scope'] ?? 'platform')) === 'platform') { ?>
                        Managed from the Edit screen for this microservicelet.
                    <?php } else { ?>
                        Only platform-scoped DYN microservicelets support microservice-level IP restriction.
                    <?php } ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php if ($scope !== 'global') { ?>
<div class="card mb-4">
    <div class="card-header d-flex justify-content-between align-items-center">
        <strong>Permission Bindings</strong>
        <div class="d-flex align-items-center gap-2">
            <?php if ((int)($this->runData['entity']['id'] ?? 0) === 1) { ?>
                <button type="button" class="btn btn-sm btn-outline-warning" data-bs-toggle="modal" data-bs-target="#overwriteBindingsModal">
                    <i class="bi bi-arrow-repeat"></i> Sync to Routes
                </button>
            <?php } ?>
            <a href="<?php echo $this->runData['route']['rad_admin_url'] . '/permissionbindings/view?object_type=ms&object_id=' . (int)$ms['id']; ?>" class="btn btn-sm btn-outline-secondary">
                <i class="bi bi-key"></i> Manage
            </a>
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

        <?php if (strtolower((string)$scope) === 'workspace') { ?>
            <div>
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
    </div>
</div>
<?php } ?>

<div class="row g-3 mb-4">
    <div class="col-md-4">
        <div class="card h-100">
            <div class="card-body">
                <div class="text-muted text-uppercase small mb-1">Routes</div>
                <div class="display-6"><?php echo $stats['routes']; ?></div>
                <div class="small text-muted">Registered under this Microservicelet</div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card h-100">
            <div class="card-body">
                <div class="text-muted text-uppercase small mb-1">Business Classes & Data Models</div>
                <div class="display-6"><?php echo $stats['controllers']; ?></div>
                <div class="small text-muted">Business classes and data models available</div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card h-100">
            <div class="card-body">
                <div class="text-muted text-uppercase small mb-1">Default Route</div>
                <?php if ($defaultRoute) { ?>
                    <div class="h5 mb-1">
                        <a href="<?php echo $this->runData['route']['rad_admin_url']; ?>/route/detail/<?php echo $defaultRoute['uid']; ?>">
                            <?php echo htmlspecialchars($defaultRoute['s_name']); ?>
                        </a>
                    </div>
                    <div class="small text-muted">ID: <?php echo (int)$defaultRoute['id']; ?> · UID: <?php echo htmlspecialchars($defaultRoute['uid']); ?></div>
                    <p class="small mb-0 text-muted"><?php echo $defaultRoute['s_description'] ? htmlspecialchars($defaultRoute['s_description']) : 'No description.'; ?></p>
                <?php } else { ?>
                    <p class="mb-0">No default route configured.</p>
                    <span class="badge bg-warning text-dark mt-2">Missing default route</span>
                <?php } ?>
            </div>
        </div>
    </div>
</div>

<div class="row g-4 mb-4">
    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <div>
                    <h3 class="h6 mb-0">Unregistered Business Class Files</h3>
                    <p class="text-muted small mb-0">Any <code>*.cls.php</code> file in this microservicelet folder that is not registered in Business Classes.</p>
                </div>
                <span class="badge bg-light text-dark border"><?php echo count($unregisteredClassFiles); ?> file(s)</span>
            </div>
            <div class="card-body">
                <div class="small text-muted mb-3">
                    Folder: <code><?php echo htmlspecialchars($filesystemAudit['directory'] ?? ''); ?></code>
                </div>
                <?php if (!$filesystemAudit['directory_exists']) { ?>
                    <p class="mb-0 text-muted">The microservicelet folder does not exist yet.</p>
                <?php } elseif (!empty($unregisteredClassFiles)) { ?>
                    <?php if ($canRegisterClassFiles) { ?>
                        <form method="post" action="<?php echo $this->runData['route']['rad_admin_url'] . '/microservice/registerclassfiles/' . $ms['uid']; ?>" class="mb-3">
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($this->runData['request']->csrf_token ?? '', ENT_QUOTES); ?>">
                            <?php foreach ($unregisteredClassFiles as $candidate) { ?>
                                <input type="hidden" name="class_files[]" value="<?php echo htmlspecialchars($candidate['file'], ENT_QUOTES); ?>">
                                <input type="hidden" name="controller_name_map[<?php echo htmlspecialchars($candidate['file'], ENT_QUOTES); ?>]" value="<?php echo htmlspecialchars($candidate['controller_name'] ?? '', ENT_QUOTES); ?>">
                                <input type="hidden" name="class_name_map[<?php echo htmlspecialchars($candidate['file'], ENT_QUOTES); ?>]" value="<?php echo htmlspecialchars($candidate['source_name'] ?? '', ENT_QUOTES); ?>">
                                <input type="hidden" name="source_file_map[<?php echo htmlspecialchars($candidate['file'], ENT_QUOTES); ?>]" value="<?php echo htmlspecialchars($candidate['file'] ?? '', ENT_QUOTES); ?>">
                            <?php } ?>
                            <button type="submit" class="btn btn-sm btn-outline-primary">
                                <i class="bi bi-plus-circle"></i> Add All as Business Classes
                            </button>
                        </form>
                    <?php } ?>
                    <ul class="list-group list-group-flush">
                        <?php foreach ($unregisteredClassFiles as $candidate) { ?>
                            <li class="list-group-item">
                                <div class="d-flex justify-content-between align-items-start gap-3 flex-wrap">
                                    <div>
                                        <div class="fw-semibold"><?php echo htmlspecialchars($candidate['file']); ?></div>
                                        <div class="small text-muted">Suggested internal key: <?php echo htmlspecialchars($candidate['controller_name']); ?></div>
                                        <?php if (!empty($candidate['declared_class_name'])) { ?>
                                            <div class="small text-muted">Detected class name: <?php echo htmlspecialchars($candidate['declared_class_name']); ?></div>
                                        <?php } ?>
                                        <?php if (empty($candidate['is_registerable'])) { ?>
                                            <div class="small text-warning">Filename does not currently match business class naming rules.</div>
                                        <?php } ?>
                                    </div>
                                    <?php if (!$canRegisterClassFiles) { ?>
                                        <span class="badge bg-light text-dark border">Read only</span>
                                    <?php } ?>
                                </div>
                                <?php if ($canRegisterClassFiles) { ?>
                                    <form method="post" action="<?php echo $this->runData['route']['rad_admin_url'] . '/microservice/registerclassfiles/' . $ms['uid']; ?>" class="mt-3">
                                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($this->runData['request']->csrf_token ?? '', ENT_QUOTES); ?>">
                                        <input type="hidden" name="class_file" value="<?php echo htmlspecialchars($candidate['file'], ENT_QUOTES); ?>">
                                        <div class="row g-3 align-items-end">
                                            <div class="col-md-4">
                                                <label class="form-label small text-muted">Internal Key</label>
                                                <input type="text" class="form-control form-control-sm" name="controller_name_map[<?php echo htmlspecialchars($candidate['file'], ENT_QUOTES); ?>]" value="<?php echo htmlspecialchars($candidate['controller_name'] ?? ''); ?>">
                                            </div>
                                            <div class="col-md-4">
                                                <label class="form-label small text-muted">Class Name</label>
                                                <input type="text" class="form-control form-control-sm" name="class_name_map[<?php echo htmlspecialchars($candidate['file'], ENT_QUOTES); ?>]" value="<?php echo htmlspecialchars($candidate['source_name'] ?? ''); ?>">
                                            </div>
                                            <div class="col-md-4">
                                                <label class="form-label small text-muted">Source File</label>
                                                <input type="text" class="form-control form-control-sm" name="source_file_map[<?php echo htmlspecialchars($candidate['file'], ENT_QUOTES); ?>]" value="<?php echo htmlspecialchars($candidate['file'] ?? ''); ?>">
                                            </div>
                                        </div>
                                        <div class="d-flex justify-content-between align-items-center mt-2 flex-wrap gap-2">
                                            <div class="small text-muted">Tip: adjust the internal key when a Data Model already owns the suggested key.</div>
                                            <button type="submit" class="btn btn-sm btn-outline-primary">
                                                Save as Business Class
                                            </button>
                                        </div>
                                    </form>
                                <?php } ?>
                            </li>
                        <?php } ?>
                    </ul>
                <?php } else { ?>
                    <p class="mb-0 text-muted">No unregistered class files found.</p>
                <?php } ?>
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <div>
                    <h3 class="h6 mb-0">Cleanup Candidates</h3>
                    <p class="text-muted small mb-0">Files that are neither registered business classes nor valid route files for the current microservicelet type.</p>
                </div>
                <span class="badge bg-light text-dark border"><?php echo count($cleanupCandidates); ?> file(s)</span>
            </div>
            <div class="card-body">
                <div class="small text-muted mb-2">
                    Expected route files: <code><?php echo htmlspecialchars($filesystemAudit['expected_route_pattern'] ?? ''); ?></code>
                </div>
                <div class="small text-muted mb-3">
                    DYN uses <code>route.{route_name}.*.php</code>; all other types use <code>route.{route_id}.*.php</code>.
                </div>
                <?php if (!$filesystemAudit['directory_exists']) { ?>
                    <p class="mb-0 text-muted">The microservicelet folder does not exist yet.</p>
                <?php } elseif (!empty($cleanupCandidates)) { ?>
                    <?php if ($canCleanupFiles) { ?>
                        <form method="post" action="<?php echo $this->runData['route']['rad_admin_url'] . '/microservice/cleanupfiles/' . $ms['uid']; ?>" class="mb-3">
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($this->runData['request']->csrf_token ?? '', ENT_QUOTES); ?>">
                            <?php foreach ($cleanupCandidates as $candidate) { ?>
                                <input type="hidden" name="cleanup_files[]" value="<?php echo htmlspecialchars($candidate['file'], ENT_QUOTES); ?>">
                            <?php } ?>
                            <button type="submit" class="btn btn-sm btn-outline-danger" onclick="return confirm('Delete all cleanup candidate files from this microservicelet folder?');">
                                <i class="bi bi-trash"></i> Delete All Cleanup Files
                            </button>
                        </form>
                    <?php } ?>
                    <ul class="list-group list-group-flush">
                        <?php foreach ($cleanupCandidates as $candidate) { ?>
                            <li class="list-group-item d-flex justify-content-between align-items-center gap-3">
                                <div>
                                    <div class="fw-semibold"><?php echo htmlspecialchars($candidate['file']); ?></div>
                                    <div class="small text-muted"><?php echo htmlspecialchars($candidate['reason']); ?></div>
                                </div>
                                <?php if ($canCleanupFiles) { ?>
                                    <form method="post" action="<?php echo $this->runData['route']['rad_admin_url'] . '/microservice/cleanupfiles/' . $ms['uid']; ?>" onsubmit="return confirm('Delete <?php echo htmlspecialchars($candidate['file'], ENT_QUOTES); ?>?');">
                                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($this->runData['request']->csrf_token ?? '', ENT_QUOTES); ?>">
                                        <input type="hidden" name="cleanup_file" value="<?php echo htmlspecialchars($candidate['file'], ENT_QUOTES); ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-danger">
                                            Delete / Cleanup
                                        </button>
                                    </form>
                                <?php } else { ?>
                                    <span class="badge bg-light text-dark border">Read only</span>
                                <?php } ?>
                            </li>
                        <?php } ?>
                    </ul>
                <?php } else { ?>
                    <p class="mb-0 text-muted">No cleanup candidates found.</p>
                <?php } ?>
            </div>
        </div>
    </div>
</div>

<div class="card mb-4">
    <div class="card-header bg-white d-flex justify-content-between align-items-center">
        <div>
            <h3 class="h6 mb-0">Beta Branching</h3>
            <p class="text-muted small mb-0">Create, merge, or discard beta branches for routes in this microservicelet.</p>
        </div>
        <span class="badge bg-light text-dark border"><?php echo (int)($branchCounts['beta_routes'] ?? 0); ?> beta route(s)</span>
    </div>
    <?php if ($branchCanManage) { ?>
        <div class="card-body d-flex flex-wrap gap-2">
            <a href="<?php echo $this->runData['route']['rad_admin_url'] . '/microservice/branchcreate/' . $ms['uid']; ?>" class="btn btn-outline-primary btn-sm">
                <i class="bi bi-branch"></i> Initialize Beta for Routes
            </a>
            <?php if ($branchCanMerge) { ?>
                <a href="<?php echo $this->runData['route']['rad_admin_url'] . '/microservice/branchmerge/' . $ms['uid']; ?>" class="btn btn-success btn-sm" onclick="return confirm('Merge beta code into live for all routes?');">
                    <i class="bi bi-git"></i> Merge Beta to Live
                </a>
            <?php } ?>
            <a href="<?php echo $this->runData['route']['rad_admin_url'] . '/microservice/branchdiscard/' . $ms['uid']; ?>" class="btn btn-outline-danger btn-sm" onclick="return confirm('Discard beta branches for all routes?');">
                <i class="bi bi-trash"></i> Discard Beta
            </a>
        </div>
    <?php } else { ?>
        <div class="card-body">
            <p class="text-muted mb-0">Branch controls are available only to system administrators.</p>
        </div>
    <?php } ?>
</div>

<div class="row g-4">
    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0">Recent Routes</h5>
                <a href="<?php echo $this->runData['route']['rad_admin_url'] . '/route/view/' . $ms['uid']; ?>" class="btn btn-sm btn-outline-secondary">
                    View all
                </a>
            </div>
            <div class="card-body">
                <?php if (!empty($recentRoutes)) { ?>
                    <ul class="list-group list-group-flush">
                        <?php foreach ($recentRoutes as $route) { ?>
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                <div>
                                    <div class="fw-semibold"><?php echo htmlspecialchars($route['s_name']); ?></div>
                                    <div class="small text-muted">ID: <?php echo (int)$route['id']; ?> · UID: <?php echo htmlspecialchars($route['uid']); ?></div>
                                    <div class="small text-muted"><?php echo $route['s_description'] ? htmlspecialchars($route['s_description']) : 'No description.'; ?></div>
                                </div>
                                <div class="btn-group btn-group-sm" role="group">
                                    <a href="<?php echo $this->runData['route']['rad_admin_url']; ?>/route/detail/<?php echo $route['uid']; ?>" class="btn btn-outline-secondary">
                                        View
                                    </a>
                                    <a href="<?php echo $this->runData['route']['rad_admin_url']; ?>/route/help/<?php echo $route['uid']; ?>" class="btn btn-outline-dark">
                                        Help
                                    </a>
                                    <a href="<?php echo $this->runData['route']['rad_admin_url'] . '/route/code/' . $route['uid'] . '/' . $ms['uid']; ?>" class="btn btn-outline-primary">
                                        Code
                                    </a>
                                </div>
                            </li>
                        <?php } ?>
                    </ul>
                <?php } else { ?>
                    <p class="mb-0 text-muted">No routes available.</p>
                <?php } ?>
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0">Recent Business Classes & Data Models</h5>
                <a href="<?php echo $this->runData['route']['rad_admin_url'] . '/controller/view/' . $ms['uid']; ?>" class="btn btn-sm btn-outline-secondary">
                    View all
                </a>
            </div>
            <div class="card-body">
                <?php if (!empty($recentControllers)) { ?>
                    <ul class="list-group list-group-flush">
                        <?php foreach ($recentControllers as $controller) { ?>
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                <div>
                                    <div class="fw-semibold"><?php echo htmlspecialchars($controller['s_name']); ?></div>
                                    <div class="small text-muted">ID: <?php echo (int)$controller['id']; ?> · UID: <?php echo htmlspecialchars($controller['uid']); ?></div>
                                    <div class="small text-muted"><?php echo $controller['s_description'] ? htmlspecialchars($controller['s_description']) : 'No description.'; ?></div>
                                </div>
                                <div class="btn-group btn-group-sm" role="group">
                                    <a href="<?php echo $this->runData['route']['rad_admin_url']; ?>/controller/detail/<?php echo $controller['uid']; ?>" class="btn btn-outline-secondary">
                                        View
                                    </a>
                                    <a href="<?php echo $this->runData['route']['rad_admin_url'] . '/controller/code/' . $ms['uid'] . '/' . $controller['s_name']; ?>" class="btn btn-outline-primary">
                                        Code
                                    </a>
                                </div>
                            </li>
                        <?php } ?>
                    </ul>
                <?php } else { ?>
                    <p class="mb-0 text-muted">No controllers available.</p>
                <?php } ?>
            </div>
        </div>
    </div>
</div>
