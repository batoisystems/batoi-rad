<?php
$events = $this->runData['data']['telemetry_events'] ?? [];
$filters = $this->runData['data']['telemetry_filters'] ?? [];
$pager = $this->runData['data']['telemetry_pagination'] ?? ['page' => 1, 'pages' => 1, 'total' => 0];
$radAdminUrl = $this->runData['route']['rad_admin_url'] ?? '';
?>
<div class="card border-0 shadow-sm mb-4">
    <div class="card-body d-flex flex-column flex-lg-row align-items-lg-center gap-3">
        <div>
            <h2 class="h4 mb-1">Telemetry Events</h2>
            <p class="text-muted mb-0">Filter and browse telemetry events. Export filtered results as CSV.</p>
        </div>
        <div class="ms-lg-auto d-flex gap-2 flex-wrap">
            <a href="<?php echo $radAdminUrl; ?>/telemetry/exportcsv?<?php echo http_build_query($filters); ?>" class="btn btn-outline-secondary btn-sm"><i class="bi bi-download me-1"></i>Export CSV</a>
            <a href="<?php echo $radAdminUrl; ?>/telemetry/view" class="btn btn-outline-primary btn-sm"><i class="bi bi-arrow-left me-1"></i>Back to Telemetry</a>
        </div>
    </div>
</div>

<div class="card border-0 shadow-sm mb-3">
    <div class="card-body">
        <form class="row g-3 align-items-end" method="get" action="<?php echo $radAdminUrl; ?>/telemetry/list">
            <div class="col-md-3">
                <label class="form-label">Severity</label>
                <select name="severity" class="form-select">
                    <option value="">Any</option>
                    <?php foreach (['high','medium','low'] as $sev) { ?>
                        <option value="<?php echo $sev; ?>" <?php echo ($filters['severity'] ?? '') === $sev ? 'selected' : ''; ?>><?php echo ucfirst($sev); ?></option>
                    <?php } ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">Component Type</label>
                <select name="component_type" class="form-select">
                    <option value="">Any</option>
                    <?php foreach (['route','controller','job','vendor','custom','error'] as $type) { ?>
                        <option value="<?php echo $type; ?>" <?php echo ($filters['component_type'] ?? '') === $type ? 'selected' : ''; ?>><?php echo ucfirst($type); ?></option>
                    <?php } ?>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label">Search (message/component)</label>
                <input type="search" name="q" class="form-control" value="<?php echo htmlspecialchars($filters['search'] ?? ''); ?>">
            </div>
            <div class="col-md-2 text-end">
                <button class="btn btn-primary" type="submit">Apply</button>
            </div>
        </form>
    </div>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-header bg-white d-flex justify-content-between align-items-center">
        <h3 class="h6 mb-0">Events (<?php echo (int)$pager['total']; ?>)</h3>
    </div>
    <div class="card-body">
        <?php if (empty($events)) { ?>
            <p class="text-muted mb-0">No events found for the selected filters.</p>
        <?php } else { ?>
            <div class="table-responsive">
                <table class="table table-sm align-middle">
                    <thead class="small text-muted">
                        <tr>
                            <th>Time</th>
                            <th>Component</th>
                            <th>Severity</th>
                            <th>Message</th>
                            <th>Duration (ms)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($events as $event) { ?>
                        <tr>
                            <td class="text-muted small"><?php echo htmlspecialchars($event['created_at'] ?? ''); ?></td>
                            <td class="text-monospace small"><?php echo htmlspecialchars(($event['component_type'] ?? '') . ':' . ($event['component_ref'] ?? '')); ?></td>
                            <td>
                                <?php $sev = strtolower($event['severity'] ?? ''); ?>
                                <span class="badge bg-<?php echo $sev === 'high' ? 'danger' : ($sev === 'medium' ? 'warning' : 'secondary'); ?>">
                                    <?php echo htmlspecialchars(ucfirst($event['severity'] ?? '')); ?>
                                </span>
                            </td>
                            <td class="small"><?php echo htmlspecialchars($event['message'] ?? ''); ?></td>
                            <td class="text-muted small"><?php echo htmlspecialchars($event['duration_ms'] ?? ''); ?></td>
                        </tr>
                        <?php } ?>
                    </tbody>
                </table>
            </div>
            <?php if ($pager['pages'] > 1) { ?>
                <nav aria-label="Telemetry pagination" class="mt-3">
                    <ul class="pagination pagination-sm mb-0">
                        <?php for ($i = 1; $i <= $pager['pages']; $i++) {
                            $query = $filters;
                            $query['page'] = $i;
                            $url = $radAdminUrl . '/telemetry/list?' . http_build_query($query);
                            $active = $i === (int)$pager['page'];
                        ?>
                        <li class="page-item <?php echo $active ? 'active' : ''; ?>">
                            <a class="page-link" href="<?php echo $url; ?>"><?php echo $i; ?></a>
                        </li>
                        <?php } ?>
                    </ul>
                </nav>
            <?php } ?>
        <?php } ?>
    </div>
</div>
