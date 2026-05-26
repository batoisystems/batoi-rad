<?php
$formSubmissionUrl = $this->runData['route']['url'];
$controller = $this->runData['data']['controller'];
$ms = $this->runData['data']['ms'];
$runtime = $this->runData['data']['controller_runtime'] ?? [];
$isBusinessLogic = strtoupper((string)($controller['s_type'] ?? 'BL')) === 'BL';
$detailUrl = $this->runData['route']['rad_admin_url'] . '/controller/detail/' . $controller['uid'];
$microserviceUrl = $this->runData['route']['rad_admin_url'] . '/microservice/detail/' . $ms['uid'];
$codeUrl = $this->runData['route']['rad_admin_url'] . '/controller/code/' . $ms['uid'] . '/' . $controller['s_name'];
$schemaUrl = $this->runData['route']['rad_admin_url'] . '/controller/viewschema/' . $controller['uid'] . '/' . $ms['uid'];
$recordsUrl = $this->runData['route']['rad_admin_url'] . '/controller/viewrecords/' . $controller['uid'] . '/' . $ms['uid'];
$typeLabel = $isBusinessLogic ? 'Business Class' : 'Data Model';
$sourceStatus = !empty($runtime['file_exists']) ? 'Available on disk' : 'Missing on disk';
?>

<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <a href="<?php echo htmlspecialchars($detailUrl); ?>" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left-circle"></i> Back to Detail
    </a>
    <div class="btn-group" role="group" aria-label="Controller quick actions">
        <a href="<?php echo htmlspecialchars($microserviceUrl); ?>" class="btn btn-outline-secondary">
            <i class="bi bi-boxes"></i> Microservicelet
        </a>
        <?php if ($isBusinessLogic): ?>
        <a href="<?php echo htmlspecialchars($codeUrl); ?>" class="btn btn-outline-primary">
            <i class="bi bi-code-slash"></i> Code
        </a>
        <?php else: ?>
        <a href="<?php echo htmlspecialchars($schemaUrl); ?>" class="btn btn-outline-primary">
            <i class="bi bi-diagram-3"></i> Schema
        </a>
        <a href="<?php echo htmlspecialchars($recordsUrl); ?>" class="btn btn-outline-success">
            <i class="bi bi-table"></i> Records
        </a>
        <?php endif; ?>
    </div>
</div>

<div class="card shadow-sm border-0 mb-4">
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-start flex-wrap gap-3">
            <div>
                <div class="text-muted small text-uppercase mb-1">Editing <?php echo htmlspecialchars($typeLabel); ?></div>
                <h2 class="h4 mb-1"><?php echo htmlspecialchars($controller['s_name']); ?></h2>
                <p class="text-muted mb-2"><?php echo $controller['s_description'] ? htmlspecialchars($controller['s_description']) : 'No description provided yet.'; ?></p>
                <div class="d-flex flex-wrap gap-2">
                    <span class="badge bg-light text-dark"><?php echo htmlspecialchars($typeLabel); ?></span>
                    <span class="badge bg-light text-dark"><?php echo htmlspecialchars($ms['s_name']); ?></span>
                    <?php if ($isBusinessLogic): ?>
                    <span class="badge <?php echo !empty($runtime['file_exists']) ? 'bg-success-subtle text-success' : 'bg-warning-subtle text-warning'; ?>">
                        <?php echo htmlspecialchars($sourceStatus); ?>
                    </span>
                    <?php else: ?>
                    <span class="badge bg-info-subtle text-info"><?php echo htmlspecialchars($runtime['table_name'] ?? ('a_' . ($controller['s_name'] ?? ''))); ?></span>
                    <?php endif; ?>
                </div>
            </div>
            <div class="text-end">
                <div class="small text-muted">Controller UID</div>
                <code><?php echo htmlspecialchars($controller['uid']); ?></code>
                <div class="small text-muted mt-2">Microservicelet UID</div>
                <code><?php echo htmlspecialchars($ms['uid']); ?></code>
            </div>
        </div>
    </div>
</div>

