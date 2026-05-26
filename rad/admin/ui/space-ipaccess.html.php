<?php
$space = $this->runData['data']['space'] ?? [];
$ipRule = $this->runData['data']['ip_access_rule'] ?? ['enabled' => false, 'raw' => ''];
$posted = $this->runData['request']->post ?? [];
$ipEnabled = isset($posted['ip_access_enabled']) ? !empty($posted['ip_access_enabled']) : !empty($ipRule['enabled']);
$ipRaw = isset($posted['ip_access_ips']) ? (string)$posted['ip_access_ips'] : (string)($ipRule['raw'] ?? '');
$detailUrl = $this->runData['route']['rad_admin_url'] . '/space/viewone/' . ($space['uid'] ?? '');
?>

<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <a href="<?php echo htmlspecialchars($detailUrl, ENT_QUOTES); ?>" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left-circle"></i> Back to Workspace
    </a>
</div>

<div class="row g-4">
    <div class="col-lg-8">
        <div class="card shadow-sm border-0">
            <div class="card-header bg-white">
                <strong>Workspace IP Restriction</strong>
            </div>
            <div class="card-body">
                <div class="mb-3">
                    <div class="small text-muted">Workspace</div>
                    <div class="fw-semibold"><?php echo htmlspecialchars($space['s_name'] ?? '', ENT_QUOTES); ?></div>
                    <div class="small text-muted">Slug: <?php echo htmlspecialchars($space['s_slug'] ?? '', ENT_QUOTES); ?></div>
                </div>

                <form method="post" action="<?php echo htmlspecialchars($this->runData['route']['url'], ENT_QUOTES); ?>">
                    <div class="form-check form-switch mb-3">
                        <input class="form-check-input" type="checkbox" role="switch" id="ip_access_enabled" name="ip_access_enabled" value="1" <?php echo $ipEnabled ? 'checked' : ''; ?>>
                        <label class="form-check-label fw-semibold" for="ip_access_enabled">Enable workspace allowlist</label>
                    </div>
                    <div class="mb-3">
                        <label for="ip_access_ips" class="form-label">Allowed IPs</label>
                        <textarea class="form-control" id="ip_access_ips" name="ip_access_ips" rows="6" placeholder="127.0.0.1&#10;::1&#10;203.0.113.10"><?php echo htmlspecialchars($ipRaw, ENT_QUOTES); ?></textarea>
                        <div class="form-text">Use commas or new lines. Exact IPv4/IPv6 matches only.</div>
                    </div>
                    <div class="d-flex gap-2 flex-wrap">
                        <button type="submit" class="btn btn-primary">Save IP Restriction</button>
                        <a href="<?php echo htmlspecialchars($detailUrl, ENT_QUOTES); ?>" class="btn btn-outline-secondary">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card shadow-sm border-0">
            <div class="card-header bg-white">
                <strong>Applies To</strong>
            </div>
            <div class="card-body">
                <p class="text-muted small mb-2">Workspace-scoped DYN requests for this workspace.</p>
                <code>/{workspace_prefix}/<?php echo htmlspecialchars($space['s_slug'] ?? '{workspace_slug}', ENT_QUOTES); ?>/{ms_name}/...</code>
            </div>
        </div>
    </div>
</div>
