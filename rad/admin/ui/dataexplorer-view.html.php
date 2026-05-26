<?php
$spaces = $this->runData['data']['spaces'] ?? [];
$selectedSpace = $this->runData['data']['selected_space'] ?? null;
$workspaceFiles = $this->runData['data']['workspace_files'] ?? [];
$legacyEntries = $this->runData['data']['legacy_entries'] ?? [];
$globalBucket = $this->runData['data']['global_bucket'] ?? null;
$storageBase = $this->runData['data']['storage_base'] ?? '';
$radAdminUrl = $this->runData['route']['rad_admin_url'];
$baseUrl = rtrim($this->runData['config']['sys']['base_url'] ?? '', '/');
$filters = $this->runData['data']['filters'] ?? ['q' => ''];
$timezone = $this->runData['entity']['timezone'] ?? ($this->runData['config']['sys']['timezone_default'] ?? 'UTC');

$formatBytes = static function ($bytes) {
    if ($bytes >= 1073741824) {
        return round($bytes / 1073741824, 2) . ' GB';
    }
    if ($bytes >= 1048576) {
        return round($bytes / 1048576, 2) . ' MB';
    }
    if ($bytes >= 1024) {
        return round($bytes / 1024, 2) . ' KB';
    }
    return $bytes . ' B';
};

$workspaceUrl = static function ($base, $relative) {
    $segments = array_map('rawurlencode', explode('/', $relative));
    return $base . '/fs-store/' . implode('/', $segments);
};
?>

<div class="card border-0 shadow-sm mb-4">
    <div class="card-body d-flex flex-column flex-lg-row justify-content-between gap-3">
        <div>
            <h4 class="mb-1">Workspace Data Explorer</h4>
            <p class="mb-0 text-muted">Browse protected uploads stored under <code><?php echo htmlspecialchars($storageBase); ?></code>. Files are grouped per workspace to keep tenant data isolated.</p>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <a href="<?php echo $radAdminUrl; ?>/appdata/view" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-table me-1"></i>Application Data
            </a>
            <a href="<?php echo $radAdminUrl; ?>/space/view" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-buildings me-1"></i>Workspaces
            </a>
        </div>
    </div>
</div>

<div class="card mb-4">
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h5 class="card-title mb-0">Workspace Buckets</h5>
            <span class="text-muted small"><?php echo count($spaces); ?> workspace<?php echo count($spaces) === 1 ? '' : 's'; ?></span>
        </div>
        <form class="row g-2 mb-3 align-items-end" method="get">
            <div class="col-md-8">
                <label class="form-label small text-muted">Search</label>
                <input type="text" name="q" class="form-control form-control-sm" placeholder="Workspace name or UID" value="<?php echo htmlspecialchars($filters['q']); ?>">
            </div>
            <div class="col-md-4 d-flex gap-2">
                <button type="submit" class="btn btn-primary btn-sm flex-fill"><i class="bi bi-funnel me-1"></i>Apply</button>
                <?php if (!empty($filters['q'])) { ?>
                    <a class="btn btn-outline-secondary btn-sm flex-fill" href="<?php echo $radAdminUrl; ?>/dataexplorer/view">Reset</a>
                <?php } ?>
            </div>
        </form>
        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead class="text-muted small">
                    <tr>
                        <th>Name</th>
                        <th>UID</th>
                        <th class="text-end">Files</th>
                        <th class="text-end">Storage</th>
                        <th>Path</th>
                        <th class="text-end">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($spaces as $space) { ?>
                        <tr>
                            <td><?php echo htmlspecialchars($space['name']); ?></td>
                            <td><code><?php echo htmlspecialchars($space['uid']); ?></code></td>
                            <td class="text-end"><?php echo number_format($space['stats']['files']); ?></td>
                            <td class="text-end"><?php echo $formatBytes($space['stats']['size']); ?></td>
                            <td><small class="text-muted"><?php echo htmlspecialchars($space['stats']['relative']); ?></small></td>
                            <td class="text-end">
                                <a class="btn btn-sm btn-outline-primary" href="<?php echo $radAdminUrl; ?>/dataexplorer/view/<?php echo rawurlencode($space['uid']); ?>">
                                    <i class="bi bi-folder2-open me-1"></i>Browse
                                </a>
                            </td>
                        </tr>
                    <?php } ?>
                    <?php if (empty($spaces)) { ?>
                        <tr><td colspan="6" class="text-center text-muted">No workspaces found.</td></tr>
                    <?php } ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php if ($selectedSpace) { ?>
    <div class="card mb-4 border-0 shadow-sm">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <div>
                    <div class="text-muted small text-uppercase mb-1">Currently Viewing</div>
                    <h5 class="mb-0"><?php echo htmlspecialchars($selectedSpace['name']); ?></h5>
                    <small class="text-muted">Bucket: <code><?php echo htmlspecialchars($selectedSpace['stats']['relative']); ?></code></small>
                </div>
                <a href="<?php echo $radAdminUrl; ?>/dataexplorer/view" class="btn btn-outline-secondary btn-sm">
                    <i class="bi bi-arrow-left me-1"></i>All workspaces
                </a>
            </div>
            <div class="row g-3 mb-3">
                <div class="col-sm-4">
                    <div class="border rounded px-3 py-2">
                        <div class="text-muted small">Files</div>
                        <div class="fs-4 fw-semibold"><?php echo number_format($selectedSpace['stats']['files']); ?></div>
                    </div>
                </div>
                <div class="col-sm-4">
                    <div class="border rounded px-3 py-2">
                        <div class="text-muted small">Storage Used</div>
                        <div class="fs-4 fw-semibold"><?php echo $formatBytes($selectedSpace['stats']['size']); ?></div>
                    </div>
                </div>
                <div class="col-sm-4">
                    <div class="border rounded px-3 py-2">
                        <div class="text-muted small">Physical Path</div>
                        <div class="text-break small"><?php echo htmlspecialchars($selectedSpace['stats']['path']); ?></div>
                    </div>
                </div>
            </div>
            <div class="table-responsive">
                <table class="table table-striped table-hover align-middle">
                    <thead class="text-muted small">
                        <tr>
                            <th>File</th>
                            <th>Relative path</th>
                            <th class="text-end">Size</th>
                            <th>Modified</th>
                            <th class="text-end">Download</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($workspaceFiles as $file) { ?>
                            <tr>
                                <td><?php echo htmlspecialchars($file['name']); ?></td>
                                <td><code><?php echo htmlspecialchars($file['subpath']); ?></code></td>
                                <td class="text-end"><?php echo $formatBytes($file['size']); ?></td>
                                <td><?php echo \Core\Sys\TimeHelper::formatUtc($file['modified'], $timezone, 'Y-m-d H:i'); ?></td>
                                <td class="text-end">
                                    <a class="btn btn-sm btn-outline-secondary" href="<?php echo $workspaceUrl($baseUrl, $file['relative']); ?>" target="_blank" rel="noopener">
                                        <i class="bi bi-download"></i>
                                    </a>
                                </td>
                            </tr>
                        <?php } ?>
                        <?php if (empty($workspaceFiles)) { ?>
                            <tr><td colspan="5" class="text-center text-muted">No files stored for this workspace yet.</td></tr>
                        <?php } ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
<?php } ?>

