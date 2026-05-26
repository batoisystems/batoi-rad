<?php
$entity = $this->runData['data']['entity'] ?? [];
$prefs = $this->runData['data']['profile_prefs'] ?? [];
$mfaEnabled = !empty($this->runData['data']['mfa_enabled']);
$lastLogin = $this->runData['data']['last_login_display'] ?? ($this->runData['data']['last_login'] ?? null);
$recentSessions = $this->runData['data']['recent_sessions'] ?? [];
$radAdminUrl = $this->runData['route']['rad_admin_url'] ?? '';
$active = 'overview';
include $this->runData['config']['dir']['admin'] . '/ui/profile-nav.partial.php';
?>
<div class="row g-3">
    <div class="col-lg-7">
        <div class="card shadow-sm h-100">
            <div class="card-body">
                <h2 class="h5">Account</h2>
                <p class="text-muted small mb-3">Your identity and sign-in status.</p>
                <div class="d-flex flex-wrap gap-4">
                    <div>
                        <div class="text-muted small">Name</div>
                        <div class="fw-semibold"><?php echo htmlspecialchars($entity['s_name'] ?? ''); ?></div>
                    </div>
                    <div>
                        <div class="text-muted small">Username</div>
                        <div class="fw-semibold"><?php echo htmlspecialchars($entity['s_identity'] ?? ''); ?></div>
                    </div>
                    <div>
                        <div class="text-muted small">Email</div>
                        <div class="fw-semibold"><?php echo htmlspecialchars($entity['s_email'] ?? ''); ?></div>
                    </div>
                </div>
                <div class="d-flex flex-wrap gap-3 mt-3">
                    <span class="badge <?php echo $mfaEnabled ? 'bg-success' : 'bg-secondary'; ?>">
                        MFA <?php echo $mfaEnabled ? 'Enabled' : 'Disabled'; ?>
                    </span>
                    <span class="badge bg-info text-dark">
                        Last login: <?php echo $lastLogin ? htmlspecialchars($lastLogin) : 'No record'; ?>
                    </span>
                </div>
                <div class="mt-4 d-flex flex-wrap gap-2">
                    <a class="btn btn-outline-primary btn-sm" href="<?php echo $radAdminUrl; ?>/profile/mfa">Manage MFA</a>
                    <a class="btn btn-outline-primary btn-sm" href="<?php echo $radAdminUrl; ?>/profile/changepwd">Change Password</a>
                    <a class="btn btn-outline-primary btn-sm" href="<?php echo $radAdminUrl; ?>/profile/sessions">View Sessions</a>
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-5">
        <div class="card shadow-sm h-100">
            <div class="card-body">
                <h2 class="h5">Preferences</h2>
                <p class="text-muted small mb-3">Personal defaults for admin views.</p>
                <div class="d-flex flex-column gap-2">
                    <div class="d-flex justify-content-between">
                        <span class="text-muted small">Rows per page</span>
                        <span class="fw-semibold"><?php echo (int)($prefs['per_page'] ?? 25); ?></span>
                    </div>
                    <div class="d-flex justify-content-between">
                        <span class="text-muted small">Density</span>
                        <span class="fw-semibold"><?php echo htmlspecialchars($prefs['density'] ?? 'comfortable'); ?></span>
                    </div>
                    <div class="d-flex justify-content-between">
                        <span class="text-muted small">Timezone</span>
                        <span class="fw-semibold"><?php echo htmlspecialchars($prefs['timezone'] ?? ''); ?></span>
                    </div>
                    <div class="d-flex justify-content-between">
                        <span class="text-muted small">Shortcuts</span>
                        <span class="fw-semibold"><?php echo !empty($prefs['show_shortcuts']) ? 'On' : 'Off'; ?></span>
                    </div>
                </div>
                <a class="btn btn-outline-primary btn-sm mt-3" href="<?php echo $radAdminUrl; ?>/profile/preferences">Edit preferences</a>
            </div>
        </div>
    </div>
</div>

<div class="card shadow-sm mt-3">
    <div class="card-body">
        <h2 class="h5">Recent Sessions</h2>
        <p class="text-muted small mb-3">Last sign-ins from browser sessions (API excluded).</p>
        <?php if (!empty($recentSessions)) { ?>
            <div class="table-responsive">
                <table class="table table-sm align-middle">
                    <thead>
                        <tr>
                            <th>When</th>
                            <th>Browser</th>
                            <th>OS</th>
                            <th>IP</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recentSessions as $row) { ?>
                            <tr>
                                <td><?php echo htmlspecialchars($row['createstamp_display'] ?? $row['createstamp'] ?? ''); ?></td>
                                <td><?php echo htmlspecialchars($row['s_browser'] ?? ''); ?></td>
                                <td><?php echo htmlspecialchars($row['s_operating_system'] ?? ''); ?></td>
                                <td><?php echo htmlspecialchars($row['s_ip'] ?? ''); ?></td>
                            </tr>
                        <?php } ?>
                    </tbody>
                </table>
            </div>
            <a class="btn btn-link px-0" href="<?php echo $radAdminUrl; ?>/profile/sessions">View all sessions</a>
        <?php } else { ?>
            <div class="text-muted small">No session history found.</div>
        <?php } ?>
    </div>
</div>
