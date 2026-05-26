<?php
$controller = $this->runData['data']['controller'];
$microservice = $this->runData['data']['microservice'];
$history = $this->runData['data']['history'] ?? [];
$fieldCount = $this->runData['data']['field_count'] ?? 0;
$workflow = $this->runData['data']['workflow_binding'] ?? null;
$createdBy = $this->runData['data']['controller_created_by'] ?? 'System';
$updatedBy = $this->runData['data']['controller_updated_by'] ?? 'System';
$runtime = $this->runData['data']['controller_runtime'] ?? [];

$statusMeta = [
    '0' => ['label' => 'Inactive', 'badge' => 'info'],
    '1' => ['label' => 'Active', 'badge' => 'success'],
    '2' => ['label' => 'Archived', 'badge' => 'danger'],
    '3' => ['label' => 'Suspended', 'badge' => 'warning'],
];
$status = $statusMeta[$controller['livestatus']] ?? $statusMeta['0'];

$codeUrl = $this->runData['route']['rad_admin_url'] . '/controller/code/' . $microservice['uid'] . '/' . $controller['s_name'];
$editUrl = $this->runData['route']['rad_admin_url'] . '/controller/edit/' . $controller['uid'] . '/' . $microservice['uid'];
$schemaUrl = $this->runData['route']['rad_admin_url'] . '/controller/viewschema/' . $controller['uid'] . '/' . $microservice['uid'];
$recordsUrl = $this->runData['route']['rad_admin_url'] . '/controller/viewrecords/' . $controller['uid'] . '/' . $microservice['uid'];
$isBusinessLogic = strtolower($controller['s_type'] ?? 'bl') === 'bl';
$isDataModel = !$isBusinessLogic;
$dmMeta = $isDataModel ? ($this->runData['data']['dm'] ?? []) : [];
$dmFieldTypeMap = [];
if ($isDataModel && !empty($dmMeta['field_types'])) {
    foreach ($dmMeta['field_types'] as $typeRow) {
        $label = $typeRow['s_description'] ?? $typeRow['s_name'] ?? ('Type #' . $typeRow['id']);
        $dmFieldTypeMap[$typeRow['id']] = $label;
    }
}
$dmRecordColumns = [];
if ($isDataModel && !empty($dmMeta['columns'])) {
    foreach ($dmMeta['columns'] as $col) {
        if (!empty($col['Field'])) {
            $dmRecordColumns[] = $col['Field'];
        }
    }
}
?>

<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <a href="<?php echo htmlspecialchars($this->runData['route']['backlink']); ?>" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left-circle"></i> Back to Microservicelet
    </a>
    <div class="btn-group" role="group" aria-label="Business Class / Data Model actions">
        <?php if ($isBusinessLogic): ?>
        <a href="<?php echo htmlspecialchars($codeUrl); ?>" class="btn btn-outline-secondary"><i class="bi bi-code-slash"></i> Code</a>
        <?php endif; ?>
        <a href="<?php echo htmlspecialchars($editUrl); ?>" class="btn btn-outline-primary"><i class="bi bi-pencil-square"></i> Edit</a>
        <?php if (!$isBusinessLogic): ?>
        <a href="<?php echo htmlspecialchars($schemaUrl); ?>" class="btn btn-outline-info"><i class="bi bi-diagram-3"></i> Schema</a>
        <a href="<?php echo htmlspecialchars($recordsUrl); ?>" class="btn btn-outline-success"><i class="bi bi-table"></i> Records</a>
        <?php endif; ?>
    </div>
</div>

