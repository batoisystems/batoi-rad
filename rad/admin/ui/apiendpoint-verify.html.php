<?php
$form = $this->runData['data']['form'];
$msList = $this->runData['data']['ms'];
$routes = $this->runData['data']['routes'];
$apis = $this->runData['data']['apis'];
$payloadPreview = $this->runData['data']['payload_preview'];
$verification = $this->runData['data']['verification'];
$gatewayUrl = $this->runData['data']['gateway_url'];
$gatewayConfig = $this->runData['data']['api_gateway'] ?? ['system_tables' => [], 'system_services' => []];
$targetLists = $this->runData['data']['target_lists'] ?? ['system_table' => [], 'system_service' => []];
$systemCatalog = $this->runData['data']['system_catalog'] ?? ['tables' => [], 'services' => []];
$namedEndpoints = $this->runData['data']['endpoints'] ?? [];
$dynUrlHint = $this->runData['data']['dyn_url_hint'] ?? '';
$apiNavActive = 'verify';
include __DIR__ . '/apiendpoint-nav.partial.php';
?>

<div class="card mb-4 border-0 shadow-sm bg-light">
    <div class="card-body d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3">
        <div>
            <h4 class="mb-1">Gateway Payload Tester</h4>
            <p class="mb-0 text-muted">Craft JSON payloads, attach credentials, and send live requests against <code><?php echo htmlspecialchars($gatewayUrl); ?></code>.</p>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <a href="<?php echo $this->runData['route']['rad_admin_url']; ?>/apiendpoint/services" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-collection me-1"></i>Browse Targets
            </a>
            <a href="<?php echo $this->runData['route']['rad_admin_url']; ?>/apiendpoint/endpoints" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-hdd-network me-1"></i>Named Endpoints
            </a>
        </div>
    </div>
</div>

<div class="card mb-4">
    <div class="card-body">
        <h6 class="card-title">How to Verify a Payload</h6>
        <ol class="mb-0 ps-3">
            <li class="mb-2">Choose the <strong>API Type</strong>. Application APIs target microservice routes; System APIs operate on <code>s_</code> tables or approved service methods.</li>
            <li class="mb-2">For Application APIs, choose the <strong>Microservicelet</strong> and <strong>Route</strong>. The Route list auto-filters when you change the microservice.</li>
            <li class="mb-2">Pick the <strong>Route</strong> you want to invoke. If the route expects parameters, note their names from the Docs page.</li>
            <li class="mb-2">Select the <strong>API Account</strong> that should issue the call, then paste its <strong>Security Key</strong>. This is the secret stored when the API key was created.</li>
            <li class="mb-2">Enter payload parameters (for application) or table criteria/data/service arguments (for system) in the JSON fields. Nested structures are allowed; ensure quotes and commas are correct.</li>
            <li class="mb-2">Click <strong>Send Test Request</strong>. The final payload that goes to the gateway is shown under “Request Payload”; the HTTP status and raw/decoded response appear in the “Response” panel so you can quickly debug errors.</li>
        </ol>
        <small class="text-muted d-block mt-3">Tip: if the gateway responds with an auth error, double-check the security key and confirm the API account is allowed to access the chosen microservice/route.</small>
    </div>
</div>

