<?php
$radAdminUrl = $this->runData['route']['rad_admin_url'] ?? '';
$assertion = $this->runData['data']['assertion'] ?? '';
$loginUrl = $this->runData['data']['login_url'] ?? '';
$post = $this->runData['request']->post ?? [];
$pref = function (string $key, string $default = '') use ($post) {
    $val = isset($post[$key]) ? (string)$post[$key] : $default;
    return htmlspecialchars($val);
};
?>

<div class="card shadow-sm mb-3">
    <div class="card-body">
        <!-- <h2 class="h5 mb-2">Issue SSO Assertion</h2> -->
        <p class="text-muted small mb-3">Use this only for the legacy assertion-based SSO flow (`/login/sso`). If your provider uses OIDC/OAuth login, use the SSO Provider wizard instead.</p>

        <div class="alert alert-info small mb-0">
            <strong>Why this page exists:</strong> It creates a short-lived signed token for a specific user email. The token can be used once within its expiry window to log in through the legacy SSO endpoint.
        </div>
    </div>
</div>

<div class="row g-3">
    <div class="col-lg-8">
        <div class="card shadow-sm">
            <div class="card-body">
                <h3 class="h6 mb-3">Generate Assertion</h3>
                <form method="post" action="<?php echo $radAdminUrl; ?>/sso/issue">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($this->runData['request']->csrf_token); ?>">

                    <div class="mb-3">
                        <label class="form-label">User email</label>
                        <input type="email" class="form-control" name="email" required value="<?php echo $pref('email'); ?>" placeholder="user@example.com">
                        <div class="form-text">Must match an existing system user identity (`s_entity.s_identity`).</div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Audience</label>
                        <input type="text" class="form-control" name="aud" placeholder="rad-admin" value="<?php echo $pref('aud', 'rad-admin'); ?>">
                        <div class="form-text">Security check value. Keep `rad-admin` unless your system is configured with a different expected audience.</div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Redirect URL (optional)</label>
                        <input type="url" class="form-control" name="redirect" placeholder="https://app.example.com/home" value="<?php echo $pref('redirect'); ?>">
                        <div class="form-text">Where user lands after successful login. Leave empty to use default route resolution.</div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">TTL (seconds)</label>
                        <input type="number" class="form-control" name="ttl" value="<?php echo $pref('ttl', '900'); ?>" min="60" step="60">
                        <div class="form-text">How long token is valid. Recommended: 300-900 seconds for security.</div>
                    </div>

                    <button type="submit" class="btn btn-primary"><i class="bi bi-box-arrow-in-right me-1"></i>Generate</button>
                    <a href="<?php echo $radAdminUrl; ?>/sso/view" class="btn btn-outline-secondary ms-2">Back</a>
                </form>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card shadow-sm h-100">
            <div class="card-body">
                <h3 class="h6 mb-3">How To Use</h3>
                <ol class="small mb-3">
                    <li>Enter the exact user email and keep TTL short.</li>
                    <li>Generate assertion and copy the login URL.</li>
                    <li>Open URL in a new browser session and verify login.</li>
                </ol>
                <div class="alert alert-warning small mb-0">
                    <strong>Security note:</strong> Anyone with this URL can log in as that user until token expiry. Do not share over insecure channels.
                </div>
            </div>
        </div>
    </div>
</div>

<?php if ($assertion) { ?>
<div class="card shadow-sm mt-3">
    <div class="card-body">
        <h3 class="h6 mb-3">Generated Output</h3>

        <div class="small mb-2"><strong>Assertion Token</strong></div>
        <div class="input-group mb-3">
            <textarea id="generated_assertion" class="form-control font-monospace" rows="3" readonly><?php echo htmlspecialchars($assertion); ?></textarea>
            <button class="btn btn-outline-secondary" type="button" data-copy-target="generated_assertion">Copy</button>
        </div>

        <?php if ($loginUrl) { ?>
            <div class="small mb-2"><strong>Login URL</strong></div>
            <div class="input-group mb-2">
                <input id="generated_login_url" class="form-control" type="text" readonly value="<?php echo htmlspecialchars($loginUrl); ?>">
                <button class="btn btn-outline-secondary" type="button" data-copy-target="generated_login_url">Copy</button>
            </div>
            <div class="small">
                <a href="<?php echo htmlspecialchars($loginUrl); ?>" target="_blank" rel="noopener">Open Login URL</a>
            </div>
        <?php } ?>
    </div>
</div>

<script>
    (function () {
        function copyText(text, done) {
            if (!text) { done(false); return; }
            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(text).then(function () { done(true); }).catch(function () { done(false); });
                return;
            }
            var ta = document.createElement('textarea');
            ta.value = text;
            ta.style.position = 'fixed';
            ta.style.opacity = '0';
            document.body.appendChild(ta);
            ta.focus();
            ta.select();
            var ok = false;
            try { ok = document.execCommand('copy'); } catch (e) { ok = false; }
            document.body.removeChild(ta);
            done(ok);
        }

        function flash(btn, ok) {
            var original = btn.textContent;
            btn.textContent = ok ? 'Copied' : 'Copy failed';
            setTimeout(function () { btn.textContent = original; }, 1200);
        }

        document.querySelectorAll('[data-copy-target]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var targetId = btn.getAttribute('data-copy-target');
                var el = document.getElementById(targetId);
                var text = el ? (el.value || el.textContent || '') : '';
                copyText(text, function (ok) { flash(btn, ok); });
            });
        });
    })();
</script>
<?php } ?>
