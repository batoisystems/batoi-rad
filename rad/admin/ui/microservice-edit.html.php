<?php
$formSubmissionUrl = $this->runData['route']['url'];
$ms = $this->runData['data']['ms'];
$detailUrl = $this->runData['route']['rad_admin_url'] . '/microservice/detail/' . $ms['uid'];
$routeListUrl = $this->runData['route']['rad_admin_url'] . '/route/view/' . $ms['uid'];
$controllerListUrl = $this->runData['route']['rad_admin_url'] . '/controller/view/' . $ms['uid'];

$reservedTemplates = [
    'api.tpl.php',
    'home.tpl.php',
    'login.tpl.php',
    'maintenance.tpl.php',
    'register.tpl.php',
    'reset-password.tpl.php',
    'error-page.tpl.php'
];
$themeDirectoryPath = $this->runData['config']['dir']['theme'];
$allTemplates = array_filter(scandir($themeDirectoryPath), function($filename) use ($reservedTemplates) {
    return strpos($filename, '.tpl.php') !== false && !in_array($filename, $reservedTemplates, true);
});
$templateDropdown = '';
foreach ($allTemplates as $template) {
    $templateName = str_replace('.tpl.php', '', $template);
    $templateDropdown .= '<option value="' . htmlspecialchars($templateName, ENT_QUOTES) . '" ' . (($ms['s_tpl_name'] ?? '') === $templateName ? 'selected' : '') . '>' . htmlspecialchars($templateName) . '</option>';
}

$routes = $this->runData['db']->select('s_msroute', ['s_ms_id' => $ms['id']], true);
$routesDropdown = '';
foreach ($routes as $route) {
    $routesDropdown .= '<option value="' . (int)$route['id'] . '" ' . (($ms['s_default_route_id'] ?? null) == $route['id'] ? 'selected' : '') . '>' . htmlspecialchars($route['s_name']) . '</option>';
}
?>

<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <a href="<?php echo htmlspecialchars($detailUrl); ?>" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left-circle"></i> Back to Detail
    </a>
    <div class="btn-group" role="group" aria-label="Microservicelet quick actions">
        <a href="<?php echo htmlspecialchars($routeListUrl); ?>" class="btn btn-outline-secondary">
            <i class="bi bi-diagram-3"></i> Routes
        </a>
        <a href="<?php echo htmlspecialchars($controllerListUrl); ?>" class="btn btn-outline-secondary">
            <i class="bi bi-columns-gap"></i> Controllers
        </a>
    </div>
</div>

<div class="card shadow-sm border-0 mb-4">
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-start flex-wrap gap-3">
            <div>
                <div class="text-muted small text-uppercase mb-1">Editing Microservicelet</div>
                <h2 class="h4 mb-1"><?php echo htmlspecialchars($ms['s_name']); ?></h2>
                <p class="text-muted mb-2"><?php echo $ms['s_description'] ? htmlspecialchars($ms['s_description']) : 'No description provided yet.'; ?></p>
                <div class="d-flex flex-wrap gap-2">
                    <span class="badge bg-light text-dark"><?php echo htmlspecialchars($ms['s_type'] ?? ''); ?></span>
                    <span class="badge bg-light text-dark"><?php echo htmlspecialchars($ms['s_scope'] ?? 'platform'); ?></span>
                    <span class="badge bg-light text-dark"><?php echo htmlspecialchars($ms['s_tpl_name'] ?? ''); ?></span>
                </div>
            </div>
            <div class="text-end">
                <div class="small text-muted">UID</div>
                <code><?php echo htmlspecialchars($ms['uid']); ?></code>
                <div class="small text-muted mt-2">ID</div>
                <span class="fw-semibold"><?php echo (int)$ms['id']; ?></span>
            </div>
        </div>
    </div>
</div>

