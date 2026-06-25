<?php
$runData = $runData ?? [];
$radAdminAssetsClass = ($runData['config']['dir']['admin'] ?? dirname(__DIR__)) . '/classes/RadAdminAssets.cls.php';
if (!class_exists('\\RadAdmin\\RadAdminAssets', false) && is_file($radAdminAssetsClass)) {
    require_once $radAdminAssetsClass;
}
$baseUrl = $runData['config']['sys']['base_url'] ?? '';
$radAssets = $runData['route']['rad_assets_url'] ?? ($baseUrl . '/rad-admin/assets');
$redirect = $baseUrl . '/rad-admin/home/view';
$ssoRole = strtolower(trim((string)($runData['config']['auth']['sso_role'] ?? 'disabled')));
$ssoClientEnabled = $ssoRole === 'client';
$ssoClientLoginUrl = $baseUrl . '/login/sso-client-init?redirect=' . urlencode($redirect);
$ssoClientLabel = trim((string)($runData['config']['auth']['sso_client']['label'] ?? 'Sign in with Organization SSO'));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>RAD Admin Login</title>
    <link href="<?php echo $radAssets; ?>/bootstrap/bootstrap-5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="<?php echo $radAssets; ?>/rad-admin.css" rel="stylesheet">
    <?php echo \RadAdmin\RadAdminAssets::renderUifHead($runData); ?>
</head>
<body class="bg-light">
<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-6 col-lg-5">
            <div class="card shadow-sm">
                <div class="card-body p-4">
                    <div class="text-center mb-3">
                        <img src="<?php echo $radAssets; ?>/img/batoi-rad-framework-logo.svg" alt="RAD Admin" height="48">
                        <h1 class="h4 mt-3 mb-0">Sign in to RAD Admin</h1>
                    </div>
                    <?php if (!empty($runData['route']['alert'])) { ?>
                        <div class="alert alert-<?php echo htmlspecialchars($runData['route']['alert']); ?>">
                            <?php echo htmlspecialchars($runData['route']['alert_message'] ?? ''); ?>
                        </div>
                    <?php } ?>
                    <?php if (!empty($runData['route']['debug_enabled'])) { ?>
                        <div class="alert alert-warning small">
                            <div class="fw-semibold mb-2">Login debug (dev_debug_flag = Y)</div>
                            <?php if (!empty($runData['route']['login_debug'])) { ?>
                                <pre class="mb-0"><code><?php
                                    echo htmlspecialchars(json_encode($runData['route']['login_debug'], JSON_PRETTY_PRINT));
                                ?></code></pre>
                            <?php } else { ?>
                                <div>No login debug payload available.</div>
                            <?php } ?>
                        </div>
                    <?php } ?>
                    <form action="<?php echo $baseUrl; ?>/login/localsession" method="post" class="needs-validation">
                        <input type="hidden" name="redirect_url_post_login" value="<?php echo htmlspecialchars($redirect); ?>">
                        <div class="mb-3">
                            <label class="form-label">Username</label>
                            <input type="text" name="s_username" class="form-control" required autocomplete="username">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Password</label>
                            <input type="password" name="s_password" class="form-control" required>
                        </div>
                        <button type="submit" class="btn btn-primary w-100">Sign in</button>
                        <?php if ($ssoClientEnabled) { ?>
                            <div class="text-center text-muted small my-2">or</div>
                            <a class="btn btn-outline-primary w-100" href="<?php echo htmlspecialchars($ssoClientLoginUrl); ?>">
                                <?php echo htmlspecialchars($ssoClientLabel); ?>
                            </a>
                        <?php } ?>
                    </form>
                    <div class="mt-3 text-center">
                        <a href="<?php echo $baseUrl; ?>/login">Use standard login</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php if (!\RadAdmin\RadAdminAssets::isUifEnabled($runData)) { ?>
<script src="<?php echo $radAssets; ?>/bootstrap/bootstrap-5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<?php } ?>
<?php echo \RadAdmin\RadAdminAssets::renderUifBody($runData); ?>
</body>
</html>
