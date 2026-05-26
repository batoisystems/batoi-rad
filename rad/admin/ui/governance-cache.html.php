<?php
$summary = $this->runData['data']['cache_summary'] ?? [];
$services = $summary['services'] ?? [];
$radAdminUrl = $this->runData['route']['rad_admin_url'] ?? '';
$enabled = !empty($summary['enabled']);
$baseDir = $summary['base_dir'] ?? '';
$totalEntries = (int)($summary['total_entries'] ?? 0);
$totalSize = $summary['total_size_label'] ?? '0 B';
?>

<div class="card border-0 shadow-sm mb-4">
    <div class="card-body d-flex flex-wrap align-items-center justify-content-between gap-3">
        <div>
            <h2 class="h5 mb-1">Cache Overview</h2>
            <p class="text-muted mb-0">File cache for route/content payloads.</p>
        </div>
        <div class="text-end">
            <div class="text-muted small">Base directory</div>
            <div class="fw-semibold"><?php echo htmlspecialchars($baseDir ?: 'Not configured'); ?></div>
        </div>
    </div>
</div>

<div class="row g-4 mb-4">
    <div class="col-lg-4">
        <div class="card shadow-sm h-100">
            <div class="card-body">
                <div class="text-muted small">Status</div>
                <div class="h5 mb-3"><?php echo $enabled ? 'Enabled' : 'Disabled'; ?></div>
                <div class="text-muted small">Entries</div>
                <div class="h4 mb-3"><?php echo $totalEntries; ?></div>
                <div class="text-muted small">Total size</div>
                <div class="h4 mb-0"><?php echo htmlspecialchars($totalSize); ?></div>
            </div>
        </div>
    </div>
    <div class="col-lg-8">
        <div class="card shadow-sm h-100">
            <div class="card-body">
                <h3 class="h6">Purge Cache</h3>
                <p class="text-muted small">Clear cached route/content payloads when you want to force a rebuild.</p>
                <form method="post" action="<?php echo $radAdminUrl; ?>/governance/cache">
                    <input type="hidden" name="action" value="purge_all">
                    <button type="submit" class="btn btn-outline-danger">
                        <i class="bi bi-trash3 me-1"></i>Mass Purge
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<div class="card shadow-sm">
    <div class="card-header bg-white">
        <h3 class="h6 mb-0">Cached Microservicelets</h3>
    </div>
    <div class="card-body">
        <?php if (empty($services)): ?>
            <div class="text-muted">No cache entries found.</div>
        <?php else: ?>
            <div class="accordion" id="cacheServices">
                <?php foreach ($services as $index => $service): ?>
                    <?php
                        $serviceId = 'cacheService' . $index;
                        $msName = $service['ms_name'] ?? 'unknown';
                        $entries = (int)($service['entries'] ?? 0);
                        $sizeLabel = $service['size_label'] ?? '0 B';
                        $types = $service['types'] ?? [];
                    ?>
                    <div class="accordion-item">
                        <h2 class="accordion-header" id="<?php echo $serviceId; ?>Heading">
                            <button class="accordion-button <?php echo $index === 0 ? '' : 'collapsed'; ?>" type="button" data-bs-toggle="collapse" data-bs-target="#<?php echo $serviceId; ?>" aria-expanded="<?php echo $index === 0 ? 'true' : 'false'; ?>" aria-controls="<?php echo $serviceId; ?>">
                                <span class="fw-semibold me-2"><?php echo htmlspecialchars($msName); ?></span>
                                <span class="badge text-bg-light me-2"><?php echo $entries; ?> entries</span>
                                <span class="badge text-bg-light"><?php echo htmlspecialchars($sizeLabel); ?></span>
                            </button>
                        </h2>
                        <div id="<?php echo $serviceId; ?>" class="accordion-collapse collapse <?php echo $index === 0 ? 'show' : ''; ?>" aria-labelledby="<?php echo $serviceId; ?>Heading" data-bs-parent="#cacheServices">
                            <div class="accordion-body">
                                <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
                                    <div class="text-muted small">Microservicelet cache entries by type.</div>
                                    <form method="post" action="<?php echo $radAdminUrl; ?>/governance/cache">
                                        <input type="hidden" name="action" value="purge_ms">
                                        <input type="hidden" name="ms_name" value="<?php echo htmlspecialchars($msName); ?>">
                                        <button type="submit" class="btn btn-outline-danger btn-sm">Purge <?php echo htmlspecialchars($msName); ?></button>
                                    </form>
                                </div>

                                <?php if (empty($types)): ?>
                                    <div class="text-muted">No cache types recorded.</div>
                                <?php else: ?>
                                    <?php foreach ($types as $typeEntry): ?>
                                        <?php
                                            $typeName = $typeEntry['type'] ?? '';
                                            $typeCount = (int)($typeEntry['count'] ?? 0);
                                            $typeSize = $typeEntry['size_label'] ?? '0 B';
                                            $items = $typeEntry['items'] ?? [];
                                        ?>
                                        <div class="border rounded p-3 mb-3">
                                            <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-2">
                                                <div>
                                                    <span class="fw-semibold"><?php echo htmlspecialchars($typeName); ?></span>
                                                    <span class="text-muted small ms-2"><?php echo $typeCount; ?> variants</span>
                                                    <span class="text-muted small ms-2"><?php echo htmlspecialchars($typeSize); ?></span>
                                                </div>
                                                <form method="post" action="<?php echo $radAdminUrl; ?>/governance/cache">
                                                    <input type="hidden" name="action" value="purge_type">
                                                    <input type="hidden" name="ms_name" value="<?php echo htmlspecialchars($msName); ?>">
                                                    <input type="hidden" name="cache_type" value="<?php echo htmlspecialchars($typeName); ?>">
                                                    <button type="submit" class="btn btn-outline-danger btn-sm">Purge type</button>
                                                </form>
                                            </div>

                                            <?php if (!empty($items)): ?>
                                                <div class="table-responsive">
                                                    <table class="table table-sm align-middle mb-0">
                                                        <thead>
                                                            <tr>
                                                                <th>Item ID</th>
                                                                <th>Variants</th>
                                                                <th>Size</th>
                                                                <th>Actions</th>
                                                            </tr>
                                                        </thead>
                                                        <tbody>
                                                            <?php foreach ($items as $item): ?>
                                                                <tr>
                                                                    <td><?php echo htmlspecialchars($item['id'] ?? ''); ?></td>
                                                                    <td><?php echo (int)($item['variants'] ?? 0); ?></td>
                                                                    <td><?php echo htmlspecialchars($item['size_label'] ?? '0 B'); ?></td>
                                                                    <td>
                                                                        <form method="post" action="<?php echo $radAdminUrl; ?>/governance/cache" class="d-inline">
                                                                            <input type="hidden" name="action" value="purge_item">
                                                                            <input type="hidden" name="ms_name" value="<?php echo htmlspecialchars($msName); ?>">
                                                                            <input type="hidden" name="cache_type" value="<?php echo htmlspecialchars($typeName); ?>">
                                                                            <input type="hidden" name="cache_id" value="<?php echo htmlspecialchars($item['id'] ?? ''); ?>">
                                                                            <button type="submit" class="btn btn-outline-danger btn-sm">Purge</button>
                                                                        </form>
                                                                    </td>
                                                                </tr>
                                                            <?php endforeach; ?>
                                                        </tbody>
                                                    </table>
                                                </div>
                                            <?php else: ?>
                                                <div class="text-muted small">No cached items for this type.</div>
                                            <?php endif; ?>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>
