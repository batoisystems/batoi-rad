<?php
$controllers = $this->runData['data']['controller'];
$microservice = $this->runData['data']['ms'];
$totalControllers = count($controllers);

$statusMeta = [
    '0' => ['label' => 'Inactive', 'badge' => 'info', 'slug' => 'inactive'],
    '1' => ['label' => 'Active', 'badge' => 'success', 'slug' => 'active'],
    '2' => ['label' => 'Archived', 'badge' => 'danger', 'slug' => 'archived'],
    '3' => ['label' => 'Suspended', 'badge' => 'warning', 'slug' => 'suspended'],
];

$activeCount = 0;
$logicCount = 0;
$dataCount = 0;

foreach ($controllers as &$controller) {
    $meta = $statusMeta[$controller['livestatus']] ?? $statusMeta['0'];
    $controller['status_meta'] = $meta;
    if ($meta['slug'] === 'active') {
        $activeCount++;
    }

    $typeSlug = strtolower($controller['s_type'] ?? 'bl');
    $controller['type_slug'] = $typeSlug;
    if ($typeSlug === 'bl') {
        $logicCount++;
    } else {
        $dataCount++;
    }

    $controller['search_blob'] = strtolower(
        trim($controller['s_name'] . ' ' . ($controller['s_description'] ?? '') . ' ' . $controller['uid'] . ' ' . $controller['id'])
    );
}
unset($controller);

