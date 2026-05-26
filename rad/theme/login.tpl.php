<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="<?php print $this->runData['route']['meta_description'];?>">
    <meta name="author" content="<?php print $this->runData['config']['sys']['author'];?>">
    <title><?php print $this->runData['route']['meta_title'];?></title>
    <link rel="canonical" href="<?php print $this->runData['route']['url'];?>">

    <!-- Bootstrap CSS -->
    <?php
    echo '<link href="'.$this->runData['route']['assets_url'].'/vendor/bootstrap/bootstrap-5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">';
    echo '<link href="'.$this->runData['route']['assets_url'].'/css/app.css" rel="stylesheet">';
    echo '<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">';
    ?>

<style>
    .form-signin {
      max-width: 1000px;  /* Adjust this value to fit your needs */
      padding: 2rem;
      margin: 2rem auto;
    } 

    .form-floating > .form-control {
        padding: 1.5rem .75rem;
    }

    .form-check-input {
        margin-top: 0;
        margin-left: 0;
    }
    .form-check-input {
        margin: 0.3rem;
    }

    .btn {
        padding: .75rem;
        font-size: 1.25rem;
    }
</style>
</head>
<body class="d-flex align-items-center py-4 bg-light">
    <?php
    $ssoClientEnabled = !empty($this->runData['data']['sso_client_enabled']);
    $ssoClientLoginUrl = $this->runData['data']['sso_client_login_url'] ?? '';
    $ssoClientLabel = $this->runData['data']['sso_client_label'] ?? 'Sign in with Organization SSO';
    $redirectUrlPostLogin = $this->runData['data']['redirect_url_post_login'] ?? '';
    ?>
    <main class="form-signin m-auto d-flex justify-content-center align-items-center vh-100">
    <form action="<?php echo $this->runData['config']['sys']['base_url'].'/login/localsession/'; ?>" method="post" class="needs-validation rounded bg-white p-4 shadow" novalidate style="width: 500px;">
        <input type="hidden" name="redirect_url_post_login" value="<?php echo htmlspecialchars($redirectUrlPostLogin, ENT_QUOTES, 'UTF-8'); ?>">
        <img src="<?php print $this->runData['route']['assets_url'];?>/img/logo-icon.svg" alt="<?php print $this->runData['config']['sys']['project_title'];?>" width="64" height="64" class="mx-auto d-block">
        <h1 class="h3 my-3 fw-normal text-center">Please sign in</h1>

        <?php if (isset($this->runData['route']['alert'])): ?>
            <div class="alert alert-<?php echo $this->runData['route']['alert']; ?>">
                <?php
                switch($this->runData['route']['alert']){
                    case 'success':
                        echo '<i class="bi bi-check-circle-fill"></i> ';
                        break;
                    case 'danger':
                        echo '<i class="bi bi-exclamation-circle-fill"></i> ';
                        break;
                    case 'info':
                        echo '<i class="bi bi-exclamation-circle-fill"></i> ';
                        break;
                    // Add more cases for other alert types as needed
                    default:
                        break;
                }
                ?>
                <?php echo $this->runData['route']['alert_message']; ?>
            </div>
        <?php endif; ?>

        <div class="form-floating mb-3">
            <input type="email" class="form-control" id="s_username" name="s_username" placeholder="name@example.com" required>
            <label for="s_username">Email address</label>
        </div>
        <div class="form-floating mb-3">
            <input type="password" class="form-control" id="s_password" name="s_password" placeholder="Password" required>
            <label for="s_password">Password</label>
        </div>

        <div class="d-flex justify-content-end mb-3">
            <a class="small" href="<?php echo $this->runData['config']['sys']['base_url'].'/login/forgotpassword/'; ?>">Forgot password?</a>
        </div>

        <div class="mb-3">
          <div class="form-check">
              <input class="form-check-input" type="checkbox" value="remember-me" id="s_rememberme" name="s_rememberme">
              <label class="form-check-label" for="s_rememberme">
                  Remember me
              </label>
          </div>
        </div>
        <button class="btn btn-primary w-100 py-2" type="submit">Sign in</button>
        <?php if ($ssoClientEnabled && $ssoClientLoginUrl !== ''): ?>
            <div class="text-center text-muted small my-2">or</div>
            <a class="btn btn-outline-primary w-100 py-2" href="<?php echo htmlspecialchars($ssoClientLoginUrl); ?>">
                <i class="bi bi-building-lock me-1"></i><?php echo htmlspecialchars($ssoClientLabel); ?>
            </a>
            <div class="small text-muted mt-2">Use local sign-in only for fallback/admin recovery.</div>
        <?php endif; ?>
    </form>
    </main>

    <!-- Bootstrap JS and jQuery -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.5.1/jquery.min.js"></script>
    <?php
    echo '<script src="'.$this->runData['route']['assets_url'].'/vendor/bootstrap/bootstrap-5.3.0/dist/js/bootstrap.bundle.min.js"></script>';
    ?>
    <!-- End Bootstrap JS and jQuery -->
</body>
</html>
