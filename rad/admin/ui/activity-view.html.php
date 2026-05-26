<?php
$data = $this->runData['data'] ?? [];
$filters = $data['filters'] ?? ['scope' => 'my', 'space_id' => 0, 'event_type' => '', 'actor_id' => 0, 'from' => null, 'to' => null];
$scopeOptions = $data['scope_options'] ?? [];
$spaceOptions = $data['space_options'] ?? [];
$activities = $data['activities'] ?? [];
$metrics = $data['metrics'] ?? ['total' => 0, 'unique_actors' => 0, 'unique_workspaces' => 0];
$pagination = $data['pagination'] ?? ['page' => 1, 'per_page' => 25, 'total' => 0, 'total_pages' => 1];
$chart = $data['chart'] ?? ['daily' => [], 'events' => []];
$isSuperAdmin = $data['is_super_admin'] ?? false;
$lastIngestRun = $data['activity_ingest_last_run'] ?? null;
$lastIngestRange = $data['activity_ingest_last_range'] ?? null;
$hasFilters = !empty($filters['space_id']) || $filters['event_type'] !== '' || ($filters['actor_id'] && $isSuperAdmin) || $filters['from'] || $filters['to'] || !empty($filters['q']);
$currentPage = (int)($pagination['page'] ?? 1);
$perPage = (int)($pagination['per_page'] ?? 25);
$totalPages = max(1, (int)($pagination['total_pages'] ?? 1));
$totalRecords = (int)($pagination['total'] ?? 0);
$queryBase = [
    'scope' => $filters['scope'],
    'space_id' => $filters['space_id'],
    'event_type' => $filters['event_type'],
    'actor_id' => $filters['actor_id'],
    'from' => $filters['from'],
    'to' => $filters['to'],
    'q' => $filters['q'] ?? '',
    'per_page' => $perPage,
];
$prevQuery = http_build_query(array_merge($queryBase, ['page' => max(1, $currentPage - 1)]));
$nextQuery = http_build_query(array_merge($queryBase, ['page' => min($totalPages, $currentPage + 1)]));

if (!function_exists('rad_activity_space_label')) {
    function rad_activity_space_label(array $spaceOptions, array $row): string {
        $spaceId = (int)($row['space_id'] ?? 0);
        if ($spaceId === 0) {
            return 'Global';
        }
        return $spaceOptions[$spaceId] ?? ('Workspace #' . $spaceId);
    }
}
?>
<style>
.activity-timeline {
    position: relative;
    padding-left: 2rem;
    border-left: 2px solid #e9ecef;
}
.activity-card {
    position: relative;
}
.activity-card::before {
    content: '';
    position: absolute;
    left: -2.1rem;
    top: 1rem;
    width: 12px;
    height: 12px;
    background: #0d6efd;
    border-radius: 50%;
    box-shadow: 0 0 0 3px rgba(13,110,253,.25);
}
</style>
<div class="card mb-3 shadow-sm border-0">
    <div class="card-body">
        <div class="d-flex flex-wrap justify-content-between align-items-start gap-3">
            <div>
                <div class="text-muted small text-uppercase">Activity Feed</div>
                <div class="h5 mb-1">Track system actions, workspace events, and account changes.</div>
                <div class="text-muted small">Filters refine the timeline and charts below.</div>
            </div>
            <?php if ($isSuperAdmin) { ?>
                <div class="text-end">
                    <div class="fw-semibold">Activity Ingest</div>
                    <div class="small text-muted">Sync access logs into the activity timeline.</div>
                    <?php if (!empty($lastIngestRun)) { ?>
                        <div class="small text-muted">
                            Last run: <?php echo htmlspecialchars($lastIngestRun); ?>
                            <?php if (!empty($lastIngestRange['start']) || !empty($lastIngestRange['end'])) { ?>
                                <span class="ms-2">
                                    (<?php echo htmlspecialchars(($lastIngestRange['start'] ?? '') . ' → ' . ($lastIngestRange['end'] ?? '')); ?>)
                                </span>
                            <?php } ?>
                        </div>
                    <?php } ?>
                    <form method="post" class="mt-2">
                        <input type="hidden" name="action" value="ingest_activity_auto">
                        <button type="submit" class="btn btn-outline-primary btn-sm">Run Activity Ingest Now</button>
                    </form>
                </div>
            <?php } ?>
        </div>
        <form class="row g-3 mt-3" method="get">
            <div class="col-md-3">
                <label class="form-label small text-muted">Scope</label>
                <select name="scope" class="form-select form-select-sm">
                    <?php foreach ($scopeOptions as $value => $label) { ?>
                        <option value="<?php echo htmlspecialchars($value); ?>" <?php echo $filters['scope'] === $value ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($label); ?>
                        </option>
                    <?php } ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label small text-muted">Workspace</label>
                <select name="space_id" class="form-select form-select-sm">
                    <option value="0">All</option>
                    <?php foreach ($spaceOptions as $spaceId => $name) { ?>
                        <option value="<?php echo $spaceId; ?>" <?php echo ($filters['space_id'] == $spaceId) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($name); ?>
                        </option>
                    <?php } ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label small text-muted">Event Type</label>
                <input type="text" name="event_type" class="form-control form-control-sm" placeholder="e.g. membership_role_assign" value="<?php echo htmlspecialchars($filters['event_type']); ?>">
            </div>
            <?php if ($isSuperAdmin) { ?>
                <div class="col-md-3">
                    <label class="form-label small text-muted">Actor (User ID)</label>
                    <input type="number" name="actor_id" class="form-control form-control-sm" value="<?php echo (int)$filters['actor_id']; ?>" min="0">
                </div>
            <?php } ?>
            <div class="col-md-3">
                <label class="form-label small text-muted">From</label>
                <input type="date" name="from" class="form-control form-control-sm" value="<?php echo htmlspecialchars($filters['from'] ?? ''); ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label small text-muted">To</label>
                <input type="date" name="to" class="form-control form-control-sm" value="<?php echo htmlspecialchars($filters['to'] ?? ''); ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label small text-muted">Search</label>
                <input type="text" name="q" class="form-control form-control-sm" placeholder="Message, event, object..." value="<?php echo htmlspecialchars($filters['q'] ?? ''); ?>">
            </div>
            <div class="col-md-3 d-flex align-items-end gap-2">
                <button type="submit" class="btn btn-primary btn-sm flex-grow-1">Apply</button>
                <?php if ($hasFilters) { ?>
                    <a href="<?php echo $this->runData['route']['rad_admin_url']; ?>/activity/view" class="btn btn-outline-secondary btn-sm flex-grow-1">Reset</a>
                <?php } ?>
            </div>
        </form>
    </div>
