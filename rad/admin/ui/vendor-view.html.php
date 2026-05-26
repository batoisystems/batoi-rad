<?php
$vendors = $this->runData['data']['vendors'] ?? [];
$stats = $this->runData['data']['vendor_stats'] ?? ['total' => 0, 'installed' => 0, 'missing' => 0, 'uncataloged' => 0];
$radAdminUrl = $this->runData['route']['rad_admin_url'];
$filters = $this->runData['data']['filters'] ?? ['q' => ''];
?>
<div class="card border-0 shadow-sm mb-4">
    <div class="card-body d-flex flex-column flex-lg-row align-items-lg-center gap-3">
        <div>
            <p class="text-muted mb-0">Browse, document, and install PHP SDKs and utilities placed under <code>rad/vendor</code>.</p>
        </div>
        <div class="ms-lg-auto d-flex gap-2 flex-wrap">
            <a href="<?php echo $radAdminUrl; ?>/vendor/add" class="btn btn-primary"><i class="bi bi-plus-circle me-1"></i>Add Library</a>
            <a href="<?php echo $radAdminUrl; ?>/vendor/filesystem" class="btn btn-outline-secondary"><i class="bi bi-hdd-stack me-1"></i>Filesystem view</a>
        </div>
    </div>
</div>
<div class="row g-3 mb-4">
    <div class="col-6 col-lg-4">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <div class="text-muted text-uppercase small">Catalogued</div>
                <div class="display-6 fw-semibold"><?php echo (int)$stats['total']; ?></div>
                <p class="text-muted small mb-0">Entries stored in <code>s_vendor</code></p>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-4">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <div class="text-muted text-uppercase small">Installed</div>
                <div class="display-6 fw-semibold text-success"><?php echo (int)$stats['installed']; ?></div>
                <p class="text-muted small mb-0">Filesystem folders detected</p>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-4">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <div class="text-muted text-uppercase small">Not cataloged</div>
                <div class="display-6 fw-semibold text-warning"><?php echo (int)$stats['uncataloged']; ?></div>
                <p class="text-muted small mb-0">Filesystem folders not cataloged</p>
            </div>
        </div>
    </div>
