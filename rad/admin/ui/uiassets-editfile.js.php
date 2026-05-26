<script src="https://cdnjs.cloudflare.com/ajax/libs/monaco-editor/0.30.1/min/vs/loader.js"></script>
<script>
    require.config({ paths: { 'vs': 'https://cdnjs.cloudflare.com/ajax/libs/monaco-editor/0.30.1/min/vs' }});
</script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/monaco-editor/0.30.1/min/vs/editor/editor.main.nls.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/monaco-editor/0.30.1/min/vs/editor/editor.main.js"></script>
<script>
(function () {
    const editorContainer = document.getElementById('asset-editor');
    if (!editorContainer) {
        return;
    }

    const assetPath = editorContainer.dataset.path || '';
    const assetExtension = (editorContainer.dataset.extension || '').toLowerCase();
    const saveUrl = editorContainer.dataset.saveUrl;
    const aiUrl = editorContainer.dataset.aiUrl;
    const initialContent = <?php echo json_encode($this->runData['data']['asset_content']); ?>;
    const copyUrlBtn = document.getElementById('asset-copy-url');
    const publicUrlEl = document.getElementById('asset-public-url');
    const formatBtn = document.getElementById('asset-format-btn');
    const wrapBtn = document.getElementById('asset-wrap-btn');
    const undoBtn = document.getElementById('asset-undo-btn');
    const redoBtn = document.getElementById('asset-redo-btn');
    const aiBtn = document.getElementById('asset-ai-btn');
    const aiStatusEl = document.getElementById('asset-ai-status');
    const saveStatusEl = document.getElementById('asset-save-status');

    let editorInstance;
    let aiBusy = false;
    let saveBusy = false;
    let isDirty = false;
    let autoSaveTimer = null;
    const AUTO_SAVE_DELAY = 2000;

    function languageFromExtension(ext) {
        const map = {
            'css': 'css',
            'scss': 'scss',
            'less': 'less',
            'js': 'javascript',
            'ts': 'typescript',
            'json': 'json',
            'xml': 'xml',
            'svg': 'xml',
            'txt': 'plaintext',
            'md': 'markdown',
            'html': 'html',
            'htm': 'html',
            'php': 'php',
            'ini': 'ini',
            'yaml': 'yaml',
            'yml': 'yaml'
        };
        return map[ext] || 'plaintext';
    }

    function setSaveStatus(message, tone = 'muted') {
        if (!saveStatusEl) {
            return;
        }
        const toneClass = {
            muted: 'text-muted',
            success: 'text-success',
            danger: 'text-danger',
            warning: 'text-warning'
        }[tone] || 'text-muted';
        saveStatusEl.className = 'small ' + toneClass;
        saveStatusEl.textContent = message;
    }

    function setAiStatus(message, tone = 'muted') {
        if (!aiStatusEl) {
            return;
        }
        const toneClass = {
            muted: 'text-muted',
            success: 'text-success',
            danger: 'text-danger',
            warning: 'text-warning',
            info: 'text-primary'
        }[tone] || 'text-muted';
        aiStatusEl.className = 'small ' + toneClass;
        aiStatusEl.textContent = message;
    }

    function setDirty(flag) {
        isDirty = flag;
        if (flag) {
            setSaveStatus('Unsaved changes pending.', 'warning');
        } else {
            setSaveStatus('All changes saved.', 'success');
        }
    }

    require(['vs/editor/editor.main'], function () {
        editorInstance = monaco.editor.create(editorContainer, {
            value: initialContent || '',
            language: languageFromExtension(assetExtension),
            theme: 'vs-dark',
            automaticLayout: true,
            wordWrap: 'on',
            minimap: { enabled: false }
        });

        editorInstance.onDidChangeModelContent(function () {
            if (!saveBusy) {
                setDirty(true);
                scheduleAutoSave();
            }
        });

        editorInstance.onKeyUp(function (event) {
            if (event.shiftKey && event.code === 'Space') {
                event.preventDefault();
                requestAiSuggestion();
            }
        });

        if (formatBtn) {
            formatBtn.addEventListener('click', function () {
                editorInstance.getAction('editor.action.formatDocument').run();
            });
        }
        if (wrapBtn) {
            wrapBtn.addEventListener('click', function () {
                const newState = editorInstance.getRawOptions().wordWrap === 'on' ? 'off' : 'on';
                editorInstance.updateOptions({ wordWrap: newState });
            });
        }
        if (undoBtn) {
            undoBtn.addEventListener('click', function () {
                editorInstance.trigger('keyboard', 'undo', null);
            });
        }
        if (redoBtn) {
            redoBtn.addEventListener('click', function () {
                editorInstance.trigger('keyboard', 'redo', null);
            });
        }
        if (aiBtn) {
            aiBtn.addEventListener('click', function (event) {
                event.preventDefault();
                requestAiSuggestion();
            });
        }
    });

    function scheduleAutoSave() {
        if (saveBusy) {
            return;
        }
        if (autoSaveTimer) {
            clearTimeout(autoSaveTimer);
        }
        autoSaveTimer = setTimeout(function () {
            if (!saveBusy && isDirty) {
                saveEditorContent({ auto: true });
            }
        }, AUTO_SAVE_DELAY);
    }

    function saveEditorContent(options = {}) {
        if (!editorInstance || saveBusy) {
            return;
        }
        saveBusy = true;
        if (autoSaveTimer) {
            clearTimeout(autoSaveTimer);
            autoSaveTimer = null;
        }
        const isAuto = !!options.auto;
        setSaveStatus(isAuto ? 'Auto-saving…' : 'Saving changes…', 'info');

        fetch(saveUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                path: assetPath,
                content: editorInstance.getValue()
            })
        })
        .then(response => {
            if (!response.ok) {
                throw new Error('Save failed');
            }
            return response.json();
        })
        .then(data => {
            if (data && data.success) {
                setDirty(false);
                if (!isAuto) {
                    setSaveStatus('All changes saved.', 'success');
                } else {
                    setSaveStatus('All changes saved (auto).', 'success');
                }
            } else {
                throw new Error(data && data.message ? data.message : 'Unable to save changes');
            }
        })
        .catch(error => {
            console.error(error);
            setSaveStatus(error.message || 'Unable to save changes.', 'danger');
        })
        .finally(() => {
            saveBusy = false;
        });
    }

    function requestAiSuggestion() {
        if (!editorInstance) {
            setAiStatus('Editor unavailable.', 'danger');
            return;
        }

        const content = editorInstance.getValue();
        if (!content.trim()) {
            setAiStatus('Add some content before requesting AI.', 'warning');
            return;
        }

        if (aiBusy) {
            setAiStatus('AI request already running…', 'warning');
            return;
        }

        aiBusy = true;
        setAiStatus('Contacting AI…', 'info');

        fetch(aiUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                path: assetPath,
                content: content
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data && data.suggestion) {
                const position = editorInstance.getPosition();
                editorInstance.executeEdits('', [{
                    range: new monaco.Range(position.lineNumber, position.column, position.lineNumber, position.column),
                    text: data.suggestion
                }]);
                setAiStatus('Suggestion inserted.', 'success');
                setDirty(true);
            } else {
                setAiStatus(data && data.error ? data.error : 'No suggestion returned.', 'danger');
            }
        })
        .catch(() => {
            setAiStatus('Unable to reach AI service.', 'danger');
        })
        .finally(() => {
            aiBusy = false;
        });
    }

    if (copyUrlBtn && publicUrlEl) {
        copyUrlBtn.addEventListener('click', function () {
            const url = publicUrlEl.textContent.trim();
            if (!url) {
                return;
            }
            navigator.clipboard.writeText(url)
                .then(() => {
                    copyUrlBtn.classList.remove('btn-outline-secondary');
                    copyUrlBtn.classList.add('btn-success');
                    copyUrlBtn.textContent = 'Copied!';
                    setTimeout(() => {
                        copyUrlBtn.classList.remove('btn-success');
                        copyUrlBtn.classList.add('btn-outline-secondary');
                        copyUrlBtn.innerHTML = '<i class="bi bi-link-45deg me-1"></i>Copy URL';
                    }, 1500);
                })
                .catch(() => {
                    copyUrlBtn.innerHTML = '<i class="bi bi-exclamation-triangle me-1"></i>Failed';
                });
        });
    }
})();
</script>
