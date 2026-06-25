<?php
$form = $this->runData['data']['form'] ?? [];
$frequencies = $this->runData['data']['frequencies'] ?? [];
$versions = $this->runData['data']['versions'] ?? [];
$radAdminUrl = $this->runData['route']['rad_admin_url'];
$mode = $form['mode'] ?? 'edit';
$scriptName = $form['s_queue_script_name'] ?? '';
$jobRoot = $this->runData['data']['job_root'] ?? '';
$isBuiltin = !empty($form['is_builtin']);
$timezone = $this->runData['entity']['timezone'] ?? ($this->runData['config']['sys']['timezone_default'] ?? 'UTC');
?>

<link rel="stylesheet" href="<?php echo htmlspecialchars(\RadAdmin\RadAdminAssets::monacoBaseUrl($this->runData), ENT_QUOTES, 'UTF-8'); ?>/editor/editor.main.css">

<div class="card mb-4">
    <div class="card-body">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
            <div>
                <div class="text-muted small"><?php echo $mode === 'create' ? 'Create Queue Job' : 'Edit Queue Job'; ?></div>
                <div class="fw-semibold fs-5"><?php echo htmlspecialchars($form['s_queue_title'] ?? ($scriptName ?: 'New Job')); ?></div>
                <div class="small text-muted">
                    Script path:
                    <code>
                        <?php
                        $base = $jobRoot ?: 'rad/data/queue/jobs';
                        echo htmlspecialchars($scriptName !== '' ? ($base . '/' . $scriptName . '.php') : ($base . '/{script_name}.php'));
                        ?>
                    </code>
                </div>
            </div>
            <div class="d-flex gap-2">
                <a href="<?php echo $radAdminUrl; ?>/queue/jobs" class="btn btn-outline-secondary btn-sm">Back to Jobs</a>
                <?php if (!empty($scriptName)) { ?>
                    <form method="post" action="<?php echo $radAdminUrl; ?>/queue/run" class="d-inline">
                        <input type="hidden" name="job" value="<?php echo htmlspecialchars($scriptName); ?>">
                        <button type="submit" class="btn btn-outline-primary btn-sm">Run now</button>
                    </form>
                <?php } ?>
            </div>
        </div>
        <form method="post" action="<?php echo $radAdminUrl; ?>/queue/save">
            <?php if ($isBuiltin) { ?>
                <div class="alert alert-info">
                    This is a built-in system job. Code is managed in core files and is read-only here.
                </div>
            <?php } ?>
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">Job title</label>
                    <input type="text" class="form-control" name="s_queue_title" value="<?php echo htmlspecialchars($form['s_queue_title'] ?? ''); ?>" placeholder="Human-friendly job name" <?php echo $isBuiltin ? 'readonly' : ''; ?>>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Frequency</label>
                    <select class="form-select" name="s_execution_frequency" <?php echo $isBuiltin ? 'disabled' : ''; ?>>
                        <?php foreach ($frequencies as $freq): ?>
                            <option value="<?php echo htmlspecialchars($freq); ?>" <?php echo ($form['s_execution_frequency'] ?? '') === $freq ? 'selected' : ''; ?>><?php echo htmlspecialchars($freq); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Status</label>
                    <select class="form-select" name="livestatus" <?php echo $isBuiltin ? 'disabled' : ''; ?>>
                        <option value="1" <?php echo ($form['livestatus'] ?? '1') === '1' ? 'selected' : ''; ?>>Active</option>
                        <option value="0" <?php echo ($form['livestatus'] ?? '') === '0' ? 'selected' : ''; ?>>Inactive</option>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Script name</label>
                    <input type="text" class="form-control" name="s_queue_script_name" value="<?php echo htmlspecialchars($scriptName); ?>" <?php echo $mode === 'edit' ? 'readonly' : ''; ?> placeholder="letters-numbers-dashes">
                    <div class="form-text">Used for file name and queue runner.</div>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Version note (optional)</label>
                    <input type="text" class="form-control" name="version_note" placeholder="What changed in this update?" <?php echo $isBuiltin ? 'readonly' : ''; ?>>
                </div>
            </div>

            <div class="mt-4">
                <label class="form-label mb-2">Job code</label>
                <div id="queue-job-editor" style="height: 460px; border: 1px solid #dee2e6;"></div>
                <textarea name="code" id="queue_job_code" class="form-control d-none"><?php echo htmlspecialchars($form['code'] ?? ''); ?></textarea>
            </div>

            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mt-3">
                <div class="small text-muted">Save to apply changes and create a version snapshot.</div>
                <?php if (!$isBuiltin) { ?>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-save me-1"></i><?php echo $mode === 'create' ? 'Create Job' : 'Save Changes'; ?>
                    </button>
                <?php } ?>
            </div>
        </form>
    </div>
