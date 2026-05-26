<?php
$gatewayUrl = $this->runData['data']['gateway_url'];
$docs = $this->runData['data']['docs'];
$apiAccounts = $this->runData['data']['apis'];
$samplePayloads = $this->runData['data']['sample_payloads'] ?? [];
$systemCatalog = $this->runData['data']['system_catalog'] ?? ['tables' => [], 'services' => []];
$namedEndpoints = $this->runData['data']['named_endpoints'] ?? [];
$apiNavActive = 'docs';
include __DIR__ . '/apiendpoint-nav.partial.php';
?>

<div class="card mb-4 border-0 shadow-sm">
    <div class="card-body d-flex flex-column flex-lg-row justify-content-between gap-3">
        <div>
            <h4 class="mb-1">API Reference</h4>
            <p class="mb-0 text-muted">Browse every routable microservicelet with example payloads and cURL commands. Use this as the contract for integrators.</p>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <a href="<?php echo $this->runData['route']['rad_admin_url']; ?>/apiendpoint/services" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-collection me-1"></i>System Catalog
            </a>
            <a href="<?php echo $this->runData['route']['rad_admin_url']; ?>/apiendpoint/verify" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-activity me-1"></i>Verify Payload
            </a>
        </div>
    </div>
</div>

<div class="card mb-4">
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
            <div>
                <h5 class="card-title mb-2">Gateway Endpoint</h5>
                <p class="mb-0">All payloads must be posted to the gateway URL below in JSON format.</p>
            </div>
            <div class="d-flex flex-wrap gap-2">
                <button type="button"
                    class="btn btn-outline-secondary btn-sm copy-btn"
                    data-copy="<?php echo htmlspecialchars($gatewayUrl, ENT_QUOTES); ?>">
                    <i class="bi bi-clipboard"></i> Copy URL
                </button>
                <button type="button" class="btn btn-outline-secondary btn-sm" id="expand-all">
                    <i class="bi bi-arrows-expand"></i> Expand All
                </button>
                <button type="button" class="btn btn-outline-secondary btn-sm" id="collapse-all">
                    <i class="bi bi-arrows-collapse"></i> Collapse All
                </button>
            </div>
        </div>
        <pre class="mb-0 mt-3"><code><?php echo htmlspecialchars($gatewayUrl); ?></code></pre>
    </div>
</div>

<div class="card mb-4">
    <div class="card-body">
        <h5 class="card-title mb-3">API Types</h5>
        <p class="mb-3 text-muted">The gateway supports two flows. Pass <code>api_type</code> to choose the pipeline that should handle the payload.</p>
        <div class="row g-3">
            <div class="col-lg-6">
                <h6 class="text-uppercase small text-muted">Application API</h6>
                <p class="small text-muted">Targets microservice routes/controllers. Requires <code>ms</code>, <code>route</code>, and optional <code>params</code>. For DYN microservicelets, <code>route</code> should be the route name (URL pattern: <code>/{ms_name}/{route_name}/...</code>, workspace: <code>/{workspace_slug_prefix}/{space_name}/{ms_name}/{route_name}/...</code>).</p>
                <pre class="mb-0"><code><?php echo htmlspecialchars($samplePayloads['application'] ?? '{}'); ?></code></pre>
            </div>
            <div class="col-lg-6">
                <h6 class="text-uppercase small text-muted">System API</h6>
                <p class="small text-muted">Operates on whitelisted <code>s_</code> tables or configured service methods. Provide a <code>system</code> block describing the target.</p>
                <pre class="mb-0"><code><?php echo htmlspecialchars($samplePayloads['system'] ?? '{}'); ?></code></pre>
            </div>
        </div>
    </div>
</div>

