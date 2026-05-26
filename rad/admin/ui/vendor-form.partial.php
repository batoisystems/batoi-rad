<?php
$radAdminUrl = $this->runData['route']['rad_admin_url'];
$vendorData = $vendorData ?? [];
$actionUrl = $actionUrl ?? ($radAdminUrl . '/vendor/add');
$submitLabel = $submitLabel ?? 'Save';
$showHandleInput = $showHandleInput ?? true;
?>
<form action="<?php echo $actionUrl; ?>" method="post">
    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($this->runData['request']->csrf_token); ?>">
    <div class="row g-3">
        <div class="col-md-6">
            <label class="form-label">Library Name <span class="text-danger">*</span></label>
            <input type="text" name="s_title" class="form-control" value="<?php echo htmlspecialchars($vendorData['s_title'] ?? ''); ?>" required>
        </div>
        <div class="col-md-6">
            <label class="form-label">Handle (folder name)</label>
            <input type="text" name="s_handle" class="form-control" value="<?php echo htmlspecialchars($vendorData['s_handle'] ?? ''); ?>" <?php echo $showHandleInput ? '' : 'readonly'; ?>>
            <small class="text-muted">Matches the directory under <code>rad/vendor/handle</code>.</small>
        </div>
        <div class="col-md-6">
            <label class="form-label">Category</label>
            <input type="text" name="s_category" class="form-control" value="<?php echo htmlspecialchars($vendorData['s_category'] ?? ''); ?>" placeholder="Messaging, Payments, Security...">
        </div>
        <div class="col-md-6">
            <label class="form-label">Documentation URL</label>
            <input type="url" name="s_doc_url" class="form-control" value="<?php echo htmlspecialchars($vendorData['s_doc_url'] ?? ''); ?>">
        </div>
        <div class="col-md-6">
            <label class="form-label">Source/Repository URL</label>
            <input type="url" name="s_source_url" class="form-control" value="<?php echo htmlspecialchars($vendorData['s_source_url'] ?? ''); ?>">
        </div>
        <div class="col-md-6">
            <label class="form-label">Install Path</label>
            <input type="text" name="s_install_path" class="form-control" value="<?php echo htmlspecialchars($vendorData['s_install_path'] ?? ''); ?>" placeholder="<?php echo htmlspecialchars($this->runData['config']['dir']['vendor'] . '/your-library'); ?>">
        </div>
        <div class="col-md-6">
            <label class="form-label">Latest Version (available)</label>
            <input type="text" name="s_version_available" class="form-control" value="<?php echo htmlspecialchars($vendorData['s_version_available'] ?? ''); ?>">
        </div>
        <div class="col-md-6">
            <label class="form-label">Installed Version</label>
            <input type="text" name="s_version_installed" class="form-control" value="<?php echo htmlspecialchars($vendorData['s_version_installed'] ?? ''); ?>">
        </div>
        <div class="col-12">
            <label class="form-label">Summary</label>
            <textarea name="s_summary" class="form-control" rows="2"><?php echo htmlspecialchars($vendorData['s_summary'] ?? ''); ?></textarea>
        </div>
        <div class="col-12">
            <label class="form-label">Usage Notes</label>
            <textarea name="s_usage_notes" class="form-control" rows="6" placeholder="Use HTML; wrap code in &lt;pre&gt;&lt;code&gt;...&lt;/code&gt;&lt;/pre&gt;."><?php echo $vendorData['s_usage_notes'] ?? ''; ?></textarea>
        </div>
        <div class="col-12 text-end">
            <button type="submit" class="btn btn-primary"><?php echo htmlspecialchars($submitLabel); ?></button>
        </div>
    </div>
</form>
