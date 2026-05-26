<!-- JS for Monaco Editor -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/monaco-editor/0.30.1/min/vs/loader.js"></script>
<script>
    require.config({ paths: { 'vs': 'https://cdnjs.cloudflare.com/ajax/libs/monaco-editor/0.30.1/min/vs' }});
</script>

<script src="https://cdnjs.cloudflare.com/ajax/libs/monaco-editor/0.30.1/min/vs/editor/editor.main.nls.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/monaco-editor/0.30.1/min/vs/editor/editor.main.js"></script>
<!-- JS for Monaco Editor -->

<script>
var myModal;
var themeAiStatusEl = document.getElementById('theme-ai-status');
var themeAiButton = document.getElementById('theme-ai-btn');
var themeAiBusy = false;
var themeSaveStatusEl = document.getElementById('theme-save-status');
var themeSaveResetTimer = null;
document.addEventListener("DOMContentLoaded", function() {
    // Initialize Bootstrap Modal
    myModal = new bootstrap.Modal(document.getElementById('findReplaceModal'), {
        keyboard: false
    });
});

var editorTpl;
var themeVersionButton = document.getElementById('theme-version-btn');

require(['vs/editor/editor.main'], function() {
        editorTpl = monaco.editor.create(document.getElementById('code_tpl'), {
        value: <?php echo json_encode($this->runData["data"]["code_tpl"]); ?>,
        language: 'html',
        theme: 'vs-dark',
        automaticLayout: true,
        wordWrap: 'on'
    });

    editorTpl.onDidChangeModelContent(function() {
        saveContent('tpl', editorTpl.getValue(), false);
    });

    editorTpl.onKeyUp(function(e) {
        if (e.shiftKey && e.code === 'Space') {
            e.preventDefault();
            e.stopPropagation();
            requestCodeAssistance(editorTpl);
        }
    });

    if (themeAiButton) {
        themeAiButton.addEventListener('click', function(e) {
            e.preventDefault();
            requestCodeAssistance(editorTpl);
            editorTpl.focus();
        });
    }

    window.performUndo = function() {
        editorTpl.trigger('anyString', 'undo', {});
    };

    window.performRedo = function() {
        editorTpl.trigger('anyString', 'redo', {});
    };

    window.toggleComment = function() {
        editorTpl.trigger('', 'editor.action.commentLine');
    };

    // Format Code
    window.formatCode = function () {
        editorTpl.getAction('editor.action.formatDocument').run();
    };

    // Go To Line
    var currentDecorations = [];

    window.goToLine = function () {
        const line = prompt('Enter the line number:', 1);
        if (line) {
            editorTpl.focus(); // Ensure the editor has focus
            editorTpl.revealLineInCenter(Number(line));

            // Set cursor position to the line
            editorTpl.setPosition({ lineNumber: Number(line), column: 1 });
            
            // Remove previous decorations
            editorTpl.deltaDecorations(currentDecorations, []);

            // Add new decorations
            currentDecorations = editorTpl.deltaDecorations([], [
                { range: new monaco.Range(Number(line), 1, Number(line), 1), options: { isWholeLine: true, linesDecorationsClassName: 'myLineDecoration' } }
            ]);
        }
    };


    window.toggleLineWrap = function() {
        const currentModel = editorTpl.getModel();
        const newState = (editorTpl.getRawOptions().wordWrap === 'on') ? 'off' : 'on';
        editorTpl.updateOptions({ wordWrap: newState });
    };

    // Find & Replace
    window.findAndReplace = function () {
        const findTerm = document.getElementById('findTerm').value;
        const replaceTerm = document.getElementById('replaceTerm').value;
    
        if (findTerm && replaceTerm) {
            const model = editorTpl.getModel();
            const findMatches = model.findMatches(findTerm, false, false, false, null, true);
    
            // Apply edits
            const edits = findMatches.map(match => {
                return {
                    range: match.range,
                    text: replaceTerm
                };
            });
    
            model.pushEditOperations([], edits, () => null);
            myModal.hide();
        }
    };

    if (themeVersionButton) {
        themeVersionButton.addEventListener('click', function() {
            if (!editorTpl) return;
            saveContent('tpl', editorTpl.getValue(), true);
        });
    }

    function saveContent(type, content, createVersion) {
        setThemeSaveStatus(createVersion ? 'Saving & creating version…' : 'Saving…', 'info');
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
            setThemeSaveStatus('All changes saved.', 'success');
            scheduleThemeSaveReset();
        })
        .catch((error) => {
            console.error('Error:', error);
            setThemeSaveStatus('Save failed. Changes may be pending…', 'danger');
        });
    }

    function requestCodeAssistance(editor) {
        if (!editor) {
            setThemeAiStatus('Editor unavailable for AI assist.', 'danger');
            return;
        }

        const content = editor.getValue();
        if (!content.trim()) {
            setThemeAiStatus('Enter some template code before requesting AI.', 'danger');
            return;
        }

        if (themeAiBusy) {
            setThemeAiStatus('AI request already running…', 'warning');
            return;
        }

        themeAiBusy = true;
        setThemeAiStatus('Contacting AI…', 'info');

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
                setThemeAiStatus('Suggestion inserted. Press Ctrl+Z to undo.', 'success');
            } else {
                setThemeAiStatus(data.error || 'AI did not return a suggestion.', 'danger');
            }
        })
        .catch((error) => {
            console.error('Error:', error);
            setThemeAiStatus('Unable to reach AI service.', 'danger');
        })
        .finally(() => {
            themeAiBusy = false;
        });
    }

    function setThemeAiStatus(message, tone = 'muted') {
        if (!themeAiStatusEl) {
            return;
        }
        const toneMap = {
            success: 'text-success',
            danger: 'text-danger',
            info: 'text-primary',
            warning: 'text-warning'
        };
        themeAiStatusEl.className = 'small ' + (toneMap[tone] || 'text-muted');
        themeAiStatusEl.textContent = message;
    }

    function setThemeSaveStatus(message, tone = 'muted') {
        if (!themeSaveStatusEl) {
            return;
        }
        const toneMap = {
            success: 'text-success',
            danger: 'text-danger',
            info: 'text-primary',
            warning: 'text-warning'
        };
        themeSaveStatusEl.className = 'small ' + (toneMap[tone] || 'text-muted');
        themeSaveStatusEl.textContent = message;
    }

    function scheduleThemeSaveReset() {
        if (themeSaveResetTimer) {
            clearTimeout(themeSaveResetTimer);
        }
        themeSaveResetTimer = setTimeout(function() {
            setThemeSaveStatus('All changes saved.', 'muted');
        }, 2000);
    }

    // ...inline completion provider removed. Only Shift+Space will trigger AI suggestions now...
});
</script>
