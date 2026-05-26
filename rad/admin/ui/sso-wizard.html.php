<?php
$radAdminUrl = $this->runData['route']['rad_admin_url'] ?? '';
$preset = $this->runData['data']['wizard_preset'] ?? [];
$form = $this->runData['data']['wizard_form'] ?? [];
$providerKey = $this->runData['data']['wizard_provider_key'] ?? 'custom';
$callbackUrl = $this->runData['data']['wizard_callback_url'] ?? '';
$providerLabel = $preset['label'] ?? 'Provider';
$tenantLabel = $preset['tenant_label'] ?? '';
$tenantPlaceholder = $preset['tenant_placeholder'] ?? '';
$friendlyHelp = $preset['friendly_help'] ?? [];
$beforeYouStart = $friendlyHelp['before_you_start'] ?? [];
$fieldHelp = $friendlyHelp['field_help'] ?? [];
$resources = $preset['resources'] ?? [];
$docsUrl = (string)($resources['docs_url'] ?? '');
$videoUrl = (string)($resources['video_url'] ?? '');
?>

<div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center mb-3 gap-2">
    <div>
        <!-- <h2 class="h4 mb-0">SSO Wizard · <?php echo htmlspecialchars((string)$providerLabel); ?></h2> -->
        <div class="text-muted small">Guided setup for common providers with discovery and validation.</div>
    </div>
    <div class="btn-group">
        <a href="<?php echo $radAdminUrl; ?>/sso/setup" class="btn btn-outline-secondary">Change Provider</a>
        <a href="<?php echo $radAdminUrl; ?>/sso/view" class="btn btn-outline-secondary">Providers</a>
    </div>
</div>

<div class="card shadow-sm mb-3">
    <div class="card-body">
        <div class="d-flex flex-wrap gap-2 small">
            <span class="badge bg-primary">1. Provider</span>
            <span class="badge bg-primary">2. Credentials</span>
            <span class="badge bg-primary">3. Discovery</span>
            <span class="badge bg-primary">4. Save & Test</span>
        </div>
    </div>
</div>

<?php if (!empty($beforeYouStart)) { ?>
<div class="card shadow-sm mb-3">
    <div class="card-body">
        <h3 class="h6 mb-2">Before You Start</h3>
        <ul class="small mb-0">
            <?php foreach ($beforeYouStart as $tip) { ?>
                <li><?php echo htmlspecialchars((string)$tip); ?></li>
            <?php } ?>
        </ul>
        <?php if ($docsUrl !== '' || $videoUrl !== '') { ?>
            <div class="mt-3 d-flex flex-wrap gap-2">
                <?php if ($docsUrl !== '') { ?>
                    <a class="btn btn-sm btn-outline-primary" href="<?php echo htmlspecialchars($docsUrl); ?>" target="_blank" rel="noopener">Official Docs</a>
                <?php } ?>
                <?php if ($videoUrl !== '') { ?>
                    <a class="btn btn-sm btn-outline-secondary" href="<?php echo htmlspecialchars($videoUrl); ?>" target="_blank" rel="noopener">Video Walkthrough</a>
                <?php } ?>
            </div>
        <?php } ?>
    </div>
</div>
<?php } ?>