<?php if ($globalBucket) { ?>
<div class="card mb-4">
    <div class="card-body">
        <h5 class="card-title mb-3">Non-SaaS / Global Bucket</h5>
        <div class="row g-3">
            <div class="col-md-4">
                <div class="border rounded px-3 py-2">
                    <div class="text-muted small">Location</div>
                    <div><code><?php echo htmlspecialchars($globalBucket['relative']); ?></code></div>
                    <small class="text-muted"><?php echo htmlspecialchars($storageBase . '/' . $globalBucket['relative']); ?></small>
                </div>
            </div>
            <div class="col-md-4">
                <div class="border rounded px-3 py-2">
                    <div class="text-muted small">Type</div>
                    <div><?php echo $globalBucket['is_dir'] ? 'Folder' : 'File'; ?></div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="border rounded px-3 py-2">
                    <div class="text-muted small">Storage</div>
                    <div class="fs-4 fw-semibold"><?php echo $formatBytes($globalBucket['size']); ?></div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php } ?>

<div class="card mb-4">
    <div class="card-body">
        <h5 class="card-title mb-3">Legacy Upload Buckets</h5>
        <p class="text-muted small mb-3">Items below exist directly under <code><?php echo htmlspecialchars($storageBase); ?></code>. Consider moving them into workspace buckets.</p>
        <div class="table-responsive">
            <table class="table table-sm align-middle">
                <thead class="text-muted small">
                    <tr>
                        <th>Name</th>
                        <th>Type</th>
                        <th class="text-end">Size</th>
                        <th>Modified</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($legacyEntries as $entry) { ?>
                        <tr>
                            <td><?php echo htmlspecialchars($entry['name']); ?></td>
                            <td><?php echo $entry['is_dir'] ? 'Folder' : 'File'; ?></td>
                            <td class="text-end"><?php echo $formatBytes($entry['size']); ?></td>
                            <td><?php echo \Core\Sys\TimeHelper::formatUtc($entry['modified'], $timezone, 'Y-m-d H:i'); ?></td>
                        </tr>
                    <?php } ?>
                    <?php if (empty($legacyEntries)) { ?>
                        <tr><td colspan="4" class="text-center text-muted">No legacy items detected.</td></tr>
                    <?php } ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