if ($totalControllers > 0) {
?>
<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
    <div class="text-muted small">
        Showing <span id="controller-visible-count"><?php echo $totalControllers; ?></span> of <?php echo $totalControllers; ?> items
    </div>
    <div class="btn-group">
        <a href="<?php echo $this->runData['route']['rad_admin_url']; ?>/microservice/detail/<?php echo $microservice['uid']; ?>" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left-circle"></i> Microservicelet Overview
        </a>
        <a href="<?php echo $this->runData['route']['rad_admin_url']; ?>/controller/add/<?php echo $microservice['uid']; ?>" class="btn btn-primary">
            <i class="bi bi-plus-circle-fill me-1"></i>Add Business Class / Data Model
        </a>
    </div>
</div>

<div class="text-muted small mb-3">
    Microservicelet: <?php echo htmlspecialchars($microservice['s_name']); ?> (ID: <?php echo (int)$microservice['id']; ?> · UID: <?php echo htmlspecialchars($microservice['uid']); ?>)
</div>

<div class="row g-3 mb-3">
    <div class="col-md-3">
        <div class="card h-100 controller-metric-card">
            <div class="card-body">
                <div class="text-muted text-uppercase small">Total</div>
                <div class="display-6 fw-semibold"><?php echo $totalControllers; ?></div>
                <div class="small text-muted">Business Classes & Data Models in <?php echo htmlspecialchars($microservice['s_name']); ?></div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card h-100 controller-metric-card">
            <div class="card-body">
                <div class="text-muted text-uppercase small">Active</div>
                <div class="display-6 fw-semibold text-success"><?php echo $activeCount; ?></div>
                <div class="small text-muted">Currently live</div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card h-100 controller-metric-card">
            <div class="card-body">
                <div class="text-muted text-uppercase small">Business Classes</div>
                <div class="display-6 fw-semibold text-primary"><?php echo $logicCount; ?></div>
                <div class="small text-muted">Business logic classes</div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card h-100 controller-metric-card">
            <div class="card-body">
                <div class="text-muted text-uppercase small">Data Models</div>
                <div class="display-6 fw-semibold text-info"><?php echo $dataCount; ?></div>
                <div class="small text-muted">Schema-driven data models</div>
            </div>
        </div>
    </div>
</div>

<div class="card mb-3">
    <div class="card-body">
        <div class="row g-3 align-items-end">
            <div class="col-lg-4">
                <label class="form-label">Search</label>
                <input type="text" class="form-control" id="controller-filter-search" placeholder="Name, UID, description...">
            </div>
            <div class="col-md-2">
                <label class="form-label">Status</label>
                <select class="form-select" id="controller-filter-status">
                    <option value="">All</option>
                    <option value="active">Active</option>
                    <option value="inactive">Inactive</option>
                    <option value="archived">Archived</option>
                    <option value="suspended">Suspended</option>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">Type</label>
                <select class="form-select" id="controller-filter-type">
                    <option value="">All</option>
                    <option value="bl">Business Class</option>
                    <option value="dm">Data Model</option>
                </select>
            </div>
            <div class="col-md-2 text-md-end">
                <button class="btn btn-outline-secondary w-100" id="controller-filter-reset">
                    <i class="bi bi-arrow-counterclockwise me-1"></i>Reset
                </button>
            </div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table align-middle" id="controller-table">
                <thead>
                    <tr>
                        <th>Business Class / Data Model</th>
                        <th>Status</th>
                        <th>Type</th>
                        <th>Description</th>
                        <th class="text-center">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($controllers as $controller): ?>
                        <tr
                            data-status="<?php echo $controller['status_meta']['slug']; ?>"
                            data-type="<?php echo htmlspecialchars($controller['type_slug']); ?>"
                            data-search="<?php echo htmlspecialchars($controller['search_blob'], ENT_QUOTES, 'UTF-8'); ?>"
                        >
                            <td>
                                <div class="fw-semibold">
                                    <a href="<?php echo $this->runData['route']['rad_admin_url']; ?>/controller/detail/<?php echo $controller['uid']; ?>">
                                        <?php echo htmlspecialchars($controller['s_name']); ?>
                                    </a>
                                </div>
                                <div class="text-muted small">ID: <?php echo $controller['id']; ?> &middot; UID: <?php echo htmlspecialchars(substr($controller['uid'], 0, 12)); ?>...</div>
                            </td>
                            <td>
                                <span class="badge bg-<?php echo $controller['status_meta']['badge']; ?>">
                                    <?php echo $controller['status_meta']['label']; ?>
                                </span>
                            </td>
                            <td>
                                <?php if ($controller['type_slug'] === 'bl'): ?>
                                    <span class="badge bg-primary-subtle text-primary"><i class="bi bi-braces-asterisk me-1"></i>Business</span>
                                <?php else: ?>
                                    <span class="badge bg-info-subtle text-info"><i class="bi bi-table me-1"></i>Data</span>
                                <?php endif; ?>
                            </td>
                            <td class="small text-muted">
                                <?php echo $controller['s_description'] ? htmlspecialchars($controller['s_description']) : '—'; ?>
                            </td>
                            <td class="text-center">
                                <div class="btn-group btn-group-sm" role="group">
                                    <a href="<?php echo $this->runData['route']['rad_admin_url']; ?>/controller/detail/<?php echo $controller['uid']; ?>" class="btn btn-outline-secondary" title="View details">
                                        <i class="bi bi-eye"></i>
                                    </a>
                                    <?php if ($controller['type_slug'] === 'bl'): ?>
                                        <a href="<?php echo $this->runData['route']['rad_admin_url']; ?>/controller/code/<?php echo $microservice['uid']; ?>/<?php echo $controller['s_name']; ?>" class="btn btn-outline-primary" title="Edit code">
                                            <i class="bi bi-code"></i>
                                        </a>
                                    <?php else: ?>
                                        <a href="<?php echo $this->runData['route']['rad_admin_url']; ?>/controller/viewschema/<?php echo $controller['uid']; ?>/<?php echo $microservice['uid']; ?>" class="btn btn-outline-primary" title="View schema">
                                            <i class="bi bi-diagram-3"></i>
                                        </a>
                                    <?php endif; ?>
                                    <a href="<?php echo $this->runData['route']['rad_admin_url']; ?>/controller/edit/<?php echo $controller['uid']; ?>/<?php echo $microservice['uid']; ?>" class="btn btn-outline-success" title="Edit controller">
                                        <i class="bi bi-pencil"></i>
                                    </a>
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
<div class="my-5 py-5 text-center">
    <img src="<?php echo $this->runData['route']['rad_assets_url']; ?>/img/no-controller.svg" alt="No controller created." height="200">
    <h1 class="h4 mt-3 text-center">There is no controller available.</h1>
    <a href="<?php echo $this->runData['route']['rad_admin_url']; ?>/controller/add/<?php echo $microservice['uid']; ?>" class="btn btn-primary mt-3"><i class="bi bi-plus-circle-fill me-1"></i> Add Controller</a>
    <a href="<?php echo $this->runData['route']['rad_admin_url']; ?>/microservice/view" class="btn btn-outline-secondary mt-3"><i class="bi bi-arrow-left-circle-fill"></i> Back to Microservicelets</a>
</div>
<?php } ?>
