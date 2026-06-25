<?php
$origin = $this->runData['data']['config_origin'] ?? 'A';
$configParams = $this->runData['data']['configParams'] ?? [];
$stats = $this->runData['data']['configStats'] ?? ['total' => 0, 'active' => 0, 'archived' => 0, 'inactive' => 0];
$radAdminUrl = $this->runData['route']['rad_admin_url'];
$historyBase = $radAdminUrl . '/config/history';
$navLinks = [
    'A' => [
        'label' => 'Application Parameters',
        'url' => $radAdminUrl . '/config/view'
    ],
    'S' => [
        'label' => 'System Parameters',
        'url' => $radAdminUrl . '/config/view/S'
    ],
    'D' => [
        'label' => 'Directory Parameters',
        'url' => $radAdminUrl . '/config/view/D'
    ]
];
$meta = [
    'A' => [
        'title' => 'Application Parameters',
        'subtitle' => 'Values scoped to this deployment. Add feature flags, API keys, or custom toggles as needed.',
        'badge' => 'Application',
        'cta' => 'Add Application Parameter'
    ],
    'S' => [
        'title' => 'System Parameters',
        'subtitle' => 'Core runtime controls. Update with care so global behavior stays predictable.',
        'badge' => 'System',
        'cta' => null
    ],
    'D' => [
        'title' => 'Directory Parameters',
        'subtitle' => 'Resolved server paths that help templates locate uploads, sessions, and other directories.',
        'badge' => 'Directory',
        'cta' => null
    ]
];
$activeMeta = $meta[$origin] ?? $meta['A'];
$canAdd = ($origin === 'A');
$paramBucket = $origin === 'A' ? 'app' : ($origin === 'S' ? 'sys' : 'dir');
$statusMap = [
    '0' => ['label' => 'Inactive', 'class' => 'secondary'],
    '1' => ['label' => 'Active', 'class' => 'success'],
    '2' => ['label' => 'Archived', 'class' => 'warning'],
    '3' => ['label' => 'Suspended', 'class' => 'danger']
];
?>
<div class="card border-0 shadow-sm mb-4 config-hero">
    <div class="card-body p-4">
        <div class="d-flex flex-column flex-md-row align-items-start align-items-md-center justify-content-between gap-3">
            <div>
                <div class="text-uppercase text-muted small fw-semibold mb-1"><?php echo htmlspecialchars($activeMeta['badge']); ?></div>
                <h1 class="h4 mb-2"><?php echo htmlspecialchars($activeMeta['title']); ?></h1>
                <p class="mb-0 text-muted"><?php echo htmlspecialchars($activeMeta['subtitle']); ?></p>
            </div>
            <div class="d-flex flex-wrap gap-2">
                <?php if ($canAdd) { ?>
                    <a href="<?php echo $radAdminUrl; ?>/config/add" class="btn btn-primary"><i class="bi bi-plus-circle me-1"></i><?php echo htmlspecialchars($activeMeta['cta']); ?></a>
                <?php } ?>
            </div>
        </div>
    </div>
</div>
<div class="d-flex flex-wrap gap-2 mb-4">
    <?php foreach ($navLinks as $code => $link) { ?>
        <a class="btn btn-sm <?php echo $origin === $code ? 'btn-primary' : 'btn-outline-secondary'; ?>" href="<?php echo $link['url']; ?>"><?php echo htmlspecialchars($link['label']); ?></a>
    <?php } ?>
