<?php
$form = $this->runData['data']['form'];
$aiAssistanceUrl = $this->runData['config']['sys']['base_url'] . '/rad-admin/upgrade/aiassist';
?>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/monaco-editor/0.30.1/min/vs/editor/editor.main.css">

<div class="card mb-4">
    <div class="card-body">
        <h5 class="card-title mb-3">Create Upgrade Script</h5>
        <p class="text-muted">Provide a unique identifier and PHP code that returns an upgrade definition array. The file will be saved inside <code>rad/upgrades</code>.</p>
        <form method="post">
            <input type="hidden" name="save_upgrade" value="1">
            <div class="mb-3">
                <label for="upgrade_id" class="form-label">Upgrade ID</label>
                <input type="text" class="form-control" id="upgrade_id" name="upgrade_id" value="<?php echo htmlspecialchars($form['id']); ?>" readonly>
                <div class="form-text">System generated identifier. File will be saved as <code><?php echo htmlspecialchars($form['id']); ?>.php</code>.</div>
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
                <div id="upgrade-editor" style="height: 400px; border: 1px solid #dee2e6;"></div>
                <textarea name="code" id="upgrade_code" class="form-control d-none"><?php echo htmlspecialchars($form['code']); ?></textarea>
                <div class="form-text">Code must return an array with keys <code>id</code>, <code>description</code> and a <code>run</code> callable.</div>
            </div>
            <button type="submit" class="btn btn-primary"><i class="bi bi-save me-1"></i>Save Upgrade</button>
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
