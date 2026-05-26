<?php
$provider = $this->runData['data']['provider'] ?? [];
$radAdminUrl = $this->runData['route']['rad_admin_url'] ?? '';
$baseUrl = rtrim((string)($this->runData['config']['sys']['base_url'] ?? ''), '/');
$isEdit = !empty($provider['id']);
$pref = function ($key, $default = '') use ($provider) {
    return htmlspecialchars((string)($provider[$key] ?? $default));
};
$claimMap = isset($provider['s_claim_map']) && $provider['s_claim_map'] !== null
    ? (is_string($provider['s_claim_map']) ? $provider['s_claim_map'] : json_encode($provider['s_claim_map'], JSON_PRETTY_PRINT))
    : '';
$action = $isEdit ? $radAdminUrl . '/sso/edit/' . (int)$provider['id'] : $radAdminUrl . '/sso/add';
$currentRedirectPath = trim((string)($provider['s_redirect_path'] ?? '/login/sso-callback'));
if ($currentRedirectPath === '/login/sso/callback') {
    $currentRedirectPath = '/login/sso-callback';
}
if ($currentRedirectPath === '') {
    $currentRedirectPath = '/login/sso-callback';
}
if ($currentRedirectPath[0] !== '/') {
    $currentRedirectPath = '/' . $currentRedirectPath;
}
$callbackUrl = $baseUrl . $currentRedirectPath;
?>
<div class="card shadow-sm mb-3">
    <div class="card-body">
        <h2 class="h5 mb-2"><?php echo $isEdit ? 'Edit' : 'Add'; ?> SSO Provider</h2>
        <p class="text-muted small mb-0">Set provider details for OIDC/OAuth login. Fields are grouped to reduce setup errors.</p>
    </div>
</div>

<form method="post" action="<?php echo $action; ?>">
    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($this->runData['request']->csrf_token); ?>">

    <div class="card shadow-sm mb-3">
        <div class="card-body">
            <h3 class="h6 mb-3">1) Basic Details</h3>
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">Provider Name</label>
                    <input type="text" class="form-control" name="s_provider_name" required value="<?php echo $pref('s_provider_name'); ?>" placeholder="Acme Identity">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Provider Type</label>
                    <select class="form-select" name="s_provider_type">
                        <?php
                        $types = [
                            'google' => 'Google (OIDC)',
                            'microsoft' => 'Microsoft Entra',
                            'oidc' => 'Generic OIDC',
                            'oauth2' => 'Generic OAuth2',
                            'batoi_idp' => 'Batoi IDP',
                            '' => 'Custom'
                        ];
                        $currentType = $provider['s_provider_type'] ?? '';
                        foreach ($types as $val => $label) {
                            $sel = ($currentType === $val) ? 'selected' : '';
                            echo "<option value=\"" . htmlspecialchars($val) . "\" {$sel}>" . htmlspecialchars($label) . "</option>";
                        }
                        ?>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Status</label>
                    <select class="form-select" name="s_status">
                        <?php $st = $provider['s_status'] ?? 'active'; ?>
                        <option value="active" <?php echo $st === 'active' ? 'selected' : ''; ?>>Active</option>
                        <option value="inactive" <?php echo $st === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                    </select>
                    <div class="form-text">Inactive providers cannot be used during login.</div>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Notes</label>
                    <input type="text" class="form-control" name="s_notes" value="<?php echo $pref('s_notes'); ?>" placeholder="Environment, tenant, restrictions">
                </div>
            </div>
        </div>
    </div>

    <div class="card shadow-sm mb-3">
        <div class="card-body">
            <h3 class="h6 mb-3">2) OAuth/OIDC App Credentials</h3>
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">Client ID</label>
                    <input type="text" class="form-control" name="s_client_id" value="<?php echo $pref('s_client_id'); ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Client Secret</label>
                    <input type="password" class="form-control" name="s_client_secret" value="<?php echo $pref('s_client_secret'); ?>" autocomplete="new-password">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Issuer URL</label>
                    <input type="url" class="form-control" name="s_issuer" value="<?php echo $pref('s_issuer'); ?>" placeholder="https://accounts.google.com">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Scopes</label>
                    <input type="text" class="form-control" name="s_scopes" value="<?php echo $pref('s_scopes', 'openid profile email'); ?>" placeholder="openid profile email">
                    <div class="form-text">Use space-separated scopes. Comma-separated values are auto-normalized.</div>
                </div>
            </div>
        </div>
    </div>

    <div class="card shadow-sm mb-3">
        <div class="card-body">
            <h3 class="h6 mb-3">3) Provider Endpoints</h3>
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">Auth URL</label>
                    <input type="url" class="form-control" name="s_auth_url" value="<?php echo $pref('s_auth_url'); ?>" placeholder="https://id.example.com/oauth2/authorize">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Token URL</label>
                    <input type="url" class="form-control" name="s_token_url" value="<?php echo $pref('s_token_url'); ?>" placeholder="https://id.example.com/oauth2/token">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Userinfo URL</label>
                    <input type="url" class="form-control" name="s_userinfo_url" value="<?php echo $pref('s_userinfo_url'); ?>" placeholder="https://id.example.com/oauth2/userinfo">
                </div>
                <div class="col-md-6">
                    <label class="form-label">JWKS URL</label>
                    <input type="url" class="form-control" name="s_jwks_url" value="<?php echo $pref('s_jwks_url'); ?>" placeholder="https://id.example.com/.well-known/jwks.json">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Redirect Path</label>
                    <input type="text" class="form-control" name="s_redirect_path" value="<?php echo htmlspecialchars($currentRedirectPath); ?>" placeholder="/login/sso-callback">
                    <div class="form-text">Path only. Full callback URL is computed from your system base URL.</div>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Computed Callback URL</label>
                    <div class="form-control bg-light text-break"><?php echo htmlspecialchars($callbackUrl); ?></div>
                    <div class="form-text">Register this URL in your identity provider application settings.</div>
                </div>
            </div>
        </div>
    </div>

    <div class="card shadow-sm mb-3">
        <div class="card-body">
            <h3 class="h6 mb-3">4) Claims & Advanced</h3>
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">Claim Map (JSON)</label>
                    <textarea class="form-control font-monospace" name="s_claim_map" rows="5" placeholder='{"email":"email","name":"name","sub":"sub"}'><?php echo htmlspecialchars($claimMap); ?></textarea>
                    <div class="form-text">Maps app fields to ID token/userinfo claim keys.</div>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Raw Configuration (JSON)</label>
                    <textarea class="form-control font-monospace" name="s_sso_configuration" rows="5" placeholder='{"tenant":"acme-prod"}'><?php echo $pref('s_sso_configuration'); ?></textarea>
                    <div class="form-text">Optional metadata for your own usage.</div>
                </div>
            </div>
        </div>
    </div>

    <div class="d-flex gap-2">
        <button type="submit" class="btn btn-primary">Save Provider</button>
        <a href="<?php echo $radAdminUrl; ?>/sso/view" class="btn btn-outline-secondary">Cancel</a>
    </div>
</form>
