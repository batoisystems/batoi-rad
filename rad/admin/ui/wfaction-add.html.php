<?php
$radAdminUrl = $this->runData['route']['rad_admin_url'] ?? '';
$states = $this->runData['data']['wf_states'] ?? [];
?>
<div class="card shadow-sm border-0">
    <div class="card-body">
        <h5 class="card-title mb-3">Add Workflow Transition</h5>
        <form method="post" action="<?php echo $radAdminUrl; ?>/wfaction/add">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($this->runData['request']->csrf_token); ?>">
            <div class="mb-3">
                <label class="form-label">Name <span class="text-danger">*</span></label>
                <input type="text" name="s_name" class="form-control" required>
            </div>
            <div class="row g-3 mb-3">
                <div class="col-md-6">
                    <label class="form-label">From State</label>
                    <select name="s_wf_state_id" class="form-select" required>
                        <option value="">Select</option>
                        <?php foreach ($states as $state) { ?>
                            <option value="<?php echo (int)$state['id']; ?>"><?php echo htmlspecialchars($state['s_name']); ?></option>
                        <?php } ?>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label">To State</label>
                    <select name="s_next_wf_state_id" class="form-select" required>
                        <option value="">Select</option>
                        <?php foreach ($states as $state) { ?>
                            <option value="<?php echo (int)$state['id']; ?>"><?php echo htmlspecialchars($state['s_name']); ?></option>
                        <?php } ?>
                    </select>
                </div>
            </div>
            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-primary"><i class="bi bi-check-circle me-1"></i>Save</button>
                <a href="<?php echo $radAdminUrl; ?>/telemetry/view" class="btn btn-outline-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>
