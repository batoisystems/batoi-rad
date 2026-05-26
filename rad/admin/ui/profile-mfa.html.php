<?php
$mfa = $this->runData['data']['mfa'] ?? [];
$otpauth = $this->runData['data']['otpauth'] ?? null;
$plainBackup = $this->runData['data']['plain_backup'] ?? [];
$secret = $this->runData['data']['secret'] ?? ($mfa['secret'] ?? '');
$radAdminUrl = $this->runData['route']['rad_admin_url'] ?? '';
$active = 'mfa';
$profileNav = $this->runData['config']['dir']['admin'] . '/ui/profile-nav.partial.php';
$qrPng = null;
$qrDataUri = '';
$qrError = '';
if (!empty($otpauth)) {
    $qrPath = ($this->runData['config']['dir']['vendor'] ?? '') . '/tcpdf/tcpdf_barcodes_2d.php';
    if (is_file($qrPath)) {
        ob_start();
        require_once $qrPath;
        ob_end_clean();
        if (class_exists('TCPDF2DBarcode')) {
            $qr = new \TCPDF2DBarcode($otpauth, 'QRCODE');
            $qrPng = $qr->getBarcodePngData(6, 6, [0, 0, 0]);
            if ($qrPng instanceof \Imagick) {
                $qrPng = $qrPng->getImageBlob();
            }
            if (!is_string($qrPng) || $qrPng === '') {
                $qrPng = null;
            }
            if (function_exists('imagecreatefromstring')) {
                $src = $qrPng ? @imagecreatefromstring($qrPng) : false;
                if ($src) {
                    $pad = 20;
                    $w = imagesx($src);
                    $h = imagesy($src);
                    $canvas = imagecreatetruecolor($w + ($pad * 2), $h + ($pad * 2));
                    $white = imagecolorallocate($canvas, 255, 255, 255);
                    imagefill($canvas, 0, 0, $white);
                    imagecopy($canvas, $src, $pad, $pad, 0, 0, $w, $h);
                    ob_start();
                    imagepng($canvas);
                    $qrPng = (string)ob_get_clean();
                    imagedestroy($canvas);
                    imagedestroy($src);
                }
            }
            if ($qrPng) {
                $qrDataUri = 'data:image/png;base64,' . base64_encode($qrPng);
            } else {
                $qrError = 'QR renderer failed to generate image.';
            }
        } else {
            $qrError = 'QR renderer unavailable.';
        }
    } else {
        $qrError = 'QR renderer not installed.';
    }
}
?>
<?php include $profileNav; ?>
<div class="card shadow-sm">
    <div class="card-body">
        <h2 class="h5 mb-3">Multi-factor Authentication</h2>
        <p class="text-muted small">Use a TOTP authenticator app and backup codes to protect your account.</p>
        <p class="text-muted small">Recommended apps: Google Authenticator, Microsoft Authenticator, Authy, 1Password, Bitwarden.</p>
        <div class="mb-3">
            <span class="badge <?php echo !empty($mfa['enabled']) ? 'bg-success' : 'bg-secondary'; ?>">
                <?php echo !empty($mfa['enabled']) ? 'Enabled' : 'Disabled'; ?>
            </span>
            <?php if ($mfa['backup_count'] ?? 0) { ?>
                <span class="badge bg-info text-dark"><?php echo (int)$mfa['backup_count']; ?> backup codes</span>
            <?php } ?>
        </div>
        <div class="row g-3">
            <div class="col-lg-5">
                <div class="border rounded p-3 h-100">
                    <div class="fw-semibold mb-2">Step 1 · Generate secret</div>
                    <p class="text-muted small mb-3">Create a new secret key to pair your authenticator app.</p>
                    <form method="post" class="d-flex gap-2 flex-wrap" action="<?php echo $radAdminUrl; ?>/profile/mfa">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($this->runData['request']->csrf_token); ?>">
                        <button type="submit" name="action" value="reset" class="btn btn-outline-primary btn-sm">Generate secret</button>
                        <?php if (!empty($mfa['enabled'])) { ?>
                            <button type="submit" name="action" value="disable" class="btn btn-outline-danger btn-sm">Disable MFA</button>
                        <?php } ?>
                    </form>
                </div>
            </div>
            <div class="col-lg-7">
                <div class="border rounded p-3 h-100">
                    <div class="fw-semibold mb-2">Step 2 · Scan QR or copy key</div>
                    <?php if ($secret) { ?>
                        <div class="row g-3 align-items-center">
                            <div class="col-md-6">
                                <?php if (!empty($qrDataUri)) { ?>
                                    <div class="small text-muted mb-2">Scan this QR code in your authenticator app</div>
                                    <div class="border rounded p-2 d-inline-block bg-white">
                                        <img src="<?php echo $qrDataUri; ?>" alt="MFA QR code" width="200" height="200">
                                    </div>
                                <?php } elseif ($qrError !== '') { ?>
                                    <div class="alert alert-warning small"><?php echo htmlspecialchars($qrError); ?></div>
                                <?php } ?>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-2">
                                    <div class="small text-muted">Secret</div>
                                    <div class="d-flex align-items-center gap-2 flex-wrap">
                                        <code class="px-2 py-1 bg-light border rounded"><?php echo htmlspecialchars($secret); ?></code>
                                        <button class="btn btn-outline-secondary btn-sm" type="button" data-copy="<?php echo htmlspecialchars($secret); ?>">Copy</button>
                                    </div>
                                </div>
                                <?php if ($otpauth) { ?>
                                    <div class="mb-2">
                                        <div class="small text-muted">TOTP URL</div>
                                        <div class="d-flex align-items-center gap-2 flex-wrap">
                                            <code class="px-2 py-1 bg-light border rounded text-truncate" style="max-width: 260px;"><?php echo htmlspecialchars($otpauth); ?></code>
                                            <button class="btn btn-outline-secondary btn-sm" type="button" data-copy="<?php echo htmlspecialchars($otpauth); ?>">Copy</button>
                                        </div>
                                    </div>
                                <?php } ?>
                            </div>
                        </div>
                    <?php } else { ?>
                        <p class="text-muted small mb-0">Generate a secret to show the QR code and setup key.</p>
                    <?php } ?>
                </div>
            </div>
        </div>
        <div class="border rounded p-3 mt-3">
            <div class="fw-semibold mb-2">Step 3 · Verify to enable</div>
            <?php if ($secret) { ?>
                <form method="post" action="<?php echo $radAdminUrl; ?>/profile/mfa" class="row g-2 align-items-end">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($this->runData['request']->csrf_token); ?>">
                    <input type="hidden" name="action" value="verify">
                    <div class="col-sm-6">
                        <label class="form-label">Enter 6-digit code</label>
                        <input type="text" name="code" class="form-control" required inputmode="numeric">
                    </div>
                    <div class="col-sm-3">
                        <button type="submit" class="btn btn-success w-100">Verify & Enable</button>
                    </div>
                </form>
            <?php } else { ?>
                <p class="text-muted small mb-0">Generate and scan the secret before verifying.</p>
            <?php } ?>
        </div>
        <div class="border rounded p-3 mt-3">
            <div class="fw-semibold mb-2">Step 4 · Backup codes</div>
            <p class="text-muted small">Keep backup codes in a safe place. Each code can be used once.</p>
            <form method="post" action="<?php echo $radAdminUrl; ?>/profile/mfa" class="d-flex gap-2 flex-wrap mb-3">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($this->runData['request']->csrf_token); ?>">
                <button type="submit" name="action" value="regen-codes" class="btn btn-outline-secondary btn-sm">Generate backup codes</button>
            </form>
            <?php if (!empty($plainBackup)) { ?>
                <div class="alert alert-info">
                    <div class="fw-semibold mb-2">Save these backup codes (shown once):</div>
                    <div class="d-flex flex-wrap gap-2 mb-2">
                        <?php foreach ($plainBackup as $code) { ?>
                            <code><?php echo htmlspecialchars($code); ?></code>
                        <?php } ?>
                    </div>
                    <div class="d-flex gap-2 flex-wrap">
                        <button type="button" class="btn btn-outline-secondary btn-sm" data-copy="<?php echo htmlspecialchars(implode("\n", $plainBackup)); ?>">Copy codes</button>
                        <button type="button" class="btn btn-outline-secondary btn-sm" id="download-codes" data-codes="<?php echo htmlspecialchars(implode("\n", $plainBackup)); ?>">Download .txt</button>
                    </div>
                </div>
            <?php } ?>
        </div>
    </div>
</div>

<script>
    document.querySelectorAll('[data-copy]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            const text = btn.getAttribute('data-copy') || '';
            if (!text) { return; }
            navigator.clipboard.writeText(text).then(function () {
                btn.textContent = 'Copied';
                setTimeout(function () { btn.textContent = 'Copy'; }, 1200);
            });
        });
    });
    const downloadBtn = document.getElementById('download-codes');
    if (downloadBtn) {
        downloadBtn.addEventListener('click', function () {
            const payload = downloadBtn.getAttribute('data-codes') || '';
            const blob = new Blob([payload || ''], { type: 'text/plain' });
            const url = URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = 'backup-codes.txt';
            document.body.appendChild(a);
            a.click();
            a.remove();
            URL.revokeObjectURL(url);
        });
    }
</script>