<div class="card mb-4">
    <div class="card-body">
        <div class="d-flex justify-content-between flex-wrap gap-3">
            <div>
                <h2 class="h4 mb-1"><?php echo htmlspecialchars($controller['s_name']); ?></h2>
                <p class="text-muted mb-2"><?php echo $controller['s_description'] ? htmlspecialchars($controller['s_description']) : 'No description provided.'; ?></p>
                <div class="d-flex flex-wrap gap-2">
                    <span class="badge bg-<?php echo $status['badge']; ?>"><?php echo $status['label']; ?></span>
                    <span class="badge bg-light text-dark"><i class="bi bi-boxes me-1"></i><?php echo htmlspecialchars($microservice['s_name']); ?> (ID: <?php echo (int)$microservice['id']; ?>, UID: <?php echo htmlspecialchars($microservice['uid']); ?>)</span>
                    <span class="badge bg-light text-dark">Type: <?php echo $isBusinessLogic ? 'Business Class' : 'Data Model'; ?></span>
                    <?php if ($workflow) { ?>
                        <span class="badge bg-primary text-white">Workflow Bound</span>
                    <?php } ?>
                </div>
            </div>
            <div class="text-end">
                <div class="small text-muted"><?php echo $isBusinessLogic ? 'Business Class' : 'Data Model'; ?> UID</div>
                <code><?php echo htmlspecialchars($controller['uid']); ?></code>
                <div class="mt-2 small text-muted"><?php echo $isBusinessLogic ? 'Business Class' : 'Data Model'; ?> ID</div>
                <span class="fw-semibold"><?php echo $controller['id']; ?></span>
            </div>
        </div>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-md-3">
        <div class="card h-100">
            <div class="card-body">
                <?php if ($isBusinessLogic): ?>
                    <div class="text-muted text-uppercase small mb-1">Source File</div>
                    <div class="fw-semibold text-break"><?php echo htmlspecialchars($runtime['source_file'] ?? 'n/a'); ?></div>
                    <div class="small text-muted">
                        <?php echo !empty($runtime['file_exists']) ? 'Present on disk' : 'Missing on disk'; ?>
                    </div>
                <?php else: ?>
                    <div class="text-muted text-uppercase small mb-1">Fields</div>
                    <div class="display-6"><?php echo $fieldCount; ?></div>
                    <div class="small text-muted">Defined in this controller</div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card h-100">
            <div class="card-body">
                <div class="text-muted text-uppercase small mb-1">Created</div>
                <div class="fw-semibold"><?php echo htmlspecialchars($createdBy); ?></div>
                <div class="small text-muted"><?php echo htmlspecialchars($controller['createstamp']); ?></div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card h-100">
            <div class="card-body">
                <div class="text-muted text-uppercase small mb-1">Last updated</div>
                <div class="fw-semibold"><?php echo htmlspecialchars($updatedBy); ?></div>
                <div class="small text-muted"><?php echo htmlspecialchars($controller['updatestamp']); ?></div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card h-100">
            <div class="card-body">
                <div class="text-muted text-uppercase small mb-1">Microservicelet</div>
                <a href="<?php echo $this->runData['route']['rad_admin_url']; ?>/microservice/detail/<?php echo $microservice['uid']; ?>" class="fw-semibold">
                    <?php echo htmlspecialchars($microservice['s_name']); ?>
                </a>
                <div class="small text-muted">ID: <?php echo (int)$microservice['id']; ?> · UID: <?php echo htmlspecialchars($microservice['uid']); ?></div>
                <div class="small text-muted">Template: <?php echo htmlspecialchars($microservice['s_tpl_name']); ?></div>
            </div>
        </div>
    </div>
</div>

