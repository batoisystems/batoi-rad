<?php
$prefs = $this->runData['data']['notification_prefs'] ?? [];
$categories = $prefs['categories'] ?? [];
$channels = $prefs['channels'] ?? [];
$frequency = $prefs['frequency'] ?? 'instant';
$radAdminUrl = $this->runData['route']['rad_admin_url'] ?? '';
$active = 'notifications';
include $this->runData['config']['dir']['admin'] . '/ui/profile-nav.partial.php';
?>
<div class="card shadow-sm">
    <div class="card-body">
        <div class="d-flex flex-wrap justify-content-between align-items-center mb-3">
            <div>
                <h2 class="h5 mb-1">Notifications</h2>
                <div class="text-muted small">Choose what should appear in your inbox.</div>
            </div>
            <a class="btn btn-outline-primary btn-sm" href="<?php echo $radAdminUrl; ?>/profile/overview">Back to overview</a>
        </div>
        <form method="post" action="<?php echo $radAdminUrl; ?>/profile/notifications" class="row g-3">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($this->runData['request']->csrf_token); ?>">
            <div class="col-md-6">
                <div class="border rounded p-3 h-100">
                    <div class="fw-semibold mb-2">Categories</div>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="notifySecurity" name="security" value="1" <?php echo !empty($categories['security']) ? 'checked' : ''; ?>>
                        <label class="form-check-label" for="notifySecurity">Security and account events</label>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="notifyWorkspace" name="workspace" value="1" <?php echo !empty($categories['workspace']) ? 'checked' : ''; ?>>
                        <label class="form-check-label" for="notifyWorkspace">Workspace and membership updates</label>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="notifySystem" name="system" value="1" <?php echo !empty($categories['system']) ? 'checked' : ''; ?>>
                        <label class="form-check-label" for="notifySystem">System announcements</label>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="notifyProduct" name="product" value="1" <?php echo !empty($categories['product']) ? 'checked' : ''; ?>>
                        <label class="form-check-label" for="notifyProduct">Product tips and release notes</label>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="border rounded p-3 h-100">
                    <div class="fw-semibold mb-2">Delivery</div>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="channelInapp" name="channel_inapp" value="1" <?php echo !empty($channels['inapp']) ? 'checked' : ''; ?>>
                        <label class="form-check-label" for="channelInapp">In-app inbox</label>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="channelEmail" name="channel_email" value="1" <?php echo !empty($channels['email']) ? 'checked' : ''; ?>>
                        <label class="form-check-label" for="channelEmail">Email</label>
                    </div>
                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" id="channelSms" name="channel_sms" value="1" <?php echo !empty($channels['sms']) ? 'checked' : ''; ?>>
                        <label class="form-check-label" for="channelSms">SMS</label>
                    </div>
                    <label class="form-label">Frequency</label>
                    <select name="frequency" class="form-select">
                        <option value="instant" <?php echo $frequency === 'instant' ? 'selected' : ''; ?>>Instant</option>
                        <option value="daily" <?php echo $frequency === 'daily' ? 'selected' : ''; ?>>Daily summary</option>
                        <option value="weekly" <?php echo $frequency === 'weekly' ? 'selected' : ''; ?>>Weekly summary</option>
                    </select>
                </div>
            </div>
            <div class="col-12">
                <button type="submit" class="btn btn-primary">Save preferences</button>
            </div>
        </form>
    </div>
</div>
