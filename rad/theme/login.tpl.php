<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="<?php print $this->runData['route']['meta_description'];?>">
    <meta name="author" content="<?php print $this->runData['config']['sys']['author'];?>">
    <title><?php print $this->runData['route']['meta_title'];?></title>
    <link rel="canonical" href="<?php print $this->runData['route']['url'];?>">

    <?php
    echo \Core\Sys\ThemeAssets::renderHead($this->runData, ['app' => false]);
    ?>
</head>
<body class="rad-auth-page">
    <?php
    $ssoClientEnabled = !empty($this->runData['data']['sso_client_enabled']);
    $ssoClientLoginUrl = $this->runData['data']['sso_client_login_url'] ?? '';
    $ssoClientLabel = $this->runData['data']['sso_client_label'] ?? 'Sign in with Organization SSO';
    $redirectUrlPostLogin = $this->runData['data']['redirect_url_post_login'] ?? '';
    ?>
    <main class="rad-auth-shell">
    <form action="<?php echo $this->runData['config']['sys']['base_url'].'/login/localsession/'; ?>" method="post" class="rad-auth-card" novalidate>
        <input type="hidden" name="redirect_url_post_login" value="<?php echo htmlspecialchars($redirectUrlPostLogin, ENT_QUOTES, 'UTF-8'); ?>">
        <img src="<?php print $this->runData['route']['assets_url'];?>/img/logo-icon.svg" alt="<?php print $this->runData['config']['sys']['project_title'];?>" class="rad-auth-logo">
        <h1 class="rad-auth-title">Please sign in</h1>

        <?php if (isset($this->runData['route']['alert'])): ?>
            <div class="rad-alert rad-alert-<?php echo htmlspecialchars($this->runData['route']['alert'], ENT_QUOTES, 'UTF-8'); ?>">
                <?php echo $this->runData['route']['alert_message']; ?>
            </div>
        <?php endif; ?>

        <div class="rad-field">
            <label class="rad-label" for="s_username">Email address</label>
            <input type="email" class="rad-input" id="s_username" name="s_username" placeholder="name@example.com" required>
        </div>
        <div class="rad-field">
            <label class="rad-label" for="s_password">Password</label>
            <input type="password" class="rad-input" id="s_password" name="s_password" placeholder="Password" required>
        </div>

        <div class="rad-row">
            <span></span>
            <a class="rad-link" href="<?php echo $this->runData['config']['sys']['base_url'].'/login/forgotpassword/'; ?>">Forgot password?</a>
        </div>

        <div class="rad-row">
          <div class="rad-checkbox">
              <input type="checkbox" value="remember-me" id="s_rememberme" name="s_rememberme">
              <label for="s_rememberme">
                  Remember me
              </label>
          </div>
        </div>
        <button class="rad-btn rad-btn-primary" type="submit">Sign in</button>
        <?php if ($ssoClientEnabled && $ssoClientLoginUrl !== ''): ?>
            <div class="rad-auth-divider">or</div>
            <a class="rad-btn rad-btn-secondary" href="<?php echo htmlspecialchars($ssoClientLoginUrl); ?>">
                <?php echo htmlspecialchars($ssoClientLabel); ?>
            </a>
            <div class="rad-muted rad-text-center">Use local sign-in only for fallback/admin recovery.</div>
        <?php endif; ?>
    </form>
    </main>

    <?php echo \Core\Sys\ThemeAssets::renderBody($this->runData); ?>
</body>
</html>
