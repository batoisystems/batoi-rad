<?php
$diffData = $this->runData['data']['diff'] ?? [];
$template = $diffData['template'] ?? '';
$version = $diffData['version'] ?? [];
$diff = $diffData['diff'] ?? [];
$branch = $diffData['branch'] ?? 'live';
$microserviceName = $diffData['microservice_name'] ?? '';
$controllerName = $diffData['controller_name'] ?? '';
$radAdminUrl = $this->runData['route']['rad_admin_url'];
$backUrl = $this->runData['route']['backlink'] ?? $radAdminUrl . '/controller/view';
$timezone = $this->runData['entity']['timezone'] ?? ($this->runData['config']['sys']['timezone_default'] ?? 'UTC');
$branchQuery = '?branch=' . ($branch === 'beta' ? 'beta' : 'live');
$downloadUrl = $radAdminUrl . '/controller/downloadversion/' . urlencode($this->runData['route']['pathparts'][3] ?? '') . '/' . urlencode($this->runData['route']['pathparts'][4] ?? '') . '/' . urlencode($version['id'] ?? '') . $branchQuery;
$restoreUrl = $radAdminUrl . '/controller/restoreversion/' . urlencode($this->runData['route']['pathparts'][3] ?? '') . '/' . urlencode($this->runData['route']['pathparts'][4] ?? '') . '/' . urlencode($version['id'] ?? '') . $branchQuery;
$currentLabel = $branch === 'beta' ? 'Current Beta' : 'Current Live';
?>
<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
    <div>
        <a class="text-decoration-none small" href="<?php echo htmlspecialchars($backUrl); ?>">
            <i class="bi bi-arrow-left"></i> Back to Controller Code
        </a>
        <h2 class="mb-1 mt-2">Controller Diff</h2>
        <div class="text-muted small">
            <?php echo htmlspecialchars($template); ?> · Branch: <?php echo htmlspecialchars(strtoupper($branch)); ?>
        </div>
    </div>
    <div class="d-flex flex-wrap gap-2">
        <a href="<?php echo htmlspecialchars($downloadUrl); ?>" class="btn btn-outline-primary">
            <i class="bi bi-download"></i> Download Version
        </a>
        <form action="<?php echo htmlspecialchars($restoreUrl); ?>" method="post" class="d-inline">
            <button type="submit" class="btn btn-outline-danger" onclick="return confirm('Restore this version to the current branch?');">
                <i class="bi bi-arrow-counterclockwise"></i> Restore This Version
            </button>
        </form>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-md-3">
        <div class="card h-100">
            <div class="card-body">
                <div class="text-muted text-uppercase small mb-1">Microservicelet</div>
                <div class="fw-semibold"><?php echo htmlspecialchars($microserviceName ?: 'n/a'); ?></div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card h-100">
            <div class="card-body">
                <div class="text-muted text-uppercase small mb-1">Controller</div>
                <div class="fw-semibold"><?php echo htmlspecialchars($controllerName ?: 'n/a'); ?></div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card h-100">
            <div class="card-body">
                <div class="text-muted text-uppercase small mb-1">Version</div>
                <div class="fw-semibold text-break"><?php echo htmlspecialchars($version['id'] ?? ''); ?></div>
                <div class="small text-muted"><?php echo isset($version['timestamp']) ? \Core\Sys\TimeHelper::formatUtc($version['timestamp'], $timezone) : 'Timestamp unavailable'; ?></div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card h-100">
            <div class="card-body">
                <div class="text-muted text-uppercase small mb-1">Saved By</div>
                <div class="fw-semibold"><?php echo htmlspecialchars($version['user'] ?? 'RAD Admin'); ?></div>
                <div class="small text-muted"><?php echo htmlspecialchars($version['note'] ?? 'No note recorded.'); ?></div>
            </div>
        </div>
    </div>
</div>

<div class="alert alert-light border mb-4">
    Review the left side as the saved version and the right side as the current branch content. Restoring will replace the current branch file with the selected saved version.
</div>

<?php if (empty($diff)) { ?>
    <div class="alert alert-info">No differences found between this version and the current controller code.</div>
<?php } else { ?>
    <div class="card diff-viewer">
        <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
            <strong>Diff Viewer</strong>
            <span class="text-muted small"><?php echo count($diff); ?> change row(s)</span>
        </div>
        <div class="card-body">
            <div class="row mb-2">
                <div class="col-md-6">
                    <h6 class="text-muted mb-0">Saved Version <?php echo htmlspecialchars($version['id'] ?? ''); ?></h6>
                </div>
                <div class="col-md-6">
                    <h6 class="text-muted mb-0"><?php echo htmlspecialchars($currentLabel); ?></h6>
                </div>
            </div>
            <?php foreach ($diff as $chunk) { ?>
                <div class="row small diff-row diff-<?php echo htmlspecialchars($chunk['type'] ?? 'equal'); ?>">
                    <div class="col-md-6">
                        <div class="diff-line">
                            <span class="diff-lineno"><?php echo htmlspecialchars((string)($chunk['old_line'] ?? '')); ?></span>
                            <pre><?php echo htmlspecialchars($chunk['old'] ?? ''); ?></pre>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="diff-line">
                            <span class="diff-lineno"><?php echo htmlspecialchars((string)($chunk['new_line'] ?? '')); ?></span>
                            <pre><?php echo htmlspecialchars($chunk['new'] ?? ''); ?></pre>
                        </div>
                    </div>
                </div>
            <?php } ?>
        </div>
    </div>
<?php } ?>
<style>
.diff-viewer pre {
    margin: 0;
    background: transparent;
    border: none;
    white-space: pre-wrap;
    word-break: break-word;
}
.diff-row {
    border-bottom: 1px solid #f1f3f5;
    padding: 8px 0;
}
.diff-line {
    display: flex;
    gap: 12px;
    align-items: flex-start;
}
.diff-lineno {
    width: 40px;
    flex: 0 0 40px;
    text-align: right;
    color: #6c757d;
}
.diff-equal {
    background: #fff;
}
.diff-insert {
    background: #e8f7ee;
}
.diff-delete {
    background: #fdecea;
}
.diff-replace {
    background: #fff7e6;
}
</style>