</div>

<?php if (!$isBuiltin) { ?>
    <div class="card">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h3 class="h6 mb-0">Version History</h3>
            </div>
            <?php if (empty($versions)) { ?>
                <div class="alert alert-secondary mb-0">No versions captured yet.</div>
            <?php } else { ?>
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Version</th>
                                <th>Timestamp</th>
                                <th>User</th>
                                <th>Size</th>
                                <th>Note</th>
                                <th class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($versions as $entry) { ?>
                                <tr>
                                    <td class="fw-semibold"><?php echo htmlspecialchars($entry['id']); ?></td>
                                    <td><?php echo isset($entry['timestamp']) ? \Core\Sys\TimeHelper::formatUtc($entry['timestamp'], $timezone) : '—'; ?></td>
                                    <td><?php echo htmlspecialchars($entry['user'] ?? 'RAD Admin'); ?></td>
                                    <td><?php echo htmlspecialchars($entry['size_human'] ?? '—'); ?></td>
                                    <td><?php echo htmlspecialchars($entry['note'] ?? ''); ?></td>
                                    <td class="text-end">
                                        <a href="<?php echo $radAdminUrl; ?>/queue/downloadversion/<?php echo urlencode($scriptName); ?>/<?php echo urlencode($entry['id']); ?>" class="btn btn-outline-primary btn-sm">
                                            <i class="bi bi-download"></i>
                                        </a>
                                        <a href="<?php echo $radAdminUrl; ?>/queue/diffversion/<?php echo urlencode($scriptName); ?>/<?php echo urlencode($entry['id']); ?>" class="btn btn-outline-info btn-sm">
                                            <i class="bi bi-arrow-left-right"></i>
                                        </a>
                                        <form action="<?php echo $radAdminUrl; ?>/queue/restoreversion/<?php echo urlencode($scriptName); ?>/<?php echo urlencode($entry['id']); ?>" method="post" class="d-inline">
                                            <button type="submit" class="btn btn-outline-danger btn-sm" onclick="return confirm('Restore this version?');">
                                                <i class="bi bi-arrow-counterclockwise"></i>
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            <?php } ?>
                        </tbody>
                    </table>
                </div>
            <?php } ?>
        </div>
    </div>
<?php } ?>

<script src="<?php echo htmlspecialchars(\RadAdmin\RadAdminAssets::monacoBaseUrl($this->runData), ENT_QUOTES, 'UTF-8'); ?>/loader.js"></script>
<script>
require.config({ paths: { 'vs': '<?php echo htmlspecialchars(\RadAdmin\RadAdminAssets::monacoBaseUrl($this->runData), ENT_QUOTES, 'UTF-8'); ?>' }});
window.addEventListener('load', function() {
    require(['vs/editor/editor.main'], function () {
        const hiddenField = document.getElementById('queue_job_code');
        const editor = monaco.editor.create(document.getElementById('queue-job-editor'), {
            value: hiddenField.value,
            language: 'php',
            theme: 'vs-dark',
            automaticLayout: true,
            minimap: { enabled: false },
            readOnly: <?php echo $isBuiltin ? 'true' : 'false'; ?>
        });
        editor.onDidChangeModelContent(function () {
            hiddenField.value = editor.getValue();
        });
    });
});
</script>
