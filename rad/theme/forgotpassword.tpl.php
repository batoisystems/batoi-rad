<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="<?php print $this->runData['route']['meta_description'];?>">
    <meta name="author" content="<?php print $this->runData['config']['sys']['author'];?>">
    <title><?php print $this->runData['route']['meta_title'];?></title>
    <link rel="canonical" href="<?php print $this->runData['route']['url'];?>">

    <?php
    echo \Core\Sys\ThemeAssets::renderHead($this->runData, ['app' => false]);
    ?>
</head>
<body class="rad-auth-page">
    <main class="rad-auth-shell">
    <form action="<?php
        $action = $this->runData['config']['sys']['base_url'].'/login/forgotpassword/';
        if (!empty($this->runData['route']['reset_mode']) && !empty($this->runData['route']['reset_token'])) {
            $action .= $this->runData['route']['reset_token'];
        }
        if (($this->runData['request']->get['debug_block'] ?? '') === '1') {
            $action .= '?debug_block=1';
        }
        echo $action;
    ?>" method="post" class="rad-auth-card" novalidate>
        <img src="<?php print $this->runData['route']['assets_url'];?>/img/logo-icon.svg" alt="<?php print $this->runData['config']['sys']['project_title'];?>" class="rad-auth-logo">
        <h1 class="rad-auth-title">
            <?php echo !empty($this->runData['route']['reset_mode']) ? 'Reset Password' : 'Forgot Password'; ?>
        </h1>
        <p class="rad-auth-copy">
            <?php echo !empty($this->runData['route']['reset_mode'])
                ? 'Create a new password to access your account.'
                : 'Enter your email address to receive a reset link.'; ?>
        </p>

        <?php if (isset($this->runData['route']['alert'])): ?>
            <div class="rad-alert rad-alert-<?php echo htmlspecialchars($this->runData['route']['alert'], ENT_QUOTES, 'UTF-8'); ?>">
                <?php echo $this->runData['route']['alert_message']; ?>
            </div>
        <?php endif; ?>

        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($this->runData['request']->csrf_token ?? '', ENT_QUOTES, 'UTF-8'); ?>">
        <?php if (!empty($this->runData['route']['reset_mode']) && empty($this->runData['route']['reset_invalid'])): ?>
            <div class="rad-field">
                <label class="rad-label" for="new_password">New password</label>
                <input type="password" class="rad-input" id="new_password" name="new_password" placeholder="New password" required minlength="8">
            </div>
            <div class="rad-field">
                <label class="rad-label" for="confirm_password">Confirm password</label>
                <input type="password" class="rad-input" id="confirm_password" name="confirm_password" placeholder="Confirm password" required minlength="8">
            </div>
            <button class="rad-btn rad-btn-primary" type="submit">Update Password</button>
        <?php elseif (!empty($this->runData['route']['reset_mode'])): ?>
            <div class="rad-text-center">
                <a class="rad-link" href="<?php echo $this->runData['config']['sys']['base_url'] . '/login/forgotpassword/'; ?>">Request a new reset link</a>
            </div>
        <?php else: ?>
            <div class="rad-field">
                <label class="rad-label" for="s_username">Email address</label>
                <input type="email" class="rad-input" id="s_username" name="s_username" placeholder="name@example.com" required>
            </div>
            <button class="rad-btn rad-btn-primary" type="submit">Send reset link</button>
        <?php endif; ?>
        <div class="rad-auth-divider">
            <a class="rad-link" href="<?php echo $this->runData['config']['sys']['base_url'] . '/login/localsession/'; ?>">Back to Login</a>
        </div>
    </form>
    </main>

    <?php echo \Core\Sys\ThemeAssets::renderBody($this->runData); ?>
</body>
</html>
