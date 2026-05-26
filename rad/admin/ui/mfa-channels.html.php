<?php
$settings = $this->runData['data']['mfa_settings'] ?? [];
$channels = $settings['channels'] ?? [];
$twilioReady = !empty($this->runData['data']['twilio_ready']);
$deliveryPriority = $settings['delivery_priority'] ?? ['sms', 'whatsapp', 'email'];
$deliveryPriority = array_values(array_unique(array_filter($deliveryPriority)));
$radAdminUrl = $this->runData['route']['rad_admin_url'] ?? '';
$active = 'channels';
include __DIR__ . '/mfa-nav.partial.php';
?>

<div class="card shadow-sm">
    <div class="card-body">
        <h2 class="h5 mb-3">Channels</h2>
        <p class="text-muted small">Enable the MFA delivery channels available to users.</p>
        <?php if (!$twilioReady): ?>
            <div class="alert alert-warning small">
                Twilio is not configured. SMS and WhatsApp channels stay disabled until provider details are saved.
            </div>
        <?php endif; ?>
        <form method="post" action="<?php echo $radAdminUrl; ?>/mfa/channels">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($this->runData['request']->csrf_token); ?>">
            <div class="row g-3">
                <div class="col-md-3">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="channels[totp]" id="ch_totp" <?php echo !empty($channels['totp']) ? 'checked' : ''; ?>>
                        <label class="form-check-label" for="ch_totp">TOTP (Authenticator app)</label>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="channels[sms]" id="ch_sms" <?php echo !empty($channels['sms']) ? 'checked' : ''; ?> <?php echo !$twilioReady ? 'disabled' : ''; ?>>
                        <label class="form-check-label" for="ch_sms">SMS</label>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="channels[whatsapp]" id="ch_whatsapp" <?php echo !empty($channels['whatsapp']) ? 'checked' : ''; ?> <?php echo !$twilioReady ? 'disabled' : ''; ?>>
                        <label class="form-check-label" for="ch_whatsapp">WhatsApp</label>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="channels[email]" id="ch_email" <?php echo !empty($channels['email']) ? 'checked' : ''; ?>>
                        <label class="form-check-label" for="ch_email">Email</label>
                    </div>
                </div>
            </div>

            <div class="form-check mt-3">
                <input class="form-check-input" type="checkbox" name="email_fallback" id="email_fallback" <?php echo !empty($settings['email_fallback']) ? 'checked' : ''; ?>>
                <label class="form-check-label" for="email_fallback">Send email code if SMS/WhatsApp unavailable</label>
            </div>

            <hr class="my-4">
            <h6>Delivery priority</h6>
            <p class="text-muted small">Only one channel is used per login. Order defines fallback when the first channel fails.</p>
            <div class="row g-3">
                <?php
                $options = ['sms' => 'SMS', 'whatsapp' => 'WhatsApp', 'email' => 'Email'];
                for ($i = 0; $i < 3; $i++) {
                    $current = $deliveryPriority[$i] ?? array_keys($options)[$i];
                ?>
                    <div class="col-md-4">
                        <label class="form-label">Priority <?php echo $i + 1; ?></label>
                        <select class="form-select" name="delivery_priority[]">
                            <?php foreach ($options as $key => $label) { ?>
                                <option value="<?php echo $key; ?>" <?php echo $current === $key ? 'selected' : ''; ?>>
                                    <?php echo $label; ?>
                                </option>
                            <?php } ?>
                        </select>
                    </div>
                <?php } ?>
            </div>

            <hr class="my-4">
            <h6>Authenticator apps</h6>
            <p class="text-muted small mb-2">Users can set up any standard TOTP app. Recommended: Google Authenticator, Microsoft Authenticator, Authy, 1Password, Bitwarden.</p>
            <p class="text-muted small mb-0">Setup: open Profile → MFA settings and scan the QR code.</p>
            <a class="small" href="<?php echo $radAdminUrl; ?>/profile/mfa">Go to Profile MFA setup</a>

            <div class="mt-4">
                <button type="submit" class="btn btn-primary">Save Channels</button>
            </div>
        </form>
    </div>
</div>
