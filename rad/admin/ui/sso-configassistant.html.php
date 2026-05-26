<?php
$radAdminUrl = $this->runData['route']['rad_admin_url'] ?? '';
$data = $this->runData['data']['sso_config_assistant'] ?? [];
$form = $data['form'] ?? [];
$mode = (string)($data['mode'] ?? 'disabled');
$runtimeRole = (string)($data['runtime_role'] ?? 'disabled');
$snippet = (string)($data['snippet'] ?? '');
$report = $data['test_report'] ?? null;
$keyGenerationReport = $data['key_generation_report'] ?? null;
$privateCandidates = $form['server_private_key_candidates'] ?? [];
$publicCandidates = $form['server_public_key_candidates'] ?? [];
$keyFolderHint = (string)($form['server_key_folder_hint'] ?? '');
?>

<div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center mb-3 gap-3">
    <div>
        <!-- <h2 class="h4 mb-0">SSO Config Assistant</h2> -->
        <div class="text-muted small">Generate exact <code>sys.inc.php</code> code for server/client/disabled mode and validate current runtime SSO settings after you paste.</div>
    </div>
    <a href="<?php echo $radAdminUrl; ?>/sso/view" class="btn btn-outline-secondary">Back to SSO</a>
</div>

<div class="alert alert-info small">
    <strong>Runtime role now:</strong> <code><?php echo htmlspecialchars($runtimeRole); ?></code>
    <?php if ($runtimeRole !== $mode) { ?>
        <br><strong>Note:</strong> Selected snippet mode is <code><?php echo htmlspecialchars($mode); ?></code>. Test uses currently loaded runtime config.
    <?php } ?>
</div>

