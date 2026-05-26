<?php
$formSubmissionUrl = $this->runData['route']['url'];
$route = $this->runData['data']['route'];
$ms = $this->runData['data']['ms'];
$detailUrl = $this->runData['route']['rad_admin_url'] . '/route/detail/' . $route['uid'];
$microserviceUrl = $this->runData['route']['rad_admin_url'] . '/microservice/detail/' . $ms['uid'];
$routeListUrl = $this->runData['route']['rad_admin_url'] . '/route/view/' . $ms['uid'];
?>

<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <a href="<?php echo htmlspecialchars($detailUrl); ?>" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left-circle"></i> Back to Detail
    </a>
    <div class="btn-group" role="group" aria-label="Route quick actions">
        <a href="<?php echo htmlspecialchars($routeListUrl); ?>" class="btn btn-outline-secondary">
            <i class="bi bi-diagram-3"></i> All Routes
        </a>
        <a href="<?php echo htmlspecialchars($microserviceUrl); ?>" class="btn btn-outline-secondary">
            <i class="bi bi-boxes"></i> Microservicelet
        </a>
    </div>
</div>

<div class="card shadow-sm border-0 mb-4">
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-start flex-wrap gap-3">
            <div>
                <div class="text-muted small text-uppercase mb-1">Editing Route</div>
                <h2 class="h4 mb-1"><?php echo htmlspecialchars($route['s_name']); ?></h2>
                <p class="text-muted mb-2"><?php echo $route['s_description'] ? htmlspecialchars($route['s_description']) : 'No description provided yet.'; ?></p>
                <div class="d-flex flex-wrap gap-2">
                    <span class="badge bg-light text-dark"><?php echo htmlspecialchars($ms['s_name']); ?></span>
                    <span class="badge bg-light text-dark"><?php echo htmlspecialchars($route['s_entity_scope'] ?? 'U'); ?></span>
                    <span class="badge bg-info-subtle text-info"><?php echo htmlspecialchars($ms['s_type'] ?? 'STA'); ?></span>
                </div>
            </div>
            <div class="text-end">
                <div class="small text-muted">Route UID</div>
                <code><?php echo htmlspecialchars($route['uid']); ?></code>
                <div class="small text-muted mt-2">Microservicelet UID</div>
                <code><?php echo htmlspecialchars($ms['uid']); ?></code>
            </div>
        </div>
    </div>
</div>

<div class="row g-4">
    <div class="col-lg-8">
        <div class="card shadow-sm border-0">
            <div class="card-header bg-white">
                <strong>Edit Route</strong>
            </div>
            <div class="card-body">
                <form action="<?php print $formSubmissionUrl; ?>" method="post" class="needs-validation" novalidate>
                    <input type="hidden" name="ms_id" value="<?php echo htmlspecialchars($this->runData['route']['pathparts'][4]); ?>">
                    <input type="hidden" name="route_id" value="<?php echo htmlspecialchars($this->runData['route']['pathparts'][3]); ?>">

                    <div class="mb-3">
                        <label for="s_name" class="form-label">Route Name</label>
                        <input type="text" class="form-control" name="s_name" id="s_name" value="<?php echo htmlspecialchars($route['s_name']); ?>" aria-required="true" required autocomplete="nope">
                        <div class="invalid-feedback">The route name must be a single segment with letters, numbers, and dashes only.</div>
                        <div class="form-text">Use a single URL segment. Spaces and slashes are removed automatically.</div>
                    </div>

                    <div class="mb-3">
                        <label for="s_description" class="form-label">Description</label>
                        <textarea class="form-control" name="s_description" id="s_description" rows="4"><?php echo htmlspecialchars($route['s_description']); ?></textarea>
                        <div class="form-text">Describe the user-facing or service purpose of this route.</div>
                    </div>

                    <div class="mb-3">
                        <label for="s_entity_scope" class="form-label">Entity Scope</label>
                        <select class="form-control" name="s_entity_scope" id="s_entity_scope" aria-describedby="entityScopeHelp">
                            <option value="UA" <?php echo ($route['s_entity_scope'] == 'UA') ? 'selected': ''; ?>>Both User and API</option>
                            <option value="U" <?php echo ($route['s_entity_scope'] == 'U') ? 'selected': ''; ?>>User Only</option>
                            <option value="A" <?php echo ($route['s_entity_scope'] == 'A') ? 'selected': ''; ?>>API Only</option>
                        </select>
                        <div class="form-text">Choose whether this route is available to users, APIs, or both.</div>
                    </div>

                    <div class="mb-3">
                        <label for="s_service_definition" class="form-label">Service Definition</label>
                        <textarea class="form-control" name="s_service_definition" id="s_service_definition" rows="6"><?php echo htmlspecialchars($route['s_service_definition']); ?></textarea>
                        <div class="form-text">For DYN routes, keep this minimal. Example: <code>{"method":"index"}</code>.</div>
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
                <strong>Route Notes</strong>
            </div>
            <div class="card-body">
                <dl class="row mb-0">
                    <dt class="col-sm-5 text-muted">Microservicelet</dt>
                    <dd class="col-sm-7"><?php echo htmlspecialchars($ms['s_name']); ?></dd>
                    <dt class="col-sm-5 text-muted">Type</dt>
                    <dd class="col-sm-7"><?php echo htmlspecialchars($ms['s_type'] ?? ''); ?></dd>
                    <dt class="col-sm-5 text-muted">Current Scope</dt>
                    <dd class="col-sm-7"><?php echo htmlspecialchars($route['s_entity_scope'] ?? ''); ?></dd>
                </dl>
            </div>
        </div>

        <div class="card shadow-sm border-0">
            <div class="card-header bg-white">
                <strong>Tips</strong>
            </div>
            <div class="card-body">
                <ul class="mb-0 ps-3">
                    <li>Renaming a route will rename the related route files automatically using the active route key convention.</li>
                    <li>Keep service-definition JSON concise and valid. Broken JSON will block save.</li>
                    <li>Use route detail and permission bindings to review effective access after changing route behavior.</li>
                </ul>
            </div>
        </div>
    </div>
</div>
