<?php
$assets_dir = $this->runData['config']['dir']['assets'];
$relativePath = $this->runData['data']['innerAssetDirectory'] ?? '';
$pathparts = $relativePath === '' ? [] : array_values(array_filter(explode('/', $relativePath)));
$filters = $this->runData['data']['filters'] ?? ['q' => ''];
$encodedPath = $relativePath ? '/' . implode('/', array_map('rawurlencode', $pathparts)) : '';

if ($relativePath !== '') {
    $fetchUrl = $this->runData['config']['sys']['base_url'] . '/rad-admin/uiassets/fetchfiles/' . $relativePath;
    $uploadUrl = $this->runData['config']['sys']['base_url'] . '/rad-admin/uiassets/uploadfiles/' . $relativePath;
} else {
    $fetchUrl = $this->runData['config']['sys']['base_url'] . '/rad-admin/uiassets/fetchfiles/';
    $uploadUrl = $this->runData['config']['sys']['base_url'] . '/rad-admin/uiassets/uploadfiles/';
}

$dir = rtrim($assets_dir, '/') . ($relativePath ? '/' . $relativePath : '');
$stats = $this->runData['data']['assetStats'] ?? ['files' => 0, 'folders' => 0, 'size' => 0, 'latest_name' => null, 'latest_time' => 0];
$timezone = $this->runData['entity']['timezone'] ?? ($this->runData['config']['sys']['timezone_default'] ?? 'UTC');
$humanSize = function ($bytes) {
    if ($bytes <= 0) {
        return '0 KB';
    }
    $units = ['B', 'KB', 'MB', 'GB'];
    $power = (int)floor(log($bytes, 1024));
    return round($bytes / pow(1024, $power), 1) . ' ' . $units[$power];
};
$latestLabel = $stats['latest_name'] ? $stats['latest_name'] . ' · ' . \Core\Sys\TimeHelper::formatUtc($stats['latest_time'], $timezone) : '—';
?>