<div class="card shadow-sm mb-3">
    <div class="card-body">
        <form method="post" action="<?php echo $radAdminUrl; ?>/sso/configassistant">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($this->runData['request']->csrf_token); ?>">

            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label">Mode</label>
                    <select class="form-select" name="mode">
                        <option value="disabled" <?php echo $mode === 'disabled' ? 'selected' : ''; ?>>Disabled</option>
                        <option value="server" <?php echo $mode === 'server' ? 'selected' : ''; ?>>SSO Server</option>
                        <option value="client" <?php echo $mode === 'client' ? 'selected' : ''; ?>>SSO Client</option>
                    </select>
                    <div class="form-text">Choose what code you want to generate for this installation.</div>
                </div>
            </div>

            <?php if ($mode === 'server') { ?>
                <hr>
                <h3 class="h6">Server settings</h3>
                <div class="alert alert-secondary small">
                    <strong>About keys:</strong> The <code>private key</code> signs ID tokens on this server. The <code>public key</code> is shared via JWKS so clients can verify signatures.
                    Never share the private key with client applications.
                </div>
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Issuer URL</label>
                        <input type="url" class="form-control" name="server_issuer" value="<?php echo htmlspecialchars((string)($form['server_issuer'] ?? '')); ?>">
                        <div class="form-text">Usually your main domain, e.g. <code>https://batoi.com</code>.</div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Default client level</label>
                        <select class="form-select" name="server_default_client_level">
                            <option value="verify_only" <?php echo (($form['server_default_client_level'] ?? 'verify_only') === 'verify_only') ? 'selected' : ''; ?>>verify_only</option>
                            <option value="full_integration" <?php echo (($form['server_default_client_level'] ?? '') === 'full_integration') ? 'selected' : ''; ?>>full_integration</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Private key path</label>
                        <input type="text" class="form-control" name="server_private_key_path" value="<?php echo htmlspecialchars((string)($form['server_private_key_path'] ?? '')); ?>">
                        <div class="form-text">
                            Suggested path:
                            <?php if (!empty($privateCandidates)) { ?>
                                <code><?php echo htmlspecialchars((string)$privateCandidates[0]); ?></code>
                            <?php } else { ?>
                                Keep key file outside web-accessible directories.
                            <?php } ?>
                        </div>
                        <?php if (count($privateCandidates) > 1) { ?>
                            <div class="form-text text-muted">Other candidates: <code><?php echo htmlspecialchars((string)$privateCandidates[1]); ?></code><?php if (!empty($privateCandidates[2])) { ?>, <code><?php echo htmlspecialchars((string)$privateCandidates[2]); ?></code><?php } ?></div>
                        <?php } ?>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Public key path</label>
                        <input type="text" class="form-control" name="server_public_key_path" value="<?php echo htmlspecialchars((string)($form['server_public_key_path'] ?? '')); ?>">
                        <div class="form-text">
                            Suggested path:
                            <?php if (!empty($publicCandidates)) { ?>
                                <code><?php echo htmlspecialchars((string)$publicCandidates[0]); ?></code>
                            <?php } else { ?>
                                Store with the private key in a secure folder.
                            <?php } ?>
                        </div>
                        <?php if (count($publicCandidates) > 1) { ?>
                            <div class="form-text text-muted">Other candidates: <code><?php echo htmlspecialchars((string)$publicCandidates[1]); ?></code><?php if (!empty($publicCandidates[2])) { ?>, <code><?php echo htmlspecialchars((string)$publicCandidates[2]); ?></code><?php } ?></div>
                        <?php } ?>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Access token TTL (seconds)</label>
                        <input type="number" min="60" class="form-control" name="server_access_token_ttl" value="<?php echo htmlspecialchars((string)($form['server_access_token_ttl'] ?? '900')); ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">ID token TTL (seconds)</label>
                        <input type="number" min="60" class="form-control" name="server_id_token_ttl" value="<?php echo htmlspecialchars((string)($form['server_id_token_ttl'] ?? '900')); ?>">
                    </div>
                    <div class="col-12">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" value="1" id="server_include_sample_clients" name="server_include_sample_clients" <?php echo !empty($form['server_include_sample_clients']) ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="server_include_sample_clients">Include sample <code>sso_server.clients</code> block in snippet (legacy/manual fallback)</label>
                        </div>
                        <div class="form-check mt-2">
                            <input class="form-check-input" type="checkbox" value="1" id="server_overwrite_keys" name="server_overwrite_keys" <?php echo !empty($form['server_overwrite_keys']) ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="server_overwrite_keys">Overwrite existing key files (rotation)</label>
                        </div>
                        <?php if ($keyFolderHint !== '') { ?>
                            <div class="form-text mt-2">If keys are not created yet, generate them in <code><?php echo htmlspecialchars($keyFolderHint); ?></code> with OpenSSL and keep file permissions restricted.</div>
                        <?php } ?>
                    </div>
                </div>
            <?php } ?>

            <?php if ($mode === 'client') { ?>
                <hr>
                <h3 class="h6">Client settings</h3>
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Login button label</label>
                        <input type="text" class="form-control" name="client_label" value="<?php echo htmlspecialchars((string)($form['client_label'] ?? '')); ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Integration level</label>
                        <select class="form-select" name="client_integration_level">
                            <option value="verify_only" <?php echo (($form['client_integration_level'] ?? 'verify_only') === 'verify_only') ? 'selected' : ''; ?>>verify_only</option>
                            <option value="full_integration" <?php echo (($form['client_integration_level'] ?? '') === 'full_integration') ? 'selected' : ''; ?>>full_integration</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Server base URL</label>
                        <input type="url" class="form-control" name="client_server_base_url" value="<?php echo htmlspecialchars((string)($form['client_server_base_url'] ?? '')); ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Server issuer</label>
                        <input type="url" class="form-control" name="client_server_issuer" value="<?php echo htmlspecialchars((string)($form['client_server_issuer'] ?? '')); ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Client ID</label>
                        <input type="text" class="form-control" name="client_client_id" value="<?php echo htmlspecialchars((string)($form['client_client_id'] ?? '')); ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Client secret</label>
                        <input type="text" class="form-control" name="client_client_secret" value="<?php echo htmlspecialchars((string)($form['client_client_secret'] ?? '')); ?>">
                    </div>
                    <div class="col-12">
                        <label class="form-label">Scope</label>
                        <input type="text" class="form-control" name="client_scope" value="<?php echo htmlspecialchars((string)($form['client_scope'] ?? '')); ?>">
                        <div class="form-text">Use <code>openid profile email</code> minimum. Add additional claims scope for full integration.</div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Authorize path</label>
                        <input type="text" class="form-control" name="client_authorize_path" value="<?php echo htmlspecialchars((string)($form['client_authorize_path'] ?? '/login/sso-server-authorize')); ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Token path</label>
                        <input type="text" class="form-control" name="client_token_path" value="<?php echo htmlspecialchars((string)($form['client_token_path'] ?? '/login/sso-server-token')); ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Userinfo path</label>
                        <input type="text" class="form-control" name="client_userinfo_path" value="<?php echo htmlspecialchars((string)($form['client_userinfo_path'] ?? '/login/sso-server-userinfo')); ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">JWKS path</label>
                        <input type="text" class="form-control" name="client_jwks_path" value="<?php echo htmlspecialchars((string)($form['client_jwks_path'] ?? '/login/sso-server-jwks')); ?>">
                    </div>
                </div>
            <?php } ?>

            <div class="mt-4 d-flex gap-2 flex-wrap">
                <button type="submit" name="action" value="generate" class="btn btn-primary">Generate sys.inc.php Snippet</button>
                <?php if ($mode === 'server') { ?>
                    <button type="submit" name="action" value="generate_keys" class="btn btn-outline-dark" onclick="return confirm('Generate SSO server key pair at the configured paths?');">Generate Keys Now</button>
                <?php } ?>
                <button type="submit" name="action" value="test" class="btn btn-outline-success">Test Current Runtime Settings</button>
            </div>
        </form>
    </div>
</div>