</div>
<div class="card border-0 shadow-sm">
    <div class="card-body">
        <div class="d-flex flex-column flex-lg-row align-items-lg-center gap-3 mb-3">
            <div class="btn-group btn-group-sm" role="group" data-vendor-filter>
                <button type="button" class="btn btn-outline-secondary active" data-status="all">All</button>
                <button type="button" class="btn btn-outline-secondary" data-status="installed">Installed</button>
                <button type="button" class="btn btn-outline-secondary" data-status="missing">Missing</button>
            </div>
            <div class="ms-lg-auto w-100 w-lg-auto">
                <div class="input-group input-group-sm">
                    <span class="input-group-text"><i class="bi bi-search"></i></span>
                    <input type="search" class="form-control" placeholder="Search libraries" data-vendor-search value="<?php echo htmlspecialchars($filters['q']); ?>">
                </div>
            </div>
        </div>
        <?php if (empty($vendors)) { ?>
            <div class="text-center py-5">
                <img src="<?php echo $this->runData['route']['rad_assets_url']; ?>/img/no-ms.svg" height="140" alt="No libraries">
                <h3 class="h5 mt-3">No libraries recorded yet</h3>
                <p class="text-muted">Add a library to get started.</p>
            </div>
        <?php } else { ?>
            <div class="table-responsive">
                <table class="table align-middle" data-vendor-table>
                    <thead class="small text-muted text-uppercase">
                        <tr>
                            <th scope="col">Library</th>
                            <th scope="col">Category</th>
                            <th scope="col">Version</th>
                            <th scope="col">Status</th>
                            <th scope="col" class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($vendors as $vendor) {
                            $fs = $vendor['filesystem'] ?? [];
                            $installed = !empty($fs['installed']);
                            $status = $installed ? 'installed' : 'missing';
                            $statusClass = $installed ? 'success' : 'warning';
                            $detailUrl = $radAdminUrl . '/vendor/detail/' . $vendor['uid'];
                            $docUrl = $vendor['s_doc_url'] ?? '';
                            $version = $installed ? ($fs['version'] ?? $vendor['s_version_installed'] ?? '—') : ($vendor['s_version_available'] ?: '—');
                        ?>
                        <tr data-status="<?php echo $status; ?>" data-search="<?php echo htmlspecialchars(strtolower(($vendor['s_title'] ?? '') . ' ' . ($vendor['s_summary'] ?? '') . ' ' . ($vendor['s_category'] ?? '')), ENT_QUOTES); ?>">
                            <td>
                                <div class="fw-semibold">
                                    <a href="<?php echo $detailUrl; ?>" class="text-decoration-none"><?php echo htmlspecialchars($vendor['s_title'] ?? $vendor['s_handle']); ?></a>
                                </div>
                                <div class="text-muted small text-truncate" style="max-width:320px;">
                                    <?php echo htmlspecialchars($vendor['s_summary'] ?? ''); ?>
                                </div>
                            </td>
                            <td class="text-muted small"><?php echo htmlspecialchars($vendor['s_category'] ?: '—'); ?></td>
                            <td class="text-muted small text-monospace"><?php echo htmlspecialchars($version); ?></td>
                            <td>
                                <span class="badge bg-<?php echo $statusClass; ?>"><?php echo ucfirst($status); ?></span>
                                <?php if ($installed && !empty($fs['size_human'])) { ?>
                                    <div class="text-muted small"><?php echo $fs['size_human']; ?></div>
                                <?php } ?>
                            </td>
                            <td class="text-end">
                                <div class="btn-group btn-group-sm" role="group">
                                    <a class="btn btn-outline-primary" href="<?php echo $detailUrl; ?>" title="View details">
                                        <i class="bi bi-eye"></i>
                                    </a>
                                    <?php if ($docUrl) { ?>
                                        <a class="btn btn-outline-secondary" href="<?php echo htmlspecialchars($docUrl); ?>" title="Docs" target="_blank">
                                            <i class="bi bi-journal-text"></i>
                                        </a>
                                    <?php } ?>
                                    <?php if (!$installed) { ?>
                                        <a class="btn btn-outline-success" href="<?php echo $detailUrl; ?>#install-library" title="Install">
                                            <i class="bi bi-cloud-arrow-down"></i>
                                        </a>
                                    <?php } ?>
                                </div>
                            </td>
                        </tr>
                        <?php } ?>
                    </tbody>
                </table>
            </div>
        <?php } ?>
    </div>
</div>
<script>
(function(){
    const table = document.querySelector('[data-vendor-table]');
    const searchInput = document.querySelector('[data-vendor-search]');
    const filterGroup = document.querySelector('[data-vendor-filter]');
    const rows = table ? Array.from(table.querySelectorAll('tbody tr')) : [];

    const applyFilters = () => {
        if (!table) { return; }
        const query = (searchInput?.value || '').trim().toLowerCase();
        const activeBtn = filterGroup?.querySelector('.active');
        const status = activeBtn ? activeBtn.getAttribute('data-status') : 'all';
        rows.forEach(row => {
            const haystack = row.getAttribute('data-search') || '';
            const rowStatus = row.getAttribute('data-status');
            const matchesQuery = !query || haystack.includes(query);
            const matchesStatus = status === 'all' || rowStatus === status;
            row.classList.toggle('d-none', !(matchesQuery && matchesStatus));
        });
    };

    searchInput?.addEventListener('input', applyFilters);
    filterGroup?.addEventListener('click', (event) => {
        const target = event.target.closest('[data-status]');
        if (!target) { return; }
        event.preventDefault();
        filterGroup.querySelectorAll('[data-status]').forEach(btn => btn.classList.remove('active'));
        target.classList.add('active');
        applyFilters();
    });

    applyFilters();

})();
</script>
