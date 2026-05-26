<?php
$logDir = $this->runData['data']['log_dir'] ?? '';
$latest = $this->runData['data']['latest_day'] ?? null;
$counts = $this->runData['data']['log_counts'] ?? ['access' => 0, 'error' => 0, 'sql' => 0];
$dates = $this->runData['data']['log_dates'] ?? [];
$lastIngest = $this->runData['data']['activity_ingest_last_run'] ?? null;
$lastRange = $this->runData['data']['activity_ingest_last_range'] ?? null;
$queueLastRun = $this->runData['data']['queue_last_run'] ?? null;
$workspaceAuditLastRun = $this->runData['data']['workspace_audit_last_run'] ?? null;
$workspaceAuditLastCount = $this->runData['data']['workspace_audit_last_count'] ?? null;
$cacheSummary = $this->runData['data']['cache_summary'] ?? [];
$cacheEnabled = !empty($cacheSummary['enabled']);
$cacheEntries = (int)($cacheSummary['total_entries'] ?? 0);
$cacheSize = $cacheSummary['total_size_label'] ?? '0 B';
$radAdminUrl = $this->runData['route']['rad_admin_url'];
$latestLabel = $latest ? sprintf('%s-%s-%s', $latest['year'], $latest['month'], $latest['day']) : 'No logs found';
$timezone = $this->runData['entity']['timezone'] ?? ($this->runData['config']['sys']['timezone_default'] ?? 'UTC');
$today = (new DateTime('now', new DateTimeZone($timezone)))->format('Y-m-d');
$lastIngestDisplay = $lastIngest ? \Core\Sys\TimeHelper::formatUtc($lastIngest, $timezone) : null;
$queueLastRunDisplay = !empty($queueLastRun['timestamp']) ? \Core\Sys\TimeHelper::formatUtc($queueLastRun['timestamp'], $timezone) : null;
$workspaceAuditLastRunDisplay = $workspaceAuditLastRun ? \Core\Sys\TimeHelper::formatUtc($workspaceAuditLastRun, $timezone) : null;
?>

<div class="card border-0 shadow-sm mb-4">
    <div class="card-body">
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-3">
            <div>
                <h2 class="h5 mb-1">Log Health</h2>
                <p class="text-muted mb-0">Filesystem logs + activity ingestion utilities.</p>
            </div>
            <span class="badge rounded-pill text-bg-light">Latest: <?php echo htmlspecialchars($latestLabel); ?></span>
        </div>
    </div>
</div>

<div class="row g-4">
    <div class="col-lg-6">
        <div class="card shadow-sm h-100">
            <div class="card-header bg-white">
                <h3 class="h6 mb-0">Log Overview</h3>
            </div>
            <div class="card-body">
                <div class="mb-3">
                    <div class="text-muted small">Log directory</div>
                    <div class="fw-semibold"><?php echo htmlspecialchars($logDir ?: 'Not configured'); ?></div>
                </div>
                <div class="row g-3">
                    <div class="col-4">
                        <div class="border rounded p-3 h-100">
                            <div class="text-muted small">Access</div>
                            <div class="h4 mb-0"><?php echo (int)$counts['access']; ?></div>
                        </div>
                    </div>
                    <div class="col-4">
                        <div class="border rounded p-3 h-100">
                            <div class="text-muted small">Errors</div>
                            <div class="h4 mb-0"><?php echo (int)$counts['error']; ?></div>
                        </div>
                    </div>
                    <div class="col-4">
                        <div class="border rounded p-3 h-100">
                            <div class="text-muted small">SQL</div>
                            <div class="h4 mb-0"><?php echo (int)$counts['sql']; ?></div>
                        </div>
                    </div>
                </div>
                <div class="mt-3 text-muted small">Counts are for the latest log day only.</div>
                <div class="mt-3">
                    <div class="text-muted small">Last activity ingestion</div>
                    <div class="fw-semibold"><?php echo htmlspecialchars($lastIngestDisplay ?: 'Not run yet'); ?></div>
                    <?php if (!empty($lastRange['start'])): ?>
                        <div class="text-muted small">Range: <?php echo htmlspecialchars($lastRange['start']); ?> → <?php echo htmlspecialchars($lastRange['end'] ?? $lastRange['start']); ?></div>
                        <div class="text-muted small">Processed: <?php echo (int)($lastRange['processed'] ?? 0); ?> · Inserted: <?php echo (int)($lastRange['inserted'] ?? 0); ?> · Skipped: <?php echo (int)($lastRange['skipped'] ?? 0); ?></div>
                    <?php endif; ?>
                    <div class="text-muted small mt-2">Last scheduled queue run</div>
                    <div class="fw-semibold"><?php echo htmlspecialchars($queueLastRunDisplay ?: 'Not run yet'); ?></div>
                    <?php if (!empty($queueLastRun['context'])): ?>
                        <div class="text-muted small">
                            Job: <?php echo htmlspecialchars($queueLastRun['context']['job'] ?? ''); ?>
                            <?php if (!empty($queueLastRun['context']['status'])): ?>
                                · Status: <?php echo htmlspecialchars($queueLastRun['context']['status']); ?>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card shadow-sm h-100">
            <div class="card-header bg-white">
                <h3 class="h6 mb-0">Activity Ingestion</h3>
            </div>
            <div class="card-body">
                <p class="text-muted small">Build user activity from access logs into <code>s_activity</code>.</p>
                <form method="post" action="<?php echo $radAdminUrl; ?>/governance/health" class="mb-3">
                    <input type="hidden" name="action" value="ingest_activity_auto">
                    <button type="submit" class="btn btn-outline-secondary">
                        <i class="bi bi-lightning-charge me-1"></i>Sync since last run
                    </button>
                </form>
                <form method="post" action="<?php echo $radAdminUrl; ?>/governance/health" class="row g-3 align-items-end">
                    <input type="hidden" name="action" value="ingest_activity">
                    <div class="col-12 col-md-6">
                        <label class="form-label">From</label>
                        <input type="date" name="date_from" class="form-control" value="<?php echo htmlspecialchars($dates[0] ?? $today); ?>">
                    </div>
                    <div class="col-12 col-md-6">
                        <label class="form-label">To</label>
                        <input type="date" name="date_to" class="form-control" value="<?php echo htmlspecialchars($dates[0] ?? $today); ?>">
                    </div>
                    <div class="col-12">
                        <button type="submit" class="btn btn-primary"><i class="bi bi-arrow-repeat me-1"></i>Build Activity Index</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<div class="card shadow-sm mt-4">
    <div class="card-header bg-white d-flex align-items-center justify-content-between">
        <div>
            <h3 class="h6 mb-0">Cache Management</h3>
            <div class="text-muted small">Route/content cache footprint and purge controls.</div>
        </div>
        <a class="btn btn-outline-secondary btn-sm" href="<?php echo $radAdminUrl; ?>/governance/cache">
            <i class="bi bi-lightning-charge me-1"></i>Open Cache
        </a>
    </div>
    <div class="card-body">
        <div class="d-flex flex-wrap gap-4">
            <div>
                <div class="text-muted small">Status</div>
                <div class="fw-semibold"><?php echo $cacheEnabled ? 'Enabled' : 'Disabled'; ?></div>
            </div>
            <div>
                <div class="text-muted small">Entries</div>
                <div class="fw-semibold"><?php echo $cacheEntries; ?></div>
            </div>
            <div>
                <div class="text-muted small">Total size</div>
                <div class="fw-semibold"><?php echo htmlspecialchars($cacheSize); ?></div>
            </div>
        </div>
    </div>