<form method="post" action="<?php echo $radAdminUrl; ?>/sso/wizard">
    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($this->runData['request']->csrf_token); ?>">
    <input type="hidden" name="provider" value="<?php echo htmlspecialchars((string)$providerKey); ?>">

    <div class="card shadow-sm mb-3">
        <div class="card-body">
            <h3 class="h6 mb-3">Provider Context</h3>
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">Provider Name</label>
                    <input class="form-control" type="text" name="s_provider_name" value="<?php echo htmlspecialchars((string)($form['s_provider_name'] ?? '')); ?>" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Status after save</label>
                    <select class="form-select" name="s_status">
                        <?php $st = (string)($form['s_status'] ?? 'inactive'); ?>
                        <option value="inactive" <?php echo $st === 'inactive' ? 'selected' : ''; ?>>Inactive (recommended until test)</option>
                        <option value="active" <?php echo $st === 'active' ? 'selected' : ''; ?>>Active</option>
                    </select>
                </div>
                <?php if ($tenantLabel !== '') { ?>
                    <div class="col-md-6">
                        <label class="form-label"><?php echo htmlspecialchars((string)$tenantLabel); ?></label>
                        <input class="form-control" type="text" name="wizard_tenant_value" value="<?php echo htmlspecialchars((string)($form['wizard_tenant_value'] ?? '')); ?>" placeholder="<?php echo htmlspecialchars((string)$tenantPlaceholder); ?>">
                        <?php if (!empty($fieldHelp['wizard_tenant_value'])) { ?><div class="form-text"><?php echo htmlspecialchars((string)$fieldHelp['wizard_tenant_value']); ?></div><?php } ?>
                    </div>
                <?php } ?>
                <div class="col-md-6">
                    <label class="form-label">Issuer URL</label>
                    <div class="input-group">
                        <input id="sso_issuer" class="form-control" type="url" name="s_issuer" value="<?php echo htmlspecialchars((string)($form['s_issuer'] ?? '')); ?>" placeholder="https://issuer.example.com">
                        <button class="btn btn-outline-secondary" type="button" data-copy-target="sso_issuer">Copy</button>
                    </div>
                    <div class="form-text">Used for OpenID discovery and token validation.</div>
                    <?php if (!empty($fieldHelp['s_issuer'])) { ?><div class="form-text"><?php echo htmlspecialchars((string)$fieldHelp['s_issuer']); ?></div><?php } ?>
                </div>
            </div>
        </div>
    </div>

    <div class="card shadow-sm mb-3">
        <div class="card-body">
            <h3 class="h6 mb-3">App Credentials</h3>
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">Client ID</label>
                    <input class="form-control" type="text" name="s_client_id" value="<?php echo htmlspecialchars((string)($form['s_client_id'] ?? '')); ?>">
                    <?php if (!empty($fieldHelp['s_client_id'])) { ?><div class="form-text"><?php echo htmlspecialchars((string)$fieldHelp['s_client_id']); ?></div><?php } ?>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Client Secret</label>
                    <input class="form-control" type="password" name="s_client_secret" value="<?php echo htmlspecialchars((string)($form['s_client_secret'] ?? '')); ?>" autocomplete="new-password">
                    <?php if (!empty($fieldHelp['s_client_secret'])) { ?><div class="form-text"><?php echo htmlspecialchars((string)$fieldHelp['s_client_secret']); ?></div><?php } ?>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Scopes</label>
                    <input class="form-control" type="text" name="s_scopes" value="<?php echo htmlspecialchars((string)($form['s_scopes'] ?? 'openid profile email')); ?>">
                    <div class="form-text">Default is usually enough. Change only if your identity team asked for extra scopes.</div>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Redirect Path</label>
                    <input class="form-control" type="text" name="s_redirect_path" value="<?php echo htmlspecialchars((string)($form['s_redirect_path'] ?? '/login/sso-callback')); ?>">
                    <div class="form-text">
                        Callback URL:
                        <code id="sso_callback_text"><?php echo htmlspecialchars((string)$callbackUrl); ?></code>
                        <button class="btn btn-link btn-sm p-0 align-baseline" type="button" data-copy-value="<?php echo htmlspecialchars((string)$callbackUrl); ?>">Copy</button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="card shadow-sm mb-3">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h3 class="h6 mb-0">Provider Endpoints</h3>
                <button class="btn btn-outline-primary btn-sm" type="submit" name="action" value="discover">Run Discovery</button>
            </div>
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">Auth URL</label>
                    <input class="form-control" type="url" name="s_auth_url" value="<?php echo htmlspecialchars((string)($form['s_auth_url'] ?? '')); ?>">
                    <?php if (!empty($fieldHelp['s_auth_url'])) { ?><div class="form-text"><?php echo htmlspecialchars((string)$fieldHelp['s_auth_url']); ?></div><?php } ?>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Token URL</label>
                    <input class="form-control" type="url" name="s_token_url" value="<?php echo htmlspecialchars((string)($form['s_token_url'] ?? '')); ?>">
                    <?php if (!empty($fieldHelp['s_token_url'])) { ?><div class="form-text"><?php echo htmlspecialchars((string)$fieldHelp['s_token_url']); ?></div><?php } ?>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Userinfo URL</label>
                    <input class="form-control" type="url" name="s_userinfo_url" value="<?php echo htmlspecialchars((string)($form['s_userinfo_url'] ?? '')); ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label">JWKS URL</label>
                    <input class="form-control" type="url" name="s_jwks_url" value="<?php echo htmlspecialchars((string)($form['s_jwks_url'] ?? '')); ?>">
                    <?php if (!empty($fieldHelp['s_jwks_url'])) { ?><div class="form-text"><?php echo htmlspecialchars((string)$fieldHelp['s_jwks_url']); ?></div><?php } ?>
                </div>
            </div>
        </div>
    </div>

    <div class="card shadow-sm mb-3">
        <div class="card-body">
            <h3 class="h6 mb-3">Claims</h3>
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">Claim Map (JSON)</label>
                    <textarea class="form-control font-monospace" rows="5" name="s_claim_map"><?php echo htmlspecialchars((string)($form['s_claim_map'] ?? '')); ?></textarea>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Notes</label>
                    <input class="form-control" type="text" name="s_notes" value="<?php echo htmlspecialchars((string)($form['s_notes'] ?? '')); ?>">
                    <label class="form-label mt-3">Raw Configuration (JSON)</label>
                    <textarea class="form-control font-monospace" rows="3" name="s_sso_configuration"><?php echo htmlspecialchars((string)($form['s_sso_configuration'] ?? '')); ?></textarea>
                </div>
            </div>
        </div>
    </div>

    <input type="hidden" name="s_provider_type" value="<?php echo htmlspecialchars((string)($form['s_provider_type'] ?? ($preset['provider_type'] ?? 'oidc'))); ?>">

    <div class="d-flex gap-2">
        <button class="btn btn-primary" type="submit" name="action" value="save">Save Provider</button>
        <a class="btn btn-outline-secondary" href="<?php echo $radAdminUrl; ?>/sso/setup">Cancel</a>
    </div>
</form>

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
                var id = btn.getAttribute('data-copy-target');
                var el = document.getElementById(id);
                var text = el ? (el.value || el.textContent || '') : '';
                copyText(text, function (ok) { flash(btn, ok); });
            });
        });

        document.querySelectorAll('[data-copy-value]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var text = btn.getAttribute('data-copy-value') || '';
                copyText(text, function (ok) { flash(btn, ok); });
            });
        });
    })();
</script>
