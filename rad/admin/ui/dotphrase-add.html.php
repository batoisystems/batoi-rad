<?php
$spaces = $this->runData['data']['spaces'] ?? [];
$phrase = $this->runData['data']['phrase'] ?? [];
$radAdminUrl = $this->runData['route']['rad_admin_url'];
$isEdit = !empty($phrase);
$actionUrl = $this->runData['route']['url'];
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h1 class="h4 mb-0"><?php echo htmlspecialchars($this->runData['route']['h1']); ?></h1>
        <p class="text-muted mb-0">Create reusable snippets for platform or SaaS scopes.</p>
    </div>
    <a href="<?php echo $radAdminUrl; ?>/dotphrase/view" class="btn btn-outline-secondary btn-sm">
        <i class="bi bi-arrow-left-circle"></i> Back
    </a>
</div>

<div class="card">
    <div class="card-body">
        <form method="post">
            <div class="mb-3">
                <label class="form-label">Phrase <span class="text-danger">*</span></label>
                <input type="text" name="s_phrase" class="form-control" value="<?php echo htmlspecialchars($phrase['s_phrase'] ?? ''); ?>" required>
                <small class="text-muted">Suggestion: prefix with dot (e.g., <code>.addr</code>)</small>
            </div>
            <div class="mb-3">
                <label class="form-label">Content <span class="text-danger">*</span></label>
                <textarea name="s_content" class="form-control" rows="5" required><?php echo htmlspecialchars($phrase['s_content'] ?? ''); ?></textarea>
            </div>
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label">Scope <span class="text-danger">*</span></label>
                    <select name="s_scope" class="form-select" required>
                        <?php foreach (['platform'=>'Platform (non-SaaS)','workspace'=>'Workspace (SaaS)'] as $k=>$label): ?>
                            <option value="<?php echo $k; ?>" <?php echo (($phrase['s_scope'] ?? 'platform') === $k) ? 'selected' : ''; ?>><?php echo $label; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Workspace (for SaaS)</label>
                    <select name="space_id" class="form-select">
                        <option value="0">None</option>
                        <?php foreach ($spaces as $space): ?>
                            <option value="<?php echo $space['id']; ?>" <?php echo ((int)($phrase['space_id'] ?? 0) === (int)$space['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($space['s_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Visibility</label>
                    <select name="s_is_public" class="form-select">
                        <option value="N" <?php echo (($phrase['s_is_public'] ?? 'N') === 'N') ? 'selected' : ''; ?>>Private (owner only)</option>
                        <option value="Y" <?php echo (($phrase['s_is_public'] ?? 'N') === 'Y') ? 'selected' : ''; ?>>Public (within scope)</option>
                    </select>
                </div>
            </div>
            <div class="row g-3 mt-3">
                <div class="col-md-6">
                    <label class="form-label">Owner Entity ID (optional)</label>
                    <input type="number" name="s_owner_id" class="form-control" value="<?php echo htmlspecialchars($phrase['s_owner_id'] ?? ''); ?>" placeholder="Entity id (user)">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Tags (JSON array, optional)</label>
                    <input type="text" name="s_tags" class="form-control" value="<?php echo htmlspecialchars($phrase['s_tags'] ?? ''); ?>" placeholder='["billing","addr"]'>
                </div>
            </div>
            <div class="mt-3">
                <label class="form-label">Description</label>
                <input type="text" name="s_description" class="form-control" value="<?php echo htmlspecialchars($phrase['s_description'] ?? ''); ?>">
            </div>
            <div class="mt-4">
                <button type="submit" class="btn btn-primary"><?php echo $isEdit ? 'Save Changes' : 'Create'; ?></button>
                <a href="<?php echo $radAdminUrl; ?>/dotphrase/view" class="btn btn-outline-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>
