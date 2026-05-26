<?php
$controller = $this->runData['data']['controller'];
$microservice = $this->runData['data']['ms'];
$fields = $this->runData['data']['fields'] ?? [];
$fieldTypes = $this->runData['data']['field_types'] ?? [];
$controllerId = (int)($this->runData['data']['schema_controller_id'] ?? ($controller['id'] ?? 0));
$tableName = 'a_' . ($controller['s_name'] ?? '');
$branch = $this->runData['data']['schema_branch'] ?? 'live';
$branchStatus = $this->runData['data']['schema_branch_status'] ?? [];
$branchHasBeta = !empty($this->runData['data']['schema_branch_has_beta']);
$branchMissing = !empty($this->runData['data']['schema_branch_missing']);
$branchCanManage = !empty($this->runData['data']['schema_branch_can_manage']);
$branchCanMerge = !empty($this->runData['data']['schema_branch_can_merge']);
$branchHistory = $this->runData['data']['schema_branch_history'] ?? [];
$branchQuery = $branch === 'beta' ? '?branch=beta' : '';
$schemaCanEdit = !($branch === 'beta' && $branchMissing);

$addEndpoint = $this->runData['route']['rad_admin_url'] . '/controller/schemaaddfield' . $branchQuery;
$updateEndpoint = $this->runData['route']['rad_admin_url'] . '/controller/schemaupdatefield' . $branchQuery;
$deleteEndpoint = $this->runData['route']['rad_admin_url'] . '/controller/schemadeletefield' . $branchQuery;
$branchCreateUrl = $this->runData['route']['rad_admin_url'] . '/controller/schemabranchcreate/' . $controller['uid'] . '/' . $microservice['uid'];
$branchMergeUrl = $this->runData['route']['rad_admin_url'] . '/controller/schemabranchmerge/' . $controller['uid'] . '/' . $microservice['uid'];
$branchDiscardUrl = $this->runData['route']['rad_admin_url'] . '/controller/schemabranchdiscard/' . $controller['uid'] . '/' . $microservice['uid'];
?>

