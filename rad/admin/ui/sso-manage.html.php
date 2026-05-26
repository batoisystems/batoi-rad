<?php
$provider = $this->runData['data']['provider'] ?? [];
$radAdminUrl = $this->runData['route']['rad_admin_url'] ?? '';
$diag = $provider['_diagnostics'] ?? ['missing' => [], 'warnings' => [], 'is_ready' => false];
$urls = $provider['_urls'] ?? ['init_url' => '', 'callback_url' => ''];
$latestTest = $provider['_latest_test'] ?? null;
$status = (string)($provider['s_status'] ?? 'active');
?>

<div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3 mb-3">
    <div>
        <h2 class="h4 mb-0"><?php echo htmlspecialchars((string)($provider['s_provider_name'] ?? 'SSO Provider')); ?></h2>
        <div class="text-muted small">
            <?php echo htmlspecialchars((string)($provider['type_label'] ?? 'Custom')); ?>
            ·
            <span class="badge bg-<?php echo $status === 'inactive' ? 'secondary' : 'success'; ?>"><?php echo htmlspecialchars((string)($provider['status_label'] ?? '')); ?></span>
        </div>
    </div>
    <div class="btn-group">
        <a class="btn btn-outline-primary" href="<?php echo $radAdminUrl; ?>/sso/edit/<?php echo (int)($provider['id'] ?? 0); ?>">Edit Provider</a>
        <a class="btn btn-outline-success" href="<?php echo htmlspecialchars((string)($urls['init_url'] ?? '')); ?>" target="_blank" rel="noopener">Test Login</a>
        <a class="btn btn-outline-info" href="<?php echo $radAdminUrl; ?>/sso/test/<?php echo (int)($provider['id'] ?? 0); ?>">Post-Save Test</a>
        <a class="btn btn-outline-secondary" href="<?php echo $radAdminUrl; ?>/sso/view">Back</a>
    </div>
</div>

<div class="row g-3 mb-3">
    <div class="col-lg-8">
        <div class="card shadow-sm h-100">
            <div class="card-body">
                <h3 class="h6 mb-3">Provider Endpoints</h3>
                <dl class="row small mb-0">
                    <dt class="col-sm-4">Callback URL</dt>
                    <dd class="col-sm-8 text-break"><code><?php echo htmlspecialchars((string)($urls['callback_url'] ?? '')); ?></code></dd>

                    <dt class="col-sm-4">SSO Init URL</dt>
                    <dd class="col-sm-8 text-break"><code><?php echo htmlspecialchars((string)($urls['init_url'] ?? '')); ?></code></dd>

                    <dt class="col-sm-4">Auth URL</dt>
                    <dd class="col-sm-8 text-break"><?php echo htmlspecialchars((string)($provider['s_auth_url'] ?? '')); ?></dd>

                    <dt class="col-sm-4">Token URL</dt>
                    <dd class="col-sm-8 text-break"><?php echo htmlspecialchars((string)($provider['s_token_url'] ?? '')); ?></dd>

                    <dt class="col-sm-4">Userinfo URL</dt>
                    <dd class="col-sm-8 text-break"><?php echo htmlspecialchars((string)($provider['s_userinfo_url'] ?? '')); ?></dd>

                    <dt class="col-sm-4">Scopes</dt>
                    <dd class="col-sm-8 text-break"><?php echo htmlspecialchars((string)($provider['s_scopes'] ?? '')); ?></dd>
                </dl>
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="card shadow-sm h-100">
            <div class="card-body">
                <h3 class="h6 mb-3">Readiness</h3>
                <?php if (is_array($latestTest)) { ?>
                    <div class="alert alert-<?php echo !empty($latestTest['passed']) ? 'success' : 'danger'; ?> small">
                        Last test: <?php echo !empty($latestTest['passed']) ? 'Passed' : 'Failed'; ?>
                        <?php if (!empty($latestTest['at'])) { ?> · <?php echo htmlspecialchars((string)$latestTest['at']); ?><?php } ?>
                        <?php if (!empty($latestTest['reason'])) { ?><br><?php echo htmlspecialchars((string)$latestTest['reason']); ?><?php } ?>
                    </div>
                <?php } ?>
                <?php if (!empty($diag['is_ready']) && empty($diag['warnings'])) { ?>
                    <div class="alert alert-success small mb-3">Provider is ready for SSO login.</div>
                <?php } else { ?>
                    <div class="alert alert-warning small mb-3">Provider has configuration issues to resolve.</div>
                <?php } ?>

                <?php if (!empty($diag['missing'])) { ?>
                    <div class="mb-3">
                        <div class="fw-semibold small text-danger">Missing Fields</div>
                        <ul class="small mb-0">
                            <?php foreach ($diag['missing'] as $missing) { ?>
                                <li><?php echo htmlspecialchars((string)$missing); ?></li>
                            <?php } ?>
                        </ul>
                    </div>
                <?php } ?>

                <?php if (!empty($diag['warnings'])) { ?>
                    <div class="mb-3">
                        <div class="fw-semibold small">Warnings</div>
                        <ul class="small mb-0">
                            <?php foreach ($diag['warnings'] as $warning) { ?>
                                <li><?php echo htmlspecialchars((string)$warning); ?></li>
                            <?php } ?>
                        </ul>
                    </div>
                <?php } ?>

                <form method="post" action="<?php echo $radAdminUrl; ?>/sso/manage/<?php echo (int)($provider['id'] ?? 0); ?>">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($this->runData['request']->csrf_token); ?>">
                    <?php if ($status === 'inactive') { ?>
                        <button class="btn btn-success btn-sm" type="submit" name="action" value="activate">Set Active</button>
                    <?php } else { ?>
                        <button class="btn btn-outline-secondary btn-sm" type="submit" name="action" value="deactivate">Set Inactive</button>
                    <?php } ?>
                </form>
            </div>
        </div>
    </div>
</div>

<div class="card shadow-sm">
    <div class="card-body">
        <h3 class="h6 mb-2">Notes</h3>
        <div class="small text-muted"><?php echo nl2br(htmlspecialchars((string)($provider['s_notes'] ?? 'No notes added.'))); ?></div>
    </div>
</div>