</div>

<div class="row g-3 mb-3">
    <div class="col-md-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <div class="text-muted small text-uppercase">Total Activities</div>
                <div class="h4 mb-0"><?php echo number_format($metrics['total']); ?></div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <div class="text-muted small text-uppercase">Unique Actors</div>
                <div class="h4 mb-0"><?php echo number_format($metrics['unique_actors']); ?></div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <div class="text-muted small text-uppercase">Workspaces Touched</div>
                <div class="h4 mb-0"><?php echo number_format($metrics['unique_workspaces']); ?></div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <div class="text-muted small text-uppercase">Current Page</div>
                <div class="h4 mb-0"><?php echo $currentPage; ?> / <?php echo $totalPages; ?></div>
            </div>
        </div>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-lg-7">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <div class="fw-semibold">Activity volume (last 14 days)</div>
                    <div class="text-muted small">Filtered view</div>
                </div>
                <div class="rad-activity-chart" data-chart="daily" data-series='<?php echo htmlspecialchars(json_encode($chart['daily'])); ?>'>
                    <div class="text-muted small">No chart data.</div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-5">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <div class="fw-semibold mb-2">Top event types</div>
                <div class="rad-activity-chart" data-chart="events" data-series='<?php echo htmlspecialchars(json_encode($chart['events'])); ?>'>
                    <div class="text-muted small">No chart data.</div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php if (empty($activities)) { ?>
    <div class="alert alert-secondary">
        No activity records found for the selected filters.
        <?php if (($filters['scope'] ?? '') !== 'all') { ?>
            <div class="mt-1">Try widening the scope to “All” or clearing the search.</div>
        <?php } ?>
    </div>
<?php } ?>

