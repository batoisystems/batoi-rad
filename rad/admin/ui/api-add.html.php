<?php
$gatewayConfig = $this->runData['data']['api_gateway'] ?? ['default_api_types' => ['application'], 'system_tables' => [], 'system_services' => []];
$selectedApiTypes = $this->runData['data']['selected_api_types'] ?? $gatewayConfig['default_api_types'];
$selectedSystemTables = $this->runData['data']['selected_system_tables'] ?? [];
$selectedSystemServices = $this->runData['data']['selected_system_services'] ?? [];
?>

<div class="card mb-4">
    <div class="card-body d-flex flex-wrap gap-2">
        <a href="<?php echo $this->runData['route']['rad_admin_url']; ?>/apiendpoint/view" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-plug-fill me-1"></i>Gateway Overview
        </a>
        <a href="<?php echo $this->runData['route']['rad_admin_url']; ?>/api/view" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-key me-1"></i>Manage API Keys
        </a>
        <a href="<?php echo $this->runData['route']['rad_admin_url']; ?>/apiendpoint/docs" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-journal-text me-1"></i>API Docs
        </a>
        <a href="<?php echo $this->runData['route']['rad_admin_url']; ?>/apiendpoint/verify" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-activity me-1"></i>Verify Payload
        </a>
    </div>
</div>

<div class="row g-4">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-body">
                <form action="<?php print $this->runData['route']['url'];?>" method="post">
                    <!-- API Name -->
                    <div class="mb-3">
                        <label for="name" class="form-label">API Name <span class="required-asterisk">*</span></label>
                        <input type="text" class="form-control" id="name" name="s_name" required autocomplete="new-password">
                    </div>

                    <!-- API Identity (Auto-generated) -->
                    <div class="mb-3">
                        <label for="identity" class="form-label">API Identity</label>
                        <input type="text" class="form-control" id="identity" value="<?php echo 'api_' . bin2hex(random_bytes(8)); ?>" readonly>
                        <small class="text-muted">Identity is generated automatically. You’ll see the final value after creation.</small>
                    </div>

                    <!-- API Secret -->
                    <div class="mb-3">
                        <label for="secret" class="form-label">API Secret <span class="required-asterisk">*</span></label>
                        <div class="input-group">
                            <input type="password" class="form-control" id="secret" name="s_secret" required autocomplete="new-password">
                            <button id="generateSecret" type="button" class="btn btn-light border" title="Generate Random Secret"><i class="bi bi-shuffle"></i></button>
                            <button id="copySecret" type="button" class="btn btn-light border" title="Copy Secret"><i class="bi bi-clipboard"></i></button>
                            <button id="toggleSecret" type="button" class="btn btn-light border" title="Show/Hide Secret"><i class="bi bi-eye-fill"></i></button>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Allowed API Types <span class="required-asterisk">*</span></label>
                        <div class="form-check">
                            <input class="form-check-input api-type-toggle" type="checkbox" id="typeApplication" name="api_types[]" value="application" <?php echo in_array('application', $selectedApiTypes, true) ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="typeApplication">Application API (microservice routes/controllers)</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input api-type-toggle" type="checkbox" id="typeSystem" name="api_types[]" value="system" <?php echo in_array('system', $selectedApiTypes, true) ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="typeSystem">System API (s_ tables and whitelisted core services)</label>
                        </div>
                    </div>

                    <div class="mb-3" id="systemOptions" style="<?php echo in_array('system', $selectedApiTypes, true) ? '' : 'display:none;'; ?>">
                        <label class="form-label">System Tables</label>
                        <select class="form-select" name="system_tables[]" id="systemTables" multiple>
                            <?php foreach ($gatewayConfig['system_tables'] as $table) { ?>
                                <option value="<?php echo htmlspecialchars($table); ?>" <?php echo in_array($table, $selectedSystemTables, true) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($table); ?>
                                </option>
                            <?php } ?>
                        </select>
                        <small class="text-muted">Leave empty to allow the default configured tables.</small>

                        <label class="form-label mt-3">System Services</label>
                        <select class="form-select" name="system_services[]" id="systemServices" multiple>
                            <?php foreach ($gatewayConfig['system_services'] as $service) {
                                $key = $service['key'];
                                $label = $service['label'] ?? $key;
                                ?>
                                <option value="<?php echo htmlspecialchars($key); ?>" <?php echo in_array($key, $selectedSystemServices, true) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($label); ?>
                                </option>
                            <?php } ?>
                        </select>
                        <small class="text-muted">Restrict to one or more pre-approved service methods.</small>
                    </div>

                    <!-- Spaces (for SaaS role only) -->
                    <div class="mb-3" id="spacesDiv">
                        <label for="spaces" class="form-label">Spaces</label>
                        <select class="form-select" id="spaces" name="spaces[]" multiple>
                            <?php
                            $spaces = $this->runData['db']->select('s_space', ['livestatus' => '1'], true);
                            foreach ($spaces as $space) {
                                echo '<option value="'.$space['id'].'">'.$space['s_name'].'</option>';
                            }
                            ?>
                        </select>
                        <small class="text-muted">Select multiple spaces by holding Ctrl (Windows) or Cmd (Mac).</small>
                    </div>

                    <!-- IP Addresses -->
                    <div class="mb-3">
                        <label for="accessIps" class="form-label">Allowed IPs</label>
                        <textarea class="form-control" id="accessIps" name="s_access_ips" rows="3" placeholder="192.168.1.10, 203.0.113.5"></textarea>
                        <small class="text-muted">Optional. Comma-separated list. Leave blank to allow calls from any IP.</small>
                    </div>

                    <button type="submit" class="btn btn-primary"><i class="bi bi-check2-circle"></i> Create API Key</button>
                </form>
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="card h-100">
            <div class="card-body">
                <h6 class="card-title">Usage Notes</h6>
                <ul class="small ps-3 mb-0">
                    <li>Secrets are hashed and cannot be retrieved later—store them in your vault before leaving the page.</li>
                    <li>SaaS keys can be scoped to Workspaces so each tenant gets its own permissions boundary.</li>
                    <li>Use non-SaaS keys for internal tooling or platform automation.</li>
                    <li>Restrict IPs whenever possible; this acts as an allow list in addition to the security key.</li>
                </ul>
            </div>
        </div>
    </div>
