<?php
$snapshots = $this->runData['data']['snapshots'] ?? [];
$channels = $this->runData['data']['channels'] ?? [];
$types = $this->runData['data']['types'] ?? [];
$filters = $this->runData['data']['filters'] ?? ['channel' => '', 'search' => '', 'type' => '', 'page' => 1, 'pages' => 1];
$radAdminUrl = $this->runData['route']['rad_admin_url'];
$timezone = $this->runData['entity']['timezone'] ?? ($this->runData['config']['sys']['timezone_default'] ?? 'UTC');
?>
<div class="card mb-4">
    <div class="card-body">
        <form class="row g-3">
            <div class="col-md-3">
                <label class="form-label">Channel</label>
                <select name="channel" class="form-select">
                    <option value="">All Channels</option>
                    <?php foreach ($channels as $channel) { ?>
                        <option value="<?php echo htmlspecialchars($channel); ?>" <?php echo $filters['channel'] === $channel ? 'selected' : ''; ?>>
                            <?php echo ucfirst($channel); ?>
                        </option>
                    <?php } ?>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label">Search Item</label>
                <input type="text" name="q" class="form-control" value="<?php echo htmlspecialchars($filters['search']); ?>" placeholder="Template ID, Route, etc.">
            </div>
            <div class="col-md-3">
                <label class="form-label">Type</label>
                <select name="type" class="form-select">
                    <option value="">All Types</option>
                    <?php foreach ($types as $typeLabel) { ?>
                        <option value="<?php echo htmlspecialchars($typeLabel); ?>" <?php echo $filters['type'] === $typeLabel ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($typeLabel); ?>
                        </option>
                    <?php } ?>
                </select>
            </div>
            <div class="col-md-2 d-flex align-items-end">
                <button type="submit" class="btn btn-primary w-100"><i class="bi bi-search me-1"></i>Filter</button>
            </div>
        </form>
    </div>
<div class="card">
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h6 class="card-title mb-0">Snapshots</h6>
            <div class="text-muted small"><?php echo count($snapshots); ?> entries</div>
        </div>
        <?php if (empty($snapshots)) { ?>
            <div class="alert alert-info mb-0">No snapshots matched your filters.</div>
        <?php } else { ?>
            <form method="post" action="<?php echo $radAdminUrl; ?>/version/bulk">
                <div class="row mb-3">
                    <div class="col-md-4">
                        <select name="bulk_action" class="form-select form-select-sm">
                            <option value="">Bulk Action</option>
                            <option value="purge">Purge Selected</option>
                            <option value="restore">Restore Selected</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <button type="submit" class="btn btn-sm btn-secondary w-100">Apply</button>
                    </div>
                </div>
                <div class="table-responsive">
                <table class="table table-sm align-middle">
                    <thead>
                        <tr>
                            <th><input type="checkbox" id="version-select-all"></th>
                            <th>Channel</th>
                            <th>Item</th>
                            <th>Type</th>
                            <th>Version</th>
                            <th>Timestamp</th>
                            <th>User</th>
                            <th>Size</th>
                            <th>Note</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($snapshots as $snapshot) { ?>
                            <tr>
                                <td>
                                    <input type="checkbox" name="snapshots[]" value="<?php echo htmlspecialchars(($snapshot['channel'] ?? '') . '|' . ($snapshot['item'] ?? '') . '|' . ($snapshot['id'] ?? '')); ?>">
                                </td>
                                <td><?php echo htmlspecialchars(ucfirst($snapshot['channel'] ?? '')); ?></td>
                                <td><code><?php echo htmlspecialchars($snapshot['item'] ?? ''); ?></code></td>
                                <td><?php echo htmlspecialchars($snapshot['type'] ?? ''); ?></td>
                                <td class="fw-semibold"><?php echo htmlspecialchars($snapshot['id'] ?? ''); ?></td>
                                <td><?php echo isset($snapshot['timestamp']) ? \Core\Sys\TimeHelper::formatUtc($snapshot['timestamp'], $timezone) : '—'; ?></td>
                                <td><?php echo htmlspecialchars($snapshot['user'] ?? 'RAD Admin'); ?></td>
                                <td><?php echo htmlspecialchars($snapshot['size_human'] ?? '—'); ?></td>
                                <td><?php echo htmlspecialchars($snapshot['note'] ?? ''); ?></td>
                                <td class="text-end">
                                    <?php if (($snapshot['channel'] ?? '') === 'template') { ?>
                                        <div class="btn-group btn-group-sm">
                                            <a href="<?php echo $radAdminUrl; ?>/template/diffversion/<?php echo urlencode($snapshot['item']); ?>/<?php echo urlencode($snapshot['id']); ?>" class="btn btn-outline-info">
                                                <i class="bi bi-arrow-left-right"></i>
                                            </a>
                                            <a href="<?php echo $radAdminUrl; ?>/template/downloadversion/<?php echo urlencode($snapshot['item']); ?>/<?php echo urlencode($snapshot['id']); ?>" class="btn btn-outline-primary">
                                                <i class="bi bi-download"></i>
                                            </a>
                                        </div>
                                    <?php } ?>
                                </td>
                            </tr>
                        <?php } ?>
                    </tbody>
                </table>
                </div>
            </form>
        <?php } ?>
        <nav class="mt-3">
            <ul class="pagination pagination-sm mb-0">
                <?php
                $page = $filters['page'] ?? 1;
                $pages = $filters['pages'] ?? 1;
                $queryBase = http_build_query([
                    'channel' => $filters['channel'] ?? '',
                    'q' => $filters['search'] ?? '',
                    'type' => $filters['type'] ?? '',
                ]);
                ?>
                <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                    <a class="page-link" href="<?php echo $radAdminUrl; ?>/version/view?<?php echo $queryBase; ?>&page=<?php echo max(1, $page - 1); ?>" aria-label="Previous">
                        <span aria-hidden="true">&laquo;</span>
                    </a>
                </li>
                <?php for ($p = 1; $p <= $pages; $p++) { ?>
                    <li class="page-item <?php echo $p == $page ? 'active' : ''; ?>">
                        <a class="page-link" href="<?php echo $radAdminUrl; ?>/version/view?<?php echo $queryBase; ?>&page=<?php echo $p; ?>"><?php echo $p; ?></a>
                    </li>
                <?php } ?>
                <li class="page-item <?php echo $page >= $pages ? 'disabled' : ''; ?>">
                    <a class="page-link" href="<?php echo $radAdminUrl; ?>/version/view?<?php echo $queryBase; ?>&page=<?php echo min($pages, $page + 1); ?>" aria-label="Next">
                        <span aria-hidden="true">&raquo;</span>
                    </a>
                </li>
            </ul>
        </nav>
    </div>
</div>
<script>
(function(){
    var master = document.getElementById('version-select-all');
    if (!master) return;
    master.addEventListener('change', function() {
        var checkboxes = document.querySelectorAll('input[name="snapshots[]"]');
        checkboxes.forEach(function(cb) {
            cb.checked = master.checked;
        });
    });
})();
</script>
