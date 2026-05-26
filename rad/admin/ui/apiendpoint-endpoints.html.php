<?php
$endpoints = $this->runData['data']['endpoints'] ?? [];
$editing = $this->runData['data']['editing_endpoint'] ?? null;
$gatewayConfig = $this->runData['data']['api_gateway'] ?? [];
$targetLists = $this->runData['data']['target_lists'] ?? [];
$systemCatalog = $this->runData['data']['system_catalog'] ?? ['tables' => [], 'services' => []];
$types = ['system_table' => 'System Table', 'system_service' => 'System Service', 'utility' => 'Utility', 'vendor' => 'Vendor', 'ai' => 'AI'];
$apiNavActive = 'endpoints';
include __DIR__ . '/apiendpoint-nav.partial.php';
?>

<div class="card mb-4 border-0 shadow-sm">
    <div class="card-body d-flex flex-column flex-lg-row justify-content-between gap-3">
        <div>
            <h4 class="mb-1">Named Endpoint Builder</h4>
            <p class="mb-0 text-muted">Create reusable slugs that wrap system tables, services, utilities, vendors, and AI presets. Reference them via the <code>endpoint</code> field in gateway payloads.</p>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <a href="<?php echo $this->runData['route']['rad_admin_url']; ?>/apiendpoint/verify" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-play-fill me-1"></i>Test Endpoint
            </a>
        </div>
    </div>
</div>

<div class="card mb-4">
    <div class="card-body d-flex flex-wrap justify-content-between gap-3">
        <div>
            <h5 class="card-title mb-2">What are named endpoints?</h5>
            <p class="mb-0 text-muted">
                Each row in <code>s_api_endpoint</code> bundles a system-table action, service preset, utility, vendor profile, or AI preset behind a slug.
                Include <code>endpoint</code> in the gateway payload to reuse these definitions instead of hand-crafting JSON every time.
            </p>
        </div>
    </div>
</div>

