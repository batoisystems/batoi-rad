<?php
$radAdminUrl = $this->runData['route']['rad_admin_url'] ?? '';
$client = $this->runData['data']['client'] ?? [];
$generated = (string)($this->runData['data']['generated_client_secret'] ?? '');
$clientId = (string)($client['client_id'] ?? '');
$redirectUrisText = implode("\n", $client['redirect_uris'] ?? []);
?>
<div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3 mb-3">
    <div>
        <h2 class="h4 mb-0">Edit SSO Server Client</h2>
        <div class="text-muted small">Update trusted redirect URLs, access level, and status for this client registration.</div>
    </div>
    <a href="<?php echo $radAdminUrl; ?>/ssoclient/view" class="btn btn-outline-secondary">Back to Clients</a>
</div>

<?php if ($generated !== '') { ?>
    <div class="alert alert-warning">
        <div class="fw-semibold mb-2">Copy this client secret now</div>
        <code class="d-block p-2 bg-light border rounded"><?php echo htmlspecialchars($generated); ?></code>
        <div class="small mt-2">This value is shown only once. Update the consuming application immediately.</div>
    </div>
<?php } ?>

<div class="row g-3 mb-3">
    <div class="col-lg-8">
        <div class="card shadow-sm">
            <div class="card-body">
                <form method="post" action="<?php echo $radAdminUrl; ?>/ssoclient/edit/<?php echo rawurlencode($clientId); ?>">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($this->runData['request']->csrf_token); ?>">
                    <input type="hidden" name="action" value="save">

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Client Name</label>
                            <input type="text" class="form-control" name="client_name" value="<?php echo htmlspecialchars((string)($client['client_name'] ?? '')); ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Client ID</label>
                            <input type="text" class="form-control" value="<?php echo htmlspecialchars($clientId); ?>" disabled>
                            <div class="form-text">Client ID is fixed after creation to avoid broken integrations.</div>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Access Level</label>
                            <select class="form-select" name="allowed_level">
                                <option value="verify_only" <?php echo (($client['allowed_level'] ?? 'verify_only') === 'verify_only') ? 'selected' : ''; ?>>Verify Only (credential validation)</option>
                                <option value="full_integration" <?php echo (($client['allowed_level'] ?? '') === 'full_integration') ? 'selected' : ''; ?>>Full Integration (all user claims)</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Status</label>
                            <select class="form-select" name="status">
                                <option value="active" <?php echo (($client['status'] ?? 'active') === 'active') ? 'selected' : ''; ?>>Active</option>
                                <option value="inactive" <?php echo (($client['status'] ?? '') === 'inactive') ? 'selected' : ''; ?>>Inactive</option>
                            </select>
                        </div>

                        <div class="col-12">
                            <label class="form-label">Redirect URI(s)</label>
                            <textarea class="form-control" name="redirect_uris_text" rows="5" required><?php echo htmlspecialchars($redirectUrisText); ?></textarea>
                            <div class="form-text">One exact URL per line. Any mismatch is blocked during authorize request.</div>
                        </div>

                        <div class="col-12">
                            <label class="form-label">Set New Secret (optional)</label>
                            <input type="text" class="form-control" name="client_secret" value="">
                            <div class="form-text">Leave blank to keep current secret. If set, this replaces the old secret immediately.</div>
                        </div>

                        <div class="col-12">
                            <label class="form-label">Notes</label>
                            <input type="text" class="form-control" name="notes" value="<?php echo htmlspecialchars((string)($client['notes'] ?? '')); ?>">
                        </div>
                    </div>

                    <div class="mt-4 d-flex gap-2">
                        <button type="submit" class="btn btn-primary">Save Changes</button>
                        <a href="<?php echo $radAdminUrl; ?>/ssoclient/view" class="btn btn-outline-secondary">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card shadow-sm">
            <div class="card-body">
                <h3 class="h6">Security Actions</h3>
                <p class="small text-muted">Rotate secret if a client credential is exposed or when applying periodic credential hygiene.</p>
                <form method="post" action="<?php echo $radAdminUrl; ?>/ssoclient/edit/<?php echo rawurlencode($clientId); ?>">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($this->runData['request']->csrf_token); ?>">
                    <input type="hidden" name="action" value="rotate_secret">
                    <button type="submit" class="btn btn-outline-warning w-100" onclick="return confirm('Rotate client secret now? Existing integrations must be updated immediately.');">Rotate Secret</button>
                </form>
            </div>
        </div>
    </div>
</div>
