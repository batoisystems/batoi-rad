<?php
$settings = $this->runData['data']['mfa_settings'] ?? [];
$summary = $this->runData['data']['mfa_summary'] ?? [];
$channels = $summary['channels'] ?? [];
$twilioReady = !empty($summary['twilio_ready']);
$radAdminUrl = $this->runData['route']['rad_admin_url'] ?? '';
$active = 'dashboard';
include __DIR__ . '/mfa-nav.partial.php';
?>

<div class="row g-3">
    <div class="col-lg-6">
        <div class="card shadow-sm h-100">
            <div class="card-body">
                <h2 class="h5 mb-3">Effective Policy</h2>
                <div class="d-flex flex-column gap-2">
                    <div class="d-flex align-items-center gap-2">
                        <span class="badge bg-<?php echo !empty($summary['enforce_admin']) ? 'success' : 'secondary'; ?>">
                            Admin MFA <?php echo !empty($summary['enforce_admin']) ? 'Required' : 'Optional'; ?>
                        </span>
                        <span class="badge bg-<?php echo !empty($summary['enforce_member']) ? 'success' : 'secondary'; ?>">
                            Member MFA <?php echo !empty($summary['enforce_member']) ? 'Required' : 'Optional'; ?>
                        </span>
                    </div>
                    <p class="text-muted small mb-0">Policy enforcement applies at login. Users still need valid contact methods or authenticator setup to pass MFA.</p>
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card shadow-sm h-100">
            <div class="card-body">
                <h2 class="h5 mb-3">Channel Readiness</h2>
                <div class="d-flex flex-wrap gap-2">
                    <span class="badge bg-<?php echo !empty($channels['totp']) ? 'success' : 'secondary'; ?>">TOTP</span>
                    <span class="badge bg-<?php echo !empty($channels['sms']) ? 'success' : 'secondary'; ?>">SMS</span>
                    <span class="badge bg-<?php echo !empty($channels['whatsapp']) ? 'success' : 'secondary'; ?>">WhatsApp</span>
                    <span class="badge bg-<?php echo !empty($channels['email']) ? 'success' : 'secondary'; ?>">Email</span>
                </div>
                <div class="mt-3 small text-muted">
                    Twilio: <?php echo $twilioReady ? 'Configured' : 'Not configured'; ?>
                    <?php if (!$twilioReady): ?>
                        · SMS/WhatsApp will stay disabled until provider details are set.
                    <?php endif; ?>
                </div>
                <div class="mt-3 small text-muted">
                    Email fallback: <?php echo !empty($summary['email_fallback']) ? 'Enabled' : 'Disabled'; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="card shadow-sm mt-3">
    <div class="card-body">
        <h2 class="h6 mb-2">Quick Links</h2>
        <div class="d-flex flex-wrap gap-2">
            <a class="btn btn-outline-primary btn-sm" href="<?php echo $radAdminUrl; ?>/mfa/policy">Edit Policy</a>
            <a class="btn btn-outline-primary btn-sm" href="<?php echo $radAdminUrl; ?>/mfa/channels">Manage Channels</a>
            <a class="btn btn-outline-primary btn-sm" href="<?php echo $radAdminUrl; ?>/mfa/providers">Configure Providers</a>
            <a class="btn btn-outline-primary btn-sm" href="<?php echo $radAdminUrl; ?>/mfa/security">Security Limits</a>
            <a class="btn btn-outline-primary btn-sm" href="<?php echo $radAdminUrl; ?>/mfa/ux">UX Settings</a>
        </div>
    </div>
</div>
