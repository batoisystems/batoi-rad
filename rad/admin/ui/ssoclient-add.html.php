<?php
$radAdminUrl = $this->runData['route']['rad_admin_url'] ?? '';
$form = $this->runData['data']['form'] ?? [];
$generated = (string)($this->runData['data']['generated_client_secret'] ?? '');
?>
<div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3 mb-3">
    <div>
        <h2 class="h4 mb-0">Add SSO Server Client</h2>
        <div class="text-muted small">This creates a trusted client application that can use this installation as an SSO server.</div>
    </div>
    <a href="<?php echo $radAdminUrl; ?>/ssoclient/view" class="btn btn-outline-secondary">Back to Clients</a>
</div>

<?php if ($generated !== '') { ?>
    <div class="alert alert-warning">
        <div class="fw-semibold mb-2">Copy this client secret now</div>
        <code class="d-block p-2 bg-light border rounded"><?php echo htmlspecialchars($generated); ?></code>
        <div class="small mt-2">For security reasons this value is not shown again after you leave this page.</div>
    </div>
<?php } ?>

<div class="card shadow-sm mb-3">
    <div class="card-body">
        <h3 class="h6">Simple setup steps</h3>
        <ol class="small mb-0">
            <li>Create a unique <strong>Client ID</strong> (example: <code>batoi-subdomain-app</code>).</li>
            <li>Paste the client app callback URL(s), one per line.</li>
            <li>Choose access level:
                <span class="text-muted">Verify Only for login verification, Full Integration if the client should receive all user profile claims.</span>
            </li>
            <li>Save and securely share the generated secret with that client application.</li>
        </ol>
    </div>
</div>

<div class="card shadow-sm">
    <div class="card-body">
        <form method="post" action="<?php echo $radAdminUrl; ?>/ssoclient/add">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($this->runData['request']->csrf_token); ?>">

            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">Client Name</label>
                    <input type="text" class="form-control" name="client_name" value="<?php echo htmlspecialchars((string)($form['client_name'] ?? '')); ?>" required>
                    <div class="form-text">Human-friendly label for admins, for example: <em>Batoi Subdomain Login</em>.</div>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Client ID</label>
                    <input type="text" class="form-control" name="client_id" value="<?php echo htmlspecialchars((string)($form['client_id'] ?? '')); ?>" required>
                    <div class="form-text">Stable technical identifier. Allowed characters: letters, numbers, dot, dash, underscore.</div>
                </div>

                <div class="col-md-6">
                    <label class="form-label">Access Level</label>
                    <select class="form-select" name="allowed_level">
                        <option value="verify_only" <?php echo (($form['allowed_level'] ?? 'verify_only') === 'verify_only') ? 'selected' : ''; ?>>Verify Only (credential validation)</option>
                        <option value="full_integration" <?php echo (($form['allowed_level'] ?? '') === 'full_integration') ? 'selected' : ''; ?>>Full Integration (all user claims)</option>
                    </select>
                    <div class="form-text">Use <strong>Verify Only</strong> for external domains. Use <strong>Full Integration</strong> for trusted internal apps.</div>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Initial Status</label>
                    <select class="form-select" name="status">
                        <option value="active" <?php echo (($form['status'] ?? 'active') === 'active') ? 'selected' : ''; ?>>Active</option>
                        <option value="inactive" <?php echo (($form['status'] ?? '') === 'inactive') ? 'selected' : ''; ?>>Inactive</option>
                    </select>
                    <div class="form-text">Inactive clients cannot authenticate until enabled.</div>
                </div>

                <div class="col-12">
                    <label class="form-label">Redirect URI(s)</label>
                    <textarea class="form-control" name="redirect_uris_text" rows="4" required><?php echo htmlspecialchars((string)($form['redirect_uris_text'] ?? '')); ?></textarea>
                    <div class="form-text">Enter one exact HTTPS callback URL per line. The server rejects redirects not listed here.</div>
                </div>

                <div class="col-md-6">
                    <label class="form-label">Client Secret (optional)</label>
                    <input type="text" class="form-control" name="client_secret" value="">
                    <div class="form-text">Leave blank to auto-generate a strong secret.</div>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Notes</label>
                    <input type="text" class="form-control" name="notes" value="<?php echo htmlspecialchars((string)($form['notes'] ?? '')); ?>">
                    <div class="form-text">Optional context such as owner/team or environment.</div>
                </div>
            </div>

            <div class="mt-4 d-flex gap-2">
                <button type="submit" class="btn btn-primary">Register Client</button>
                <a href="<?php echo $radAdminUrl; ?>/ssoclient/view" class="btn btn-outline-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>