<div class="row g-4">
    <div class="col-lg-7">
        <div class="card h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h5 class="card-title mb-0">Registered Endpoints</h5>
                    <a href="<?php echo $this->runData['route']['rad_admin_url']; ?>/apiendpoint/endpoints" class="btn btn-sm btn-outline-secondary">Reset Form</a>
                </div>
                <div class="table-responsive">
                    <table class="table table-sm table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Slug</th>
                                <th>Type</th>
                                <th>Target</th>
                                <th>Description</th>
                                <th class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($endpoints as $endpoint) { ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($endpoint['s_name']); ?></td>
                                    <td><code><?php echo htmlspecialchars($endpoint['s_slug']); ?></code></td>
                                    <td><span class="badge text-bg-light"><?php echo htmlspecialchars($endpoint['s_type']); ?></span></td>
                                    <td><?php echo htmlspecialchars($endpoint['s_target']); ?></td>
                                    <td><small class="text-muted"><?php echo htmlspecialchars($endpoint['s_description'] ?? ''); ?></small></td>
                                    <td class="text-end">
                                        <a class="btn btn-sm btn-outline-primary me-1" href="<?php echo $this->runData['route']['rad_admin_url']; ?>/apiendpoint/endpoints/<?php echo $endpoint['uid']; ?>">
                                            <i class="bi bi-pencil"></i>
                                        </a>
                                        <form method="post" class="d-inline" onsubmit="return confirm('Delete this endpoint definition?');">
                                            <input type="hidden" name="_action" value="delete">
                                            <input type="hidden" name="uid" value="<?php echo htmlspecialchars($endpoint['uid']); ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-danger">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            <?php } ?>
                            <?php if (empty($endpoints)) { ?>
                                <tr><td colspan="6" class="text-center text-muted">No endpoints defined yet.</td></tr>
                            <?php } ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-5">
        <div class="card h-100">
            <div class="card-body">
                <h5 class="card-title mb-3"><?php echo $editing ? 'Edit Endpoint' : 'Add Endpoint'; ?></h5>
                <form method="post">
                    <?php if ($editing) { ?>
                        <input type="hidden" name="uid" value="<?php echo htmlspecialchars($editing['uid']); ?>">
                    <?php } ?>
                    <div class="mb-3">
                        <label class="form-label">Name</label>
                        <input type="text" class="form-control" name="s_name" value="<?php echo htmlspecialchars($editing['s_name'] ?? ''); ?>" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Slug</label>
                        <input type="text" class="form-control" name="s_slug" value="<?php echo htmlspecialchars($editing['s_slug'] ?? ''); ?>" required>
                        <small class="text-muted">Used in payload as <code>endpoint</code>.</small>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Type</label>
                        <select class="form-select" name="s_type" required>
                            <?php foreach ($types as $value => $label) { ?>
                                <option value="<?php echo $value; ?>" <?php echo (($editing['s_type'] ?? '') === $value) ? 'selected' : ''; ?>><?php echo $label; ?></option>
                            <?php } ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Target</label>
                        <div class="input-group">
                            <select class="form-select" id="target_preset">
                                <option value="">Select preset</option>
                                <?php foreach ($targetLists as $key => $values) {
                                    foreach ($values as $value) { ?>
                                        <option value="<?php echo htmlspecialchars($value); ?>" data-type="<?php echo htmlspecialchars($key); ?>">
                                            <?php echo htmlspecialchars($value); ?>
                                        </option>
                                <?php }
                                } ?>
                                <option value="_custom">Custom…</option>
                            </select>
                            <input type="text" class="form-control" id="target_input" name="s_target" value="<?php echo htmlspecialchars($editing['s_target'] ?? ''); ?>" required>
                        </div>
                        <small class="text-muted">Table name, service key, callable alias, vendor profile, or AI preset.</small>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <textarea class="form-control" name="s_description" rows="2"><?php echo htmlspecialchars($editing['s_description'] ?? ''); ?></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Definition (JSON)</label>
                        <textarea class="form-control" name="s_definition" rows="4" placeholder='{"method":"POST"}'><?php echo htmlspecialchars($editing['s_definition'] ?? ''); ?></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Rate Limit (JSON)</label>
                        <textarea class="form-control" name="s_rate_limit" rows="2" placeholder='{"per_minute":60}'><?php echo htmlspecialchars($editing['s_rate_limit'] ?? ''); ?></textarea>
                    </div>
                <div class="mb-3">
                    <label class="form-label">Access Role IDs (comma-separated)</label>
                    <input type="text" class="form-control" name="s_access_role_ids" value="<?php echo htmlspecialchars($editing['s_access_role_ids'] ?? ''); ?>" disabled>
                    <small class="form-text text-muted">Deprecated. Govern access via roles/permission bindings.</small>
                </div>
                <button type="submit" class="btn btn-primary w-100"><?php echo $editing ? 'Update Endpoint' : 'Create Endpoint'; ?></button>
                </form>
                <hr class="my-4">
                <h6 class="card-title mb-2">Tests for this endpoint</h6>
                <?php
                    $this->runData['data']['test_hook_scope'] = $this->runData['data']['test_hook_scope'] ?? 'api';
                    $this->runData['data']['test_hook_ref'] = $this->runData['data']['test_hook_ref'] ?? ($editing['id'] ?? null);
                    if ($editing) {
                        $this->runData['data']['test_hooks'] = $this->runData['data']['test_hooks'] ?? [];
                        include $this->runData['config']['dir']['admin'].'/ui/partials/test-hooks.html.php';
                    } else {
                        echo '<div class="text-muted small">Select an endpoint to view or generate related test plans.</div>';
                    }
                ?>
            </div>
        </div>
    </div>
</div>

<script>
(function() {
    const typeSelect = document.querySelector('select[name="s_type"]');
    const targetPreset = document.getElementById('target_preset');
    const targetInput = document.getElementById('target_input');
    const currentValue = targetInput.value;

    function refreshPresetOptions() {
        const type = typeSelect.value;
        Array.from(targetPreset.options).forEach(option => {
            if (!option.dataset.type || option.value === '' || option.value === '_custom') {
                option.hidden = false;
                return;
            }
            option.hidden = option.dataset.type !== type;
        });
        // try selecting current input
        const match = Array.from(targetPreset.options).find(opt => opt.value === targetInput.value);
        if (match && !match.hidden) {
            match.selected = true;
        } else {
            targetPreset.value = '';
        }
    }

    targetPreset.addEventListener('change', () => {
        if (targetPreset.value === '_custom') {
            targetInput.value = '';
            targetInput.focus();
        } else if (targetPreset.value !== '') {
            targetInput.value = targetPreset.value;
        }
    });

    typeSelect.addEventListener('change', refreshPresetOptions);
    refreshPresetOptions();
})();
</script>
