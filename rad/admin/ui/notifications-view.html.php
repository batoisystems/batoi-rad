<?php
$data = $this->runData['data'] ?? [];
$filters = $data['filters'] ?? ['scope' => 'inbox', 'space_id' => 0, 'q' => '', 'severity' => '', 'event_type' => ''];
$scopeOptions = $data['scope_options'] ?? [];
$spaceOptions = $data['space_options'] ?? [];
$notifications = $data['notifications'] ?? [];
$metrics = $data['metrics'] ?? ['total' => 0, 'unread' => 0];
$isSuperAdmin = $data['is_super_admin'] ?? false;
$page = (int)($data['page'] ?? 1);
$pages = (int)($data['pages'] ?? 1);
$perPage = (int)($data['per_page'] ?? 25);
$total = (int)($data['total'] ?? 0);

if (!function_exists('rad_notifications_space_label')) {
    function rad_notifications_space_label(array $spaceOptions, array $row): string {
        $spaceId = (int)($row['space_id'] ?? 0);
        if ($spaceId === 0) {
            return 'Global';
        }
        return $spaceOptions[$spaceId] ?? ('Workspace #' . $spaceId);
    }
}
?>
<div class="card mb-3">
    <div class="card-body">
        <div class="d-flex flex-wrap gap-2 align-items-center justify-content-between">
            <div class="d-flex flex-wrap gap-2 align-items-center">
            <?php foreach ($scopeOptions as $value => $label) { ?>
                <?php
                $isActive = $filters['scope'] === $value;
                $url = $this->runData['route']['rad_admin_url'] . '/notifications/view?' . http_build_query([
                    'scope' => $value,
                    'space_id' => $filters['space_id'],
                    'q' => $filters['q'],
                    'severity' => $filters['severity'],
                    'event_type' => $filters['event_type'],
                ]);
                ?>
                <a href="<?php echo htmlspecialchars($url); ?>" class="btn btn-sm <?php echo $isActive ? 'btn-primary' : 'btn-outline-secondary'; ?>">
                    <?php echo htmlspecialchars($label); ?>
                </a>
            <?php } ?>
            </div>
            <?php if (!empty($isSuperAdmin)) { ?>
                <a class="btn btn-outline-primary btn-sm" href="<?php echo $this->runData['route']['rad_admin_url']; ?>/notifications/settings">Settings</a>
            <?php } ?>
        </div>
        <form class="row g-3 mt-3" method="get">
            <input type="hidden" name="scope" value="<?php echo htmlspecialchars($filters['scope']); ?>">
            <div class="col-md-4">
                <label class="form-label small text-muted">Search</label>
                <input type="search" name="q" class="form-control form-control-sm" placeholder="Search message or metadata" value="<?php echo htmlspecialchars($filters['q']); ?>">
            </div>
            <div class="col-md-4">
                <label class="form-label small text-muted">Workspace</label>
                <select name="space_id" class="form-select form-select-sm">
                    <option value="0">All Workspaces</option>
                    <?php foreach ($spaceOptions as $spaceId => $name) { ?>
                        <option value="<?php echo $spaceId; ?>" <?php echo ($filters['space_id'] == $spaceId) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($name); ?>
                        </option>
                    <?php } ?>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label small text-muted">Event type</label>
                <input type="text" name="event_type" class="form-control form-control-sm" placeholder="e.g. membership_role_change" value="<?php echo htmlspecialchars($filters['event_type']); ?>">
            </div>
            <div class="col-md-4">
                <label class="form-label small text-muted">Severity</label>
                <select name="severity" class="form-select form-select-sm">
                    <option value="">All severities</option>
                    <option value="info" <?php echo $filters['severity'] === 'info' ? 'selected' : ''; ?>>Info</option>
                    <option value="warn" <?php echo $filters['severity'] === 'warn' ? 'selected' : ''; ?>>Warn</option>
                    <option value="critical" <?php echo $filters['severity'] === 'critical' ? 'selected' : ''; ?>>Critical</option>
                </select>
            </div>
            <div class="col-md-4 d-flex align-items-end">
                <button type="submit" class="btn btn-primary btn-sm me-2">Apply</button>
                <?php if ($filters['q'] !== '' || $filters['space_id'] !== 0 || $filters['severity'] !== '' || $filters['event_type'] !== '') { ?>
                    <a href="<?php echo $this->runData['route']['rad_admin_url']; ?>/notifications/view" class="btn btn-outline-secondary btn-sm">Reset</a>
                <?php } ?>
            </div>
            <div class="col-md-4">
                <label class="form-label small text-muted">Per page</label>
                <select name="per_page" class="form-select form-select-sm">
                    <?php foreach ([10, 25, 50, 100, 200] as $value) { ?>
                        <option value="<?php echo $value; ?>" <?php echo $perPage === $value ? 'selected' : ''; ?>>
                            <?php echo $value; ?>
                        </option>
                    <?php } ?>
                </select>
            </div>
        </form>
    </div>