</div>

<div class="card shadow-sm mt-4">
    <div class="card-header bg-white d-flex align-items-center justify-content-between">
        <div>
            <h3 class="h6 mb-0">Workspace SQL Audit</h3>
            <div class="text-muted small">Flags workspace-scoped routes that appear to query without space_id filters.</div>
        </div>
        <a class="btn btn-outline-secondary btn-sm" href="<?php echo $radAdminUrl; ?>/governance/workspace-audit">
            <i class="bi bi-search me-1"></i>View Report
        </a>
    </div>
    <div class="card-body">
        <div class="d-flex flex-wrap gap-3 align-items-center justify-content-between">
            <div>
                <div class="text-muted small">Last scan</div>
                <div class="fw-semibold"><?php echo htmlspecialchars($workspaceAuditLastRunDisplay ?: 'Not run yet'); ?></div>
                <div class="text-muted small">Issues found: <?php echo htmlspecialchars($workspaceAuditLastCount ?: '0'); ?></div>
            </div>
            <form method="post" action="<?php echo $radAdminUrl; ?>/governance/health">
                <input type="hidden" name="action" value="workspace_audit_scan">
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-lightning-charge me-1"></i>Run Workspace Scan
                </button>
            </form>
        </div>
    </div>
</div>

<div class="card shadow-sm mt-4">
    <div class="card-header bg-white d-flex align-items-center justify-content-between">
        <div>
            <h3 class="h6 mb-0">Stray Routes &amp; Controllers</h3>
            <div class="text-muted small">Detects filesystem route/controller files missing from system tables.</div>
        </div>
        <a class="btn btn-outline-secondary btn-sm" href="<?php echo $radAdminUrl; ?>/governance/strayroutes">
            <i class="bi bi-search me-1"></i>View Report
        </a>
    </div>
    <div class="card-body">
        <p class="text-muted small mb-0">Scan rad/ms/{ms_name} for route.* and *.cls.php without matching s_msroute or s_mscontroller rows.</p>
    </div>
</div>

<div class="card shadow-sm mt-4">
    <div class="card-header bg-white d-flex align-items-center justify-content-between">
        <div>
            <h3 class="h6 mb-0">Forgot Password Log</h3>
            <div class="text-muted small">Monitor password reset requests with access/error context.</div>
        </div>
        <a class="btn btn-outline-secondary btn-sm" href="<?php echo $radAdminUrl; ?>/governance/forgotpasswordlog">
            <i class="bi bi-unlock me-1"></i>View Log
        </a>
    </div>
    <div class="card-body">
        <p class="text-muted small mb-0">Review reset requests, token usage, and cleanup options.</p>
    </div>
</div>

<div class="card shadow-sm mt-4">
    <div class="card-header bg-white">
        <h3 class="h6 mb-0">Maintenance</h3>
    </div>
    <div class="card-body">
        <form method="post" action="<?php echo $radAdminUrl; ?>/governance/health" class="row g-3 align-items-end">
            <input type="hidden" name="action" value="purge_logs">
            <div class="col-12 col-md-6">
                <label class="form-label">Purge logs older than</label>
                <select name="purge_days" class="form-select" required>
                    <option value="">Select retention window</option>
                    <option value="30">30 days</option>
                    <option value="60">60 days</option>
                    <option value="90">90 days</option>
                    <option value="180">6 months</option>
                    <option value="365">1 year</option>
                </select>
            </div>
            <div class="col-12 col-md-6">
                <button type="submit" class="btn btn-outline-danger"><i class="bi bi-trash3 me-1"></i>Run Cleanup</button>
            </div>
        </form>
    </div>
</div>
