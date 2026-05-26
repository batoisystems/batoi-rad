<?php
$maps = $this->runData['data']['scope_maps'] ?? ['ms' => [], 'route' => [], 'api' => []];
$defaults = $this->runData['data']['add_defaults'] ?? [];
$scopeDefault = $defaults['s_scope'] ?? 'microservice';
?>
<div class="card">
    <div class="card-body">
        <h4 class="mb-3">Add Test Plan</h4>
        <form method="post" class="row g-3" id="tp-add-form">
            <div class="col-md-6">
                <label class="form-label">Name</label>
                <input type="text" name="s_name" class="form-control" id="tp-name" required>
            </div>
            <div class="col-md-6">
                <label class="form-label">Scope</label>
                <select name="s_scope" class="form-select" id="tp-scope">
                    <option value="microservice" <?php echo $scopeDefault === 'microservice' ? 'selected' : ''; ?>>Microservicelet</option>
                    <option value="route" <?php echo $scopeDefault === 'route' ? 'selected' : ''; ?>>Route</option>
                    <option value="api" <?php echo $scopeDefault === 'api' ? 'selected' : ''; ?>>API</option>
                </select>
            </div>
            <div class="col-md-12">
                <label class="form-label">Description</label>
                <textarea name="s_description" class="form-control" rows="2"></textarea>
            </div>
            <div class="col-md-6 scope-ms">
                <label class="form-label">Microservicelet</label>
                <select name="s_ms_id" class="form-select" id="tp-ms">
                    <option value="">Select</option>
                    <?php foreach ($maps['ms'] as $id => $name) { ?>
                        <option value="<?php echo (int)$id; ?>" <?php echo (!empty($defaults['s_ms_id']) && (int)$defaults['s_ms_id'] === (int)$id) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($name ?? ''); ?>
                        </option>
                    <?php } ?>
                </select>
            </div>
            <div class="col-md-6 scope-route d-none">
                <label class="form-label">Route</label>
                <select name="s_route_id" class="form-select" id="tp-route">
                    <option value="">Select</option>
                    <?php foreach ($maps['route'] as $id => $name) { ?>
                        <option value="<?php echo (int)$id; ?>" <?php echo (!empty($defaults['s_route_id']) && (int)$defaults['s_route_id'] === (int)$id) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($name ?? ''); ?>
                        </option>
                    <?php } ?>
                </select>
            </div>
            <div class="col-md-6 scope-api d-none">
                <label class="form-label">API Endpoint</label>
                <select name="s_apiendpoint_id" class="form-select" id="tp-api">
                    <option value="">Select</option>
                    <?php foreach ($maps['api'] as $id => $name) { ?>
                        <option value="<?php echo (int)$id; ?>" <?php echo (!empty($defaults['s_apiendpoint_id']) && (int)$defaults['s_apiendpoint_id'] === (int)$id) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($name ?? ''); ?>
                        </option>
                    <?php } ?>
                </select>
            </div>
            <div class="col-md-6">
                <label class="form-label">Auto?</label>
                <select name="s_auto" class="form-select">
                    <option value="N">Manual</option>
                    <option value="Y">Auto</option>
                </select>
            </div>
            <div class="col-12">
                <button type="submit" class="btn btn-primary">Create</button>
                <a href="<?php echo $this->runData['route']['rad_admin_url']; ?>/testplan/view" class="btn btn-outline-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const scopeSelect = document.getElementById('tp-scope');
    const msBlock = document.querySelector('.scope-ms');
    const routeBlock = document.querySelector('.scope-route');
    const apiBlock = document.querySelector('.scope-api');
    const nameInput = document.getElementById('tp-name');
    const msSelect = document.getElementById('tp-ms');
    const routeSelect = document.getElementById('tp-route');
    const apiSelect = document.getElementById('tp-api');
    const refresh = () => {
        const val = scopeSelect.value;
        msBlock.classList.toggle('d-none', val !== 'microservice');
        routeBlock.classList.toggle('d-none', val !== 'route');
        apiBlock.classList.toggle('d-none', val !== 'api');
        autoFillName();
    };
    const autoFillName = () => {
        const val = scopeSelect.value;
        let selectedText = '';
        if (val === 'microservice') {
            selectedText = msSelect.options[msSelect.selectedIndex]?.text || '';
        } else if (val === 'route') {
            selectedText = routeSelect.options[routeSelect.selectedIndex]?.text || '';
        } else {
            selectedText = apiSelect.options[apiSelect.selectedIndex]?.text || '';
        }
        const isNameUntouched = nameInput.value.trim() === '' || nameInput.dataset.autofilled === '1';
        if (isNameUntouched && selectedText) {
            nameInput.value = `Auto: ${selectedText} tests`;
            nameInput.dataset.autofilled = '1';
        }
    };

    scopeSelect.addEventListener('change', refresh);
    msSelect.addEventListener('change', autoFillName);
    routeSelect.addEventListener('change', autoFillName);
    apiSelect.addEventListener('change', autoFillName);
    nameInput.addEventListener('input', () => { nameInput.dataset.autofilled = ''; });
    refresh();
    autoFillName();
});
</script>
