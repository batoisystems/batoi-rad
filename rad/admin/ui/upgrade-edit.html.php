<?php
$form = $this->runData['data']['form'];
$aiAssistanceUrl = $this->runData['config']['sys']['base_url'] . '/rad-admin/upgrade/aiassist';
$timezone = $this->runData['entity']['timezone'] ?? ($this->runData['config']['sys']['timezone_default'] ?? 'UTC');
?>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/monaco-editor/0.30.1/min/vs/editor/editor.main.css">

<div class="card mb-4">
    <div class="card-body">
        <h5 class="card-title mb-3">Edit Upgrade Script</h5>
        <form method="post">
            <input type="hidden" name="update_upgrade" value="1">
            <div class="mb-3">
                <label class="form-label">Upgrade ID</label>
                <input type="text" class="form-control" value="<?php echo htmlspecialchars($form['id']); ?>" readonly>
                <input type="hidden" id="upgrade_id" name="upgrade_id" value="<?php echo htmlspecialchars($form['id']); ?>">
            </div>
            <div class="mb-3">
                <label for="description" class="form-label">Description</label>
                <input type="text" class="form-control" id="description" name="description" value="<?php echo htmlspecialchars($form['description']); ?>" placeholder="Optional summary">
            </div>
            <div class="mb-3">
                <div class="d-flex flex-wrap justify-content-between align-items-center mb-2 gap-2">
                    <label class="form-label mb-0">Upgrade Code</label>
                    <div class="d-flex align-items-center gap-2">
                        <button type="button" class="btn btn-sm btn-outline-primary" id="upgrade-ai-btn">
                            <i class="bi bi-stars me-1"></i>Ask AI
                        </button>
                        <small class="text-muted" id="upgrade-ai-status">Shift+Space inside the editor for inline suggestions.</small>
                    </div>
                </div>
                <div id="upgrade-editor" style="height: 420px; border: 1px solid #dee2e6;"></div>
                <textarea name="code" id="upgrade_code" class="form-control d-none"><?php echo htmlspecialchars($form['code']); ?></textarea>
                <?php
                $versions = $this->runData['data']['versions'] ?? [];
                if (!empty($versions)) {
                ?>
                <div class="table-responsive mt-3">
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
                                    <a href="<?php echo $this->runData['route']['rad_admin_url']; ?>/upgrade/downloadversion/<?php echo urlencode($form['id']); ?>/<?php echo urlencode($entry['id']); ?>" class="btn btn-outline-primary btn-sm">
                                        <i class="bi bi-download"></i>
                                    </a>
                                    <a href="<?php echo $this->runData['route']['rad_admin_url']; ?>/upgrade/diffversion/<?php echo urlencode($form['id']); ?>/<?php echo urlencode($entry['id']); ?>" class="btn btn-outline-info btn-sm">
                                        <i class="bi bi-arrow-left-right"></i>
                                    </a>
                                    <form action="<?php echo $this->runData['route']['rad_admin_url']; ?>/upgrade/restoreversion/<?php echo urlencode($form['id']); ?>/<?php echo urlencode($entry['id']); ?>" method="post" class="d-inline">
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
            <button type="submit" class="btn btn-primary"><i class="bi bi-save me-1"></i>Update Upgrade</button>
        </form>
    </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/monaco-editor/0.30.1/min/vs/loader.js"></script>
<script>
require.config({ paths: { 'vs': 'https://cdnjs.cloudflare.com/ajax/libs/monaco-editor/0.30.1/min/vs' }});

const upgradeAiEndpoint = '<?php echo $aiAssistanceUrl; ?>';
let upgradeEditor;
let upgradeAiBusy = false;

function setUpgradeAiStatus(message, tone = 'muted') {
    const statusEl = document.getElementById('upgrade-ai-status');
    if (!statusEl) {
        return;
    }
    const toneMap = {
        success: 'text-success',
        danger: 'text-danger',
        info: 'text-primary',
        warning: 'text-warning'
    };
    statusEl.className = 'small ' + (toneMap[tone] || 'text-muted');
    statusEl.textContent = message;
}

function triggerUpgradeAiSuggestion() {
    if (!upgradeEditor) {
        return;
    }

    const snippet = upgradeEditor.getValue();
    if (!snippet.trim()) {
        setUpgradeAiStatus('Add some upgrade code before requesting AI.', 'danger');
        return;
    }

    if (upgradeAiBusy) {
        setUpgradeAiStatus('AI request already in progress…', 'warning');
        return;
    }

    upgradeAiBusy = true;
    setUpgradeAiStatus('Fetching suggestion…', 'info');

    const payload = {
        content: snippet,
        upgrade_id: document.getElementById('upgrade_id')?.value || '',
        description: document.getElementById('description')?.value || ''
    };

    fetch(upgradeAiEndpoint, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
    })
    .then(res => res.json())
    .then(data => {
        if (data.suggestion) {
            const cursor = upgradeEditor.getPosition();
            upgradeEditor.executeEdits('aiassist', [{
                range: new monaco.Range(cursor.lineNumber, cursor.column, cursor.lineNumber, cursor.column),
                text: data.suggestion
            }]);
            upgradeEditor.focus();
            setUpgradeAiStatus('Suggestion inserted. Press Ctrl+Z to undo.', 'success');
        } else {
            setUpgradeAiStatus(data.error || 'AI did not return a suggestion.', 'danger');
        }
    })
    .catch(() => {
        setUpgradeAiStatus('Unable to reach AI service.', 'danger');
    })
    .finally(() => {
        upgradeAiBusy = false;
    });
}

window.addEventListener('load', function() {
    const aiButton = document.getElementById('upgrade-ai-btn');
    if (aiButton) {
        aiButton.addEventListener('click', function(e) {
            e.preventDefault();
            triggerUpgradeAiSuggestion();
        });
    }

    require(['vs/editor/editor.main'], function () {
        const hiddenField = document.getElementById('upgrade_code');
        upgradeEditor = monaco.editor.create(document.getElementById('upgrade-editor'), {
            value: hiddenField.value,
            language: 'php',
            theme: 'vs-dark',
            automaticLayout: true,
            minimap: { enabled: false }
        });

        upgradeEditor.onDidChangeModelContent(function () {
            hiddenField.value = upgradeEditor.getValue();
        });

        upgradeEditor.onKeyUp(function(e) {
            if (e.shiftKey && e.code === 'Space') {
                e.preventDefault();
                e.stopPropagation();
                triggerUpgradeAiSuggestion();
            }
        });
    });
});
</script>
