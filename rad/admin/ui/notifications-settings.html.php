<?php
$settings = $this->runData['data']['settings'] ?? [];
$definitions = $this->runData['data']['setting_definitions'] ?? [];
$radAdminUrl = $this->runData['route']['rad_admin_url'] ?? '';

$enabled = $settings['notifications_enabled'] ?? 'Y';
$mode = $settings['notifications_mode'] ?? 'both';
$severity = $settings['notifications_min_severity'] ?? 'info';
?>

<div class="row g-4">
    <div class="col-12 col-xl-8">
        <div class="card shadow-sm">
            <div class="card-body">
                <h2 class="h5 mb-3">Notification Controls</h2>
                <form method="post" action="<?php echo $radAdminUrl; ?>/notifications/settings">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($this->runData['request']->csrf_token); ?>">

                    <div class="mb-4">
                        <label class="form-label fw-semibold">Notifications enabled</label>
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" role="switch" id="notifications_enabled" name="notifications_enabled" <?php echo ($enabled === 'Y') ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="notifications_enabled">
                                <?php echo htmlspecialchars($definitions['notifications_enabled']['description'] ?? ''); ?>
                            </label>
                        </div>
                    </div>

                    <div class="mb-4">
                        <label class="form-label fw-semibold">Delivery mode</label>
                        <select class="form-select" name="notifications_mode">
                            <?php foreach (($definitions['notifications_mode']['options'] ?? []) as $value => $label) { ?>
                                <option value="<?php echo htmlspecialchars($value); ?>" <?php echo ($mode === $value) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($label); ?>
                                </option>
                            <?php } ?>
                        </select>
                    </div>

                    <div class="mb-4">
                        <label class="form-label fw-semibold">Minimum severity</label>
                        <select class="form-select" name="notifications_min_severity">
                            <?php foreach (($definitions['notifications_min_severity']['options'] ?? []) as $value => $label) { ?>
                                <option value="<?php echo htmlspecialchars($value); ?>" <?php echo ($severity === $value) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($label); ?>
                                </option>
                            <?php } ?>
                        </select>
                    </div>

                    <div class="d-flex flex-wrap gap-2">
                        <button type="submit" class="btn btn-primary">Save Settings</button>
                        <a href="<?php echo $radAdminUrl; ?>/notifications/view" class="btn btn-outline-secondary">Back to Notifications</a>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="col-12 col-xl-4">
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <h3 class="h6 text-uppercase text-muted">How Notifications Work</h3>
                <ul class="small text-muted mb-3">
                    <li>Notifications are generated from activity metadata (via <code>$this-&gt;runData['activity']</code>).</li>
                    <li><strong>Realtime</strong> creates notifications during live requests; <strong>Ingest</strong> creates them when logs are processed.</li>
                    <li>When mode is <strong>Both</strong>, ingest skips items already marked as notified.</li>
                </ul>
                <h3 class="h6 text-uppercase text-muted">Audience Rules</h3>
                <ul class="small text-muted mb-3">
                    <li>Platform activity targets the entity only (global scope).</li>
                    <li>Workspace activity targets both the entity and the workspace feed.</li>
                </ul>
                <h3 class="h6 text-uppercase text-muted">Severity Threshold</h3>
                <ul class="small text-muted mb-0">
                    <li><strong>Info</strong> includes info, warn, critical.</li>
                    <li><strong>Warn</strong> includes warn, critical.</li>
                    <li><strong>Critical</strong> includes critical only.</li>
                </ul>
            </div>
        </div>

        <div class="card border-0 shadow-sm mt-4">
            <div class="card-body">
                <h3 class="h6 text-uppercase text-muted">Send Test Notification</h3>
                <form method="post" action="<?php echo $radAdminUrl; ?>/notifications/settings">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($this->runData['request']->csrf_token); ?>">
                    <input type="hidden" name="action" value="send_test">
                    <div class="mb-2">
                        <label class="form-label small text-muted">Message</label>
                        <input type="text" name="test_message" class="form-control form-control-sm" value="Test notification">
                    </div>
                    <button type="submit" class="btn btn-outline-primary btn-sm">Send Test</button>
                </form>
                <div class="small text-muted mt-2">Sends a direct notification to the current user.</div>
            </div>
        </div>
    </div>
</div>
