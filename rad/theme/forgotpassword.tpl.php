<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
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
      max-width: 1000px;
      padding: 2rem;
      margin: 2rem auto;
    }
    .form-floating > .form-control {
        padding: 1.5rem .75rem;
    }
    .btn {
        padding: .75rem;
        font-size: 1.25rem;
    }
</style>
	
</head>
<body>
    <main class="form-signin m-auto d-flex justify-content-center align-items-center vh-100">
    <form action="<?php
        $action = $this->runData['config']['sys']['base_url'].'/login/forgotpassword/';
        if (!empty($this->runData['route']['reset_mode']) && !empty($this->runData['route']['reset_token'])) {
            $action .= $this->runData['route']['reset_token'];
        }
        if (($this->runData['request']->get['debug_block'] ?? '') === '1') {
            $action .= '?debug_block=1';
        }
        echo $action;
    ?>" method="post" class="needs-validation rounded bg-white p-4 shadow" novalidate style="width: 500px;">
        <img src="<?php print $this->runData['route']['assets_url'];?>/img/logo-icon.svg" alt="<?php print $this->runData['config']['sys']['project_title'];?>" width="64" height="64" class="mx-auto d-block">
        <h1 class="h3 my-3 fw-normal text-center">
            <?php echo !empty($this->runData['route']['reset_mode']) ? 'Reset Password' : 'Forgot Password'; ?>
        </h1>
        <p class="text-center text-muted mb-3">
            <?php echo !empty($this->runData['route']['reset_mode'])
                ? 'Create a new password to access your account.'
                : 'Enter your email address to receive a reset link.'; ?>
        </p>

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

        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($this->runData['request']->csrf_token ?? '', ENT_QUOTES, 'UTF-8'); ?>">
        <?php if (!empty($this->runData['route']['reset_mode']) && empty($this->runData['route']['reset_invalid'])): ?>
            <div class="form-floating mb-3">
                <input type="password" class="form-control" id="new_password" name="new_password" placeholder="New password" required minlength="8">
                <label for="new_password">New password</label>
            </div>
            <div class="form-floating mb-3">
                <input type="password" class="form-control" id="confirm_password" name="confirm_password" placeholder="Confirm password" required minlength="8">
                <label for="confirm_password">Confirm password</label>
            </div>
            <button class="btn btn-primary w-100 py-2" type="submit">Update Password</button>
        <?php elseif (!empty($this->runData['route']['reset_mode'])): ?>
            <div class="text-center mt-2">
                <a href="<?php echo $this->runData['config']['sys']['base_url'] . '/login/forgotpassword/'; ?>">Request a new reset link</a>
            </div>
        <?php else: ?>
            <div class="form-floating mb-3">
                <input type="email" class="form-control" id="s_username" name="s_username" placeholder="name@example.com" required>
                <label for="s_username">Email address</label>
            </div>
            <button class="btn btn-primary w-100 py-2" type="submit">Send reset link</button>
        <?php endif; ?>
        <!-- create a reset password link -->
        <div class="text-center mt-3">
            <a href="<?php echo $this->runData['config']['sys']['base_url'] . '/login/localsession/'; ?>">Back to Login</a>
        </div>
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
