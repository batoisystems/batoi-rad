<?php
$gatewayUrl = $this->runData['data']['gateway_url'];
$apiAccounts = $this->runData['data']['apis'];
$msList = $this->runData['data']['ms'];
$samplePayloads = $this->runData['data']['sample_payloads'] ?? [];
$systemCatalog = $this->runData['data']['system_catalog'] ?? ['tables' => [], 'services' => []];
$namedEndpoints = $this->runData['data']['named_endpoints'] ?? [];
$filters = $this->runData['data']['filters'] ?? ['q' => '', 'status' => ''];
$stats = [
    ['label' => 'Microservicelets', 'value' => count($msList ?? []), 'icon' => 'bi-diagram-3'],
    ['label' => 'Routes', 'value' => count($this->runData['data']['routes'] ?? []), 'icon' => 'bi-signpost-split'],
    ['label' => 'API Accounts', 'value' => count($apiAccounts ?? []), 'icon' => 'bi-key'],
    ['label' => 'Named Endpoints', 'value' => count($namedEndpoints ?? []), 'icon' => 'bi-hdd-network'],
];
$apiNavActive = 'overview';
include __DIR__ . '/apiendpoint-nav.partial.php';
?>

<div class="card mb-4 border-0 shadow-sm bg-gradient">
    <div class="card-body">
        <div class="d-flex flex-column flex-lg-row align-items-lg-center justify-content-between gap-3">
            <div>
                <h4 class="mb-1">RAD API Gateway</h4>
                <p class="mb-0 text-muted">Inspect the single /api surface, review allowed targets, and deploy named endpoints for repeatable integrations.</p>
            </div>
            <div class="d-flex flex-wrap gap-2">
                <a href="<?php echo $this->runData['route']['rad_admin_url']; ?>/api/add" class="btn btn-primary btn-sm">
                    <i class="bi bi-plus-circle me-1"></i>New API Key
                </a>
                <a href="<?php echo $this->runData['route']['rad_admin_url']; ?>/apiendpoint/verify" class="btn btn-outline-light btn-sm text-dark">
                    <i class="bi bi-activity me-1"></i>Send Test
                </a>
            </div>
        </div>
        <div class="row g-3 mt-2">
            <?php foreach ($stats as $stat) { ?>
                <div class="col-6 col-lg-3">
                    <div class="border rounded-3 px-3 py-2 bg-white h-100">
                        <div class="text-muted small d-flex align-items-center gap-1">
                            <i class="bi <?php echo $stat['icon']; ?>"></i>
                            <?php echo htmlspecialchars($stat['label']); ?>
                        </div>
                        <div class="fs-4 fw-semibold"><?php echo number_format($stat['value']); ?></div>
                    </div>
                </div>
            <?php } ?>
        </div>
    </div>
</div>

<div class="card mb-4">
    <div class="card-body">
        <form class="row g-3" method="get">
            <div class="col-md-6">
                <label class="form-label small text-muted">Search</label>
                <input type="text" name="q" class="form-control form-control-sm" placeholder="Endpoint slug or description" value="<?php echo htmlspecialchars($filters['q']); ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label small text-muted">Status</label>
                <select name="status" class="form-select form-select-sm">
                    <option value="">All</option>
                    <option value="1" <?php echo $filters['status'] === '1' ? 'selected' : ''; ?>>Active</option>
                    <option value="2" <?php echo $filters['status'] === '2' ? 'selected' : ''; ?>>Archived</option>
                    <option value="3" <?php echo $filters['status'] === '3' ? 'selected' : ''; ?>>Suspended</option>
                </select>
            </div>
            <div class="col-md-3 d-flex align-items-end gap-2">
                <button type="submit" class="btn btn-primary btn-sm"><i class="bi bi-funnel me-1"></i>Apply</button>
                <?php if ($filters['q'] !== '' || $filters['status'] !== '') { ?>
                    <a href="<?php echo $this->runData['route']['rad_admin_url']; ?>/apiendpoint/view" class="btn btn-outline-secondary btn-sm">Reset</a>
                <?php } ?>
            </div>
        </form>
    </div>
</div>

<div class="card mb-4">
    <div class="card-body">
        <h5 class="card-title mb-3">Gateway URL</h5>
        <p class="card-text">
            All API requests must be POSTed to <code><?php echo htmlspecialchars($gatewayUrl); ?></code> with the payload shown below.
        </p>
        <ul class="mb-0">
            <li>Content-Type must be <code>application/json</code></li>
            <li>Include the API Key (<code>s_identity</code>) and the Security Key (API secret) inside the payload</li>
            <li>Use the <code>ms</code> and <code>route</code> keys to define which service should respond</li>
        </ul>
    </div>
</div>

<div class="card mb-4">
    <div class="card-body">
        <h5 class="card-title mb-2">Named Endpoints (s_api_endpoint)</h5>
        <p class="mb-1 text-muted">
            Wrap frequently used system/service routines into reusable slugs. Add the slug as <code>endpoint</code> in any payload and the gateway will hydrate its definition automatically.
        </p>
        <p class="mb-0">
            <span class="badge text-bg-primary me-2"><?php echo count($namedEndpoints); ?></span>
            active definition<?php echo count($namedEndpoints) === 1 ? '' : 's'; ?> ready to call.
        </p>
    </div>
