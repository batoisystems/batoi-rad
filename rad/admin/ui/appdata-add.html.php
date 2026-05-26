<?php
$formSubmissionUrl = $this->runData['route']['url'];
$microservices = $this->runData['data']['microservices'] ?? [];
?>
<div class="card shadow-sm border-0">
    <div class="card-body">
        <h5 class="card-title mb-3"><?php echo (strtolower($this->runData['request']->get['scope'] ?? '') === 'global') ? 'Create Global Data Model' : 'Create Scoped Data Model'; ?></h5>
        <p class="text-muted mb-3">
            <?php if (strtolower($this->runData['request']->get['scope'] ?? '') === 'global'): ?>
                Global data models use <code>s_ms_id = 0</code> and are not tied to a microservicelet. A backing <code>a_*</code> table will be created automatically.
            <?php else: ?>
                Scoped data models belong to a specific microservicelet. A backing <code>a_*</code> table will be created automatically.
            <?php endif; ?>
        </p>
        <form action="<?php print $formSubmissionUrl;?>" method="post" class="row g-3 needs-validation" novalidate>
            <div class="col-md-6">
                <label for="s_name" class="form-label">Data Model Name <span class="text-danger">*</span></label>
                <input type="text" class="form-control" name="s_name" id="s_name" required autocomplete="off" placeholder="e.g., orders">
                <div class="invalid-feedback">Provide a data model name (alphanumeric/underscore).</div>
            </div>
            <?php $isGlobal = strtolower($this->runData['request']->get['scope'] ?? '') === 'global'; ?>
            <?php if (!$isGlobal): ?>
            <div class="col-md-6">
                <label class="form-label">Microservicelet <span class="text-danger">*</span></label>
                <select class="form-select" name="s_ms_id" required>
                    <option value="">Select microservicelet</option>
                    <?php foreach ($microservices as $ms): ?>
                        <option value="<?php echo (int)$ms['id']; ?>">
                            <?php echo htmlspecialchars($ms['s_name'] ?? ''); ?> · ID: <?php echo (int)$ms['id']; ?> · UID: <?php echo htmlspecialchars($ms['uid'] ?? ''); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <div class="form-text">Data model will be scoped to this microservicelet.</div>
            </div>
            <?php endif; ?>
            <div class="col-12">
                <label for="s_description" class="form-label">Description</label>
                <input type="text" class="form-control" name="s_description" id="s_description" placeholder="Short description (optional)">
            </div>
            <div class="col-12 d-flex gap-2">
                <button type="submit" class="btn btn-primary"><i class="bi bi-diagram-3 me-1"></i>Create Data Model</button>
                <a href="<?php echo htmlspecialchars($this->runData['route']['rad_admin_url'] . '/appdata/view'); ?>" class="btn btn-outline-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>
