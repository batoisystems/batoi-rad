<!-- JS for Monaco Editor -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/monaco-editor/0.30.1/min/vs/loader.js"></script>
<script>
    require.config({ paths: { 'vs': 'https://cdnjs.cloudflare.com/ajax/libs/monaco-editor/0.30.1/min/vs' }});
</script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/monaco-editor/0.30.1/min/vs/editor/editor.main.nls.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/monaco-editor/0.30.1/min/vs/editor/editor.main.js"></script>

<script>
    var editorLoad, editorPagepart, editorPrepart, editorPostpart;
    var routeAiStatusEl = document.getElementById('route-ai-status');
    var routeAiButton = document.getElementById('route-ai-btn');
    var routeAiBusy = false;
    var activeRouteEditor = null;
    var activeRouteType = 'load';
    var routeSaveStatusEl = document.getElementById('route-save-status');
    var routeSaveResetTimer = null;
    var routeVersionButton = document.getElementById('route-version-btn');

    require(['vs/editor/editor.main'], function() {
        editorLoad = monaco.editor.create(document.getElementById('code_load'), {
            value: <?php echo json_encode($this->runData["data"]["code_load"]); ?>,
            language: 'php',
            theme: 'vs-dark',
            automaticLayout: true
        });

        editorPagepart = monaco.editor.create(document.getElementById('code_pagepart'), {
            value: <?php echo json_encode($this->runData["data"]["code_pagepart"]); ?>,
            language: 'html',
            theme: 'vs-dark',
            automaticLayout: true
        });

        editorPrepart = monaco.editor.create(document.getElementById('code_prepart'), {
            value: <?php echo json_encode($this->runData["data"]["code_prepart"]); ?>,
            language: 'css',
            theme: 'vs-dark',
            automaticLayout: true
        });

        editorPostpart = monaco.editor.create(document.getElementById('code_postpart'), {
            value: <?php echo json_encode($this->runData["data"]["code_postpart"]); ?>,
            language: 'javascript',
            theme: 'vs-dark',
            automaticLayout: true
        });

        [
            {editor: editorLoad, type: 'load'},
            {editor: editorPagepart, type: 'pagepart'},
            {editor: editorPrepart, type: 'prepart'},
            {editor: editorPostpart, type: 'postpart'}
        ].forEach(function(pair) {
            pair.editor.onDidFocusEditorWidget(function() {
                activeRouteEditor = pair.editor;
                activeRouteType = pair.type;
            });
        });

        // Add change event listeners to each editor
        editorLoad.onDidChangeModelContent(function() {
            saveContent('load', editorLoad.getValue(), false);
        });

        editorPagepart.onDidChangeModelContent(function() {
            saveContent('pagepart', editorPagepart.getValue(), false);
        });

        editorPrepart.onDidChangeModelContent(function() {
            saveContent('prepart', editorPrepart.getValue(), false);
        });

        editorPostpart.onDidChangeModelContent(function() {
            saveContent('postpart', editorPostpart.getValue(), false);
        });

        // Only trigger AI assist on Shift+Space for each editor
        [editorLoad, editorPagepart, editorPrepart, editorPostpart].forEach(editor => {
            editor.onKeyUp(function(e) {
                if (e.shiftKey && e.code === 'Space') {
                    e.preventDefault();
                    e.stopPropagation();
                    requestCodeAssistance(editor);
                }
            });
        });
    });

    if (routeAiButton) {
        routeAiButton.addEventListener('click', function(e) {
            e.preventDefault();
            const targetEditor = activeRouteEditor || editorLoad;
            if (targetEditor) {
                requestCodeAssistance(targetEditor);
                targetEditor.focus();
            } else {
                setRouteAiStatus('Open an editor before requesting AI.', 'danger');
            }
        });
    }

    if (routeVersionButton) {
        routeVersionButton.addEventListener('click', function() {
            var editor = activeRouteEditor || editorLoad;
            var type = activeRouteType || 'load';
            if (!editor) {
                setRouteSaveStatus('Open an editor before saving.', 'danger');
                return;
            }
            saveContent(type, editor.getValue(), true);
        });
    }

    function saveContent(type, content, createVersion) {
        setRouteSaveStatus((createVersion ? 'Saving & versioning ' : 'Saving ') + routeLabel(type) + '…', 'info');
        fetch('<?php print $postThroughAjaxUrl;?>', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                type: type,
                content: content,
                create_version: createVersion ? 1 : 0
            })
        })
        .then(response => response.json())
        .then(data => {
            setRouteSaveStatus(routeLabel(type) + ' saved.', 'success');
            scheduleRouteSaveReset();
        })
        .catch((error) => {
            console.error('Error:', error);
            setRouteSaveStatus('Save failed for ' + routeLabel(type) + '.', 'danger');
        });
    }

    function requestCodeAssistance(editor) {
        if (!editor) {
            setRouteAiStatus('No editor available for AI assist.', 'danger');
            return;
        }

        const content = editor.getValue();
        if (!content.trim()) {
            setRouteAiStatus('Add some code before requesting AI.', 'danger');
            return;
        }

        if (routeAiBusy) {
            setRouteAiStatus('AI request in progress…', 'warning');
            return;
        }

        routeAiBusy = true;
        setRouteAiStatus('Contacting AI…', 'info');

        fetch('<?php print $aiAssistanceUrl;?>', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({ content: content })
        })
        .then(response => response.json())
        .then(data => {
            if (data.suggestion) {
                const currentPosition = editor.getPosition();
                editor.executeEdits('', [{ range: new monaco.Range(currentPosition.lineNumber, currentPosition.column, currentPosition.lineNumber, currentPosition.column), text: data.suggestion }]);
                setRouteAiStatus('Suggestion inserted. Press Ctrl+Z to undo.', 'success');
            } else {
                setRouteAiStatus(data.error || 'AI did not return a suggestion.', 'danger');
            }
        })
        .catch((error) => {
            console.error('Error:', error);
            setRouteAiStatus('Unable to reach AI service.', 'danger');
        })
        .finally(() => {
            routeAiBusy = false;
        });
    }

    function setRouteAiStatus(message, tone = 'muted') {
        if (!routeAiStatusEl) {
            return;
        }
        const toneMap = {
            success: 'text-success',
            danger: 'text-danger',
            info: 'text-primary',
            warning: 'text-warning'
        };
        routeAiStatusEl.className = 'small ' + (toneMap[tone] || 'text-muted');
        routeAiStatusEl.textContent = message;
    }

    function setRouteSaveStatus(message, tone = 'muted') {
        if (!routeSaveStatusEl) {
            return;
        }
        const toneMap = {
            success: 'text-success',
            danger: 'text-danger',
            info: 'text-primary',
            warning: 'text-warning'
        };
        routeSaveStatusEl.className = 'small ' + (toneMap[tone] || 'text-muted');
        routeSaveStatusEl.textContent = message;
    }

    function scheduleRouteSaveReset() {
        if (routeSaveResetTimer) {
            clearTimeout(routeSaveResetTimer);
        }
        routeSaveResetTimer = setTimeout(function() {
            setRouteSaveStatus('All changes saved.', 'muted');
        }, 2000);
    }

    function routeLabel(type) {
        switch (type) {
            case 'load':
                return 'Load code';
            case 'pagepart':
                return 'Pagepart code';
            case 'prepart':
                return 'Prepart code';
            case 'postpart':
                return 'Postpart code';
            default:
                return 'Route code';
        }
    }

    function performUndo(editor) {
        editor.trigger('keyboard', 'undo', null);
    }

    function performRedo(editor) {
        editor.trigger('keyboard', 'redo', null);
    }

    function toggleComment(editor) {
        editor.trigger('keyboard', 'editor.action.commentLine', null);
    }

    function formatCode(editor) {
        editor.trigger('keyboard', 'editor.action.formatDocument', null);
    }

    function goToLine(editor) {
        var line = prompt("Enter line number:");
        if (line !== null) {
            editor.revealLineInCenter(parseInt(line));
            editor.setPosition({ lineNumber: parseInt(line), column: 1 });
            editor.focus();
        }
    }

    function toggleLineWrap(editor) {
        var currentWrap = editor.getRawOptions().wordWrap;
        editor.updateOptions({ wordWrap: currentWrap === 'on' ? 'off' : 'on' });
    }

    function findAndReplace() {
        var findTerm = document.getElementById('findTerm').value;
        var replaceTerm = document.getElementById('replaceTerm').value;
        [editorLoad, editorPagepart, editorPrepart, editorPostpart].forEach(editor => {
            var model = editor.getModel();
            var matches = model.findMatches(findTerm, true, false, false, null, true);
            editor.executeEdits('', matches.map(match => {
                return {
                    range: match.range,
                    text: replaceTerm
                };
            }));
        });
    }

    function toggleExpand(panel) {
        var leftPanel = document.getElementById('left-panel');
        var rightPanel = document.getElementById('right-panel');
        if (panel === 'left') {
            leftPanel.classList.toggle('col-md-12');
            leftPanel.classList.toggle('col-md-6');
            rightPanel.classList.toggle('d-none');
        } else if (panel === 'right') {
            rightPanel.classList.toggle('col-md-12');
            rightPanel.classList.toggle('col-md-6');
            leftPanel.classList.toggle('d-none');
        }
    }
</script>
