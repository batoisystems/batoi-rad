<?php
$settings = $this->runData['data']['mfa_settings'] ?? [];
$rate = $settings['rate_limit'] ?? [];
$radAdminUrl = $this->runData['route']['rad_admin_url'] ?? '';
$active = 'security';
include __DIR__ . '/mfa-nav.partial.php';
?>

<div class="card shadow-sm">
    <div class="card-body">
        <h2 class="h5 mb-3">Security Limits</h2>
        <p class="text-muted small">Control OTP lifetimes and lockout behavior.</p>
        <form method="post" action="<?php echo $radAdminUrl; ?>/mfa/security">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($this->runData['request']->csrf_token); ?>">
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label">Trusted device TTL (days)</label>
                    <input type="number" class="form-control" name="trusted_device_ttl_days" value="<?php echo (int)($settings['trusted_device_ttl_days'] ?? 30); ?>" min="1">
                </div>
                <div class="col-md-4">
                    <label class="form-label">OTP TTL (seconds)</label>
                    <input type="number" class="form-control" name="otp_ttl_seconds" value="<?php echo (int)($settings['otp_ttl_seconds'] ?? 300); ?>" min="60">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Max attempts</label>
                    <input type="number" class="form-control" name="rate_limit[max_attempts]" value="<?php echo (int)($rate['max_attempts'] ?? 5); ?>" min="1">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Lockout (minutes)</label>
                    <input type="number" class="form-control" name="rate_limit[lockout_minutes]" value="<?php echo (int)($rate['lockout_minutes'] ?? 15); ?>" min="1">
                </div>
            </div>
            <div class="mt-4">
                <button type="submit" class="btn btn-primary">Save Security</button>
            </div>
        </form>
    </div>
</div>