<?php if ($mode === 'server' && is_array($keyGenerationReport)) {
    $keySummary = $keyGenerationReport['summary'] ?? ['ok' => 0, 'warning' => 0, 'error' => 0];
    $keyItems = $keyGenerationReport['items'] ?? [];
    ?>
    <div class="card shadow-sm mb-3 border-<?php echo (int)($keySummary['error'] ?? 0) > 0 ? 'danger' : 'success'; ?>">
        <div class="card-body">
            <h3 class="h6 mb-2">Key Readiness Check</h3>
            <div class="small mb-2">
                <span class="badge bg-success">OK: <?php echo (int)($keySummary['ok'] ?? 0); ?></span>
                <span class="badge bg-danger ms-2">Errors: <?php echo (int)($keySummary['error'] ?? 0); ?></span>
            </div>
            <ul class="small mb-0">
                <?php foreach ($keyItems as $row) {
                    $status = (string)($row['status'] ?? 'ok');
                    $textClass = $status === 'error' ? 'text-danger' : 'text-success';
                    ?>
                    <li class="<?php echo $textClass; ?>">
                        <strong><?php echo htmlspecialchars((string)($row['label'] ?? '')); ?>:</strong>
                        <?php echo htmlspecialchars((string)($row['message'] ?? '')); ?>
                    </li>
                <?php } ?>
            </ul>
            <div class="small text-muted mt-2">If keys are ready, generate snippet, paste in <code>rad/config/sys.inc.php</code>, then run <strong>Test Current Runtime Settings</strong>.</div>
        </div>
    </div>
<?php } ?>

<?php if ($snippet !== '') { ?>
    <div class="card shadow-sm mb-3">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-center mb-2">
                <h3 class="h6 mb-0">Generated auth snippet</h3>
                <button type="button" class="btn btn-sm btn-outline-primary" id="copy_sso_snippet_btn">Copy Snippet</button>
            </div>
            <div class="alert alert-warning small">Paste this under the top-level array in <code>rad/config/sys.inc.php</code>. Then reload app and run test.</div>
            <pre class="bg-light border rounded p-3 small" style="white-space:pre-wrap;" id="sso_generated_snippet"><?php echo htmlspecialchars($snippet); ?></pre>
            <div class="small text-success mt-2 d-none" id="copy_sso_snippet_feedback">Snippet copied to clipboard.</div>
        </div>
    </div>
<?php } ?>

<?php if (is_array($report)) {
    $items = $report['items'] ?? [];
    $summary = $report['summary'] ?? ['ok' => 0, 'warning' => 0, 'error' => 0];
    ?>
    <div class="card shadow-sm">
        <div class="card-body">
            <h3 class="h6 mb-3">Runtime test results (<code><?php echo htmlspecialchars((string)($report['role'] ?? 'disabled')); ?></code>)</h3>
            <div class="row g-2 mb-3">
                <div class="col-md-4"><span class="badge bg-success">OK: <?php echo (int)($summary['ok'] ?? 0); ?></span></div>
                <div class="col-md-4"><span class="badge bg-warning text-dark">Warnings: <?php echo (int)($summary['warning'] ?? 0); ?></span></div>
                <div class="col-md-4"><span class="badge bg-danger">Errors: <?php echo (int)($summary['error'] ?? 0); ?></span></div>
            </div>
            <div class="table-responsive">
                <table class="table table-sm table-hover align-middle">
                    <thead class="table-light">
                    <tr>
                        <th>Status</th>
                        <th>Check</th>
                        <th>Details</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($items as $row) {
                        $status = (string)($row['status'] ?? 'ok');
                        $badge = 'success';
                        if ($status === 'warning') {
                            $badge = 'warning text-dark';
                        } elseif ($status === 'error') {
                            $badge = 'danger';
                        }
                        ?>
                        <tr>
                            <td><span class="badge bg-<?php echo $badge; ?>"><?php echo strtoupper(htmlspecialchars($status)); ?></span></td>
                            <td><?php echo htmlspecialchars((string)($row['label'] ?? '')); ?></td>
                            <td><?php echo htmlspecialchars((string)($row['message'] ?? '')); ?></td>
                        </tr>
                    <?php } ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
<?php } ?>

<script>
    (function () {
        var btn = document.getElementById('copy_sso_snippet_btn');
        var snippet = document.getElementById('sso_generated_snippet');
        var feedback = document.getElementById('copy_sso_snippet_feedback');
        if (!btn || !snippet) {
            return;
        }
        btn.addEventListener('click', function () {
            var text = snippet.textContent || '';
            if (!text) {
                return;
            }
            navigator.clipboard.writeText(text).then(function () {
                if (!feedback) {
                    return;
                }
                feedback.classList.remove('d-none');
                window.setTimeout(function () {
                    feedback.classList.add('d-none');
                }, 1800);
            }).catch(function () {
                btn.textContent = 'Copy failed';
                window.setTimeout(function () {
                    btn.textContent = 'Copy Snippet';
                }, 1800);
            });
        });
    })();
</script>