<div class="card mb-4">
    <div class="card-body">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
            <div>
                <h5 class="card-title mb-1">System Targets & Named Endpoints</h5>
                <p class="mb-0 text-muted">Reference the catalog below when crafting a System API payload or select a named endpoint to auto-fill the definition.</p>
            </div>
            <a href="<?php echo $this->runData['route']['rad_admin_url']; ?>/apiendpoint/endpoints" class="btn btn-outline-primary btn-sm">
                <i class="bi bi-hdd-network me-1"></i>Manage Endpoints
            </a>
        </div>
        <div class="row g-4">
            <div class="col-lg-4">
                <h6 class="text-uppercase small text-muted">Allowed Tables</h6>
                <?php if (!empty($systemCatalog['tables'])) { ?>
                    <div class="d-flex flex-wrap gap-2">
                        <?php foreach ($systemCatalog['tables'] as $table) { ?>
                            <span class="badge text-bg-light"><?php echo htmlspecialchars($table); ?></span>
                        <?php } ?>
                    </div>
                <?php } else { ?>
                    <p class="text-muted mb-0">No tables configured.</p>
                <?php } ?>
            </div>
            <div class="col-lg-8">
                <h6 class="text-uppercase small text-muted">Service Presets</h6>
                <?php if (!empty($systemCatalog['services'])) { ?>
                    <div class="table-responsive">
                        <table class="table table-sm align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>Label</th>
                                    <th>Key</th>
                                    <th>Callable</th>
                                    <th>Args</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($systemCatalog['services'] as $service) { ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($service['label']); ?></td>
                                        <td><code><?php echo htmlspecialchars($service['key']); ?></code></td>
                                        <td><small><?php echo htmlspecialchars($service['callable']); ?></small></td>
                                        <td>
                                            <?php if (!empty($service['args_hint'])) { ?>
                                                <code><?php echo htmlspecialchars(implode(', ', $service['args_hint'])); ?></code>
                                            <?php } else { ?>
                                                <span class="text-muted">—</span>
                                            <?php } ?>
                                        </td>
                                    </tr>
                                <?php } ?>
                            </tbody>
                        </table>
                    </div>
                <?php } else { ?>
                    <p class="text-muted mb-0">No service manifest entries yet.</p>
                <?php } ?>
            </div>
        </div>
        <hr>
        <?php if (!empty($namedEndpoints)) { ?>
            <h6 class="text-uppercase small text-muted">Named Endpoints (s_api_endpoint)</h6>
            <div class="table-responsive">
                <table class="table table-sm mb-0">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Slug</th>
                            <th>Type</th>
                            <th>Target</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($namedEndpoints as $endpoint) { ?>
                            <tr>
                                <td><?php echo htmlspecialchars($endpoint['s_name']); ?></td>
                                <td><code><?php echo htmlspecialchars($endpoint['s_slug']); ?></code></td>
                                <td><?php echo htmlspecialchars($endpoint['s_type']); ?></td>
                                <td><?php echo htmlspecialchars($endpoint['s_target']); ?></td>
                            </tr>
                        <?php } ?>
                    </tbody>
                </table>
            </div>
        <?php } else { ?>
            <p class="text-muted mb-0">No named endpoints yet. Create one to reuse a compound payload.</p>
        <?php } ?>
    </div>
</div>