<div class="card mb-4">
    <div class="card-header">
        <strong>Controller Metadata</strong>
    </div>
    <div class="card-body">
        <div class="row">
            <div class="col-md-6">
                <dl class="row mb-0">
                    <dt class="col-sm-4 text-muted">Internal Key</dt>
                    <dd class="col-sm-8"><code><?php echo htmlspecialchars($controller['s_name']); ?></code></dd>
                    <dt class="col-sm-4 text-muted">Access Scope</dt>
                    <?php $accessScope = (strtolower($microservice['s_scope'] ?? '') === 'global') ? 'public' : 'private'; ?>
                    <dd class="col-sm-8"><?php echo htmlspecialchars($accessScope); ?></dd>
                    <dt class="col-sm-4 text-muted">SaaS</dt>
                    <?php $msScope = $microservice['s_scope'] ?? 'platform'; $isSaas = ($msScope === 'workspace'); ?>
                    <dd class="col-sm-8"><?php echo $isSaas ? 'SaaS (' . htmlspecialchars($msScope) . ')' : 'Non-SaaS (' . htmlspecialchars($msScope) . ')'; ?></dd>
                    <?php if ($isBusinessLogic): ?>
                    <dt class="col-sm-4 text-muted">Source File</dt>
                    <dd class="col-sm-8"><code><?php echo htmlspecialchars($runtime['source_file'] ?? ''); ?></code></dd>
                    <dt class="col-sm-4 text-muted">Class Name</dt>
                    <dd class="col-sm-8"><code><?php echo htmlspecialchars($runtime['class_name'] ?? ''); ?></code></dd>
                    <?php else: ?>
                    <dt class="col-sm-4 text-muted">Table</dt>
                    <dd class="col-sm-8"><code><?php echo htmlspecialchars($runtime['table_name'] ?? ('a_' . ($controller['s_name'] ?? ''))); ?></code></dd>
                    <?php endif; ?>
                </dl>
            </div>
            <div class="col-md-6">
                <dl class="row mb-0">
                    <dt class="col-sm-4 text-muted">Livestatus</dt>
                    <dd class="col-sm-8"><?php echo $status['label']; ?></dd>
                    <dt class="col-sm-4 text-muted">Version</dt>
                    <dd class="col-sm-8"><?php echo (int)$controller['versioncode']; ?></dd>
                    <?php if ($isBusinessLogic): ?>
                    <dt class="col-sm-4 text-muted">Source Status</dt>
                    <dd class="col-sm-8">
                        <?php if (!empty($runtime['file_exists'])): ?>
                            <span class="badge bg-success-subtle text-success">Available</span>
                        <?php else: ?>
                            <span class="badge bg-danger-subtle text-danger">Missing</span>
                        <?php endif; ?>
                    </dd>
                    <dt class="col-sm-4 text-muted">Resolved Path</dt>
                    <dd class="col-sm-8"><code class="text-break"><?php echo htmlspecialchars($runtime['file_path'] ?? ''); ?></code></dd>
                    <?php endif; ?>
                </dl>
            </div>
        </div>
    </div>
</div>

<?php if ($isBusinessLogic && empty($runtime['file_exists'])) { ?>
<div class="alert alert-warning mb-4">
    The registered Business Class source file is missing from disk. Open <strong>Code</strong> to recreate it, or fix the source file metadata.
</div>
<?php } ?>

<div class="card mb-4">
    <div class="card-header d-flex justify-content-between align-items-center">
        <strong>Update History</strong>
        <span class="text-muted small"><?php echo count($history); ?> entries</span>
    </div>
    <div class="card-body">
        <?php if (empty($history)) { ?>
            <p class="mb-0 text-muted">No version history recorded for this controller yet.</p>
        <?php } else { ?>
            <div class="table-responsive">
                <table class="table table-sm align-middle">
                    <thead>
                        <tr>
                            <th>Version</th>
                            <th>Modified By</th>
                            <th>Timestamp</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($history as $entry): ?>
                            <?php $collapseId = 'controller-history-' . $entry['id']; ?>
                            <tr>
                                <td>#<?php echo $entry['s_version_number'] ?? $entry['id']; ?></td>
                                <td><?php echo htmlspecialchars($entry['modifier_label']); ?></td>
                                <td><span class="text-muted small"><?php echo htmlspecialchars($entry['s_modified_timestamp'] ?? ''); ?></span></td>
                                <td class="text-end">
                                    <?php if (!empty($entry['snapshot'])): ?>
                                        <button class="btn btn-sm btn-outline-secondary" type="button" data-bs-toggle="collapse" data-bs-target="#<?php echo $collapseId; ?>">
                                            View snapshot
                                        </button>
                                    <?php else: ?>
                                        <span class="text-muted small">No snapshot</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php if (!empty($entry['snapshot'])): ?>
                                <tr class="collapse" id="<?php echo $collapseId; ?>">
                                    <td colspan="4">
                                        <pre class="bg-dark text-light rounded p-3 mb-0"><?php echo htmlspecialchars($entry['snapshot']); ?></pre>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php } ?>
    </div>
</div>