<div class="card mb-4">
    <div class="card-body">
        <h5 class="card-title mb-3">System Target Catalog</h5>
        <p class="text-muted">The System API pipeline only accepts the tables/services listed below. Use this list when composing payloads or registering named endpoints.</p>
        <div class="row g-4">
            <div class="col-lg-4">
                <h6 class="text-uppercase small text-muted">Whitelisted Tables</h6>
                <?php if (!empty($systemCatalog['tables'])) { ?>
                    <div class="d-flex flex-wrap gap-2">
                        <?php foreach ($systemCatalog['tables'] as $table) { ?>
                            <span class="badge text-bg-light"><?php echo htmlspecialchars($table); ?></span>
                        <?php } ?>
                    </div>
                <?php } else { ?>
                    <p class="text-muted mb-0">No tables are currently whitelisted.</p>
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
                    <p class="text-muted mb-0">No service presets are available.</p>
                <?php } ?>
            </div>
        </div>
    </div>
</div>

<?php if (!empty($namedEndpoints)) { ?>
<div class="card mb-4">
    <div class="card-body">
        <h5 class="card-title mb-3">Named Endpoints</h5>
        <p class="text-muted">Include the slug as <code>endpoint</code> in your payload (optionally with <code>api_type</code>) to execute these curated routines.</p>
        <div class="table-responsive">
            <table class="table table-sm table-striped mb-0 align-middle">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Slug</th>
                        <th>Type</th>
                        <th>Target</th>
                        <th>Description</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($namedEndpoints as $endpoint) { ?>
                        <tr>
                            <td><?php echo htmlspecialchars($endpoint['s_name']); ?></td>
                            <td><code><?php echo htmlspecialchars($endpoint['s_slug']); ?></code></td>
                            <td><?php echo htmlspecialchars($endpoint['s_type']); ?></td>
                            <td><?php echo htmlspecialchars($endpoint['s_target']); ?></td>
                            <td><small class="text-muted"><?php echo htmlspecialchars($endpoint['s_description'] ?? ''); ?></small></td>
                        </tr>
                    <?php } ?>
                </tbody>
            </table>
        </div>
        <div class="mt-3">
            <a href="<?php echo $this->runData['route']['rad_admin_url']; ?>/apiendpoint/endpoints" class="btn btn-outline-primary btn-sm">
                <i class="bi bi-hdd-network me-1"></i>Manage Named Endpoints
            </a>
        </div>
    </div>
</div>
<?php } ?>

<?php if (!empty($apiAccounts)) { ?>
    <div class="card mb-4">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h5 class="card-title mb-0">API Accounts</h5>
                <a href="<?php echo $this->runData['route']['rad_admin_url']; ?>/api/add" class="btn btn-outline-primary btn-sm">
                    <i class="bi bi-plus-square me-1"></i>Add API
                </a>
            </div>
            <div class="table-responsive">
                <table class="table table-striped table-hover table-sm mb-0">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Name</th>
                            <th>API Key</th>
                            <th>Role Type</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($apiAccounts as $api) {
                            $roleType = 'NA';
                            ?>
                            <tr>
                                <td><?php echo $api['id']; ?></td>
                                <td><?php echo htmlspecialchars($api['s_name']); ?></td>
                                <td>
                                    <code><?php echo htmlspecialchars($api['s_identity']); ?></code>
                                    <button type="button"
                                        class="btn btn-outline-secondary btn-sm copy-btn ms-2"
                                        data-copy="<?php echo htmlspecialchars($api['s_identity'], ENT_QUOTES); ?>">
                                        <i class="bi bi-clipboard"></i>
                                    </button>
                                </td>
                                <td><?php echo htmlspecialchars($roleType); ?></td>
                            </tr>
                        <?php } ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
<?php } else { ?>
    <div class="alert alert-info">No API accounts available. Create one to obtain API keys and secrets.</div>
<?php } ?>

<?php if (empty($docs)) { ?>
    <div class="alert alert-warning">No microservicelets or routes available for documentation.</div>
<?php } ?>

