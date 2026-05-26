<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo htmlspecialchars($this->runData['route']['meta_title'] ?? 'Multi-factor Verification'); ?></title>
    <link href="<?php echo $this->runData['route']['assets_url']; ?>/vendor/bootstrap/bootstrap-5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="<?php echo $this->runData['route']['assets_url']; ?>/css/app.css" rel="stylesheet">
</head>
<body class="d-flex align-items-center py-4 bg-light">
<main class="m-auto d-flex justify-content-center align-items-center vh-100">
    <div class="card shadow-sm" style="width:420px;">
        <div class="card-body p-4">
            <h1 class="h4 mb-3 text-center">Verify your identity</h1>
            <?php if (!empty($this->runData['route']['alert'])) { ?>
                <div class="alert alert-<?php echo htmlspecialchars($this->runData['route']['alert']); ?>">
                    <?php echo htmlspecialchars($this->runData['route']['alert_message'] ?? ''); ?>
                </div>
            <?php } ?>
            <form method="post" action="<?php echo $this->runData['config']['sys']['base_url']; ?>/login/mfa">
                <div class="mb-3">
                    <label class="form-label">Verification code</label>
                    <input type="text" name="mfa_code" class="form-control" inputmode="numeric" autofocus required>
                    <div class="form-text">Enter the 6-digit code from your authenticator app or the code sent via SMS, WhatsApp, or email.</div>
                    <div class="form-text">Need setup help? Open Profile → MFA settings to scan the QR code.</div>
                    <?php if (!empty($this->runData['data']['mfa_hint'])) { ?>
                        <div class="text-muted small mt-1">Demo code: <?php echo htmlspecialchars($this->runData['data']['mfa_hint']); ?></div>
                    <?php } ?>
                </div>
                <?php if (!empty($this->runData['data']['trust_requested'])) { ?>
                <div class="alert alert-info py-2">
                    This device will be trusted after successful verification.
                </div>
                <?php } ?>
                <button type="submit" class="btn btn-primary w-100">Verify</button>
            </form>
            <div class="mt-3 text-center">
                <a href="<?php echo $this->runData['config']['sys']['base_url']; ?>/login/logout">Use a different account</a>
            </div>
        </div>
    </div>
</main>
<script src="<?php echo $this->runData['route']['assets_url']; ?>/vendor/bootstrap/bootstrap-5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
