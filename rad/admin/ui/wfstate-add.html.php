<?php
$radAdminUrl = $this->runData['route']['rad_admin_url'] ?? '';
?>
<div class="card shadow-sm border-0">
    <div class="card-body">
        <h5 class="card-title mb-3">Add Workflow State</h5>
        <form method="post" action="<?php echo $radAdminUrl; ?>/wfstate/add">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($this->runData['request']->csrf_token); ?>">
            <div class="mb-3">
                <label class="form-label">Name <span class="text-danger">*</span></label>
                <input type="text" name="s_name" class="form-control" required>
            </div>
            <div class="mb-3">
                <label class="form-label">Flow order</label>
                <input type="number" name="s_flow_order" class="form-control" min="0" step="1">
                <div class="form-text">Optional ordering for visualisation.</div>
            </div>
            <div class="mb-3">
                <label class="form-label">Definition (JSON)</label>
                <textarea name="s_definition" class="form-control" rows="4" placeholder='{ "initial": true }'></textarea>
                <div class="form-text">Store any workflow metadata (e.g., initial/terminal flags) as JSON.</div>
            </div>
            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-primary"><i class="bi bi-check-circle me-1"></i>Save</button>
                <a href="<?php echo $radAdminUrl; ?>/wfstate/view" class="btn btn-outline-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>