</div>

<form class="card" onsubmit="return false;">
    <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
        <div>
            <strong><?php echo (int)$metrics['total']; ?> notifications</strong>
            <span class="text-muted small">(<?php echo (int)$metrics['unread']; ?> unread)</span>
        </div>
        <div class="d-flex gap-2">
            <button type="button" class="btn btn-success btn-sm" id="notif-bulk-read">Mark selected as read</button>
            <button type="button" class="btn btn-outline-danger btn-sm" id="notif-bulk-archive">Archive selected</button>
            <?php if (!empty($notifications) && !empty($canMarkAll)) { ?>
                <button type="button" class="btn btn-outline-primary btn-sm" id="notif-mark-all">Mark all read</button>
            <?php } ?>
        </div>
    </div>
    <div class="table-responsive">
        <table class="table table-sm align-middle mb-0">
            <thead class="table-light">
            <tr>
                <th style="width:32px;"><input type="checkbox" class="form-check-input" onclick="document.querySelectorAll('.notif-check').forEach(cb => cb.checked = this.checked);"></th>
                <th>Message</th>
                <th class="text-nowrap">Scope</th>
                <th class="text-nowrap">Workspace</th>
                <th class="text-nowrap">Severity</th>
                <th class="text-nowrap">Created</th>
                <th></th>
            </tr>
            </thead>
            <tbody>
            <?php if (empty($notifications)) { ?>
                <tr>
                    <td colspan="7" class="text-center py-4 text-muted">
                        No notifications found for the selected filters.
                        <?php if (($filters['scope'] ?? '') !== 'all') { ?>
                            <div class="mt-1">Try widening the scope to “All” or clearing the search.</div>
                        <?php } ?>
                    </td>
                </tr>
            <?php } ?>
            <?php foreach ($notifications as $row) { ?>
                <tr class="<?php echo $row['is_read'] ? '' : 'fw-semibold'; ?>">
                    <td>
                        <input type="checkbox" class="form-check-input notif-check" name="selected[]" value="<?php echo (int)$row['id']; ?>">
                    </td>
                    <td>
                        <div><?php echo htmlspecialchars($row['s_message'] ?? ''); ?></div>
                        <div class="small text-muted">
                            <?php if (!empty($row['metadata']['event_type'])) { ?>
                                <span class="badge bg-secondary-subtle border text-dark me-1"><?php echo htmlspecialchars($row['metadata']['event_type']); ?></span>
                            <?php } ?>
                            <?php if (!empty($row['metadata']['ip'])) { ?>
                                IP: <?php echo htmlspecialchars($row['metadata']['ip']); ?>
                            <?php } ?>
                        </div>
                    </td>
                    <td>
                        <span class="badge <?php echo $row['scope'] === 'user' ? 'bg-info' : ($row['scope'] === 'workspace' ? 'bg-primary' : 'bg-secondary'); ?>">
                            <?php echo htmlspecialchars($row['scope_label']); ?>
                        </span>
                    </td>
                    <td class="text-nowrap"><?php echo htmlspecialchars(rad_notifications_space_label($spaceOptions, $row)); ?></td>
                    <td class="text-nowrap">
                        <?php
                        $severity = $row['severity'] ?? 'info';
                        $badgeClass = 'text-bg-secondary';
                        if ($severity === 'warn') {
                            $badgeClass = 'text-bg-warning';
                        } elseif ($severity === 'critical') {
                            $badgeClass = 'text-bg-danger';
                        } elseif ($severity === 'info') {
                            $badgeClass = 'text-bg-info';
                        }
                        ?>
                        <span class="badge <?php echo $badgeClass; ?>"><?php echo htmlspecialchars(ucfirst($severity)); ?></span>
                    </td>
                    <td class="text-nowrap">
                        <div><?php echo htmlspecialchars($row['relative_time']); ?></div>
                        <div class="small text-muted"><?php echo htmlspecialchars($row['createstamp'] ?? ''); ?></div>
                    </td>
                    <td class="text-end">
                        <div class="btn-group btn-group-sm" role="group">
                            <button type="button" class="btn btn-outline-secondary notif-action-read" data-id="<?php echo (int)$row['id']; ?>">Mark read</button>
                            <button type="button" class="btn btn-outline-danger notif-action-archive" data-id="<?php echo (int)$row['id']; ?>">Archive</button>
                            <?php if (!empty($row['link'])) { ?>
                                <a href="<?php echo htmlspecialchars($row['link']); ?>" target="_blank" class="btn btn-outline-primary">Open</a>
                            <?php } ?>
                        </div>
                    </td>
                </tr>
            <?php } ?>
            </tbody>
        </table>
    </div>
