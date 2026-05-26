<?php
$settings = $this->runData['data']['mfa_settings'] ?? [];
$radAdminUrl = $this->runData['route']['rad_admin_url'] ?? '';
$active = 'policy';
include __DIR__ . '/mfa-nav.partial.php';
?>

<div class="card shadow-sm">
    <div class="card-body">
        <h2 class="h5 mb-3">Policy</h2>
        <p class="text-muted small">Define who must complete MFA during login. Users still need a valid delivery channel (authenticator app, SMS, etc.).</p>
        <form method="post" action="<?php echo $radAdminUrl; ?>/mfa/policy">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($this->runData['request']->csrf_token); ?>">
            <div class="form-check mb-3">
                <input class="form-check-input" type="checkbox" name="enforce_admin_mfa" id="enforce_admin_mfa" <?php echo !empty($settings['enforce_admin_mfa']) ? 'checked' : ''; ?>>
                <label class="form-check-label" for="enforce_admin_mfa">Require MFA for admin roles</label>
                <div class="form-text">Recommended for all system administrators.</div>
            </div>
            <div class="form-check mb-3">
                <input class="form-check-input" type="checkbox" name="enforce_member_mfa" id="enforce_member_mfa" <?php echo !empty($settings['enforce_member_mfa']) ? 'checked' : ''; ?>>
                <label class="form-check-label" for="enforce_member_mfa">Require MFA for member/saas roles</label>
            </div>
            <div class="mt-4">
                <button type="submit" class="btn btn-primary">Save Policy</button>
            </div>
        </form>
    </div>
</div>
