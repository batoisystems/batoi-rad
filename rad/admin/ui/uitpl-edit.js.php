<script src="https://cdnjs.cloudflare.com/ajax/libs/monaco-editor/0.30.1/min/vs/loader.js"></script>
<script>
    require.config({ paths: { 'vs': 'https://cdnjs.cloudflare.com/ajax/libs/monaco-editor/0.30.1/min/vs' }});
</script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/monaco-editor/0.30.1/min/vs/editor/editor.main.nls.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/monaco-editor/0.30.1/min/vs/editor/editor.main.js"></script>
<script>
(function () {
    const editorContainer = document.getElementById('uitpl-editor');
    const hiddenInput = document.getElementById('uitpl-editor-input');
    const form = document.getElementById('uitpl-edit-form');
    if (!editorContainer || !hiddenInput || !form) {
        return;
    }
    const language = editorContainer.dataset.language || 'php';
    const initialContent = editorContainer.dataset.content || '';
    let editorInstance;

    require(['vs/editor/editor.main'], function () {
        editorInstance = monaco.editor.create(editorContainer, {
            value: initialContent,
            language: language,
            theme: 'vs-dark',
            automaticLayout: true,
            wordWrap: 'on',
            minimap: { enabled: false }
        });
    });

    form.addEventListener('submit', function () {
        if (editorInstance) {
            hiddenInput.value = editorInstance.getValue();
        }
    });
})();
</script>