<div id="controller-schema-app"
     data-controller-id="<?php echo $controllerId; ?>"
     data-controller-uid="<?php echo htmlspecialchars($controller['uid']); ?>"
     data-branch="<?php echo htmlspecialchars($branch); ?>"
     data-branch-missing="<?php echo $branchMissing ? '1' : '0'; ?>"
     data-add-endpoint="<?php echo htmlspecialchars($addEndpoint); ?>"
     data-update-endpoint="<?php echo htmlspecialchars($updateEndpoint); ?>"
     data-delete-endpoint="<?php echo htmlspecialchars($deleteEndpoint); ?>">

    <?php if ($branchMissing && $branchCanManage) { ?>
        <div class="alert alert-warning d-flex align-items-center justify-content-between flex-wrap gap-2">
            <div>
                <strong>Beta schema not initialized.</strong>
                <div class="small text-muted">Create a beta schema to edit without changing live tables.</div>
            </div>
            <a href="<?php echo $branchCreateUrl; ?>" class="btn btn-outline-primary btn-sm">
                <i class="bi bi-branch"></i> Create Beta Schema
            </a>
        </div>
    <?php } ?>

    <div class="card border-0 shadow-sm mb-3">
        <div class="card-body d-flex align-items-center justify-content-between flex-wrap gap-2">
            <div>
                <div class="text-muted small">Schema branch</div>
                <div class="d-flex align-items-center gap-2 flex-wrap">
                    <span class="badge <?php echo $branch === 'beta' ? 'bg-warning text-dark' : 'bg-success'; ?>">
                        <?php echo strtoupper($branch); ?>
                    </span>
                    <?php if (!empty($branchStatus['s_status'])) { ?>
                        <span class="badge bg-light text-dark border">Status: <?php echo htmlspecialchars($branchStatus['s_status']); ?></span>
                    <?php } ?>
                </div>
            </div>
            <div class="d-flex align-items-center gap-2 flex-wrap">
                <?php if ($branchCanManage) { ?>
                    <?php if ($branch === 'beta') { ?>
                        <?php if ($branchCanMerge) { ?>
                            <a href="<?php echo $branchMergeUrl; ?>" class="btn btn-success btn-sm" onclick="return confirm('Merge beta schema into live?');">
                                <i class="bi bi-check2-circle"></i> Merge to Live
                            </a>
                        <?php } ?>
                        <a href="<?php echo $branchDiscardUrl; ?>" class="btn btn-outline-danger btn-sm" onclick="return confirm('Discard beta schema?');">
                            <i class="bi bi-trash"></i> Discard Beta
                        </a>
                        <a href="<?php echo $this->runData['route']['rad_admin_url']; ?>/controller/viewschema/<?php echo $controller['uid']; ?>/<?php echo $microservice['uid']; ?>" class="btn btn-outline-secondary btn-sm">
                            Live View
                        </a>
                    <?php } else { ?>
                        <?php if ($branchHasBeta) { ?>
                            <a href="<?php echo $this->runData['route']['rad_admin_url']; ?>/controller/viewschema/<?php echo $controller['uid']; ?>/<?php echo $microservice['uid']; ?>?branch=beta" class="btn btn-warning btn-sm">
                                Beta View
                            </a>
                        <?php } else { ?>
                            <a href="<?php echo $branchCreateUrl; ?>" class="btn btn-outline-primary btn-sm">
                                <i class="bi bi-branch"></i> Create Beta Schema
                            </a>
                        <?php } ?>
                    <?php } ?>
                <?php } ?>
            </div>
        </div>
    </div>

    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
        <div>
            <h2 class="h4 mb-0"><?php echo htmlspecialchars($controller['s_name']); ?> Schema</h2>
            <div class="text-muted small"><?php echo htmlspecialchars($tableName); ?> &middot; <?php echo htmlspecialchars($microservice['s_name']); ?></div>
        </div>
        <div class="btn-group">
            <a href="<?php echo $this->runData['route']['rad_admin_url']; ?>/controller/detail/<?php echo $controller['uid']; ?>" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left-circle"></i> Controller Details
            </a>
            <button type="button" class="btn btn-primary" id="open-field-modal" <?php echo $schemaCanEdit ? '' : 'disabled'; ?>>
                <i class="bi bi-plus-circle-fill me-1"></i>Add Field
            </button>
        </div>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-md-4">
            <div class="card h-100 controller-metric-card">
                <div class="card-body">
                    <div class="text-muted text-uppercase small">Fields</div>
                    <div class="display-6 fw-semibold"><?php echo count($fields); ?></div>
                    <div class="small text-muted">Custom data fields</div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card h-100 controller-metric-card">
                <div class="card-body">
                    <div class="text-muted text-uppercase small">Table Name</div>
                    <div class="fw-semibold"><?php echo htmlspecialchars($tableName); ?></div>
                    <div class="small text-muted">Managed automatically</div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card h-100 controller-metric-card">
                <div class="card-body">
                    <div class="text-muted text-uppercase small">Status</div>
                    <span class="badge bg-<?php echo ($controller['livestatus'] == 1) ? 'success' : 'secondary'; ?>">
                        <?php echo $controller['livestatus'] == 1 ? 'Active' : 'Inactive'; ?>
                    </span>
                    <div class="small text-muted mt-2">Last updated <?php echo htmlspecialchars($controller['updatestamp']); ?></div>
                </div>
            </div>
        </div>
    </div>

    <?php if (!empty($branchHistory)) { ?>
        <div class="card mb-3">
            <div class="card-header bg-white fw-semibold">Schema Branch Timeline</div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table mb-0">
                        <thead>
                            <tr>
                                <th>When</th>
                                <th>Status</th>
                                <th>Note</th>
                                <th>Actor</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($branchHistory as $entry) { ?>
                                <tr>
                                    <td class="text-muted small"><?php echo htmlspecialchars($entry['createstamp'] ?? $entry['updatestamp'] ?? ''); ?></td>
                                    <td><?php echo htmlspecialchars($entry['s_status'] ?? ''); ?></td>
                                    <td><?php echo htmlspecialchars($entry['s_note'] ?? ''); ?></td>
                                    <td><?php echo htmlspecialchars($entry['actor_label'] ?? ('User #' . ($entry['createdby'] ?? ''))); ?></td>
                                </tr>
                            <?php } ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    <?php } ?>

    <?php if (count($fields) > 0) { ?>
    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table align-middle" id="controller-fields-table">
                    <thead>
                        <tr>
                            <th>Label</th>
                            <th>Field</th>
                            <th>Type</th>
                            <th>Required</th>
                            <th>Help Text</th>
                            <th>Indexed</th>
                            <th class="text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($fields as $field): ?>
                            <?php
                                $typeRow = array_filter($fieldTypes, function($type) use ($field) {
                                    return (int)$type['id'] === (int)$field['s_field_type_id'];
                                });
                                $typeRow = $typeRow ? array_values($typeRow)[0] : null;
                                $typeLabel = $typeRow['s_description'] ?? 'Unknown';
                                $fieldData = [
                                    'id' => $field['id'],
                                    'name' => $field['s_field_name'],
                                    'label' => $field['s_field_label'],
                                    'field_type_id' => $field['s_field_type_id'],
                                    'help_text' => $field['s_help_text'],
                                    'nullable' => (int)$field['s_is_nullable'],
                                    'definition' => $field['s_definition'],
                                    'is_indexed' => !empty($field['is_indexed']),
                                ];
                            ?>
                            <tr data-field='<?php echo htmlspecialchars(json_encode($fieldData), ENT_QUOTES, 'UTF-8'); ?>'>
                                <td class="fw-semibold"><?php echo htmlspecialchars($field['s_field_label']); ?></td>
                                <td><code><?php echo htmlspecialchars($field['s_field_name']); ?></code></td>
                                <td><?php echo htmlspecialchars($typeLabel); ?></td>
                                <td>
                                    <?php if ((int)$field['s_is_nullable'] === 0): ?>
                                        <span class="badge bg-danger-subtle text-danger">Required</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">Optional</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-muted small">
                                    <?php echo $field['s_help_text'] ? htmlspecialchars($field['s_help_text']) : '—'; ?>
                                </td>
                                <td>
                                    <?php if (!empty($field['is_indexed'])) { ?>
                                        <span class="badge bg-info-subtle text-info">Indexed</span>
                                    <?php } else { ?>
                                        <span class="text-muted">—</span>
                                    <?php } ?>
                                </td>
                                <td class="text-center">
                                    <div class="btn-group btn-group-sm">
                                        <button class="btn btn-outline-secondary schema-edit-field" type="button">
                                            <i class="bi bi-pencil"></i>
                                        </button>
                                        <button class="btn btn-outline-danger schema-delete-field" type="button">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?php } else { ?>
        <div class="card">
            <div class="card-body text-center py-5">
                <img src="<?php echo $this->runData['route']['rad_assets_url']; ?>/img/no-controller.svg" alt="No schema" height="200">
                <h5 class="mt-3">No fields defined yet.</h5>
                <p class="text-muted mb-0">Use the Add Field button to start building this Data Manager.</p>
            </div>
        </div>
    <?php } ?>
