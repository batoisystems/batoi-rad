<?php
    // print '<pre>';print_r($this->runData['data']);print '</pre>';
    // form submission url is the self url
    $formSubmissionUrl = $this->runData['route']['url'];
    // print '<pre>';print_r($formSubmissionUrl);print '</pre>';
?>
<?php $routeOptions = $this->runData['data']['route_options'] ?? []; ?>
<form action="<?php print $formSubmissionUrl;?>" method="post" class="needs-validation" novalidate>
    <input type="hidden" name="role_id" value="<?php echo $this->runData['data']['role']['id']; ?>">
    <!-- Role Name -->
    <div class="form-group">
        <label for="s_role_name">Role Name <span class="text-danger">*</span></label>
        <input type="text" class="form-control" name="s_role_name" id="s_role_name" value="<?php echo $this->runData['data']['role']['s_role_name']; ?>" aria-required="true" required autocomplete="nope">
        <div class="invalid-feedback">
            The Role Name can have any characters including blank spaces.
        </div>
        <small id="nameHelp" class="form-text text-muted">The Role name can have any characters including blank spaces.</small>
    </div>
    
    <!-- Scope -->
    <?php $supportsMsScope = (bool)($this->runData['data']['supports_ms_scope'] ?? false); ?>
    <div class="form-group">
        <label for="s_scope">Scope <span class="text-danger">*</span></label>
        <?php $scope = $this->runData['data']['role']['s_scope'] ?? 'platform'; ?>
        <select class="form-control" name="s_scope" id="s_scope">
            <option value="platform" <?php echo $scope === 'platform' ? 'selected': '';?>>Platform (Non-SaaS)</option>
            <option value="workspace" <?php echo $scope === 'workspace' ? 'selected': '';?>>Workspace (SaaS)</option>
        </select>
        <div class="invalid-feedback">
            Please select a scope.
        </div>
        <small id="saasHelp" class="form-text text-muted">Platform = non-SaaS. Workspace = SaaS.</small>
    </div>

    <?php $showDefaultRoute = $scope === 'platform'; ?>
    <div class="form-group" id="route_form_group" style="<?php echo $showDefaultRoute ? '' : 'display:none;'; ?>">
        <label for="s_default_route_id">Default Route</label>
        <?php $selectedRoute = $this->runData['data']['role']['s_default_route_id'] ?? ''; ?>
        <select class="form-control" name="s_default_route_id" id="s_default_route_id">
            <option value="">(None)</option>
            <?php foreach ($routeOptions as $opt): ?>
                <option value="<?php echo (int)$opt['id']; ?>" <?php echo ((int)$selectedRoute === (int)$opt['id']) ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($opt['label']); ?>
                </option>
            <?php endforeach; ?>
        </select>
        <div class="invalid-feedback">
            Please choose a default route.
        </div>
        <small id="defaultRouteHelp" class="form-text text-muted">Optional: map this role to a default route (for portal redirects or dashboards).</small>
    </div>

    <!-- Submit button with an icon -->
    <button type="submit" class="btn btn-primary">
        <i class="bi bi-check-circle"></i> Save
    </button>
    <a href="<?php print $this->runData['route']['rad_admin_url'] . '/role/view';?>" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left-circle"></i> Cancel and Go Back</a>
</form>
