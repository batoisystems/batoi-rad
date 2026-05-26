<?php
$backUrl = $this->runData['route']['rad_admin_url'] . '/uitpl/view';
?>
<div class="container-fluid py-3">
    <div class="card">
        <div class="card-body">
            <form method="post">
                <div class="mb-3">
                    <label class="form-label">Template Path (relative to <code>rad/data/uitpl</code>)</label>
                    <input type="text" class="form-control" name="template_path" placeholder="auth/forgot-password.php" required>
                    <div class="form-text">Allowed: letters, numbers, dashes, underscores, slashes, and .php extension.</div>
                </div>
                <div class="mb-3">
                    <label class="form-label">Template Content</label>
                    <textarea class="form-control font-monospace" name="template_content" rows="18" placeholder="&lt;section&gt;...&lt;/section&gt;"></textarea>
                </div>
                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-primary">Create Template</button>
                    <a href="<?php echo $backUrl; ?>" class="btn btn-outline-secondary">Cancel</a>
                </div>
            </form>
        </div>
    </div>
</div>