</div>
<div class="row g-3 mb-4">
    <div class="col-12 col-md-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <div class="text-muted text-uppercase small">Total</div>
                <div class="fs-3 fw-bold"><?php echo (int)($stats['total'] ?? 0); ?></div>
                <p class="mb-0 text-muted small">Parameters in this group</p>
            </div>
        </div>
    </div>
    <div class="col-12 col-md-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <div class="text-muted text-uppercase small">Active</div>
                <div class="fs-3 fw-bold"><?php echo (int)($stats['active'] ?? 0); ?></div>
                <p class="mb-0 text-muted small">Available to the runtime</p>
            </div>
        </div>
    </div>
    <?php if ($origin !== 'D') { ?>
        <div class="col-12 col-md-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="text-muted text-uppercase small">Archived</div>
                    <div class="fs-3 fw-bold"><?php echo (int)($stats['archived'] ?? 0); ?></div>
                    <p class="mb-0 text-muted small">Hidden but preserved</p>
                </div>
            </div>
        </div>
        <div class="col-12 col-md-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="text-muted text-uppercase small">Updated</div>
                    <div class="fs-6 fw-bold"><?php echo htmlspecialchars($stats['last_updated'] ?? 'Not available'); ?></div>
                    <p class="mb-0 text-muted small">Most recent change</p>
                </div>
            </div>
        </div>
    <?php } else { ?>
        <div class="col-12 col-md-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="text-muted text-uppercase small">Tracked Paths</div>
                    <div class="fs-3 fw-bold"><?php echo (int)($stats['total'] ?? 0); ?></div>
                    <p class="mb-0 text-muted small">Server directories referenced by RAD</p>
                </div>
            </div>
        </div>
    <?php } ?>