<?php foreach ($docs as $doc) {
    $ms = $doc['microservice'];
    $routes = $doc['routes'];
    $msBodyId = 'ms-body-' . $ms['id'];
    ?>
    <div class="card mb-4">
        <div class="card-header d-flex justify-content-between align-items-start flex-wrap gap-2">
            <div>
                <h5 class="mb-1"><?php echo htmlspecialchars($ms['s_name']); ?></h5>
                <div class="text-muted small">
                    <?php echo strtoupper(htmlspecialchars($ms['s_type'])); ?> microservicelet
                    <?php if (!empty($ms['s_description'])) { ?>
                        · <?php echo htmlspecialchars($ms['s_description']); ?>
                    <?php } ?>
                </div>
            </div>
            <div class="text-end">
                <?php $accessScope = (strtolower($ms['s_scope'] ?? '') === 'global') ? 'public' : 'private'; ?>
                <span class="badge <?php echo $accessScope === 'public' ? 'bg-success' : 'bg-secondary'; ?>">
                    <?php echo ucfirst($accessScope); ?>
                </span>
                <div class="small text-muted">Routes: <?php echo count($routes); ?></div>
                <button type="button"
                    class="btn btn-link btn-sm p-0 ms-2 toggle-ms"
                    data-target="<?php echo $msBodyId; ?>">
                    <span class="toggle-label">Collapse</span>
                </button>
            </div>
        </div>
        <div class="card-body docs-ms-body" data-ms-body="<?php echo $msBodyId; ?>" data-collapsed="false">
            <?php if (empty($routes)) { ?>
                <p class="text-muted mb-0">No routes defined for this microservicelet.</p>
            <?php } else { ?>
                <?php foreach ($routes as $index => $route) {
                    $sampleId = 'sample-' . $ms['id'] . '-' . $route['id'] . '-' . $index;
                    $curlId = 'curl-' . $ms['id'] . '-' . $route['id'] . '-' . $index;
                    $routeBodyId = 'route-body-' . $ms['id'] . '-' . $route['id'] . '-' . $index;
                    ?>
                    <div class="border rounded-3 p-3 mb-3 docs-route">
                        <div class="d-flex justify-content-between align-items-start flex-wrap gap-2">
                            <div>
                                <h6 class="mb-1"><?php echo htmlspecialchars($route['name']); ?></h6>
                                <?php if (!empty($route['description'])) { ?>
                                    <p class="text-muted small mb-2"><?php echo htmlspecialchars($route['description']); ?></p>
                                <?php } ?>
                            </div>
                            <div class="text-end">
                                <span class="badge bg-light text-dark">Route #<?php echo $route['id']; ?></span>
                                <div>
                                    <button type="button"
                                        class="btn btn-link btn-sm p-0 toggle-route"
                                        data-target="<?php echo $routeBodyId; ?>">
                                        <span class="toggle-label">Collapse</span>
                                    </button>
                                </div>
                            </div>
                        </div>

                        <div class="docs-route-body mt-3" data-route-body="<?php echo $routeBodyId; ?>" data-collapsed="false">
                            <div class="mb-3">
                                <div class="small text-uppercase text-muted fw-semibold mb-1">Route Path</div>
                                <div class="d-flex align-items-center gap-2 flex-wrap">
                                    <code class="mb-0"><?php echo htmlspecialchars($route['route_path']); ?></code>
                                    <button type="button"
                                        class="btn btn-outline-secondary btn-sm copy-btn"
                                        data-copy="<?php echo htmlspecialchars($route['route_path'], ENT_QUOTES); ?>">
                                        <i class="bi bi-clipboard"></i> Copy
                                    </button>
                                </div>
                                <?php if (strtoupper($ms['s_type'] ?? '') === 'DYN') { ?>
                                    <?php
                                        $scope = strtolower($ms['s_scope'] ?? 'platform');
                                        $prefix = trim((string)($this->runData['config']['sys']['workspace_slug_prefix'] ?? ''), "/ \t\n\r\0\x0B");
                                        $pattern = $scope === 'workspace'
                                            ? '/' . ($prefix !== '' ? $prefix . '/' : '') . '{space_name}/' . ($ms['s_name'] ?? '{ms_name}') . '/{route_name}/...'
                                            : '/' . ($ms['s_name'] ?? '{ms_name}') . '/{route_name}/...';
                                    ?>
                                    <div class="small text-muted mt-1">DYN URL pattern: <code><?php echo htmlspecialchars($pattern); ?></code></div>
                                <?php } ?>
                            </div>

                            <?php
                                $permissionBindings = $route['permission_bindings'] ?? ['route' => [], 'microservice' => []];
                                $hasRouteBindings = !empty($permissionBindings['route']);
                                $hasMsBindings = !empty($permissionBindings['microservice']);
                            ?>
                            <?php if ($hasRouteBindings || $hasMsBindings) { ?>
                                <div class="mb-3">
                                    <div class="small text-uppercase text-muted fw-semibold mb-1">Permission Bindings</div>
                                    <?php if ($hasRouteBindings) { ?>
                                        <p class="small text-muted mb-1">Route specific</p>
                                        <ul class="small ps-3 mb-2">
                                            <?php foreach ($permissionBindings['route'] as $binding) { ?>
                                                <li>
                                                    <?php echo htmlspecialchars($binding['role_name']); ?>
                                                    <span class="text-muted">(<?php echo htmlspecialchars($binding['access_level']); ?>)</span>
                                                </li>
                                            <?php } ?>
                                        </ul>
                                    <?php } ?>
                                    <?php if ($hasMsBindings) { ?>
                                        <p class="small text-muted mb-1">Microservicelet defaults</p>
                                        <ul class="small ps-3 mb-0">
                                            <?php foreach ($permissionBindings['microservice'] as $binding) { ?>
                                                <li>
                                                    <?php echo htmlspecialchars($binding['role_name']); ?>
                                                    <span class="text-muted">(<?php echo htmlspecialchars($binding['access_level']); ?>)</span>
                                                </li>
                                            <?php } ?>
                                        </ul>
                                    <?php } ?>
                                </div>
                            <?php } ?>

                            <?php if (!empty($route['definition'])) { ?>
                                <div class="mb-3">
                                    <details>
                                        <summary class="small text-uppercase text-muted fw-semibold mb-2">Service Definition</summary>
                                        <pre class="mb-0"><code><?php echo htmlspecialchars(json_encode($route['definition'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)); ?></code></pre>
                                    </details>
                                </div>
                            <?php } ?>

                            <div class="mb-3">
                                <div class="small text-uppercase text-muted fw-semibold mb-1">Sample Payload</div>
                                <pre class="mb-2"><code id="<?php echo $sampleId; ?>"><?php echo htmlspecialchars($route['sample_payload']); ?></code></pre>
                                <button type="button"
                                    class="btn btn-outline-secondary btn-sm copy-btn"
                                    data-copy="<?php echo htmlspecialchars($route['sample_payload'], ENT_QUOTES); ?>">
                                    <i class="bi bi-clipboard"></i> Copy JSON
                                </button>
                            </div>

                            <div>
                                <div class="small text-uppercase text-muted fw-semibold mb-1">cURL Example</div>
                                <pre class="mb-2"><code id="<?php echo $curlId; ?>"><?php echo htmlspecialchars($route['curl_example']); ?></code></pre>
                                <button type="button"
                                    class="btn btn-outline-secondary btn-sm copy-btn"
                                    data-copy="<?php echo htmlspecialchars($route['curl_example'], ENT_QUOTES); ?>">
                                    <i class="bi bi-clipboard"></i> Copy cURL
                                </button>
                            </div>
                        </div>
                    </div>
                <?php } ?>
            <?php } ?>
        </div>
    </div>
<?php } ?>

<script>
(function() {
    const buttons = document.querySelectorAll('.copy-btn');
    const expandAllBtn = document.getElementById('expand-all');
    const collapseAllBtn = document.getElementById('collapse-all');

    const copyText = (text) => {
        if (navigator.clipboard && window.isSecureContext) {
            return navigator.clipboard.writeText(text);
        }

        return new Promise((resolve, reject) => {
            const textarea = document.createElement('textarea');
            textarea.value = text;
            textarea.style.position = 'fixed';
            textarea.style.left = '-1000px';
            textarea.style.top = '-1000px';
            document.body.appendChild(textarea);
            textarea.focus();
            textarea.select();

            try {
                const successful = document.execCommand('copy');
                document.body.removeChild(textarea);
                if (successful) {
                    resolve();
                } else {
                    reject();
                }
            } catch (err) {
                document.body.removeChild(textarea);
                reject(err);
            }
        });
    };

    const showCopied = (btn) => {
        if (!btn.dataset.copyOriginal) {
            btn.dataset.copyOriginal = btn.innerHTML;
        }
        btn.classList.remove('btn-outline-secondary');
        btn.classList.add('btn-success');
        btn.innerHTML = '<i class="bi bi-clipboard-check"></i> Copied';
        setTimeout(() => {
            btn.classList.add('btn-outline-secondary');
            btn.classList.remove('btn-success');
            btn.innerHTML = btn.dataset.copyOriginal;
        }, 2000);
    };

    buttons.forEach((btn) => {
        btn.addEventListener('click', () => {
            const text = btn.getAttribute('data-copy') || '';
            if (!text) {
                return;
            }
            copyText(text)
                .then(() => showCopied(btn))
                .catch(() => showCopied(btn));
        });
    });

    const toggleSection = (body, collapsed, labelEl) => {
        if (!body) {
            return;
        }
        body.style.display = collapsed ? 'none' : '';
        body.dataset.collapsed = collapsed ? 'true' : 'false';
        if (labelEl) {
            labelEl.textContent = collapsed ? 'Expand' : 'Collapse';
        }
    };

    document.querySelectorAll('.toggle-ms').forEach((btn) => {
        btn.addEventListener('click', () => {
            const target = btn.getAttribute('data-target');
            const body = document.querySelector(`[data-ms-body="${target}"]`);
            const collapsed = body.dataset.collapsed === 'true' ? false : true;
            toggleSection(body, collapsed, btn.querySelector('.toggle-label'));
        });
    });

    document.querySelectorAll('.toggle-route').forEach((btn) => {
        btn.addEventListener('click', () => {
            const target = btn.getAttribute('data-target');
            const body = document.querySelector(`[data-route-body="${target}"]`);
            const collapsed = body.dataset.collapsed === 'true' ? false : true;
            toggleSection(body, collapsed, btn.querySelector('.toggle-label'));
        });
    });

    if (expandAllBtn) {
        expandAllBtn.addEventListener('click', () => {
            document.querySelectorAll('.docs-ms-body').forEach((body) => {
                const btn = document.querySelector(`.toggle-ms[data-target="${body.dataset.msBody}"] .toggle-label`);
                toggleSection(body, false, btn);
            });
            document.querySelectorAll('.docs-route-body').forEach((body) => {
                const btn = document.querySelector(`.toggle-route[data-target="${body.dataset.routeBody}"] .toggle-label`);
                toggleSection(body, false, btn);
            });
        });
    }

    if (collapseAllBtn) {
        collapseAllBtn.addEventListener('click', () => {
            document.querySelectorAll('.docs-ms-body').forEach((body) => {
                const btn = document.querySelector(`.toggle-ms[data-target="${body.dataset.msBody}"] .toggle-label`);
                toggleSection(body, true, btn);
            });
            document.querySelectorAll('.docs-route-body').forEach((body) => {
                const btn = document.querySelector(`.toggle-route[data-target="${body.dataset.routeBody}"] .toggle-label`);
                toggleSection(body, true, btn);
            });
        });
    }
})();
</script>
