<?php
$settings = $this->runData['data']['mfa_settings'] ?? [];
$channels = $settings['channels'] ?? [];
$rate = $settings['rate_limit'] ?? [];
$twilio = $settings['twilio'] ?? [];
$ui = $settings['ui'] ?? [];
$radAdminUrl = $this->runData['route']['rad_admin_url'] ?? '';
?>
<div class="card shadow-sm">
    <div class="card-body">
        <h2 class="h5 mb-3">System MFA Settings</h2>
        <form method="post" action="<?php echo $radAdminUrl; ?>/config/mfa">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($this->runData['request']->csrf_token); ?>">
            <div class="row g-3">
                <div class="col-md-4">
                    <div class="form-check mb-2">
                        <input class="form-check-input" type="checkbox" name="enforce_admin_mfa" id="enforce_admin_mfa" <?php echo !empty($settings['enforce_admin_mfa']) ? 'checked' : ''; ?>>
                        <label class="form-check-label" for="enforce_admin_mfa">Enforce for admin roles</label>
                    </div>
                    <div class="form-check mb-2">
                        <input class="form-check-input" type="checkbox" name="enforce_member_mfa" id="enforce_member_mfa" <?php echo !empty($settings['enforce_member_mfa']) ? 'checked' : ''; ?>>
                        <label class="form-check-label" for="enforce_member_mfa">Enforce for member/saas roles</label>
                    </div>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Trusted device TTL (days)</label>
                    <input type="number" class="form-control" name="trusted_device_ttl_days" value="<?php echo (int)($settings['trusted_device_ttl_days'] ?? 30); ?>" min="1">
                </div>
                <div class="col-md-4">
                    <label class="form-label">OTP TTL (seconds)</label>
                    <input type="number" class="form-control" name="otp_ttl_seconds" value="<?php echo (int)($settings['otp_ttl_seconds'] ?? 300); ?>" min="60">
                </div>
            </div>

            <hr>
            <h6>Channels</h6>
            <div class="row g-3">
                <?php foreach (['totp' => 'TOTP (app)', 'sms' => 'SMS', 'whatsapp' => 'WhatsApp', 'email' => 'Email fallback'] as $key => $label) { ?>
                    <div class="col-md-3">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="channels[<?php echo $key; ?>]" id="ch_<?php echo $key; ?>" <?php echo !empty($channels[$key]) ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="ch_<?php echo $key; ?>"><?php echo $label; ?></label>
                        </div>
                    </div>
                <?php } ?>
            </div>

            <hr>
            <h6>Rate limits</h6>
            <div class="row g-3">
                <div class="col-md-3">
                    <label class="form-label">Max attempts</label>
                    <input type="number" class="form-control" name="rate_limit[max_attempts]" value="<?php echo (int)($rate['max_attempts'] ?? 5); ?>" min="1">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Lockout (minutes)</label>
                    <input type="number" class="form-control" name="rate_limit[lockout_minutes]" value="<?php echo (int)($rate['lockout_minutes'] ?? 15); ?>" min="1">
                </div>
            </div>

            <hr>
            <h6>Twilio (SMS/WhatsApp)</h6>
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label">Account SID</label>
                    <input type="text" class="form-control" name="twilio[account_sid]" value="<?php echo htmlspecialchars($twilio['account_sid'] ?? ''); ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Auth Token</label>
                    <input type="text" class="form-control" name="twilio[auth_token]" value="<?php echo htmlspecialchars($twilio['auth_token'] ?? ''); ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label">From</label>
                    <input type="text" class="form-control" name="twilio[from]" value="<?php echo htmlspecialchars($twilio['from'] ?? ''); ?>" placeholder="+1234567890">
                </div>
            </div>

            <div class="form-check mt-3">
                <input class="form-check-input" type="checkbox" name="email_fallback" id="email_fallback" <?php echo !empty($settings['email_fallback']) ? 'checked' : ''; ?>>
                <label class="form-check-label" for="email_fallback">Send email code if SMS/WhatsApp unavailable</label>
            </div>

            <hr>
            <h6>UI</h6>
            <div class="form-check">
                <input class="form-check-input" type="checkbox" name="ui[show_hint]" id="ui_show_hint" <?php echo !empty($ui['show_hint']) ? 'checked' : ''; ?>>
                <label class="form-check-label" for="ui_show_hint">Show test hint code on MFA screen (avoid in production)</label>
            </div>

            <div class="mt-4">
                <button type="submit" class="btn btn-primary">Save</button>
                <a href="<?php echo $radAdminUrl; ?>/config/view/S" class="btn btn-outline-secondary ms-2">Cancel</a>
            </div>
        </form>
    </div>
</div>