<?php if (!empty($activities)) { ?>
<div class="card border-0 shadow-sm">
    <div class="card-body">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-3">
            <div class="fw-semibold">Activity Timeline</div>
            <div class="d-flex align-items-center gap-2">
                <span class="text-muted small">Rows per page</span>
                <form method="get" class="d-flex align-items-center gap-2">
                    <?php foreach ($queryBase as $key => $value) { ?>
                        <input type="hidden" name="<?php echo htmlspecialchars($key); ?>" value="<?php echo htmlspecialchars((string)$value); ?>">
                    <?php } ?>
                    <select name="per_page" class="form-select form-select-sm w-auto" onchange="this.form.submit()">
                        <?php foreach ([25, 50, 100, 200] as $size) { ?>
                            <option value="<?php echo $size; ?>" <?php echo $perPage === $size ? 'selected' : ''; ?>><?php echo $size; ?></option>
                        <?php } ?>
                    </select>
                </form>
            </div>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead class="table-light">
                    <tr>
                        <th>When</th>
                        <th>Event</th>
                        <th>Message</th>
                        <th>Workspace</th>
                        <th>Actor</th>
                        <th>Scope</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($activities as $activity) { ?>
                        <tr>
                            <td class="text-muted small">
                                <div class="fw-semibold"><?php echo htmlspecialchars($activity['relative_time']); ?></div>
                                <div><?php echo htmlspecialchars($activity['createstamp'] ?? ''); ?></div>
                            </td>
                            <td>
                                <?php if (!empty($activity['event_label'])) { ?>
                                    <span class="badge bg-secondary-subtle text-dark border"><?php echo htmlspecialchars($activity['event_label']); ?></span>
                                <?php } ?>
                            </td>
                            <td>
                                <div class="fw-semibold"><?php echo htmlspecialchars($activity['s_message'] ?? ''); ?></div>
                                <?php if (!empty($activity['metadata_pairs'])) { ?>
                                    <div class="text-muted small">
                                        <?php foreach ($activity['metadata_pairs'] as $pair) { ?>
                                            <span class="me-2"><?php echo htmlspecialchars($pair['label'] . ': ' . $pair['value']); ?></span>
                                        <?php } ?>
                                    </div>
                                <?php } ?>
                            </td>
                            <td class="text-muted small"><?php echo htmlspecialchars(rad_activity_space_label($spaceOptions, $activity)); ?></td>
                            <td class="text-muted small">
                                <?php echo $activity['actor_name'] ? htmlspecialchars($activity['actor_name']) : 'ID ' . (int)($activity['s_actor_id'] ?? 0); ?>
                            </td>
                            <td>
                                <span class="badge <?php echo $activity['scope'] === 'user' ? 'bg-info' : ($activity['scope'] === 'workspace' ? 'bg-primary' : 'bg-secondary'); ?>">
                                    <?php echo htmlspecialchars($activity['scope_label']); ?>
                                </span>
                            </td>
                            <td class="text-end">
                                <?php if (!empty($activity['link'])) { ?>
                                    <a href="<?php echo htmlspecialchars($activity['link']); ?>" target="_blank" class="btn btn-outline-primary btn-sm">Open</a>
                                <?php } ?>
                            </td>
                        </tr>
                    <?php } ?>
                </tbody>
            </table>
        </div>
        <div class="d-flex justify-content-between align-items-center mt-3">
            <div class="text-muted small">
                Showing <?php echo count($activities); ?> of <?php echo number_format($totalRecords); ?> records
            </div>
            <div class="btn-group">
                <a class="btn btn-outline-secondary btn-sm <?php echo $currentPage <= 1 ? 'disabled' : ''; ?>" href="<?php echo $this->runData['route']['rad_admin_url']; ?>/activity/view?<?php echo $prevQuery; ?>">Previous</a>
                <span class="btn btn-outline-secondary btn-sm disabled"><?php echo $currentPage; ?> / <?php echo $totalPages; ?></span>
                <a class="btn btn-outline-secondary btn-sm <?php echo $currentPage >= $totalPages ? 'disabled' : ''; ?>" href="<?php echo $this->runData['route']['rad_admin_url']; ?>/activity/view?<?php echo $nextQuery; ?>">Next</a>
            </div>
        </div>
    </div>
</div>
<?php } ?>

<script>
(function () {
    function renderBar(container, data) {
        if (!Array.isArray(data) || data.length === 0) {
            return;
        }
        container.innerHTML = '';
        var max = 0;
        data.forEach(function (row) {
            var total = parseInt(row.total || row.count || 0, 10);
            if (total > max) {
                max = total;
            }
        });
        data.forEach(function (row) {
            var label = row.day || row.event_key || row.label || 'Event';
            var total = parseInt(row.total || row.count || 0, 10);
            var percent = max > 0 ? Math.round((total / max) * 100) : 0;
            var line = document.createElement('div');
            line.className = 'd-flex align-items-center gap-2 mb-2';
            line.innerHTML = '<div class="small text-muted" style="width:120px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">' +
                label + '</div><div class="flex-grow-1"><div class="progress" style="height:8px;"><div class="progress-bar bg-primary" style="width:' +
                percent + '%"></div></div></div><div class="small fw-semibold" style="width:40px;text-align:right;">' + total + '</div>';
            container.appendChild(line);
        });
    }

    var daily = document.querySelector('[data-chart="daily"]');
    if (daily && daily.dataset.series) {
        renderBar(daily, JSON.parse(daily.dataset.series || '[]'));
    }
    var events = document.querySelector('[data-chart="events"]');
    if (events && events.dataset.series) {
        renderBar(events, JSON.parse(events.dataset.series || '[]'));
    }
})();
</script>