</div>

<div class="card mb-4">
    <div class="card-body">
        <h5 class="card-title mb-3">Sample Payloads</h5>
        <p class="text-muted">Set <code>api_type</code> to switch between application (microservice) and system (core tables & services) flows.</p>
        <div class="row g-3">
            <div class="col-lg-6">
                <h6 class="text-uppercase small text-muted">Application API</h6>
                <pre class="mb-2"><code><?php echo htmlspecialchars($samplePayloads['application'] ?? '{}'); ?></code></pre>
            </div>
            <div class="col-lg-6">
                <h6 class="text-uppercase small text-muted">System API</h6>
                <pre class="mb-2"><code><?php echo htmlspecialchars($samplePayloads['system'] ?? '{}'); ?></code></pre>
            </div>
        </div>
        <small class="text-muted">Security Key is the secret defined while creating the API account. It cannot be retrieved once saved.</small>
    </div>
</div>


<?php if (!empty($apiAccounts)) { ?>
    <div class="card mb-4">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-center mb-2">
                <h5 class="card-title mb-0">API Accounts</h5>
                <div class="btn-group">
                    <a href="<?php echo $this->runData['route']['rad_admin_url']; ?>/api/add" class="btn btn-primary btn-sm">
                        <i class="bi bi-plus-circle me-1"></i>Add API Key
                    </a>
                    <a href="<?php print $this->runData['route']['rad_admin_url'];?>/api/view" class="btn btn-outline-secondary btn-sm">
                        <i class="bi bi-key me-1"></i>Manage Keys
                    </a>
                    <a href="<?php echo $this->runData['route']['rad_admin_url']; ?>/apiendpoint/docs" class="btn btn-outline-secondary btn-sm">
                        <i class="bi bi-journal-text me-1"></i>API Docs
                    </a>
                    <a href="<?php echo $this->runData['route']['rad_admin_url']; ?>/apiendpoint/verify" class="btn btn-outline-secondary btn-sm">
                        <i class="bi bi-activity me-1"></i>Verify Payload
                    </a>
                </div>
            </div>
            <div class="table-responsive">
                <table class="table table-striped table-hover table-sm mb-0">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Name</th>
                            <th>API Key</th>
                            <th>Role Type</th>
                            <th>Allowed IPs</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($apiAccounts as $api) { ?>
                            <tr>
                                <td><?php echo $api['id']; ?></td>
                                <td><?php echo htmlspecialchars($api['s_name']); ?></td>
                                <td><code><?php echo htmlspecialchars($api['s_identity']); ?></code></td>
                                <td>NA</td>
                                <td><?php echo !empty($api['s_access_ips']) ? htmlspecialchars($api['s_access_ips']) : 'NA'; ?></td>
                            </tr>
                        <?php } ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
<?php } else { ?>
    <div class="alert alert-warning">No API accounts exist yet. Create one to get API and security keys.</div>
<?php } ?>

<?php if (!empty($msList)) { ?>
    <div class="card">
        <div class="card-body">
            <h5 class="card-title mb-3">Available Microservicelet Targets</h5>
            <p class="text-muted">
                Microservicelet names map to the <code>ms</code> property inside the payload and determine which services/routes are invoked.
            </p>
            <div class="table-responsive">
                <table class="table table-bordered table-hover table-sm mb-0">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Name</th>
                            <th>Type</th>
                            <th>Access Scope</th>
                            <th>Default Route ID</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($msList as $ms) { ?>
                            <tr>
                                <td><?php echo $ms['id']; ?></td>
                                <td><?php echo htmlspecialchars($ms['s_name']); ?></td>
                                <td>
                                    <?php echo htmlspecialchars($ms['s_type']); ?>
                                    <?php if (strtoupper($ms['s_type'] ?? '') === 'DYN') {
                                        $scope = strtolower($ms['s_scope'] ?? 'platform');
                                        $pattern = $scope === 'workspace'
                                            ? '/{space_slug}/' . ($ms['s_name'] ?? '{ms_name}') . '/{route_name}/...'
                                            : '/' . ($ms['s_name'] ?? '{ms_name}') . '/{route_name}/...';
                                    ?>
                                        <span class="ms-1 text-muted d-inline-flex align-items-center" title="DYN URL pattern: <?php echo htmlspecialchars($pattern, ENT_QUOTES); ?>">
                                            <i class="bi bi-info-circle align-middle"></i>
                                        </span>
                                    <?php } ?>
                                </td>
                                <?php $accessScope = (strtolower($ms['s_scope'] ?? '') === 'global') ? 'public' : 'private'; ?>
                                <td><?php echo htmlspecialchars($accessScope); ?></td>
                                <td><?php echo $ms['s_default_route_id']; ?></td>
                            </tr>
                        <?php } ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
<?php } else { ?>
    <div class="alert alert-info mt-3">No Microservicelets currently expose API-compatible routes.</div>
<?php } ?>
