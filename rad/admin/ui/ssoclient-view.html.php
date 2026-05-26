<?php
$radAdminUrl = $this->runData['route']['rad_admin_url'] ?? '';
$clients = $this->runData['data']['sso_server_clients'] ?? [];
$summary = $this->runData['data']['sso_server_client_summary'] ?? [];
?>
<div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3 mb-3">
    <div>
        <h2 class="h4 mb-0">SSO Server Clients</h2>
        <div class="text-muted small">Register client applications that can authenticate against this installation when <code>auth.sso_role</code> is set to <code>server</code>.</div>
    </div>
    <div class="btn-group">
        <a href="<?php echo $radAdminUrl; ?>/ssoclient/add" class="btn btn-primary"><i class="bi bi-plus-circle me-1"></i>Add Client</a>
        <a href="<?php echo $radAdminUrl; ?>/sso/view" class="btn btn-outline-secondary">Back to SSO Providers</a>
    </div>
</div>

<div class="row g-3 mb-3">
    <div class="col-6 col-lg-3">
        <div class="card shadow-sm h-100"><div class="card-body"><div class="text-muted small">Total Clients</div><div class="h3 mb-0"><?php echo (int)($summary['total'] ?? 0); ?></div></div></div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="card shadow-sm h-100"><div class="card-body"><div class="text-muted small">Active</div><div class="h3 mb-0 text-success"><?php echo (int)($summary['active'] ?? 0); ?></div></div></div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="card shadow-sm h-100"><div class="card-body"><div class="text-muted small">Verify Only</div><div class="h3 mb-0 text-secondary"><?php echo (int)($summary['verify_only'] ?? 0); ?></div></div></div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="card shadow-sm h-100"><div class="card-body"><div class="text-muted small">Full Integration</div><div class="h3 mb-0 text-primary"><?php echo (int)($summary['full_integration'] ?? 0); ?></div></div></div>
    </div>
</div>

<div class="alert alert-info small">
    <strong>How to use this:</strong> Create one client per consuming application. Share its <code>client_id</code> and <code>client_secret</code> with that application. Restrict every client to exact redirect URLs only.
</div>

<div class="card shadow-sm">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0 align-middle">
                <thead class="table-light">
                <tr>
                    <th>Client</th>
                    <th>Access Level</th>
                    <th>Redirect URIs</th>
                    <th>Status</th>
                    <th class="text-end">Actions</th>
                </tr>
                </thead>
                <tbody>
                <?php if (empty($clients)) { ?>
                    <tr><td colspan="5" class="text-center text-muted py-4">No SSO clients registered yet.</td></tr>
                <?php } ?>
                <?php foreach ($clients as $client) {
                    $clientId = (string)($client['client_id'] ?? '');
                    $redirectUris = $client['redirect_uris'] ?? [];
                    ?>
                    <tr>
                        <td>
                            <div class="fw-semibold"><?php echo htmlspecialchars((string)($client['client_name'] ?? $clientId)); ?></div>
                            <div class="small text-muted"><code><?php echo htmlspecialchars($clientId); ?></code></div>
                        </td>
                        <td>
                            <?php if (($client['allowed_level'] ?? 'verify_only') === 'full_integration') { ?>
                                <span class="badge bg-primary">Full Integration</span>
                                <div class="small text-muted mt-1">User profile and claims are available to client.</div>
                            <?php } else { ?>
                                <span class="badge bg-secondary">Verify Only</span>
                                <div class="small text-muted mt-1">Credential validation with limited identity data.</div>
                            <?php } ?>
                        </td>
                        <td class="small">
                            <?php if (empty($redirectUris)) { ?>
                                <span class="text-danger">No redirect URI configured</span>
                            <?php } else { ?>
                                <?php foreach ($redirectUris as $uri) { ?>
                                    <div class="text-break"><?php echo htmlspecialchars((string)$uri); ?></div>
                                <?php } ?>
                            <?php } ?>
                        </td>
                        <td>
                            <span class="badge bg-<?php echo ($client['status'] ?? 'active') === 'active' ? 'success' : 'secondary'; ?>">
                                <?php echo ($client['status'] ?? 'active') === 'active' ? 'Active' : 'Inactive'; ?>
                            </span>
                        </td>
                        <td class="text-end">
                            <a class="btn btn-sm btn-outline-primary" href="<?php echo $radAdminUrl; ?>/ssoclient/edit/<?php echo rawurlencode($clientId); ?>">Edit</a>
                            <form method="post" action="<?php echo $radAdminUrl; ?>/ssoclient/view" class="d-inline">
                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($this->runData['request']->csrf_token); ?>">
                                <input type="hidden" name="action" value="toggle">
                                <input type="hidden" name="client_id" value="<?php echo htmlspecialchars($clientId); ?>">
                                <button class="btn btn-sm btn-outline-secondary ms-1" type="submit">
                                    <?php echo ($client['status'] ?? 'active') === 'active' ? 'Disable' : 'Enable'; ?>
                                </button>
                            </form>
                            <form method="post" action="<?php echo $radAdminUrl; ?>/ssoclient/view" class="d-inline" onsubmit="return confirm('Remove this client registration?');">
                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($this->runData['request']->csrf_token); ?>">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="client_id" value="<?php echo htmlspecialchars($clientId); ?>">
                                <button class="btn btn-sm btn-outline-danger ms-1" type="submit">Delete</button>
                            </form>
                        </td>
                    </tr>
                <?php } ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
