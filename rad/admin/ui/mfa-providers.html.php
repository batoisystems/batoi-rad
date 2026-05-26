<?php
$settings = $this->runData['data']['mfa_settings'] ?? [];
$twilio = $settings['twilio'] ?? [];
$twilioReady = !empty($this->runData['data']['twilio_ready']);
$radAdminUrl = $this->runData['route']['rad_admin_url'] ?? '';
$active = 'providers';
include __DIR__ . '/mfa-nav.partial.php';
?>

<div class="card shadow-sm">
    <div class="card-body">
        <h2 class="h5 mb-3">Providers</h2>
        <p class="text-muted small">Configure SMS/WhatsApp delivery. Auth tokens are stored as-is in system config.</p>
        <div class="alert alert-<?php echo $twilioReady ? 'success' : 'warning'; ?> small">
            Twilio status: <?php echo $twilioReady ? 'Configured' : 'Not configured'; ?>
        </div>
        <form method="post" action="<?php echo $radAdminUrl; ?>/mfa/providers">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($this->runData['request']->csrf_token); ?>">
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label">Account SID</label>
                    <input type="text" class="form-control" name="twilio[account_sid]" value="<?php echo htmlspecialchars($twilio['account_sid'] ?? ''); ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Auth Token</label>
                    <div class="input-group">
                        <input type="password" class="form-control" name="twilio[auth_token]" id="twilio_auth_token" placeholder="<?php echo !empty($twilio['auth_token']) ? '••••••••' : ''; ?>">
                        <button class="btn btn-outline-secondary" type="button" data-toggle="token" data-target="twilio_auth_token">Show</button>
                    </div>
                    <div class="form-text">Leave blank to keep the current token.</div>
                </div>
                <div class="col-md-4">
                    <label class="form-label">From</label>
                    <input type="text" class="form-control" name="twilio[from]" value="<?php echo htmlspecialchars($twilio['from'] ?? ''); ?>" placeholder="+1234567890">
                </div>
            </div>
            <div class="mt-4">
                <button type="submit" class="btn btn-primary">Save Providers</button>
            </div>
        </form>
    </div>
</div>

<script>
    document.querySelectorAll('[data-toggle="token"]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            const target = document.getElementById(btn.getAttribute('data-target'));
            if (!target) { return; }
            const isPassword = target.getAttribute('type') === 'password';
            target.setAttribute('type', isPassword ? 'text' : 'password');
            btn.textContent = isPassword ? 'Hide' : 'Show';
        });
    });
</script>
