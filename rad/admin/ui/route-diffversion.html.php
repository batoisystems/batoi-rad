<?php
$diffData = $this->runData['data']['diff'] ?? [];
$template = $diffData['template'] ?? '';
$version = $diffData['version'] ?? [];
$diff = $diffData['diff'] ?? [];
$part = $diffData['part'] ?? '';
$radAdminUrl = $this->runData['route']['rad_admin_url'];
$backUrl = $this->runData['route']['backlink'] ?? $radAdminUrl . '/route/view';
?>
<div class="container">
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
        <div>
            <a class="text-decoration-none small" href="<?php echo $backUrl; ?>">
                <i class="bi bi-arrow-left"></i> Back to Route
            </a>
            <h2 class="mb-1 mt-2">Route Diff - <?php echo htmlspecialchars($template); ?></h2>
            <div class="text-muted small">Part: <?php echo htmlspecialchars(ucfirst($part)); ?> · Version <?php echo htmlspecialchars($version['id'] ?? ''); ?></div>
        </div>
    </div>

    <?php if (empty($diff)) { ?>
        <div class="alert alert-info">No differences found between this version and the current code.</div>
    <?php } else { ?>
        <div class="diff-viewer card">
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <h6 class="text-muted mb-2">Version <?php echo htmlspecialchars($version['id'] ?? ''); ?></h6>
                    </div>
                    <div class="col-md-6">
                        <h6 class="text-muted mb-2">Current</h6>
                    </div>
                </div>
                <?php foreach ($diff as $chunk) { ?>
                    <div class="row small diff-row diff-<?php echo $chunk['type']; ?>">
                        <div class="col-md-6">
                            <div class="diff-line">
                                <span class="diff-lineno"><?php echo $chunk['old_line'] ?? ''; ?></span>
                                <pre><?php echo htmlspecialchars($chunk['old'] ?? ''); ?></pre>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="diff-line">
                                <span class="diff-lineno"><?php echo $chunk['new_line'] ?? ''; ?></span>
                                <pre><?php echo htmlspecialchars($chunk['new'] ?? ''); ?></pre>
                            </div>
                        </div>
                    </div>
                <?php } ?>
            </div>
        </div>
    <?php } ?>
</div>
<style>
.diff-viewer pre {
    margin: 0;
    background: transparent;
    border: none;
    white-space: pre-wrap;
}
.diff-row {
    border-bottom: 1px solid #f1f3f5;
    padding: 6px 0;
}
.diff-line {
    display: flex;
    gap: 12px;
}
.diff-lineno {
    width: 40px;
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
