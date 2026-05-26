<script>
(function() {
    const app = document.getElementById('ai-assist-app');
    if (!app) {
        return;
    }

    const attachments = [];
    const contextUrl = app.dataset.contextUrl;
    const chatUrl = app.dataset.chatUrl;
    const attachmentsContainer = document.getElementById('ai-attachments');
    const attachmentsEmpty = document.getElementById('ai-attachments-empty');
    const responseStatus = document.getElementById('ai-response-status');
    const responseOutput = document.getElementById('ai-response');
    const promptInput = document.getElementById('ai-prompt');
    const runButton = document.getElementById('ai-run-btn');
    const copyButton = document.getElementById('ai-copy-response');
    const clearButton = document.getElementById('ai-clear-response');

    function renderAttachments() {
        attachmentsContainer.innerHTML = '';
        if (attachments.length === 0) {
            attachmentsEmpty.style.display = 'block';
            return;
        }
        attachmentsEmpty.style.display = 'none';
        attachments.forEach((item, index) => {
            const wrapper = document.createElement('div');
            wrapper.className = 'd-flex justify-content-between align-items-start border rounded p-2 mb-2';
            const body = document.createElement('div');
            body.innerHTML = `<strong>${item.label}</strong><br><small class="text-muted">${item.type}</small>`;
            const removeBtn = document.createElement('button');
            removeBtn.type = 'button';
            removeBtn.className = 'btn btn-sm btn-outline-danger';
            removeBtn.innerHTML = '<i class="bi bi-trash"></i>';
            removeBtn.addEventListener('click', () => {
                attachments.splice(index, 1);
                renderAttachments();
            });
            wrapper.appendChild(body);
            wrapper.appendChild(removeBtn);
            attachmentsContainer.appendChild(wrapper);
        });
    }

    function attachContext(type) {
        const select = app.querySelector(`[data-context-select="${type}"]`);
        if (!select || !select.value) {
            setStatus('Select an item before attaching.', true);
            return;
        }

        setStatus('Loading context...', false);
        fetch(contextUrl, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ type: type, id: select.value })
        })
        .then(res => res.json())
        .then(data => {
            if (data.error) {
                setStatus(data.error, true);
                return;
            }
            attachments.push({
                type: data.type || type,
                id: data.id || select.value,
                label: data.label || select.options[select.selectedIndex].text,
                content: data.content || ''
            });
            setStatus('Context attached.', false);
            renderAttachments();
        })
        .catch(() => setStatus('Unable to fetch context.', true));
    }

    function setStatus(message, isError) {
        if (!responseStatus) return;
        responseStatus.className = isError ? 'text-danger small' : 'text-muted small';
        responseStatus.textContent = message;
    }

    function runPrompt() {
        const prompt = promptInput.value.trim();
        if (!prompt) {
            setStatus('Please enter a prompt first.', true);
            promptInput.focus();
            return;
        }

        setStatus('Contacting AI...', false);
        runButton.disabled = true;
        runButton.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Working';

        fetch(chatUrl, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                prompt: prompt,
                attachments: attachments
            })
        })
        .then(res => res.json())
        .then(data => {
            if (data.error) {
                responseOutput.textContent = data.error;
                setStatus('AI error', true);
                return;
            }
            responseOutput.textContent = data.suggestion || '[Empty response]';
            setStatus('Response received.', false);
        })
        .catch(() => {
            responseOutput.textContent = 'Unable to reach AI service.';
            setStatus('AI request failed.', true);
        })
        .finally(() => {
            runButton.disabled = false;
            runButton.innerHTML = '<i class="bi bi-stars me-1"></i>Run Prompt';
        });
    }

    function copyResponse() {
        const text = responseOutput.textContent.trim();
        if (!text) {
            setStatus('Nothing to copy yet.', true);
            return;
        }
        navigator.clipboard.writeText(text)
            .then(() => setStatus('Copied to clipboard.', false))
            .catch(() => setStatus('Unable to copy.', true));
    }

    function clearResponse() {
        responseOutput.textContent = '';
        setStatus('Cleared.', false);
    }

    document.querySelectorAll('[data-context-add]').forEach(btn => {
        btn.addEventListener('click', function() {
            attachContext(this.getAttribute('data-context-add'));
        });
    });
    document.querySelectorAll('[data-context-quick]').forEach(btn => {
        btn.addEventListener('click', function() {
            const type = this.getAttribute('data-context-quick');
            const id = this.dataset.id;
            if (!type || !id) {
                return;
            }
            setStatus('Loading context...', false);
            fetch(contextUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ type, id })
            })
            .then(res => res.json())
            .then(data => {
                if (data.error) {
                    setStatus(data.error, true);
                    return;
                }
                attachments.push({
                    type: data.type || type,
                    id: data.id || id,
                    label: data.label || this.textContent.trim(),
                    content: data.content || ''
                });
                setStatus('Context attached.', false);
                renderAttachments();
            })
            .catch(() => setStatus('Unable to fetch context.', true));
        });
    });

    document.querySelectorAll('[data-ai-prompt]').forEach(btn => {
        btn.addEventListener('click', function() {
            const template = this.getAttribute('data-ai-prompt') || '';
            if (!template) {
                return;
            }
            if (!promptInput.value.trim()) {
                promptInput.value = template;
            } else {
                promptInput.value = promptInput.value.trim() + "\n\n" + template;
            }
            promptInput.focus();
        });
    });

    runButton.addEventListener('click', runPrompt);
    copyButton.addEventListener('click', copyResponse);
    clearButton.addEventListener('click', clearResponse);
})();
</script>
