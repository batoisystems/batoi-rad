<?php
$systemCatalog = $this->runData['data']['system_catalog'] ?? ['tables' => [], 'services' => []];
$namedEndpoints = $this->runData['data']['named_endpoints'] ?? [];
$apiNavActive = 'catalog';
include __DIR__ . '/apiendpoint-nav.partial.php';
?>

<div class="card mb-4 border-0 shadow-sm">
    <div class="card-body">
        <h4 class="mb-1">System Target Catalog</h4>
        <p class="mb-0 text-muted">Reference list of all tables and service callables the gateway accepts. Update <code>api_services.php</code> / config files to change this roster.</p>
    </div>
</div>

<div class="card mb-4">
    <div class="card-body">
        <h5 class="card-title mb-3">System Tables</h5>
        <?php if (!empty($systemCatalog['tables'])) { ?>
            <div class="d-flex flex-wrap gap-2">
                <?php foreach ($systemCatalog['tables'] as $table) { ?>
                    <span class="badge text-bg-light"><?php echo htmlspecialchars($table); ?></span>
                <?php } ?>
            </div>
        <?php } else { ?>
            <p class="text-muted mb-0">No tables are currently whitelisted for the System API.</p>
        <?php } ?>
    </div>
</div>

<div class="card mb-4">
    <div class="card-body">
        <h5 class="card-title mb-3">Service Presets</h5>
        <?php if (!empty($systemCatalog['services'])) { ?>
            <div class="table-responsive">
                <table class="table table-striped table-hover table-sm align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Label</th>
                            <th>Key</th>
                            <th>Callable</th>
                            <th>Arguments</th>
                            <th>Description</th>
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
                                <td><small class="text-muted"><?php echo htmlspecialchars($service['description'] ?? ''); ?></small></td>
                            </tr>
                        <?php } ?>
                    </tbody>
                </table>
            </div>
        <?php } else { ?>
            <p class="text-muted mb-0">No service presets are registered.</p>
        <?php } ?>
    </div>
</div>

<?php if (!empty($namedEndpoints)) { ?>
    <div class="card">
        <div class="card-body">
            <h5 class="card-title mb-3">Named Endpoints</h5>
            <div class="table-responsive">
                <table class="table table-sm table-striped mb-0">
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
        </div>
    </div>
<?php } ?>