<?php if ($isDataModel) { ?>
<div class="card mb-4">
    <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
        <div>
            <h5 class="mb-1">Data Model Overview</h5>
            <div class="text-muted small">
                <?php echo htmlspecialchars($dmMeta['table'] ?? ''); ?> &middot;
                <?php echo (int)$fieldCount; ?> field(s)
            </div>
        </div>
        <div class="btn-group btn-group-sm" role="group">
            <a href="<?php echo htmlspecialchars($schemaUrl); ?>" class="btn btn-outline-primary">
                <i class="bi bi-diagram-3"></i> Manage Schema
            </a>
            <a href="<?php echo htmlspecialchars($recordsUrl); ?>" class="btn btn-outline-success">
                <i class="bi bi-table"></i> Manage Records
            </a>
            <a href="<?php echo $this->runData['route']['rad_admin_url']; ?>/appdata/sync" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-repeat"></i> Sync Utility
            </a>
        </div>
    </div>
    <div class="card-body">
        <?php if (empty($dmMeta['table_exists'])) { ?>
            <div class="alert alert-warning mb-4">
                The physical table for this data model is missing. Use the Application Data Sync utility before making schema or data changes.
            </div>
        <?php } ?>
        <div class="row g-4">
            <div class="col-lg-6">
                <h6 class="text-uppercase text-muted small mb-2">Schema Preview</h6>
                <?php if (empty($dmMeta['fields'])) { ?>
                    <p class="text-muted mb-0">No custom fields defined yet. Use <strong>Manage Schema</strong> to start modelling data.</p>
                <?php } else { ?>
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Field</th>
                                <th>Label</th>
                                <th>Type</th>
                                <th>Nullable</th>
                                <th>Indexed</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($dmMeta['fields'] as $field) { ?>
                                <tr>
                                    <td><code><?php echo htmlspecialchars($field['s_field_name']); ?></code></td>
                                    <td><?php echo htmlspecialchars($field['s_field_label']); ?></td>
                                    <td><?php echo htmlspecialchars($dmFieldTypeMap[$field['s_field_type_id']] ?? 'Field Type #' . $field['s_field_type_id']); ?></td>
                                    <td><?php echo ((int)$field['s_is_nullable'] === 1) ? 'Yes' : 'No'; ?></td>
                                    <td>
                                        <?php if (!empty($field['is_indexed'])) { ?>
                                            <span class="badge bg-info-subtle text-info">Indexed</span>
                                        <?php } else { ?>
                                            <span class="text-muted">&mdash;</span>
                                        <?php } ?>
                                    </td>
                                </tr>
                            <?php } ?>
                        </tbody>
                    </table>
                </div>
                <?php } ?>
            </div>
            <div class="col-lg-6">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <h6 class="text-uppercase text-muted small mb-0">Recent Records</h6>
                    <span class="badge bg-light text-dark"><?php echo (int)($dmMeta['records_total'] ?? count($dmMeta['rows'] ?? [])); ?> total</span>
                </div>
                <?php if (empty($dmMeta['rows'])) { ?>
                    <p class="text-muted mb-0">No data captured yet. Use <strong>Manage Records</strong> to add entries.</p>
                <?php } else { ?>
                    <?php
                    $previewColumns = array_slice($dmRecordColumns, 0, 4);
                    if (empty($previewColumns) && !empty($dmMeta['rows'][0])) {
                        $previewColumns = array_slice(array_keys($dmMeta['rows'][0]), 0, 4);
                    }
                    ?>
                    <div class="table-responsive">
                        <table class="table table-sm table-striped align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <?php foreach ($previewColumns as $col) { ?>
                                        <th><?php echo htmlspecialchars($col); ?></th>
                                    <?php } ?>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($dmMeta['rows'] as $row) { ?>
                                <tr>
                                    <?php foreach ($previewColumns as $col) { ?>
                                        <td><?php echo isset($row[$col]) ? htmlspecialchars((string)$row[$col]) : ''; ?></td>
                                    <?php } ?>
                                </tr>
                                <?php } ?>
                            </tbody>
                        </table>
                    </div>
                    <div class="text-end mt-2">
                        <a class="btn btn-link btn-sm px-0" href="<?php echo htmlspecialchars($recordsUrl); ?>">
                            Open record manager <i class="bi bi-arrow-up-right"></i>
                        </a>
                    </div>
                <?php } ?>
            </div>
        </div>
    </div>
</div>
<?php } ?>
