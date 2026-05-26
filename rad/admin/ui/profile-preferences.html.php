<?php
$prefs = $this->runData['data']['profile_prefs'] ?? [];
$radAdminUrl = $this->runData['route']['rad_admin_url'] ?? '';
$active = 'preferences';
include $this->runData['config']['dir']['admin'] . '/ui/profile-nav.partial.php';
?>
<div class="card shadow-sm">
    <div class="card-body">
        <div class="d-flex flex-wrap justify-content-between align-items-center mb-3">
            <div>
                <h2 class="h5 mb-1">Preferences</h2>
                <div class="text-muted small">Control your admin defaults and layout density.</div>
            </div>
            <a class="btn btn-outline-primary btn-sm" href="<?php echo $radAdminUrl; ?>/profile/overview">Back to overview</a>
        </div>
        <form method="post" action="<?php echo $radAdminUrl; ?>/profile/preferences" class="row g-3">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($this->runData['request']->csrf_token); ?>">
            <div class="col-md-4">
                <label class="form-label">Rows per page</label>
                <select name="per_page" class="form-select">
                    <?php foreach ([10, 25, 50, 100, 200] as $value) { ?>
                        <option value="<?php echo $value; ?>" <?php echo ((int)($prefs['per_page'] ?? 25) === $value) ? 'selected' : ''; ?>>
                            <?php echo $value; ?>
                        </option>
                    <?php } ?>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label">Density</label>
                <select name="density" class="form-select">
                    <option value="comfortable" <?php echo ($prefs['density'] ?? 'comfortable') === 'comfortable' ? 'selected' : ''; ?>>Comfortable</option>
                    <option value="compact" <?php echo ($prefs['density'] ?? 'comfortable') === 'compact' ? 'selected' : ''; ?>>Compact</option>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label">Timezone (optional)</label>
                <input type="text" name="timezone" class="form-control" value="<?php echo htmlspecialchars($prefs['timezone'] ?? ''); ?>" placeholder="e.g. Asia/Kolkata">
            </div>
            <div class="col-12">
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" id="showShortcuts" name="show_shortcuts" value="1" <?php echo !empty($prefs['show_shortcuts']) ? 'checked' : ''; ?>>
                    <label class="form-check-label" for="showShortcuts">Show quick shortcuts where available</label>
                </div>
            </div>
            <div class="col-12">
                <button type="submit" class="btn btn-primary">Save preferences</button>
            </div>
        </form>
    </div>
</div>
