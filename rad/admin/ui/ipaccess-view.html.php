<?php
$rule = $this->runData['data']['ip_access_rule'] ?? ['enabled' => false, 'ips' => [], 'invalid' => [], 'raw' => ''];
$configFile = $this->runData['data']['ip_access_config_file'] ?? '';
$clientIp = $this->runData['data']['current_client_ip'] ?? '';
$radAdminUrl = $this->runData['route']['rad_admin_url'];
?>

<div class="row g-4">
    <div class="col-lg-8">
        <div class="card shadow-sm border-0">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start flex-wrap gap-3 mb-4">
                    <div>
                        <h2 class="h4 mb-1">RAD Admin Allowlist</h2>
                        <p class="text-muted mb-0">Control access to <code>/rad-admin/</code> from approved IP addresses. Entity <code>1</code> remains immutable and can always access RAD Admin after authentication.</p>
                    </div>
                    <span class="badge <?php echo !empty($rule['enabled']) ? 'bg-danger' : 'bg-secondary'; ?>">
                        <?php echo !empty($rule['enabled']) ? 'Restriction Enabled' : 'Restriction Disabled'; ?>
                    </span>
                </div>

                <form method="post" action="<?php echo htmlspecialchars($radAdminUrl . '/ipaccess/view'); ?>">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($this->runData['request']->csrf_token ?? '', ENT_QUOTES); ?>">

                    <div class="form-check form-switch mb-3">
                        <input class="form-check-input" type="checkbox" role="switch" id="enabled" name="enabled" value="1" <?php echo !empty($rule['enabled']) ? 'checked' : ''; ?>>
                        <label class="form-check-label fw-semibold" for="enabled">Enable RAD Admin IP restriction</label>
                    </div>

                    <div class="mb-3">
                        <label for="ip" class="form-label">Allowed IP addresses</label>
                        <textarea class="form-control" id="ip" name="ip" rows="8" placeholder="127.0.0.1&#10;::1&#10;203.0.113.10"><?php echo htmlspecialchars($rule['raw'] ?? ''); ?></textarea>
                        <div class="form-text">Use commas or new lines. Only exact IPv4/IPv6 entries are supported.</div>
                    </div>

                    <div class="d-flex gap-2 flex-wrap">
                        <button type="submit" class="btn btn-primary">Save IP Access Settings</button>
                        <a href="<?php echo htmlspecialchars($radAdminUrl . '/home/view'); ?>" class="btn btn-outline-secondary">Back to Dashboard</a>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card shadow-sm border-0 mb-4">
            <div class="card-body">
                <h3 class="h6 text-uppercase text-muted mb-3">Current State</h3>
                <dl class="row mb-0">
                    <dt class="col-sm-5 text-muted">Your IP</dt>
                    <dd class="col-sm-7"><?php echo htmlspecialchars($clientIp !== '' ? $clientIp : 'Unavailable'); ?></dd>
                    <dt class="col-sm-5 text-muted">Valid IPs</dt>
                    <dd class="col-sm-7"><?php echo (int)count($rule['ips'] ?? []); ?></dd>
                    <dt class="col-sm-5 text-muted">Config file</dt>
                    <dd class="col-sm-7"><code><?php echo htmlspecialchars($configFile); ?></code></dd>
                </dl>
            </div>
        </div>

        <div class="card shadow-sm border-0">
            <div class="card-body">
                <h3 class="h6 text-uppercase text-muted mb-3">Coverage</h3>
                <ul class="mb-0 ps-3">
                    <li>This screen controls only RAD Admin access.</li>
                    <li>Platform DYN microservicelets are managed on each microservicelet edit screen.</li>
                    <li>Workspace DYN restriction is managed on each workspace edit screen.</li>
                    <li>API allowlists remain managed by the existing API stack and are unchanged here.</li>
                </ul>
            </div>
        </div>
    </div>
</div>
