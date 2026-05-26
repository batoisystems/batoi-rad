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

<?php
$gatewayConfig = $this->runData['data']['api_gateway'] ?? ['default_api_types' => ['application'], 'system_tables' => [], 'system_services' => []];
$authInfo = [
    'spaces' => '',
    'access_ips' => $this->runData['data']['api']['s_access_ips'] ?? '',
];
$selectedApiTypes = $this->runData['data']['selected_api_types'] ?? ($authInfo['api_types'] ?? $gatewayConfig['default_api_types']);
$selectedSystemTables = $this->runData['data']['selected_system_tables'] ?? ($authInfo['system_tables'] ?? []);
$selectedSystemServices = $this->runData['data']['selected_system_services'] ?? ($authInfo['system_services'] ?? []);
?>

<div class="row g-4">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-body">
                <form action="<?php print $this->runData['route']['url'];?>" method="post" onsubmit="return validateIPs();">
                    <!-- API Name -->
                    <div class="mb-3">
                        <label for="name" class="form-label">API Name <span class="required-asterisk">*</span></label>
                        <input type="text" class="form-control" id="name" name="s_name" value="<?php print htmlspecialchars($this->runData['data']['api']['s_name'], ENT_QUOTES, 'UTF-8'); ?>" required autocomplete="new-password">
                    </div>

                    <!-- API Identity (Read-only) -->
                    <div class="mb-3">
                        <label for="identity" class="form-label">API Identity</label>
                        <input type="text" class="form-control" id="identity" value="<?php print htmlspecialchars($this->runData['data']['api']['s_identity'], ENT_QUOTES, 'UTF-8'); ?>" readonly>
                        <small class="text-muted">Identity cannot be changed after creation.</small>
                    </div>

                    <!-- API Secret (Optional) -->
                    <div class="mb-3">
                        <label for="secret" class="form-label">API Secret (leave blank to keep current)</label>
                        <div class="input-group">
                            <input type="password" class="form-control" id="secret" name="s_secret" autocomplete="new-password">
                            <button id="generateSecret" type="button" class="btn btn-light border" title="Generate Random Secret"><i class="bi bi-shuffle"></i></button>
                            <button id="copySecret" type="button" class="btn btn-light border" title="Copy Secret"><i class="bi bi-clipboard"></i></button>
                            <button id="toggleSecret" type="button" class="btn btn-light border" title="Show/Hide Secret"><i class="bi bi-eye-fill"></i></button>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Allowed API Types</label>
                        <div class="form-check">
                            <input class="form-check-input api-type-toggle" type="checkbox" id="typeApplication" name="api_types[]" value="application" <?php echo in_array('application', $selectedApiTypes, true) ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="typeApplication">Application API</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input api-type-toggle" type="checkbox" id="typeSystem" name="api_types[]" value="system" <?php echo in_array('system', $selectedApiTypes, true) ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="typeSystem">System API</label>
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
                    </div>


                    <!-- Spaces (for SaaS role only) -->
                    <div class="mb-3" id="spacesDiv">
                        <label for="spaces" class="form-label">Spaces</label>
                        <select class="form-select" id="spaces" name="spaces[]" multiple>
                            <?php
                            $spaces = $this->runData['db']->select('s_space', ['livestatus' => '1'], true);
                            $selectedSpaces = array_filter(array_map('trim', explode(',', $authInfo['spaces'] ?? '')));
                            foreach ($spaces as $space) {
                                $selected = in_array($space['id'], $selectedSpaces) ? 'selected' : '';
                                echo '<option value="'.htmlspecialchars($space['id'], ENT_QUOTES, 'UTF-8').'" '.$selected.'>'.htmlspecialchars($space['s_name'], ENT_QUOTES, 'UTF-8').'</option>';
                            }
                            ?>
                        </select>
                        <small class="text-muted">Only used when Role Type = SaaS.</small>
                    </div>

                    <!-- IP Addresses -->
                    <div class="mb-3">
                        <label for="accessIps" class="form-label">Allowed IPs</label>
                        <textarea class="form-control" id="accessIps" name="s_access_ips" rows="3" placeholder="192.168.1.1, 203.0.113.5"><?php print htmlspecialchars($authInfo['access_ips'], ENT_QUOTES, 'UTF-8'); ?></textarea>
                        <small class="text-muted">Comma-separated list. Leave blank to accept requests from any IP.</small>
                    </div>

                    <button type="submit" class="btn btn-primary"><i class="bi bi-check2-circle"></i> Save Changes</button>
                </form>
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="card h-100">
            <div class="card-body">
                <h6 class="card-title">Rotating Secrets</h6>
                <p class="small text-muted">Use this form whenever you want to rotate secrets or update workspace/IP restrictions. Remember to distribute the new secret to every integration and revoke the previous one from your secret store.</p>
                <h6 class="card-title mt-3">Scoped Access</h6>
                <ul class="small ps-3 mb-0">
                    <li>SaaS keys inherit workspace permissions from the spaces you select.</li>
                    <li>Non-SaaS keys operate globally; use them for platform automation or internal tooling.</li>
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

    // IP Validation
    function validateIPs() {
        const ipField = document.getElementById('accessIps').value.trim();
        if (ipField === '') {
            return true; // Allow empty IP field
        }
        const ips = ipField.split(',');
        const ipPattern = /^(25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)\.(25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)\.(25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)\.(25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)$/;

        for (let i = 0; i < ips.length; i++) {
            const ip = ips[i].trim();
            if (!ipPattern.test(ip)) {
                alert(`Invalid IP address format: ${ip}`);
                return false; // Prevent form submission
            }
        }

        return true; // Allow form submission if all IPs are valid
    }

    function refreshSystemOptions() {
        const systemChecked = document.getElementById('typeSystem').checked;
        const container = document.getElementById('systemOptions');
        if (container) {
            container.style.display = systemChecked ? 'block' : 'none';
        }
    }
    document.querySelectorAll('.api-type-toggle').forEach(function (checkbox) {
        checkbox.addEventListener('change', refreshSystemOptions);
    });
    refreshSystemOptions();
</script>