<div class="container-fluid py-3">
    <div class="card mb-4">
        <div class="card-body">
            <div class="btn-toolbar rad-stacked-toolbar" role="toolbar">
                <div class="btn-group" role="group">
                    <a href="<?php echo $this->runData['route']['rad_admin_url']; ?>/theme/view" class="btn btn-outline-primary">
                        <i class="bi bi-braces me-2"></i>Theme Templates
                    </a>
                    <a href="<?php echo $this->runData['route']['rad_admin_url']; ?>/uiassets/view" class="btn btn-primary text-white">
                        <i class="bi bi-images me-2"></i>Theme Assets
                    </a>
                </div>
                <div class="btn-group mt-2 mt-md-0 ms-md-2" role="group">
                    <a href="#upload-panel" class="btn btn-outline-secondary">
                        <i class="bi bi-cloud-upload me-2"></i>Upload Files
                    </a>
                    <a href="<?php echo $this->runData['route']['rad_admin_url']; ?>/uiassets/trash" class="btn btn-outline-danger">
                        <i class="bi bi-trash3 me-2"></i>Trash
                    </a>
                </div>
            </div>
        </div>
    </div>

    <div id="asset-feedback" class="mb-3"></div>

    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <div class="card h-100 border-0 bg-primary-subtle">
                <div class="card-body">
                    <div class="text-muted small">Total Items</div>
                    <div class="fs-3 fw-bold"><?php echo ($stats['files'] + $stats['folders']); ?></div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card h-100 border-0 bg-success-subtle">
                <div class="card-body">
                    <div class="text-muted small">Files</div>
                    <div class="fs-4 fw-semibold"><?php echo $stats['files']; ?></div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card h-100 border-0 bg-info-subtle">
                <div class="card-body">
                    <div class="text-muted small">Storage Used</div>
                    <div class="fs-5 fw-semibold"><?php echo $humanSize($stats['size']); ?></div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card h-100 border-0 bg-warning-subtle">
                <div class="card-body">
                    <div class="text-muted small">Latest Update</div>
                    <div class="small fw-semibold"><?php echo htmlspecialchars($latestLabel); ?></div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-12">
            <div class="card mb-3">
                <div class="card-body">
                    <form class="row g-2 align-items-end" method="get">
                        <div class="col-md-6 col-lg-4">
                            <label class="form-label small text-muted mb-1">Search by name or path</label>
                            <div class="input-group input-group-sm">
                                <span class="input-group-text"><i class="bi bi-search"></i></span>
                                <input type="text" name="q" class="form-control" placeholder="Filter files/folders" value="<?php echo htmlspecialchars($filters['q']); ?>">
                            </div>
                        </div>
                        <div class="col-md-6 col-lg-3 d-flex gap-2">
                            <button type="submit" class="btn btn-primary btn-sm flex-grow-1"><i class="bi bi-funnel"></i> Apply</button>
                            <?php if ($filters['q'] !== '') { ?>
                                <a class="btn btn-outline-secondary btn-sm flex-grow-1" href="<?php echo $this->runData['route']['rad_admin_url']; ?>/uiassets/view<?php echo $encodedPath; ?>">Reset</a>
                            <?php } ?>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        <!-- Left Panel: File and Folder Listing -->
        <div class="col-lg-8 mb-3">
            <div class="card">
                <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
                    <div id="view-controls">
                        <ul class="nav nav-tabs card-header-tabs">
                            <li class="nav-item">
                                <a class="nav-link active" id="folder-view-tab" data-bs-toggle="tab" href="#folder-view">
                                    <i class="bi bi-folder"></i> Folders
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" id="list-view-tab" data-bs-toggle="tab" href="#list-view">
                                    <i class="bi bi-list-ul"></i> List View
                                </a>
                            </li>
                        </ul>
                    </div>
                    <div class="input-group" style="max-width: 320px;">
                        <span class="input-group-text"><i class="bi bi-search"></i></span>
                        <input type="text" id="asset-search" class="form-control" placeholder="Filter files and folders">
                    </div>
                </div>
                <div class="card-body tab-content">
                    <div id="folder-view" class="tab-pane fade show active">
                        <div class="file-browser folder-view">
                            <!-- Content will be loaded dynamically -->
                        </div>
                    </div>
                    <div id="list-view" class="tab-pane fade">
                        <table class="table table-striped table-hover">
                            <thead>
                                <tr>
                                    <th class="sortable" data-sort="icon">
                                        Icon <span class="sort-icon"></span>
                                    </th>
                                    <th class="sortable" data-sort="name">
                                        File Name <span class="sort-icon"></span>
                                    </th>
                                    <th class="sortable" data-sort="type">
                                        Type <span class="sort-icon"></span>
                                    </th>
                                    <th class="sortable" data-sort="size">
                                        Size <span class="sort-icon"></span>
                                    </th>
                                    <th class="sortable" data-sort="lastUpdated">
                                        Last Updated <span class="sort-icon"></span>
                                    </th>
                                    <th>
                                        Actions
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                <!-- Content will be loaded dynamically -->
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Right Panel: File Upload Section -->
        <div class="col-lg-4">
            <div class="card">
                <div class="card-header">
                    <i class="bi bi-cloud-upload-fill"></i> Upload Files
                </div>
                <div class="card-body" id="upload-panel">
                    <div id="drop-area" class="border border-secondary rounded p-3 d-flex flex-column align-items-center justify-content-center" style="height: 300px;">
                        <form class="my-form d-flex flex-column align-items-center">
                            <div class="mb-3">
                                <i class="bi bi-cloud-arrow-up-fill display-4 text-secondary"></i>
                            </div>
                            <p class="text-muted text-center mb-3">Drag and drop files or folders here, or click below to upload.</p>
                            <input type="file" id="fileElem" class="d-none" multiple accept="*/*">
                            <label class="upload-label btn btn-primary mb-3" for="fileElem">
                                <i class="bi bi-upload"></i> Select Files
                            </label>
                        </form>
                        <div class="w-100 mb-3">
                            <progress id="progress-bar" max="100" value="0" class="w-100"></progress>
                        </div>
                        <div id="upload-message" class="text-center w-100"></div>
                        <div id="gallery" class="text-center w-100"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

</div>