</div>
<div class="card border-0 shadow-sm">
    <div class="card-body">
        <div class="d-flex flex-column flex-lg-row align-items-lg-center gap-3 mb-3">
            <?php if ($origin !== 'D') { ?>
                <div class="btn-group" role="group" aria-label="Status filters" data-config-status-filter>
                    <button type="button" class="btn btn-outline-secondary btn-sm active" data-status-filter="all">All</button>
                    <button type="button" class="btn btn-outline-secondary btn-sm" data-status-filter="1">Active</button>
                    <button type="button" class="btn btn-outline-secondary btn-sm" data-status-filter="0">Inactive</button>
                    <button type="button" class="btn btn-outline-secondary btn-sm" data-status-filter="2">Archived</button>
                </div>
            <?php } ?>
            <div class="ms-lg-auto w-100 w-lg-auto">
                <div class="input-group input-group-sm">
                    <span class="input-group-text"><i class="bi bi-search"></i></span>
                    <input type="search" class="form-control" placeholder="Search parameters" data-config-search>
                </div>
            </div>
        </div>
        <?php if (count($configParams) > 0) { ?>
            <div class="table-responsive">
                <table class="table align-middle mb-0 config-table" data-config-table>
                    <thead class="text-muted small text-uppercase">
                        <tr>
                            <th class="text-nowrap">Parameter</th>
                            <th>Value</th>
                            <th class="text-nowrap"><?php echo $origin === 'D' ? 'Directory' : 'Status'; ?></th>
                            <th><?php echo $origin === 'D' ? 'Description' : 'Details'; ?></th>
                            <?php if ($origin !== 'D') { ?><th class="text-end">Actions</th><?php } ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($configParams as $configParam) {
                            $handle = $configParam['s_config_handle'] ?? '';
                            $value = $configParam['s_config_value'] ?? '';
                            $description = $configParam['s_description'] ?? '';
                            $status = (string)($configParam['livestatus'] ?? '1');
                            $statusLabel = $statusMap[$status]['label'] ?? 'Unknown';
                            $statusClass = $statusMap[$status]['class'] ?? 'secondary';
                            $updatedStamp = $configParam['updatestamp'] ?? $configParam['createstamp'] ?? '';
                            $updatedHuman = $updatedStamp ? (new \DateTime($updatedStamp))->format('M d, Y H:i') : 'Not available';
                            $searchTokens = strtolower($handle . ' ' . $value . ' ' . $description);
                            $copyReference = sprintf('$this->runData[\'config\'][\'%s\'][\'%s\']', $paramBucket, $handle);
                        ?>
                        <tr data-status="<?php echo htmlspecialchars($status); ?>" data-search="<?php echo htmlspecialchars($searchTokens, ENT_QUOTES); ?>">
                            <td>
                                <div class="fw-semibold text-nowrap"><?php echo htmlspecialchars($handle); ?></div>
                                <?php if ($origin !== 'D') { ?>
                                    <div class="text-muted small">UID: <?php echo htmlspecialchars($configParam['uid'] ?? '—'); ?></div>
                                <?php } ?>
                            </td>
                            <td class="text-monospace small">
                                <span class="d-inline-block text-truncate" style="max-width: 320px;" title="<?php echo htmlspecialchars($value); ?>"><?php echo htmlspecialchars($value); ?></span>
                            </td>
                            <td>
                                <?php if ($origin === 'D') { ?>
                                    <span class="badge bg-info text-dark">Path</span>
                                <?php } else { ?>
                                    <span class="badge bg-<?php echo $statusClass; ?>"><?php echo htmlspecialchars($statusLabel); ?></span>
                                <?php } ?>
                            </td>
                            <td class="small">
                                <?php if ($origin === 'D') { ?>
                                    <?php echo htmlspecialchars($description); ?>
                                <?php } else { ?>
                                    <div class="text-muted">Last updated <?php echo htmlspecialchars($updatedHuman); ?></div>
                                    <?php if (!empty($description)) { ?><div class="text-muted">Notes: <?php echo htmlspecialchars($description); ?></div><?php } ?>
                                <?php } ?>
                            </td>
                            <?php if ($origin !== 'D') { ?>
                                <td class="text-end">
                                    <div class="btn-group btn-group-sm" role="group">
                                        <button type="button" class="btn btn-outline-secondary" title="Copy reference" data-copy="<?php echo htmlspecialchars($copyReference, ENT_QUOTES); ?>">
                                            <i class="bi bi-clipboard"></i>
                                        </button>
                                        <a href="<?php echo $radAdminUrl; ?>/config/edit/<?php echo $configParam['uid']; ?>" class="btn btn-outline-primary" title="Edit parameter">
                                            <i class="bi bi-pencil-square"></i>
                                        </a>
                                        <button type="button" class="btn btn-outline-secondary" title="View history" data-history-trigger data-history-url="<?php echo $historyBase . '/' . $configParam['uid']; ?>">
                                            <i class="bi bi-clock-history"></i>
                                        </button>
                                        <?php if ($origin === 'A') { ?>
                                            <?php if ($status === '2') { ?>
                                                <a href="<?php echo $radAdminUrl; ?>/config/activate/<?php echo $configParam['uid']; ?>" class="btn btn-outline-success" title="Activate">
                                                    <i class="bi bi-arrow-counterclockwise"></i>
                                                </a>
                                            <?php } else { ?>
                                                <a href="<?php echo $radAdminUrl; ?>/config/archive/<?php echo $configParam['uid']; ?>" class="btn btn-outline-danger" title="Archive">
                                                    <i class="bi bi-archive"></i>
                                                </a>
                                            <?php } ?>
                                        <?php } ?>
                                    </div>
                                </td>
                            <?php } ?>
                        </tr>
                        <?php } ?>
                    </tbody>
                </table>
            </div>
        <?php } ?>
        <?php $emptyClass = count($configParams) === 0 ? '' : 'd-none'; ?>
        <div class="text-center py-5 <?php echo $emptyClass; ?>" data-empty-state>
            <img src="<?php echo $this->runData['route']['rad_assets_url']; ?>/img/no-ms.svg" alt="No parameters" height="140" class="mb-3">
            <h2 class="h5 mb-2">No parameters to display</h2>
            <p class="text-muted mb-3">Use the search and filters to narrow down results, or add a new parameter.</p>
            <?php if ($canAdd) { ?><a href="<?php echo $radAdminUrl; ?>/config/add" class="btn btn-primary"><i class="bi bi-plus-circle me-1"></i><?php echo htmlspecialchars($activeMeta['cta']); ?></a><?php } ?>
        </div>
    </div>
</div>
<div class="modal fade" id="configHistoryModal" tabindex="-1" aria-hidden="true" data-history-base="<?php echo $historyBase; ?>">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Parameter History</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="text-center text-muted py-5" data-history-placeholder>Pick a parameter to load history.</div>
                <div class="d-none" data-history-content>
                    <div class="list-group" data-history-list></div>
                </div>
            </div>
        </div>
    </div>