<div class="row g-4">
    <div class="col-lg-8">
        <div class="card shadow-sm border-0">
            <div class="card-header bg-white">
                <strong>Edit Microservicelet</strong>
            </div>
            <div class="card-body">
                <form action="<?php print $formSubmissionUrl; ?>" method="post" class="needs-validation" novalidate>
                    <input type="hidden" name="ms_id" value="<?php echo (int)$ms['id']; ?>">

                    <div class="mb-3">
                        <label for="s_name" class="form-label">Microservicelet Name</label>
                        <input type="text" class="form-control" name="s_name" id="s_name" value="<?php echo htmlspecialchars($ms['s_name']); ?>" aria-required="true" required autocomplete="nope">
                        <div class="invalid-feedback">The microservicelet name must not use spaces or unsupported characters.</div>
                        <div class="form-text">Use lowercase alphanumeric characters and dashes. Renaming changes the filesystem folder when permitted.</div>
                    </div>

                    <div class="mb-3">
                        <label for="s_description" class="form-label">Description</label>
                        <textarea class="form-control" name="s_description" id="s_description" rows="4"><?php echo htmlspecialchars($ms['s_description']); ?></textarea>
                        <div class="form-text">Summarize the purpose of this microservicelet for other admins.</div>
                    </div>

                    <div class="mb-3">
                        <label for="s_type" class="form-label">Type</label>
                        <select class="form-control" name="s_type" id="s_type">
                            <option value="STA" <?php echo ($ms['s_type'] == 'STA') ? 'selected': ''; ?>>Static Route</option>
                            <option value="DYN" <?php echo ($ms['s_type'] == 'DYN') ? 'selected': ''; ?>>Dynamic Route</option>
                            <option value="ID" <?php echo ($ms['s_type'] == 'ID') ? 'selected': ''; ?>>ID-based Route</option>
                            <option value="UID" <?php echo ($ms['s_type'] == 'UID') ? 'selected': ''; ?>>UID-based Route</option>
                        </select>
                        <div class="form-text">Choose the routing model used by this microservicelet.</div>
                    </div>

                    <div class="mb-3">
                        <label for="s_scope" class="form-label">Scope</label>
                        <select class="form-control" name="s_scope" id="s_scope">
                            <option value="platform" <?php echo ($ms['s_scope'] ?? 'platform') === 'platform' ? 'selected' : ''; ?>>Platform</option>
                            <option value="workspace" <?php echo ($ms['s_scope'] ?? '') === 'workspace' ? 'selected' : ''; ?>>Workspace</option>
                            <option value="global" <?php echo ($ms['s_scope'] ?? '') === 'global' ? 'selected' : ''; ?>>Global</option>
                        </select>
                        <div class="form-text">Platform = private non-SaaS, Workspace = SaaS, Global = public.</div>
                    </div>

                    <div class="mb-3">
                        <label for="default_route" class="form-label">Default Route</label>
                        <select class="form-control" name="default_route" id="default_route" required>
                            <?php print $routesDropdown; ?>
                        </select>
                        <div class="form-text">Used when the microservicelet URL is opened without an explicit route.</div>
                    </div>

                    <div class="mb-3">
                        <label for="s_tpl_name" class="form-label">Template</label>
                        <select class="form-control" name="s_tpl_name" id="s_tpl_name">
                            <?php print $templateDropdown; ?>
                        </select>
                        <div class="form-text">Select the theme template used for route rendering.</div>
                    </div>

                    <div class="mb-3">
                        <label for="s_definition" class="form-label">Definition (JSON)</label>
                        <textarea class="form-control" name="s_definition" id="s_definition" rows="6"><?php echo htmlspecialchars($ms['s_definition']); ?></textarea>
                        <div class="form-text">Advanced configuration only. Keep the JSON valid before saving.</div>
                    </div>

                    <div class="d-flex gap-2 flex-wrap">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-check-circle"></i> Save Changes
                        </button>
                        <a href="<?php echo htmlspecialchars($detailUrl); ?>" class="btn btn-outline-secondary">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card shadow-sm border-0 mb-4">
            <div class="card-header bg-white">
                <strong>Runtime Notes</strong>
            </div>
            <div class="card-body">
                <dl class="row mb-0">
                    <dt class="col-sm-5 text-muted">Type</dt>
                    <dd class="col-sm-7"><?php echo htmlspecialchars($ms['s_type'] ?? ''); ?></dd>
                    <dt class="col-sm-5 text-muted">Scope</dt>
                    <dd class="col-sm-7"><?php echo htmlspecialchars($ms['s_scope'] ?? 'platform'); ?></dd>
                    <dt class="col-sm-5 text-muted">Template</dt>
                    <dd class="col-sm-7"><?php echo htmlspecialchars($ms['s_tpl_name'] ?? ''); ?></dd>
                    <dt class="col-sm-5 text-muted">Default Route</dt>
                    <dd class="col-sm-7"><?php echo (int)($ms['s_default_route_id'] ?? 0); ?></dd>
                </dl>
            </div>
        </div>

        <div class="card shadow-sm border-0">
            <div class="card-header bg-white">
                <strong>Tips</strong>
            </div>
            <div class="card-body">
                <ul class="mb-0 ps-3">
                    <li>Rename only when needed. A rename affects the microservicelet folder and can impact linked code paths.</li>
                    <li>Keep scope aligned with your RAD App permission model: platform, workspace, or global.</li>
                    <li>Change type only when you understand the routing consequences for route keys and file naming.</li>
                </ul>
            </div>
        </div>
    </div>
</div>