</form>

<?php if ($pages > 1) { ?>
    <?php
    $query = [
        'scope' => $filters['scope'],
        'space_id' => $filters['space_id'],
        'q' => $filters['q'],
        'severity' => $filters['severity'],
        'event_type' => $filters['event_type'],
        'per_page' => $perPage,
    ];
    $baseUrl = $this->runData['route']['rad_admin_url'] . '/notifications/view?';
    $prev = max(1, $page - 1);
    $next = min($pages, $page + 1);
    ?>
    <nav class="mt-3" aria-label="Notification pagination">
        <ul class="pagination">
            <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                <a class="page-link" href="<?php echo $baseUrl . http_build_query(array_merge($query, ['page' => $prev])); ?>">Previous</a>
            </li>
            <li class="page-item disabled">
                <span class="page-link">Page <?php echo $page; ?> of <?php echo $pages; ?> · <?php echo $total; ?> total</span>
            </li>
            <li class="page-item <?php echo $page >= $pages ? 'disabled' : ''; ?>">
                <a class="page-link" href="<?php echo $baseUrl . http_build_query(array_merge($query, ['page' => $next])); ?>">Next</a>
            </li>
        </ul>
    </nav>
<?php } ?>

<script>
(function () {
    const markUrl = '<?php echo $this->runData['route']['rad_admin_url']; ?>/notifications/markread';
    const archiveUrl = '<?php echo $this->runData['route']['rad_admin_url']; ?>/notifications/archive';
    const markAllBtn = document.getElementById('notif-mark-all');

    function selectedIds() {
        return Array.from(document.querySelectorAll('.notif-check:checked')).map(cb => parseInt(cb.value, 10)).filter(Boolean);
    }

    function performAction(url, ids) {
        if (!ids.length) {
            alert('Select at least one notification.');
            return;
        }
        fetch(url, {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({ids: ids})
        })
            .then(resp => resp.json().then(data => ({ok: resp.ok, data})))
            .then(result => {
                if (!result.ok) {
                    throw new Error(result.data.error || 'Unexpected error');
                }
                window.location.reload();
            })
            .catch(err => alert(err.message || 'Unable to update notifications.'));
    }

    document.getElementById('notif-bulk-read')?.addEventListener('click', () => performAction(markUrl, selectedIds()));
    document.getElementById('notif-bulk-archive')?.addEventListener('click', () => performAction(archiveUrl, selectedIds()));
    markAllBtn?.addEventListener('click', () => performAction(markUrl, 'ALL'));

    document.querySelectorAll('.notif-action-read').forEach(btn => {
        btn.addEventListener('click', () => performAction(markUrl, [parseInt(btn.dataset.id, 10)]));
    });
    document.querySelectorAll('.notif-action-archive').forEach(btn => {
        btn.addEventListener('click', () => performAction(archiveUrl, [parseInt(btn.dataset.id, 10)]));
    });
})();
</script>
