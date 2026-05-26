<?php
$settings = $this->runData['data']['mfa_settings'] ?? [];
$ui = $settings['ui'] ?? [];
$radAdminUrl = $this->runData['route']['rad_admin_url'] ?? '';
$active = 'ux';
include __DIR__ . '/mfa-nav.partial.php';
?>

<div class="card shadow-sm">
    <div class="card-body">
        <h2 class="h5 mb-3">UX Settings</h2>
        <p class="text-muted small">Adjust MFA experience details for troubleshooting.</p>
        <form method="post" action="<?php echo $radAdminUrl; ?>/mfa/ux">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($this->runData['request']->csrf_token); ?>">
            <div class="form-check mb-3">
                <input class="form-check-input" type="checkbox" name="ui[show_hint]" id="ui_show_hint" <?php echo !empty($ui['show_hint']) ? 'checked' : ''; ?>>
                <label class="form-check-label" for="ui_show_hint">Show test hint code on MFA screen</label>
                <div class="form-text text-danger">Use only in development. Exposes OTP hints on the login screen.</div>
            </div>
            <div class="mt-4">
                <button type="submit" class="btn btn-primary">Save UX</button>
            </div>
        </form>
    </div>
</div>