</div>

<script>
    // Toggle secret visibility
    document.getElementById('toggleSecret').addEventListener('click', function() {
        const secretField = document.getElementById('secret');
        if (secretField.type === 'password') {
            secretField.type = 'text';
            this.innerHTML = '<i class="bi bi-eye-slash-fill"></i>';
        } else {
            secretField.type = 'password';
            this.innerHTML = '<i class="bi bi-eye-fill"></i>';
        }
    });

    // Generate random secret
    document.getElementById('generateSecret').addEventListener('click', function() {
        const charset = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*()_-+=<>?";
        let secret = "";
        for (let i = 0; i < 24; i++) {
            const randomIndex = Math.floor(Math.random() * charset.length);
            secret += charset[randomIndex];
        }
        document.getElementById('secret').value = secret;
    });

    // Copy secret to clipboard
    document.getElementById('copySecret').addEventListener('click', function() {
        const secretField = document.getElementById('secret');
        secretField.select();
        document.execCommand('copy');
        alert('Secret copied to clipboard');
    });

    // Show or hide the spaces selection based on role type
    document.getElementById('roleType').addEventListener('change', function() {
        const spacesDiv = document.getElementById('spacesDiv');
        if (this.value === 'saas') {
            spacesDiv.style.display = 'block';
        } else {
            spacesDiv.style.display = 'none';
        }
    });

    // Initially hide the spaces selection if the default is not SaaS
    if (document.getElementById('roleType').value !== 'saas') {
        document.getElementById('spacesDiv').style.display = 'none';
    }

    function refreshSystemOptions() {
        const systemChecked = document.getElementById('typeSystem').checked;
        const container = document.getElementById('systemOptions');
        if (!container) {
            return;
        }
        container.style.display = systemChecked ? 'block' : 'none';
    }
    document.querySelectorAll('.api-type-toggle').forEach(function (checkbox) {
        checkbox.addEventListener('change', refreshSystemOptions);
    });
    refreshSystemOptions();
</script>