</div>
<script>
(function(){
    const table = document.querySelector('[data-config-table]');
    const searchInput = document.querySelector('[data-config-search]');
    const statusGroup = document.querySelector('[data-config-status-filter]');
    const emptyState = document.querySelector('[data-empty-state]');

    const applyFilters = () => {
        if (!table) { return; }
        const rows = table.querySelectorAll('tbody tr');
        const query = searchInput ? searchInput.value.trim().toLowerCase() : '';
        const statusBtn = statusGroup ? statusGroup.querySelector('.active') : null;
        const status = statusBtn ? statusBtn.getAttribute('data-status-filter') : 'all';
        let visible = 0;
        rows.forEach(row => {
            const haystack = row.getAttribute('data-search') || '';
            const rowStatus = row.getAttribute('data-status');
            const matchesQuery = !query || haystack.includes(query);
            const matchesStatus = status === 'all' || rowStatus === status;
            const shouldShow = matchesQuery && matchesStatus;
            row.classList.toggle('d-none', !shouldShow);
            if (shouldShow) { visible++; }
        });
        if (emptyState) {
            emptyState.classList.toggle('d-none', visible > 0);
        }
    };

    if (searchInput) {
        searchInput.addEventListener('input', applyFilters);
    }
    if (statusGroup) {
        statusGroup.addEventListener('click', (event) => {
            const target = event.target.closest('[data-status-filter]');
            if (!target) { return; }
            event.preventDefault();
            statusGroup.querySelectorAll('[data-status-filter]').forEach(btn => btn.classList.remove('active'));
            target.classList.add('active');
            applyFilters();
        });
    }

    const copyButtons = document.querySelectorAll('[data-copy]');
    copyButtons.forEach(btn => {
        btn.addEventListener('click', async () => {
            const value = btn.getAttribute('data-copy');
            if (!value) { return; }
            try {
                await navigator.clipboard.writeText(value);
                btn.classList.add('text-success');
                setTimeout(() => btn.classList.remove('text-success'), 1200);
            } catch (error) {
                alert('Unable to copy to clipboard.');
            }
        });
    });

    const historyModalEl = document.getElementById('configHistoryModal');
    const historyModal = historyModalEl && window.RadAdminUI ? window.RadAdminUI.getModal(historyModalEl) : null;
    const historyList = historyModalEl ? historyModalEl.querySelector('[data-history-list]') : null;
    const historyPlaceholder = historyModalEl ? historyModalEl.querySelector('[data-history-placeholder]') : null;
    const historyContent = historyModalEl ? historyModalEl.querySelector('[data-history-content]') : null;

    const renderHistory = (entries) => {
        if (!historyList || !historyPlaceholder || !historyContent) { return; }
        historyList.innerHTML = '';
        if (!entries || entries.length === 0) {
            historyPlaceholder.textContent = 'No history captured yet for this parameter.';
            historyPlaceholder.classList.remove('d-none');
            historyContent.classList.add('d-none');
            return;
        }
        historyPlaceholder.classList.add('d-none');
        historyContent.classList.remove('d-none');
        entries.forEach(entry => {
            const item = document.createElement('div');
            item.className = 'list-group-item';
            const meta = document.createElement('div');
            meta.className = 'small fw-semibold mb-1';
            meta.textContent = `${entry.s_modified_human || entry.s_modified_timestamp || ''} · ${entry.modifier_label || 'System'}`;
            const snapshot = document.createElement('pre');
            snapshot.className = 'bg-body-tertiary rounded p-3 small text-break';
            snapshot.textContent = entry.snapshot || 'No snapshot body available.';
            item.appendChild(meta);
            item.appendChild(snapshot);
            historyList.appendChild(item);
        });
    };

    document.querySelectorAll('[data-history-trigger]').forEach(button => {
        button.addEventListener('click', async () => {
            const url = button.getAttribute('data-history-url');
            if (!url || !historyModal) { return; }
            historyPlaceholder.textContent = 'Loading history…';
            historyPlaceholder.classList.remove('d-none');
            historyContent.classList.add('d-none');
            historyModal.show();
            try {
                const response = await fetch(url, { headers: { 'Accept': 'application/json' } });
                if (!response.ok) {
                    throw new Error('Unable to fetch history');
                }
                const payload = await response.json();
                renderHistory(payload.entries || []);
            } catch (error) {
                historyPlaceholder.textContent = 'Unable to load history. Please try again.';
                historyContent.classList.add('d-none');
            }
        });
    });

    applyFilters();
})();
</script>
