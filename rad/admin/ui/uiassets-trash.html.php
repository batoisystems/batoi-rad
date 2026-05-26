<?php
$stats = $this->runData['data']['archived_stats'] ?? ['count' => 0, 'size' => 0, 'size_readable' => '0 B', 'latest' => null];
$fetchUrl = $this->runData['config']['sys']['base_url'] . '/rad-admin/uiassets/fetcharchived';
$restoreUrl = $this->runData['config']['sys']['base_url'] . '/rad-admin/uiassets/unarchive';
$purgeUrl = $this->runData['config']['sys']['base_url'] . '/rad-admin/uiassets/purgearchive';
$emptyUrl = $this->runData['config']['sys']['base_url'] . '/rad-admin/uiassets/emptytrash';
$timezone = $this->runData['entity']['timezone'] ?? ($this->runData['config']['sys']['timezone_default'] ?? 'UTC');
$latestLabel = $stats['latest'] ? \Core\Sys\TimeHelper::formatUtc($stats['latest'], $timezone) : '—';
?>

<div class="container-fluid py-3">
    <div class="card mb-3">
        <div class="card-body d-flex flex-wrap justify-content-between align-items-start gap-3">
            <div>
                <div class="text-muted small">Trash Overview</div>
                <div class="fs-4 fw-semibold"><?php echo (int)$stats['count']; ?> item(s)</div>
                <div class="small text-muted">Total size: <code><?php echo htmlspecialchars($stats['size_readable']); ?></code></div>
                <div class="small text-muted">Latest archive: <?php echo htmlspecialchars($latestLabel); ?></div>
            </div>
            <div class="btn-group" role="group">
                <a href="<?php echo $this->runData['route']['rad_admin_url']; ?>/uiassets/view" class="btn btn-outline-secondary">
                    <i class="bi bi-arrow-left me-2"></i>Back to Assets
                </a>
            </div>
        </div>
    </div>

    <div class="alert alert-warning border d-flex flex-wrap justify-content-between align-items-center gap-3">
        <div>
            <strong>Reminder:</strong> Archived files stay here until restored or permanently deleted. Emptying the trash removes everything.
        </div>
        <button class="btn btn-outline-danger btn-sm" id="empty-trash-btn">
            <i class="bi bi-trash3-fill me-1"></i>Empty Trash
        </button>
    </div>

    <div id="trash-feedback"></div>

    <div class="card">
        <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
            <div class="d-flex align-items-center gap-2">
                <i class="bi bi-archive text-secondary"></i>
                <strong>Archived Items</strong>
            </div>
            <button class="btn btn-outline-secondary btn-sm" id="refresh-trash-btn">
                <i class="bi bi-arrow-clockwise me-1"></i>Refresh
            </button>
        </div>
        <div class="card-body">
            <div id="trash-empty" class="alert alert-info mb-0">No archived files at the moment.</div>
            <div class="table-responsive d-none" id="trash-table-wrapper">
                <table class="table table-striped align-middle" id="trash-table">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Original Path</th>
                            <th>Size</th>
                            <th>Archived On</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                </table>
            </div>
        </div>
    </div>
</div>