<div class="card mb-4">
    <div class="card-body">
        <form method="post" action="#gateway-response">
            <div class="row">
                <div class="col-md-4 mb-3">
                    <label for="api_type" class="form-label">API Type</label>
                    <select class="form-select" id="api_type" name="api_type">
                        <option value="application" <?php echo $form['api_type'] === 'application' ? 'selected' : ''; ?>>Application API</option>
                        <option value="system" <?php echo $form['api_type'] === 'system' ? 'selected' : ''; ?>>System API</option>
                    </select>
                </div>
                <div class="col-md-4 mb-3">
                    <label for="endpoint_slug" class="form-label">Named Endpoint (optional)</label>
                    <select class="form-select" id="endpoint_slug" name="endpoint_slug">
                        <option value="">(Optional)</option>
                        <?php foreach ($endpoints as $endpoint) { ?>
                            <option value="<?php echo htmlspecialchars($endpoint['s_slug']); ?>" data-type="<?php echo htmlspecialchars($endpoint['s_type']); ?>" <?php echo ($form['endpoint_slug'] === $endpoint['s_slug']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($endpoint['s_name']); ?>
                            </option>
                        <?php } ?>
                    </select>
                    <small class="text-muted">Managed via s_api_endpoint. Selecting one overrides manual target settings.</small>
                </div>
                <div class="col-md-4 mb-3">
                    <label for="ms_id" class="form-label">Microservicelet</label>
                    <select class="form-select" id="ms_id" name="ms_id">
                        <option value="">Select Microservicelet</option>
                        <?php foreach ($msList as $ms) { ?>
                            <option value="<?php echo $ms['id']; ?>"
                                data-type="<?php echo htmlspecialchars($ms['s_type']); ?>"
                                data-scope="<?php echo htmlspecialchars($ms['s_scope']); ?>"
                                data-name="<?php echo htmlspecialchars($ms['s_name']); ?>"
                                <?php echo ($form['ms_id'] == $ms['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($ms['s_name']); ?> (<?php echo htmlspecialchars($ms['s_type']); ?>)
                            </option>
                        <?php } ?>
                    </select>
                </div>
                <div class="col-md-4 mb-3">
                    <label for="route_id" class="form-label">Route</label>
                    <select class="form-select" id="route_id" name="route_id">
                        <option value="">Select Route</option>
                        <?php foreach ($routes as $route) { ?>
                            <option value="<?php echo $route['id']; ?>" data-ms="<?php echo $route['s_ms_id']; ?>" <?php echo ($form['route_id'] == $route['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($route['s_name'] ?: $route['uid'] ?: $route['id']); ?>
                                (MS #<?php echo $route['s_ms_id']; ?>)
                            </option>
                        <?php } ?>
                    </select>
                    <small class="text-muted">Route list auto-filters when you change the microservicelet.</small>
                    <div class="small text-muted mt-1" id="dyn-url-hint" data-hint="<?php echo htmlspecialchars($dynUrlHint, ENT_QUOTES); ?>">
                        <?php if ($dynUrlHint !== '') { ?>
                            DYN URL pattern: <code><?php echo htmlspecialchars($dynUrlHint); ?></code>
                        <?php } ?>
                    </div>
                </div>
                <div class="col-md-4 mb-3">
                    <label for="api_id" class="form-label">API Account</label>
                    <select class="form-select" id="api_id" name="api_id" required>
                        <option value="">Select API</option>
                        <?php foreach ($apis as $api) { ?>
                            <option value="<?php echo $api['id']; ?>" <?php echo ($form['api_id'] == $api['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($api['s_name']); ?> (<?php echo htmlspecialchars($api['s_identity']); ?>)
                            </option>
                        <?php } ?>
                    </select>
                </div>
            </div>

            <div class="row">
                <div class="col-md-6 mb-3">
                    <label for="security_key" class="form-label">Security Key</label>
                    <input type="password" class="form-control" id="security_key" name="security_key" value="<?php echo htmlspecialchars($form['security_key']); ?>" required>
                    <small class="text-muted">Enter the secret value defined when the API was created.</small>
                </div>
                <div class="col-md-6 mb-3">
                    <label for="params_json" class="form-label">Payload Parameters (JSON)</label>
                    <textarea class="form-control" id="params_json" name="params_json" rows="5" placeholder='{"key":"value"}'><?php echo htmlspecialchars($form['params_json']); ?></textarea>
                    <small class="text-muted">Application API only. Provide parameters as a JSON object.</small>
                </div>
            </div>

            <div id="systemFields" class="border rounded p-3 mb-3" style="<?php echo $form['api_type'] === 'system' ? '' : 'display:none;'; ?>">
                <div class="row">
                    <div class="col-md-4 mb-3">
                        <label for="system_target_type" class="form-label">System target type</label>
                        <select class="form-select" id="system_target_type" name="system_target_type">
                            <option value="table" <?php echo $form['system_target_type'] === 'table' ? 'selected' : ''; ?>>Table</option>
                            <option value="service" <?php echo $form['system_target_type'] === 'service' ? 'selected' : ''; ?>>Service</option>
                        </select>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label for="system_target_select" class="form-label">Target</label>
                        <select class="form-select" id="system_target_select">
                            <option value="">Select a target</option>
                        </select>
                        <input type="text"
                               class="form-control mt-2 d-none"
                               id="system_target_custom"
                               placeholder="Enter custom target">
                        <input type="hidden" id="system_target" name="system_target" value="<?php echo htmlspecialchars($form['system_target']); ?>">
                        <small class="text-muted">Choose from configured tables/services or pick “Custom target” to type one.</small>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label for="system_action" class="form-label">Action</label>
                        <select class="form-select" id="system_action" name="system_action">
                            <option value="select" <?php echo $form['system_action'] === 'select' ? 'selected' : ''; ?>>Select</option>
                            <option value="insert" <?php echo $form['system_action'] === 'insert' ? 'selected' : ''; ?>>Insert</option>
                            <option value="update" <?php echo $form['system_action'] === 'update' ? 'selected' : ''; ?>>Update</option>
                            <option value="delete" <?php echo $form['system_action'] === 'delete' ? 'selected' : ''; ?>>Delete</option>
                        </select>
                        <small class="text-muted">For services this is ignored.</small>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-4 mb-3">
                        <label for="system_criteria" class="form-label">Criteria (JSON object)</label>
                        <textarea class="form-control" id="system_criteria" name="system_criteria" rows="4" placeholder='{"id":1}'><?php echo htmlspecialchars($form['system_criteria']); ?></textarea>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label for="system_data" class="form-label">Data (JSON object)</label>
                        <textarea class="form-control" id="system_data" name="system_data" rows="4" placeholder='{"column":"value"}'><?php echo htmlspecialchars($form['system_data']); ?></textarea>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label for="system_arguments" class="form-label">Service Arguments (JSON array)</label>
                        <textarea class="form-control" id="system_arguments" name="system_arguments" rows="4" placeholder='["arg1","arg2"]'><?php echo htmlspecialchars($form['system_arguments']); ?></textarea>
                    </div>
                </div>
            </div>

            <div class="d-flex justify-content-end gap-2">
                <button type="submit" class="btn btn-primary"><i class="bi bi-play-fill me-1"></i>Send Test Request</button>
            </div>
        </form>
    </div>
</div>

<?php if ($payloadPreview) { ?>
    <div class="card mb-4">
        <div class="card-body">
            <h6 class="card-title">Request Payload</h6>
            <pre class="mb-0"><code><?php echo htmlspecialchars(json_encode($payloadPreview, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)); ?></code></pre>
        </div>
    </div>
<?php } ?>

<?php if ($verification) { ?>
    <div class="card" id="gateway-response">
        <div class="card-body">
            <h6 class="card-title">Response</h6>
            <p class="mb-1"><strong>HTTP Status:</strong> <?php echo $verification['http_code'] ?: 'N/A'; ?></p>
            <?php if ($verification['error']) { ?>
                <div class="alert alert-danger">cURL Error: <?php echo htmlspecialchars($verification['error']); ?></div>
            <?php } ?>
            <?php if ($verification['body'] !== false) { ?>
                <pre class="mb-2"><code><?php echo htmlspecialchars($verification['body']); ?></code></pre>
            <?php } else { ?>
                <p class="text-muted mb-2">No response body.</p>
            <?php } ?>
            <?php if ($verification['decoded']) { ?>
                <details>
                    <summary>Decoded JSON</summary>
                    <pre class="mt-2 mb-0"><code><?php echo htmlspecialchars(json_encode($verification['decoded'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)); ?></code></pre>
                </details>
            <?php } ?>
        </div>
    </div>
<?php } ?>

<script>
(function() {
    const msSelect = document.getElementById('ms_id');
    const routeSelect = document.getElementById('route_id');
    const apiTypeSelect = document.getElementById('api_type');
    const systemFields = document.getElementById('systemFields');
    const paramsField = document.getElementById('params_json');
    const systemAction = document.getElementById('system_action');
    const targetTypeSelect = document.getElementById('system_target_type');
    const endpointSelect = document.getElementById('endpoint_slug');
    const targetSelect = document.getElementById('system_target_select');
    const targetHidden = document.getElementById('system_target');
    const targetCustom = document.getElementById('system_target_custom');
    const dynHint = document.getElementById('dyn-url-hint');
    const targetOptions = <?php echo json_encode($targetLists, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
    const workspacePrefix = <?php echo json_encode(trim((string)($this->runData['config']['sys']['workspace_slug_prefix'] ?? ''), "/ \t\n\r\0\x0B")); ?>;

    function filterRoutes() {
        const msId = msSelect.value;
        Array.from(routeSelect.options).forEach(option => {
            if (!option.value) {
                option.hidden = false;
                option.disabled = false;
                return;
            }
            if (!msId || option.getAttribute('data-ms') === msId) {
                option.hidden = false;
                option.disabled = false;
            } else {
                option.hidden = true;
                option.disabled = true;
                if (routeSelect.value === option.value) {
                    routeSelect.value = '';
                }
            }
        });
    }

    function updateDynHint() {
        if (!dynHint || !msSelect) {
            return;
        }
        const selected = msSelect.options[msSelect.selectedIndex];
        const type = selected ? (selected.getAttribute('data-type') || '') : '';
        if (type.toUpperCase() !== 'DYN') {
            dynHint.innerHTML = '';
            return;
        }
        const scope = (selected.getAttribute('data-scope') || '').toLowerCase();
        const msName = selected.getAttribute('data-name') || '{ms_name}';
        const pattern = scope === 'workspace'
            ? (workspacePrefix ? '/' + workspacePrefix + '/' : '/')
              + '{space_name}' + '/' + msName + '/' + '{route_name}' + '/...'
            : '/' + msName + '/' + '{route_name}' + '/...';
        dynHint.innerHTML = 'DYN URL pattern: <code>' + pattern + '</code>';
    }

    if (msSelect) {
        msSelect.addEventListener('change', filterRoutes);
        msSelect.addEventListener('change', updateDynHint);
        filterRoutes();
        updateDynHint();
    }

    function toggleSections() {
        const endpointChosen = endpointSelect && endpointSelect.value !== '';
        if (endpointChosen) {
            apiTypeSelect.value = 'system';
        }
        const isSystem = apiTypeSelect.value === 'system';
        document.getElementById('ms_id').closest('.col-md-4').style.display = (isSystem && endpointChosen) ? 'none' : (isSystem ? 'none' : '');
        document.getElementById('route_id').closest('.col-md-4').style.display = (isSystem && endpointChosen) ? 'none' : (isSystem ? 'none' : '');
        paramsField.closest('.col-md-6').style.display = isSystem && !endpointChosen ? 'none' : '';
        systemFields.style.display = isSystem ? '' : 'none';
    }
    apiTypeSelect.addEventListener('change', toggleSections);
    if (endpointSelect) {
        endpointSelect.addEventListener('change', toggleSections);
    }
    toggleSections();

    function refreshSystemAction() {
        const targetType = targetTypeSelect.value;
        if (targetType === 'service') {
            systemAction.value = 'call';
            systemAction.disabled = true;
        } else {
            systemAction.disabled = false;
        }
    }
    targetTypeSelect.addEventListener('change', () => {
        refreshSystemAction();
        populateTargetSelect();
    });
    refreshSystemAction();

    function populateTargetSelect() {
        if (!targetSelect) {
            return;
        }
        const typeKey = targetTypeSelect.value === 'service' ? 'system_service' : 'system_table';
        const options = targetOptions[typeKey] || [];
        const currentValue = targetHidden.value.trim();
        targetSelect.innerHTML = '';

        const label = typeKey === 'system_service' ? 'service' : 'table';
        const placeholder = document.createElement('option');
        placeholder.value = '';
        placeholder.textContent = options.length ? `Select a ${label}` : `No ${label}s configured`;
        placeholder.disabled = options.length > 0;
        if (currentValue === '' && options.length > 0) {
            placeholder.selected = true;
        }
        targetSelect.appendChild(placeholder);

        let matched = false;
        options.forEach(value => {
            const opt = document.createElement('option');
            opt.value = value;
            opt.textContent = value;
            if (value === currentValue) {
                opt.selected = true;
                matched = true;
            }
            targetSelect.appendChild(opt);
        });

        const customOption = document.createElement('option');
        customOption.value = '__custom__';
        customOption.textContent = 'Custom target…';
        if (!matched && currentValue !== '') {
            customOption.selected = true;
        }
        if (options.length === 0) {
            customOption.selected = true;
        }
        targetSelect.appendChild(customOption);

        const shouldShowCustom = options.length === 0 || (!matched && currentValue !== '') || customOption.selected;
        toggleCustomTarget(shouldShowCustom);
    }

    function toggleCustomTarget(show) {
        if (!targetCustom) {
            return;
        }
        if (show) {
            targetCustom.classList.remove('d-none');
            if (targetCustom.value.trim() === '') {
                targetCustom.value = targetHidden.value.trim();
            }
            targetHidden.value = targetCustom.value.trim();
        } else {
            targetCustom.classList.add('d-none');
            targetCustom.value = '';
        }
    }

    if (targetSelect) {
        targetSelect.addEventListener('change', () => {
            if (targetSelect.value === '__custom__') {
                toggleCustomTarget(true);
                targetCustom.focus();
            } else {
                toggleCustomTarget(false);
                targetHidden.value = targetSelect.value;
            }
        });
    }
    if (targetCustom) {
        targetCustom.addEventListener('input', () => {
            targetHidden.value = targetCustom.value.trim();
        });
    }

    populateTargetSelect();
})();
</script>