</div>

<div class="modal fade schema-field-modal" id="schemaFieldModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <form id="schema-field-form">
                <div class="modal-header">
                    <h5 class="modal-title" id="schema-field-modal-title">Add Field</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" id="schema-field-id" name="field_id" value="">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Field Label</label>
                            <input type="text" class="form-control" id="schema-field-label" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Field Name</label>
                            <div class="input-group">
                                <span class="input-group-text">a_</span>
                                <input type="text" class="form-control" id="schema-field-name" placeholder="auto-generated if left blank">
                            </div>
                            <div class="form-text">Stored as <code id="schema-field-name-preview">a_</code></div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Field Type</label>
                            <select class="form-select" id="schema-field-type" required>
                                <?php foreach ($fieldTypes as $type): ?>
                                    <option value="<?php echo $type['id']; ?>"
                                            data-meta="<?php echo htmlspecialchars(json_encode($type['meta'] ?? []), ENT_QUOTES, 'UTF-8'); ?>">
                                        <?php echo htmlspecialchars($type['s_description']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6 schema-length-group">
                            <label class="form-label">Length</label>
                            <input type="number" min="1" class="form-control" id="schema-field-length" placeholder="Optional">
                        </div>
                        <div class="col-md-3 schema-precision-group">
                            <label class="form-label">Precision</label>
                            <input type="number" min="1" class="form-control" id="schema-field-precision" placeholder="Optional">
                        </div>
                        <div class="col-md-3 schema-scale-group">
                            <label class="form-label">Scale</label>
                            <input type="number" min="0" class="form-control" id="schema-field-scale" placeholder="Optional">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Help Text</label>
                            <input type="text" class="form-control" id="schema-field-help" placeholder="Shown on forms">
                        </div>
                        <div class="col-12 schema-options-group d-none">
                            <label class="form-label">Options</label>
                            <textarea class="form-control" id="schema-field-options" rows="3" placeholder="value|label per line"></textarea>
                            <div class="form-text">Enter one option per line using <code>value|label</code>. Label is optional.</div>
                        </div>
                        <div class="col-md-6 schema-fk-table-group d-none">
                            <label class="form-label">Related Table</label>
                            <input type="text" class="form-control" id="schema-field-fk-table" placeholder="e.g., a_users">
                        </div>
                        <div class="col-md-6 schema-fk-column-group d-none">
                            <label class="form-label">Related Column</label>
                            <input type="text" class="form-control" id="schema-field-fk-column" placeholder="e.g., id">
                        </div>
                        <div class="col-12 schema-source-group d-none">
                            <label class="form-label">Data Source</label>
                            <input type="text" class="form-control" id="schema-field-source" placeholder="URL or list identifier for auto-suggest">
                        </div>
                        <div class="col-12 schema-custom-sql-group d-none">
                            <label class="form-label">Custom SQL Definition</label>
                            <textarea class="form-control" id="schema-field-custom-sql" rows="2" placeholder="FULL SQL fragment for custom type"></textarea>
                            <div class="form-text">Provide the full column definition when using a custom type.</div>
                        </div>
                        <div class="col-md-6 d-flex align-items-end">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="schema-field-required">
                                <label class="form-check-label" for="schema-field-required">
                                    Field is required
                                </label>
                            </div>
                        </div>
                        <div class="col-md-6 d-flex align-items-end">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="schema-field-index">
                                <label class="form-check-label" for="schema-field-index">
                                    Create index
                                </label>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary" id="schema-field-save-btn">Save Field</button>
                </div>
            </form>
        </div>
    </div>
</div>

<style>
.schema-field-modal {
    z-index: 10050;
}
body.schema-field-modal-open .modal-backdrop {
    z-index: 10040 !important;
}
</style>
<script>
document.addEventListener('shown.bs.modal', function(event) {
    if (event.target && event.target.id === 'schemaFieldModal') {
        document.body.classList.add('schema-field-modal-open');
    }
});
document.addEventListener('hidden.bs.modal', function(event) {
    if (event.target && event.target.id === 'schemaFieldModal') {
        document.body.classList.remove('schema-field-modal-open');
    }
});
</script>
