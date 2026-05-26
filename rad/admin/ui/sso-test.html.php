<?php
$provider = $this->runData['data']['provider'] ?? [];
$latestTest = $provider['_latest_test'] ?? null;
$launchUrl = $this->runData['data']['test_launch_url'] ?? '';
$returnUrl = $this->runData['data']['test_return_url'] ?? '';
$radAdminUrl = $this->runData['route']['rad_admin_url'] ?? '';
$providerId = (int)($provider['id'] ?? 0);
?>

<div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-2 mb-3">
    <div>
        <!-- <h2 class="h4 mb-0">SSO Provider Test</h2> -->
        <div class="text-muted small"><?php echo htmlspecialchars((string)($provider['s_provider_name'] ?? '')); ?> · <?php echo htmlspecialchars((string)($provider['type_label'] ?? '')); ?></div>
    </div>
    <div class="btn-group">
        <a class="btn btn-outline-secondary" href="<?php echo $radAdminUrl; ?>/sso/manage/<?php echo $providerId; ?>">Back to Manage</a>
        <a class="btn btn-outline-secondary" href="<?php echo $radAdminUrl; ?>/sso/view">All Providers</a>
    </div>
</div>

<?php if (is_array($latestTest)) { ?>
<div class="alert alert-<?php echo !empty($latestTest['passed']) ? 'success' : 'danger'; ?> small">
    <strong>Latest Recorded Test:</strong>
    <?php echo !empty($latestTest['passed']) ? 'Passed' : 'Failed'; ?>
    <?php if (!empty($latestTest['at'])) { ?> on <?php echo htmlspecialchars((string)$latestTest['at']); ?><?php } ?>
    <?php if (!empty($latestTest['reason'])) { ?><br><?php echo htmlspecialchars((string)$latestTest['reason']); ?><?php } ?>
</div>
<?php } ?>

<div class="card shadow-sm mb-3">
    <div class="card-body">
        <h3 class="h6 mb-3">How this test works</h3>
        <ol class="small mb-0">
            <li>Click <strong>Launch Test Login</strong>.</li>
            <li>Complete identity provider login and consent.</li>
            <li>You will return to RAD Admin and result is auto-recorded as pass/fail.</li>
        </ol>
    </div>
</div>

<div class="card shadow-sm mb-3">
    <div class="card-body">
        <h3 class="h6 mb-3">Test URLs</h3>
        <div class="small mb-2"><strong>Launch URL</strong></div>
        <div class="form-control bg-light text-break mb-3"><code><?php echo htmlspecialchars((string)$launchUrl); ?></code></div>

        <div class="small mb-2"><strong>Return URL</strong></div>
        <div class="form-control bg-light text-break"><code><?php echo htmlspecialchars((string)$returnUrl); ?></code></div>
    </div>
</div>

<div class="d-flex gap-2">
    <a class="btn btn-primary" href="<?php echo htmlspecialchars((string)$launchUrl); ?>" target="_blank" rel="noopener">Launch Test Login</a>
    <a class="btn btn-outline-secondary" href="<?php echo $radAdminUrl; ?>/sso/test/<?php echo $providerId; ?>">Refresh Page</a>
</div>