<div class="row g-4">
    <div class="col-lg-8">
        <div class="card shadow-sm border-0">
            <div class="card-header bg-white">
                <strong>Edit Metadata</strong>
            </div>
            <div class="card-body">
                <div class="alert alert-info">
                    The internal controller key is locked on this page. Renaming it would require coordinated updates to Business Class files or Data Model tables.
                </div>
                <form action="<?php print $formSubmissionUrl; ?>" method="post" class="needs-validation" novalidate>
                    <input type="hidden" name="controller_id" value="<?php echo htmlspecialchars($controller['uid']); ?>">
                    <div class="mb-3">
                        <label for="s_name" class="form-label">Internal Key</label>
                        <input type="text" class="form-control" name="s_name" id="s_name" value="<?php echo htmlspecialchars($controller['s_name']); ?>" readonly>
                        <div class="form-text">Stable internal identifier used by RAD runtime, route definitions, and linked assets.</div>
                    </div>
                    <div class="mb-3">
                        <label for="s_description" class="form-label">Description</label>
                        <textarea class="form-control" name="s_description" id="s_description" rows="4" required><?php echo htmlspecialchars($controller['s_description']); ?></textarea>
                        <div class="invalid-feedback">Description is required.</div>
                        <div class="form-text">Use the description to explain the purpose of this <?php echo strtolower($typeLabel); ?> to other admins.</div>
                    </div>
                    <div class="mb-3">
                        <label for="s_type" class="form-label">Type</label>
                        <input type="text" class="form-control" id="s_type" value="<?php echo htmlspecialchars($typeLabel . ' (' . ($controller['s_type'] ?? '') . ')'); ?>" readonly>
                    </div>
                    <div class="d-flex gap-2 flex-wrap">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-check-circle"></i> Save Changes
                        </button>
                        <a href="<?php echo htmlspecialchars($detailUrl); ?>" class="btn btn-outline-secondary">
                            Cancel
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card shadow-sm border-0 mb-4">
            <div class="card-header bg-white">
                <strong>Runtime Info</strong>
            </div>
            <div class="card-body">
                <dl class="row mb-0">
                    <dt class="col-sm-5 text-muted">Internal Key</dt>
                    <dd class="col-sm-7"><code><?php echo htmlspecialchars($controller['s_name']); ?></code></dd>
                    <?php if ($isBusinessLogic): ?>
                    <dt class="col-sm-5 text-muted">Source File</dt>
                    <dd class="col-sm-7"><code class="text-break"><?php echo htmlspecialchars($runtime['source_file'] ?? ''); ?></code></dd>
                    <dt class="col-sm-5 text-muted">Class Name</dt>
                    <dd class="col-sm-7"><code><?php echo htmlspecialchars($runtime['class_name'] ?? ''); ?></code></dd>
                    <dt class="col-sm-5 text-muted">Resolved Path</dt>
                    <dd class="col-sm-7"><code class="text-break"><?php echo htmlspecialchars($runtime['file_path'] ?? ''); ?></code></dd>
                    <dt class="col-sm-5 text-muted">Source Status</dt>
                    <dd class="col-sm-7"><?php echo htmlspecialchars($sourceStatus); ?></dd>
                    <?php else: ?>
                    <dt class="col-sm-5 text-muted">Table</dt>
                    <dd class="col-sm-7"><code><?php echo htmlspecialchars($runtime['table_name'] ?? ('a_' . ($controller['s_name'] ?? ''))); ?></code></dd>
                    <dt class="col-sm-5 text-muted">Resolved Path</dt>
                    <dd class="col-sm-7 text-muted">Not applicable for Data Models</dd>
                    <?php endif; ?>
                </dl>
            </div>
        </div>

        <div class="card shadow-sm border-0">
            <div class="card-header bg-white">
                <strong>Tips</strong>
            </div>
            <div class="card-body">
                <ul class="mb-0 ps-3">
                    <li>Use this page for metadata updates only. Identity changes should be handled through a dedicated rename workflow.</li>
                    <li>For Business Classes, edit PHP logic from the <strong>Code</strong> page rather than trying to change runtime file metadata here.</li>
                    <li>For Data Models, use <strong>Schema</strong> and <strong>Records</strong> to manage structure and data safely.</li>
                </ul>
            </div>
        </div>
    </div>
</div>
