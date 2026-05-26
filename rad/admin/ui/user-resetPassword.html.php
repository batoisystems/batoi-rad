<?php
$user = $this->runData['data']['user'] ?? [];
$accessUrl = $this->runData['data']['access_url'] ?? '';
$resetResult = $this->runData['data']['reset_result'] ?? null;
$radAdminUrl = $this->runData['route']['rad_admin_url'] ?? '';
$uid = htmlspecialchars($user['uid'] ?? '');
$username = htmlspecialchars($user['username'] ?? '');
$userName = htmlspecialchars($user['name'] ?? 'User');
?>

<div class="card shadow-sm mb-3">
    <div class="card-body d-flex flex-column flex-md-row justify-content-between gap-3">
        <div>
            <h2 class="h5 mb-1"><?php echo $userName; ?></h2>
            <div class="text-muted small">@<?php echo $username; ?></div>
            <div class="text-muted small">UID: <?php echo $uid; ?></div>
        </div>
        <div class="d-flex align-items-center gap-2">
            <a class="btn btn-outline-secondary" href="<?php echo $radAdminUrl; ?>/user/viewone/<?php echo $uid; ?>">
                <i class="bi bi-arrow-left me-1"></i>Back to User
            </a>
        </div>
    </div>
</div>

<div class="row g-3">
    <div class="col-lg-6">
        <div class="card shadow-sm">
            <div class="card-header bg-white">
                <h3 class="h6 mb-0">Reset Password</h3>
            </div>
            <div class="card-body">
                <form action="<?php echo $radAdminUrl; ?>/user/resetPassword/<?php echo $uid; ?>" method="post" autocomplete="off">
                    <div class="mb-3">
                        <label class="form-label">New password</label>
                        <div class="input-group">
                            <input type="password" class="form-control" name="s_password" id="reset-password-input" placeholder="Leave blank to auto-generate">
                            <button class="btn btn-outline-secondary" type="button" data-toggle-password="reset-password-input">
                                <i class="bi bi-eye"></i>
                            </button>
                        </div>
                        <div class="form-text">Minimum 8 characters. Leave blank to auto-generate on submit.</div>
                    </div>
                    <div class="d-flex flex-wrap gap-2">
                        <button type="button" class="btn btn-outline-primary" id="generate-password">
                            <i class="bi bi-shuffle me-1"></i>Generate
                        </button>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-key me-1"></i>Reset Password
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="col-lg-6">
        <div class="card shadow-sm">
            <div class="card-header bg-white">
                <h3 class="h6 mb-0">Access Bundle</h3>
            </div>
            <div class="card-body">
                <?php if ($resetResult): ?>
                    <?php $password = htmlspecialchars($resetResult['password'] ?? ''); ?>
                    <div class="mb-3">
                        <label class="form-label">Access URL</label>
                        <input type="text" class="form-control" value="<?php echo htmlspecialchars($accessUrl); ?>" readonly>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Username</label>
                        <input type="text" class="form-control" value="<?php echo $username; ?>" readonly>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Password</label>
                        <div class="input-group">
                            <input type="password" class="form-control" id="reset-password-result" value="<?php echo $password; ?>" readonly>
                            <button class="btn btn-outline-secondary" type="button" data-toggle-password="reset-password-result">
                                <i class="bi bi-eye"></i>
                            </button>
                        </div>
                        <div class="form-text">Store this password securely. It will not be shown again after leaving this page.</div>
                    </div>
                    <div class="d-flex flex-wrap gap-2">
                        <button type="button" class="btn btn-outline-secondary" data-copy-target="reset-password-result">
                            <i class="bi bi-clipboard me-1"></i>Copy Password
                        </button>
                        <button type="button" class="btn btn-outline-primary" id="copy-access-bundle"
                                data-access-url="<?php echo htmlspecialchars($accessUrl); ?>"
                                data-username="<?php echo $username; ?>"
                                data-password="<?php echo $password; ?>">
                            <i class="bi bi-clipboard-check me-1"></i>Copy Access Bundle
                        </button>
                    </div>
                <?php else: ?>
                    <div class="text-muted small">Reset a password to generate an access bundle for sharing.</div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
