<?php
$fsFolders = $this->runData['data']['fs_folders'] ?? [];
$uncataloged = $this->runData['data']['uncataloged'] ?? [];
$catalogLookup = $this->runData['data']['catalog_lookup'] ?? [];
$vendorRoot = $this->runData['data']['vendor_root'] ?? '';
$radAdminUrl = $this->runData['route']['rad_admin_url'];
$counts = [
    'catalogued' => max(0, count($fsFolders) - count($uncataloged)),
    'uncataloged' => count($uncataloged),
];
?>
<div class="card border-0 shadow-sm mb-4">
    <div class="card-body d-flex flex-column flex-lg-row align-items-lg-center gap-3">
        <div>
            <div class="fw-semibold">Filesystem libraries</div>
            <div class="text-muted small">Detected under <code><?php echo htmlspecialchars($vendorRoot ?: 'rad/vendor'); ?></code></div>
        </div>
        <div class="ms-lg-auto d-flex flex-wrap gap-2">
            <span class="badge bg-success">Catalogued: <?php echo (int)$counts['catalogued']; ?></span>
            <span class="badge bg-warning text-dark">Uncatalogued: <?php echo (int)$counts['uncataloged']; ?></span>
            <a href="<?php echo $radAdminUrl; ?>/vendor/view" class="btn btn-outline-secondary btn-sm"><i class="bi bi-collection me-1"></i>View catalog</a>
        </div>
    </div>
</div>
<?php if (empty($fsFolders)) { ?>
    <div class="card border-0 shadow-sm">
        <div class="card-body text-muted">No folders found in <code>rad/vendor</code>.</div>
    </div>
<?php } else { ?>
    <div class="card border-0 shadow-sm">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table align-middle">
                    <thead class="small text-muted text-uppercase">
                        <tr>
                            <th scope="col">Folder</th>
                            <th scope="col">Suggested handle</th>
                            <th scope="col">Status</th>
                            <th scope="col">Last modified</th>
                            <th scope="col" class="text-end">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($fsFolders as $folder) {
                            $catalogId = $folder['catalog_id'] ?? null;
                            $catalogEntry = $catalogId && isset($catalogLookup[$catalogId]) ? $catalogLookup[$catalogId] : null;
                            $isCatalogued = (bool)$catalogEntry;
                            $statusClass = $isCatalogued ? 'success' : 'warning text-dark';
                            $statusLabel = $isCatalogued ? 'Catalogued' : 'Not catalogued';
                            $detailUrl = $isCatalogued ? $radAdminUrl . '/vendor/detail/' . $catalogEntry['uid'] : '';
                            $addUrl = $radAdminUrl . '/vendor/add?handle=' . urlencode($folder['suggested_handle'] ?? $folder['name']) . '&path=' . urlencode($folder['path']);
                        ?>
                        <tr>
                            <td class="text-monospace">
                                <?php echo htmlspecialchars($folder['name']); ?>
                                <div class="text-muted small"><?php echo htmlspecialchars($folder['path']); ?></div>
                            </td>
                            <td class="text-muted small text-monospace">
                                <?php echo htmlspecialchars($folder['suggested_handle'] ?? $folder['name']); ?>
                            </td>
                            <td>
                                <span class="badge bg-<?php echo $statusClass; ?>"><?php echo $statusLabel; ?></span>
                                <?php if ($isCatalogued) { ?>
                                    <div class="small text-muted"><?php echo htmlspecialchars($catalogEntry['s_title'] ?? $catalogEntry['s_handle']); ?></div>
                                <?php } ?>
                            </td>
                            <td class="text-muted small"><?php echo htmlspecialchars($folder['last_modified'] ?? ''); ?></td>
                            <td class="text-end">
                                <?php if ($isCatalogued) { ?>
                                    <a class="btn btn-sm btn-outline-primary" href="<?php echo $detailUrl; ?>"><i class="bi bi-box-arrow-up-right me-1"></i>View</a>
                                <?php } else { ?>
                                    <a class="btn btn-sm btn-outline-success" href="<?php echo $addUrl; ?>"><i class="bi bi-plus-circle me-1"></i>Add to catalog</a>
                                <?php } ?>
                            </td>
                        </tr>
                        <?php } ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
<?php } ?>
