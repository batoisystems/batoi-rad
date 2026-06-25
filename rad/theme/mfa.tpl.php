<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo htmlspecialchars($this->runData['route']['meta_title'] ?? 'Multi-factor Verification'); ?></title>
    <?php echo \Core\Sys\ThemeAssets::renderHead($this->runData, ['app' => false]); ?>
</head>
<body class="rad-auth-page">
<main class="rad-auth-shell">
    <div class="rad-auth-card">
            <h1 class="rad-auth-title">Verify your identity</h1>
            <?php if (!empty($this->runData['route']['alert'])) { ?>
                <div class="rad-alert rad-alert-<?php echo htmlspecialchars($this->runData['route']['alert']); ?>">
                    <?php echo htmlspecialchars($this->runData['route']['alert_message'] ?? ''); ?>
                </div>
            <?php } ?>
            <form method="post" action="<?php echo $this->runData['config']['sys']['base_url']; ?>/login/mfa">
                <div class="rad-field">
                    <label class="rad-label">Verification code</label>
                    <input type="text" name="mfa_code" class="rad-input" inputmode="numeric" autofocus required>
                    <div class="rad-field-help">Enter the 6-digit code from your authenticator app or the code sent via SMS, WhatsApp, or email.</div>
                    <div class="rad-field-help">Need setup help? Open Profile MFA settings to scan the QR code.</div>
                    <?php if (!empty($this->runData['data']['mfa_hint'])) { ?>
                        <div class="rad-field-help">Demo code: <?php echo htmlspecialchars($this->runData['data']['mfa_hint']); ?></div>
                    <?php } ?>
                </div>
                <?php if (!empty($this->runData['data']['trust_requested'])) { ?>
                <div class="rad-alert rad-alert-info">
                    This device will be trusted after successful verification.
                </div>
                <?php } ?>
                <button type="submit" class="rad-btn rad-btn-primary">Verify</button>
            </form>
            <div class="rad-auth-divider">
                <a class="rad-link" href="<?php echo $this->runData['config']['sys']['base_url']; ?>/login/logout">Use a different account</a>
            </div>
    </div>
</main>
<?php echo \Core\Sys\ThemeAssets::renderBody($this->runData); ?>
</body>
</html>
