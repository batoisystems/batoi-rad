<?php
$formSubmissionUrl = $this->runData['route']['url'];
$space = $this->runData['data']['space'] ?? [];
$radAdminUrl = $this->runData['route']['rad_admin_url'];
$status = isset($space['livestatus']) ? (string)$space['livestatus'] : '1';
$definition = isset($space['s_definition']) ? (string)$space['s_definition'] : '{}';
?>

<div class="card border-0 shadow-sm mb-4 bg-body-tertiary">
    <div class="card-body p-4">
        <div class="row g-3 align-items-start">
            <div class="col-lg-8">
                <div class="text-uppercase text-muted small fw-semibold mb-2">Workspace Maintenance</div>
                <h2 class="h3 mb-2">Edit workspace metadata with explicit control</h2>
                <p class="text-muted mb-0">Update identifiers, lifecycle state, ownership, and the JSON definition from one place without losing the workspace context.</p>
            </div>
            <div class="col-lg-4">
                <div class="card border-0 shadow-sm-sm h-100">
                    <div class="card-body">
                        <div class="small text-muted">Workspace</div>
                        <div class="fw-semibold"><?php echo htmlspecialchars((string)($space['s_name'] ?? 'Workspace')); ?></div>
                        <div class="small text-muted mt-2">UID: <?php echo htmlspecialchars((string)($space['uid'] ?? '')); ?></div>
                        <div class="small text-muted">Slug: <?php echo htmlspecialchars((string)($space['s_slug'] ?? '—')); ?></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<form action="<?php echo htmlspecialchars($formSubmissionUrl); ?>" method="post" class="row g-4" novalidate>
    <input type="hidden" name="space_id" value="<?php echo (int)($space['id'] ?? 0); ?>">

    <div class="col-lg-8">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <h3 class="h5 mb-3">Workspace Profile</h3>
                <div class="row g-3">
                    <div class="col-12">
                        <label for="s_name" class="form-label">Workspace Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="s_name" id="s_name" value="<?php echo htmlspecialchars((string)($space['s_name'] ?? '')); ?>" required autocomplete="off">
                        <div class="form-text">Use a stable, recognizable label for admins and operators.</div>
                    </div>
                    <div class="col-md-6">
                        <label for="s_slug" class="form-label">Slug</label>
                        <input type="text" class="form-control" name="s_slug" id="s_slug" value="<?php echo htmlspecialchars((string)($space['s_slug'] ?? '')); ?>" maxlength="50" autocomplete="off">
                        <div class="form-text">Lowercase and URL-friendly. Leave blank only if you want it regenerated from the name.</div>
                    </div>
                    <div class="col-md-6">
                        <label for="livestatus" class="form-label">Status</label>
                        <select class="form-select" name="livestatus" id="livestatus">
                            <?php foreach ([
                                '1' => 'Active',
                                '2' => 'Archived',
                                '3' => 'Suspended',
                                '0' => 'Inactive (hidden)',
                            ] as $key => $label) { ?>
                                <option value="<?php echo $key; ?>" <?php echo $status === $key ? 'selected' : ''; ?>><?php echo htmlspecialchars($label); ?></option>
                            <?php } ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label for="s_owner_entity_id" class="form-label">Owner Entity ID</label>
                        <input type="number" class="form-control" name="s_owner_entity_id" id="s_owner_entity_id" value="<?php echo isset($space['s_owner_entity_id']) ? (int)$space['s_owner_entity_id'] : ''; ?>" min="1">
                        <div class="form-text">Set the primary accountable user for this workspace.</div>
                    </div>
                    <div class="col-12">
                        <label for="s_description" class="form-label">Description</label>
                        <textarea class="form-control" name="s_description" id="s_description" rows="4"><?php echo htmlspecialchars((string)($space['s_description'] ?? '')); ?></textarea>
                        <div class="form-text">Helpful context for team ownership, usage, or environment purpose.</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <h3 class="h5 mb-3">Definition</h3>
                <label for="s_definition" class="form-label">Workspace JSON <span class="text-danger">*</span></label>
                <textarea class="form-control font-monospace" name="s_definition" id="s_definition" rows="14" spellcheck="false" required><?php echo htmlspecialchars($definition); ?></textarea>
                <div class="form-text mt-2">Keep this valid JSON. IP restriction and other workspace-scoped settings are merged from here.</div>
            </div>
        </div>
    </div>

    <div class="col-12">
        <div class="d-flex flex-wrap gap-2">
            <button type="submit" class="btn btn-primary">
                <i class="bi bi-check-circle me-1"></i>Save Changes
            </button>
            <a href="<?php echo htmlspecialchars($radAdminUrl . '/space/viewone/' . urlencode((string)($space['uid'] ?? ''))); ?>" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left-circle me-1"></i>Back to Workspace
            </a>
        </div>
    </div>
</form>

<script>
document.addEventListener('DOMContentLoaded', function() {
    var definitionInput = document.getElementById('s_definition');
    if (definitionInput && !definitionInput.value) {
        definitionInput.value = '{}';
    }
    var nameInput = document.getElementById('s_name');
    var slugInput = document.getElementById('s_slug');
    if (nameInput && slugInput) {
        nameInput.addEventListener('blur', function() {
            if (slugInput.value.trim() !== '') {
                return;
            }
            slugInput.value = (this.value || '')
                .toLowerCase()
                .replace(/[^a-z0-9]+/g, '-')
                .replace(/^-+|-+$/g, '')
                .substring(0, 50);
        });
    }
});
</script>
