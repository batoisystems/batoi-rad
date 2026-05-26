<script>
(function(){
    const treeRoot = document.querySelector('.codex-tree-root');
    const treeList = document.getElementById('codex-tree');
    const editorEl = document.getElementById('codex-editor');
    const aiPanel = document.getElementById('codex-ai-panel');
    const toggleAiBtn = document.getElementById('codex-toggle-ai');
    const saveVersionBtn = document.getElementById('codex-save-version');
    const fixBtn = document.getElementById('codex-fix');
    const aiMessages = document.getElementById('codex-ai-messages');
    const aiForm = document.getElementById('codex-ai-form');
    const aiInput = document.getElementById('codex-ai-text');
    const saveStatus = document.getElementById('codex-save-status');
    const activeFileLabel = document.getElementById('codex-active-file');
    const activePathLabel = document.getElementById('codex-active-path');
    const toolLog = document.getElementById('codex-tool-log');
    const diffPanel = document.getElementById('codex-diff-panel');
    const diffTitle = document.getElementById('codex-diff-title');
    const diffSubtitle = document.getElementById('codex-diff-subtitle');
    const diffApplyBtn = document.getElementById('codex-diff-apply');
    const diffCancelBtn = document.getElementById('codex-diff-cancel');
    const diffEditorEl = document.getElementById('codex-diff-editor');
    const rootSwitcher = document.getElementById('codex-root-switcher');
    const treeFilterInput = document.getElementById('codex-tree-filter');
    const treeFilterClear = document.getElementById('codex-tree-filter-clear');
    const treeFileCount = document.getElementById('codex-tree-count');
    const treeFolderCount = document.getElementById('codex-tree-folder-count');
    const treePanel = document.querySelector('.codex-panel.codex-tree');
    const treeToggleBtn = document.getElementById('codex-toggle-tree');
    const aiContext = document.getElementById('codex-ai-context');
    const aiContextPath = document.getElementById('codex-ai-context-path');
    const aiQuickButtons = document.querySelectorAll('[data-codex-prompt]');
    const sendBtn = document.querySelector('.codex-send-btn');
    const toolMenu = document.getElementById('codex-tool-menu');
    const toolMenuTrigger = document.getElementById('codex-tool-trigger');
    const treePlaceholder = '<li class="text-muted small">Select a root to load files.</li>';
    const treeLoading = '<li class="text-muted small">Loading…</li>';
    const csrfMeta = document.querySelector('meta[name="rad-csrf"]');
    const csrfToken = ((csrfMeta && csrfMeta.getAttribute('content')) || window.__RAD_CSRF || '').trim();
    const shell = document.querySelector('.codex-shell');
    const shellAdminUrl = shell ? (shell.getAttribute('data-admin-url') || '') : '';
    const adminBaseUrl = ((shellAdminUrl || window.__RAD_ADMIN_URL || '/rad-admin').replace(/\/$/, '')) || '/rad-admin';
    const MONACO_BASE_URL = 'https://cdnjs.cloudflare.com/ajax/libs/monaco-editor/0.30.1/min/vs';
    if (window.monaco && !window.__MONACO_READY) {
        window.__MONACO_READY = true;
    }

    let editor;
    let activePath = '';
    let activeDisplayPath = '';
    let activeLanguage = 'php';
    let saveTimer;
    let activeRouteType = 'load';
    let diffEditor;
    let diffModels = { original: null, modified: null };
    let pendingPatchAction = null;
    let autocompleteRegistered = false;
    let activeRootKey = (treeRoot && treeRoot.dataset.rootkey) || 'ms';
    const treeCache = {};
    const treeStats = {};
    const msInitialMarkup = treeList ? (treeList.innerHTML || treePlaceholder) : treePlaceholder;
    const chatHistory = [];
    const CHAT_HISTORY_LIMIT = 10;
    const AUTO_SAVE_DELAY = 4000;
    const ROOT_PREFIX = {
        ms: 'ms',
        theme: 'theme',
        upgrade: 'upgrades',
    };
    let autoSaveTimer = null;
    let lastSavedContent = '';
    if (treeList) {
        treeCache[activeRootKey] = treeList.innerHTML || treePlaceholder;
        const initialStats = readStatsFromDom(treeList);
        treeStats[activeRootKey] = initialStats;
        updateTreeMetaDisplay(initialStats);
        applyTreeFilter('');
    }
    updateAiContext('None selected', '');

    function withCsrfHeaders(base) {
        const headers = Object.assign({}, base || {});
        if (csrfToken) {
            headers['X-CSRF-Token'] = csrfToken;
        }
        return headers;
    }

    function adminUrl(path) {
        if (!path) return adminBaseUrl;
        return adminBaseUrl + (path.startsWith('/') ? path : '/' + path);
    }

    function codexFetch(url, options) {
        const opts = Object.assign({ method: 'GET' }, options || {});
        const method = (opts.method || 'GET').toUpperCase();
        let finalUrl = url;
        if (csrfToken && method === 'GET' && finalUrl.indexOf('csrf_token=') === -1) {
            finalUrl += (finalUrl.includes('?') ? '&' : '?') + 'csrf_token=' + encodeURIComponent(csrfToken);
        }
        opts.headers = withCsrfHeaders(opts.headers);
        opts.credentials = opts.credentials || 'same-origin';
        return fetch(finalUrl, opts);
    }

    function ensureMonaco() {
        if (window.monaco) {
            window.__MONACO_READY = true;
            return Promise.resolve(window.monaco);
        }
        if (window.__MONACO_READY) {
            return Promise.resolve(window.monaco);
        }
        if (!window.__MONACO_WAITERS) {
            window.__MONACO_WAITERS = [];
        }
        return new Promise((resolve, reject) => {
            window.__MONACO_WAITERS.push({ resolve, reject });
            if (window.__MONACO_LOADING) {
                return;
            }
            window.__MONACO_LOADING = true;

            const onReady = () => {
                window.__MONACO_READY = true;
                const waiters = window.__MONACO_WAITERS || [];
                while (waiters.length) {
                    waiters.shift().resolve(window.monaco);
                }
            };

            const onError = (err) => {
                const waiters = window.__MONACO_WAITERS || [];
                while (waiters.length) {
                    waiters.shift().reject(err || new Error('Unable to load Monaco.'));
                }
            };

            const configureAndLoad = () => {
                if (!window.__MONACO_CONFIGURED && window.require && window.require.config) {
                    window.require.config({ paths: { 'vs': MONACO_BASE_URL } });
                    window.__MONACO_CONFIGURED = true;
                }
                window.require(['vs/editor/editor.main'], () => {
                    window.__MONACO_LOADING = false;
                    onReady();
                }, onError);
            };

            if (window.require && window.require.config) {
                configureAndLoad();
            } else {
                const script = document.createElement('script');
                script.src = MONACO_BASE_URL + '/loader.js';
                script.onload = configureAndLoad;
                script.onerror = onError;
                document.head.appendChild(script);
            }
        });
    }

    function initMonaco() {
        if (!editorEl) {
            console.error('Editor element missing.');
            return;
        }
        if (editor) {
            return;
        }
        editor = monaco.editor.create(editorEl, {
            value: '',
            language: 'php',
            theme: 'vs-dark',
            automaticLayout: true,
            wordWrap: 'on',
            minimap: { enabled: false }
        });
        editor.onDidChangeModelContent(function() {
            setStatus('Unsaved changes', 'warning');
            scheduleAutoSave();
        });
    }

    function ensureDiffEditor() {
        if (!diffEditor) {
            diffEditor = monaco.editor.createDiffEditor(diffEditorEl, {
                automaticLayout: true,
                readOnly: true,
                renderSideBySide: true,
                minimap: { enabled: false },
            });
        }
    }

    function fetchTree(node) {
        const relPath = node.dataset.path || '';
        const ul = node.querySelector('ul');
        if (ul && ul.dataset.loaded) {
            ul.classList.toggle('show');
            persistTreeCache();
            return;
        }
        const rootKey = activeRootKey || (treeRoot ? treeRoot.dataset.rootkey : 'ms');
        const cacheKey = `${rootKey}:${relPath || '/'}`;
        codexFetch(adminUrl('/filemanager/tree') + '?root=' + encodeURIComponent(rootKey) + '&path=' + encodeURIComponent(relPath))
            .then(res => res.json())
            .then(data => {
                const treeData = data.tree || [];
                ul.innerHTML = buildTreeHtml(treeData);
                ul.dataset.loaded = '1';
                ul.classList.add('show');
                persistTreeCache();
                treeStats[cacheKey] = countTreeData(treeData);
                updateTreeMetaDisplay(treeStats[cacheKey]);
                applyTreeFilter(treeFilterInput ? treeFilterInput.value : '');
            });
    }

    function buildTreeHtml(items) {
        if (!items.length) {
            return '<li class="text-muted small">Empty directory.</li>';
        }
        const rootPrefix = ROOT_PREFIX[activeRootKey] || ROOT_PREFIX.ms;
        return items.map(item => {
            const rawPath = item.path || '';
            const childPath = escapeHtml(rawPath);
            const displayPath = `${rootPrefix}/${rawPath}`;
            const label = item.type === 'file'
                ? escapeHtml(formatFileLabel(displayPath))
                : escapeHtml(item.name || '');
            if (item.type === 'directory') {
                return `<li class="codex-tree-item codex-tree-folder" data-type="dir" data-path="${childPath}">
                            <i class="bi bi-folder me-1"></i><span>${label}</span>
                            <ul class="list-unstyled ms-3 mt-2 collapse"></ul>
                        </li>`;
            }
            return `<li class="codex-tree-item codex-tree-file" data-type="file" data-path="${childPath}">
                        <i class="bi bi-file-earmark me-1"></i><span>${label}</span>
                    </li>`;
        }).join('');
    }

    function escapeHtml(value) {
        return (value || '').replace(/[&<>"']/g, function(ch) {
            switch (ch) {
                case '&': return '&amp;';
                case '<': return '&lt;';
                case '>': return '&gt;';
                case '"': return '&quot;';
                case "'": return '&#39;';
                default: return ch;
            }
        });
    }

    function setActiveRoot(rootKey) {
        if (!rootKey || !treeList || rootKey === activeRootKey) return;
        persistTreeCache();
        activeRootKey = rootKey;
        if (treeRoot) {
            treeRoot.dataset.rootkey = rootKey;
        }
        updateRootButtons(rootKey);
        if (treeCache[rootKey]) {
            treeList.innerHTML = treeCache[rootKey];
            return;
        }
        if (rootKey === 'ms' && msInitialMarkup) {
            treeCache[rootKey] = msInitialMarkup;
            treeList.innerHTML = msInitialMarkup;
            persistTreeCache();
            return;
        }
        loadRootTree(rootKey, true);
    }

    function updateRootButtons(activeKey) {
        if (!rootSwitcher) return;
        rootSwitcher.querySelectorAll('button[data-rootkey]').forEach(btn => {
            btn.classList.toggle('active', btn.dataset.rootkey === activeKey);
        });
    }

    function loadRootTree(rootKey, force = false) {
        if (!treeList) return;
        const targetRoot = rootKey || activeRootKey;
        if (!targetRoot) return;
        if (!force && treeCache[targetRoot]) {
            if (targetRoot === activeRootKey) {
                treeList.innerHTML = treeCache[targetRoot];
                updateTreeMetaDisplay(treeStats[targetRoot] || readStatsFromDom(treeList));
                applyTreeFilter(treeFilterInput ? treeFilterInput.value : '');
            }
            return;
        }
        if (targetRoot === activeRootKey) {
            treeList.innerHTML = treeLoading;
        }
        setStatus('Loading file tree…', 'info');
        codexFetch(adminUrl('/filemanager/tree') + '?root=' + encodeURIComponent(targetRoot))
            .then(res => res.json())
            .then(data => {
                if (data.error) {
                    if (targetRoot === activeRootKey) {
                        treeList.innerHTML = `<li class="text-danger small">${escapeHtml(data.error)}</li>`;
                    }
                    setStatus(data.error, 'danger');
                    return;
                }
                const treeData = data.tree || [];
                const html = buildTreeHtml(treeData);
                treeCache[targetRoot] = html;
                treeStats[targetRoot] = countTreeData(treeData);
                if (targetRoot === activeRootKey) {
                    treeList.innerHTML = html;
                    persistTreeCache();
                    updateTreeMetaDisplay(treeStats[targetRoot]);
                    applyTreeFilter(treeFilterInput ? treeFilterInput.value : '');
                }
                setStatus('Tree loaded', 'success');
            })
            .catch(() => {
                if (targetRoot === activeRootKey) {
                    treeList.innerHTML = '<li class="text-danger small">Unable to load tree.</li>';
                }
                setStatus('Unable to load tree', 'danger');
            });
    }

    function persistTreeCache() {
        if (!treeList) return;
        treeCache[activeRootKey] = treeList.innerHTML || treePlaceholder;
    }

    function countTreeData(nodes) {
        let files = 0;
        let folders = 0;
        (nodes || []).forEach(node => {
            if (node.type === 'directory') {
                folders++;
                const result = countTreeData(node.children || []);
                files += result.files;
                folders += result.folders;
            } else {
                files++;
            }
        });
        return { files, folders };
    }

    function readStatsFromDom(root) {
        if (!root) return { files: 0, folders: 0 };
        const files = root.querySelectorAll('li.codex-tree-item[data-type="file"]:not(.filtered-out)').length;
        const folders = root.querySelectorAll('li.codex-tree-item[data-type="dir"], li.codex-tree-item[data-type="ms"]').length;
        return { files, folders };
    }

    function updateTreeMetaDisplay(stats) {
        if (!treeFileCount || !treeFolderCount) return;
        const files = stats?.files ?? 0;
        const folders = stats?.folders ?? 0;
        treeFileCount.textContent = `${files} file${files === 1 ? '' : 's'}`;
        treeFolderCount.textContent = `${folders} folder${folders === 1 ? '' : 's'}`;
    }

    function applyTreeFilter(term) {
        if (!treeList) return;
        const query = (term || '').trim().toLowerCase();
        const items = Array.from(treeList.querySelectorAll('li.codex-tree-item'));
        if (!query) {
            items.forEach(li => li.classList.remove('filtered-out'));
            return;
        }
        items.forEach(li => li.classList.add('filtered-out'));
        items.forEach(li => {
            const label = (li.textContent || '').toLowerCase();
            if (label.includes(query)) {
                revealNode(li);
            }
        });
    }

    function revealNode(li) {
        if (!li) return;
        li.classList.remove('filtered-out');
        const parentLi = li.parentElement ? li.parentElement.closest('li.codex-tree-item') : null;
        if (parentLi) {
            parentLi.classList.remove('filtered-out');
            const childContainer = parentLi.querySelector('ul');
            if (childContainer && !childContainer.classList.contains('show')) {
                childContainer.classList.add('show');
            }
            revealNode(parentLi);
        }
    }

    function describeFile(path) {
        const base = (path || '').split('/').pop() || '';
        const baseNoExt = base.replace(/\.[^.]+$/, '');
        const ms = (path || '').split('/')[0] || '';
        const routeMatch = base.match(/^route\.(\d+)\.(pagepart|prepart|postpart|php)$/i);
        if (routeMatch) {
            const id = routeMatch[1];
            const type = routeMatch[2].toLowerCase();
            const typeLabel = {
                php: 'Route Load',
                pagepart: 'Page Part',
                prepart: 'Pre Part',
                postpart: 'Post Part'
            }[type] || 'Route File';
            return `${ms ? ms + ' • ' : ''}${typeLabel} #${id}`;
        }
        const controllerMatch = base.match(/^([A-Za-z0-9_-]+)\.cls\.php$/i);
        if (controllerMatch) {
            const name = controllerMatch[1];
            return `${ms ? ms + ' • ' : ''}Controller ${name}`;
        }
        const ctrlMatch = base.match(/^controller\.([^.]+)\.php$/i);
        if (ctrlMatch) {
            return `${ms ? ms + ' • ' : ''}Controller ${ctrlMatch[1]}`;
        }
        const tplMatch = base.match(/^(.*)\.(tpl\.php|html\.php)$/i);
        if (tplMatch && ms === 'theme') {
            return `Theme Template • ${tplMatch[1]}`;
        }
        return baseNoExt || path || 'Untitled file';
    }

    function formatFileLabel(path) {
        const friendly = describeFile(path);
        const base = (path || '').split('/').pop() || '';
        if (friendly && friendly !== base && friendly.indexOf('•') !== -1) {
            return friendly;
        }
        const trimmed = base.replace(/\.[^.]+$/, '');
        return trimmed || friendly || 'Untitled';
    }

    function updateAiContext(title, path) {
        if (!aiContext) return;
        const titleNode = aiContext.querySelector('#codex-ai-context-title') || aiContext.querySelector('strong');
        if (titleNode) {
            titleNode.textContent = title || 'None selected';
        }
        if (aiContextPath) {
            aiContextPath.textContent = path || '';
        }
        aiContext.dataset.path = path || '';
    }

    function toRadRelativePath(path) {
        const prefix = ROOT_PREFIX[activeRootKey] || ROOT_PREFIX.ms;
        const trimmed = (path || '').replace(/^\/+/, '');
        return prefix ? `${prefix}/${trimmed}` : trimmed;
    }

    function loadFile(path) {
        if (!path) return;
        const radPath = toRadRelativePath(path);
        setStatus('Loading ' + path + '…', 'info');
        codexFetch(adminUrl('/filemanager/read'), {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'path=' + encodeURIComponent(radPath) + (csrfToken ? '&csrf_token=' + encodeURIComponent(csrfToken) : ''),
        })
        .then(res => res.json())
        .then(data => {
            if (data.error) {
                setStatus(data.error, 'danger');
                return;
            }
            activePath = radPath;
            activeDisplayPath = path;
            activeLanguage = detectLanguage(activePath);
            const displayName = describeFile(activeDisplayPath || activePath);
            activeFileLabel.textContent = displayName;
            activePathLabel.textContent = activeDisplayPath || activePath;
            updateAiContext(displayName, activeDisplayPath || activePath);
            editor.getModel().setValue(data.content || '');
            monaco.editor.setModelLanguage(editor.getModel(), activeLanguage);
            lastSavedContent = data.content || '';
            clearAutoSaveTimer();
            setStatus('File loaded', 'success');
        });
    }

    function detectLanguage(path) {
        if (path.endsWith('.php')) return 'php';
        if (path.endsWith('.js')) return 'javascript';
        if (path.endsWith('.css')) return 'css';
        if (path.endsWith('.html') || path.endsWith('.tpl.php')) return 'html';
        return 'plaintext';
    }

    function saveFile(createVersion, options = {}) {
        if (!activePath) {
            setStatus('Select a file before saving.', 'danger');
            return Promise.reject('No file selected');
        }
        const { auto = false } = options;
        const payload = {
            path: activePath,
            content: editor.getValue(),
            create_version: createVersion ? 1 : 0,
            csrf_token: csrfToken,
        };
        setStatus(auto ? 'Autosaving…' : (createVersion ? 'Saving & versioning…' : 'Saving…'), 'info');
        return codexFetch(adminUrl('/filemanager/write'), {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload),
        })
        .then(res => res.json())
        .then(data => {
            if (data.error) {
                setStatus(data.error, 'danger');
                return Promise.reject(data.error);
            }
            lastSavedContent = editor.getValue();
            setStatus(auto ? 'Autosaved' : 'Saved', 'success');
            scheduleStatusReset();
            return true;
        })
        .catch((err) => {
            setStatus('Save failed', 'danger');
            return Promise.reject(err);
        });
    }

    function setStatus(message, tone) {
        if (!saveStatus) return;
        const toneMap = {
            success: 'text-success',
            danger: 'text-danger',
            info: 'text-primary',
            warning: 'text-warning',
        };
        saveStatus.className = 'small ' + (toneMap[tone] || 'text-muted');
        saveStatus.textContent = message;
    }

    function scheduleStatusReset() {
        if (saveTimer) clearTimeout(saveTimer);
        saveTimer = setTimeout(() => setStatus('Idle', 'muted'), 2000);
    }

    function scheduleAutoSave() {
        if (!activePath || !editor) return;
        if (autoSaveTimer) clearTimeout(autoSaveTimer);
        autoSaveTimer = setTimeout(() => {
            autoSaveTimer = null;
            if (!activePath || !editor) return;
            if (editor.getValue() === lastSavedContent) return;
            saveFile(false, { auto: true }).catch(() => {});
        }, AUTO_SAVE_DELAY);
    }

    function clearAutoSaveTimer() {
        if (autoSaveTimer) {
            clearTimeout(autoSaveTimer);
            autoSaveTimer = null;
        }
    }

    function appendAiMessage(role, text) {
        if (!aiMessages) return;
        const placeholder = aiMessages.querySelector('.codex-chat-placeholder');
        if (placeholder) {
            placeholder.remove();
        }
        const ts = new Date();
        const message = document.createElement('div');
        message.className = 'codex-chat-msg';
        message.dataset.role = (role || '').toLowerCase();
        const label = formatRoleLabel(role);
        const bodyHtml = renderMessageContent(text);
        message.innerHTML = `
            <div class="codex-chat-meta d-flex justify-content-between align-items-start">
                <div class="d-flex align-items-center gap-2">
                    <span class="codex-chat-badge role-${message.dataset.role || 'system'}">${label}</span>
                    <span class="text-muted small">${formatTime(ts)}</span>
                </div>
            </div>
            <div class="codex-chat-body">${bodyHtml}</div>
        `;
        const body = message.querySelector('.codex-chat-body');
        if (body && body.scrollHeight > 360) {
            message.classList.add('is-collapsible');
            body.style.maxHeight = '360px';
            body.style.overflow = 'hidden';
            const toggle = document.createElement('button');
            toggle.type = 'button';
            toggle.className = 'btn btn-link btn-sm codex-chat-toggle px-0';
            toggle.textContent = 'Expand';
            toggle.addEventListener('click', () => {
                const expanded = message.classList.toggle('is-expanded');
                body.style.maxHeight = expanded ? 'none' : '360px';
                body.style.overflow = expanded ? 'visible' : 'hidden';
                toggle.textContent = expanded ? 'Collapse' : 'Expand';
            });
            message.appendChild(toggle);
        }
        aiMessages.appendChild(message);
        aiMessages.scrollTop = aiMessages.scrollHeight;
    }

    function formatRoleLabel(role) {
        const r = (role || '').toLowerCase();
        if (r === 'you' || r === 'user') return 'You';
        if (r === 'codex' || r === 'assistant') return 'Codex';
        if (r.indexOf('tool') === 0) return 'Tool';
        return role || 'System';
    }

    function formatTime(date) {
        const d = date instanceof Date ? date : new Date();
        const hh = String(d.getHours()).padStart(2, '0');
        const mm = String(d.getMinutes()).padStart(2, '0');
        return `${hh}:${mm}`;
    }

    function escapeHtml(str) {
        return (str || '').replace(/[&<>"']/g, (ch) => ({
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#39;',
        }[ch]));
    }

    function renderMessageContent(text) {
        if (!text) return '<em class="text-muted">Empty reply.</em>';
        const blocks = [];
        const fence = /```([\w.+-]*)?\n([\s\S]*?)```/g;
        let lastIndex = 0;
        let match;
        while ((match = fence.exec(text)) !== null) {
            if (match.index > lastIndex) {
                blocks.push({ type: 'text', content: text.slice(lastIndex, match.index) });
            }
            blocks.push({ type: 'code', lang: match[1] || '', content: match[2] || '' });
            lastIndex = match.index + match[0].length;
        }
        if (lastIndex < text.length) {
            blocks.push({ type: 'text', content: text.slice(lastIndex) });
        }
        return blocks.map(block => {
            if (block.type === 'code') {
                const code = escapeHtml(block.content);
                const lang = block.lang ? `<span class="codex-chat-code-lang">${escapeHtml(block.lang)}</span>` : '';
                return `
                    <div class="codex-chat-code card card-body py-2 px-3 mb-2">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span class="text-muted small">Code${lang ? ` • ${lang}` : ''}</span>
                            <button type="button" class="btn btn-link btn-sm p-0 codex-copy-code" data-copy="${encodeURIComponent(block.content)}">
                                Copy
                            </button>
                        </div>
                        <pre class="mb-0"><code>${code}</code></pre>
                    </div>
                `;
            }
            return renderInlineFormatted(block.content);
        }).join('');
    }

    function renderInlineFormatted(text) {
        const safe = escapeHtml(text);
        const withInline = safe
            .replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>')
            .replace(/\*(.+?)\*/g, '<em>$1</em>')
            .replace(/`([^`]+)`/g, '<code>$1</code>');
        const lines = withInline.split(/\r?\n/);
        let html = '';
        let inUl = false;
        let inOl = false;
        const closeLists = () => {
            if (inUl) { html += '</ul>'; inUl = false; }
            if (inOl) { html += '</ol>'; inOl = false; }
        };
        lines.forEach((line) => {
            const trimmed = line.trim();
            if (/^(?:-|\*)\s+/.test(trimmed)) {
                if (!inUl) { closeLists(); html += '<ul class="codex-chat-list">'; inUl = true; }
                html += `<li>${trimmed.replace(/^(?:-|\*)\s+/, '')}</li>`;
            } else if (/^\d+[.)]\s+/.test(trimmed)) {
                if (!inOl) { closeLists(); html += '<ol class="codex-chat-list">'; inOl = true; }
                html += `<li>${trimmed.replace(/^\d+[.)]\s+/, '')}</li>`;
            } else if (trimmed) {
                closeLists();
                html += `<p class="mb-1">${trimmed}</p>`;
            }
        });
        closeLists();
        return html || '<p class="mb-1 text-muted">…</p>';
    }

    if (treeList) {
        treeList.addEventListener('click', function(e) {
            const li = e.target.closest('.codex-tree-item');
            if (!li) return;
            const type = li.dataset.type;
            if (type === 'ms' || type === 'dir') {
                fetchTree(li);
            } else if (type === 'file') {
                loadFile(li.dataset.path);
            }
            persistTreeCache();
        });
    }
    if (treeFilterInput) {
        treeFilterInput.addEventListener('input', (e) => {
            applyTreeFilter(e.target.value);
        });
    }
    if (treeFilterClear) {
        treeFilterClear.addEventListener('click', () => {
            if (treeFilterInput) {
                treeFilterInput.value = '';
            }
            applyTreeFilter('');
        });
    }
    const updateTreeToggleIcon = () => {
        if (!treeToggleBtn || !treePanel || !shell) return;
        const collapsed = treePanel.classList.contains('collapsed');
        treeToggleBtn.innerHTML = collapsed
            ? '<i class="bi bi-chevron-double-right"></i>'
            : '<i class="bi bi-chevron-double-left"></i>';
        shell.classList.toggle('tree-panel-collapsed', collapsed);
    };
    const updateAiToggleIcon = () => {
        if (!toggleAiBtn || !aiPanel || !shell) return;
        const collapsed = aiPanel.classList.contains('collapsed');
        toggleAiBtn.innerHTML = collapsed
            ? '<i class="bi bi-chevron-double-left"></i>'
            : '<i class="bi bi-chevron-double-right"></i>';
        shell.classList.toggle('ai-panel-collapsed', collapsed);
    };
    window.codexTogglePanel = function(side, evt) {
        if (evt) evt.preventDefault();
        if (side === 'tree' && treePanel) {
            treePanel.classList.toggle('collapsed');
            updateTreeToggleIcon();
        } else if (side === 'ai' && aiPanel) {
            aiPanel.classList.toggle('collapsed');
            updateAiToggleIcon();
        }
        return false;
    };
    updateTreeToggleIcon();
    updateAiToggleIcon();

    document.getElementById('codex-undo').addEventListener('click', () => editor && editor.trigger('codex', 'undo'));
    document.getElementById('codex-redo').addEventListener('click', () => editor && editor.trigger('codex', 'redo'));
    document.getElementById('codex-format').addEventListener('click', () => editor && editor.getAction('editor.action.formatDocument').run());
    document.getElementById('codex-wrap').addEventListener('click', () => {
        const current = editor.getRawOptions().wordWrap;
        editor.updateOptions({ wordWrap: current === 'on' ? 'off' : 'on' });
    });

    document.getElementById('codex-refresh-tree').addEventListener('click', () => {
        loadRootTree(activeRootKey, true);
    });
    window.addEventListener('keydown', function(e) {
        if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 's') {
            e.preventDefault();
            saveFile(false);
        }
    });
    saveVersionBtn.addEventListener('click', () => saveFile(true));
    fixBtn.addEventListener('click', () => fixSelection());
    let isSubmitting = false;
    let lastPromptSent = '';
    let lastPromptTime = 0;

    function sendPrompt(valueOverride) {
        if (isSubmitting) return;
        const question = (valueOverride !== undefined ? valueOverride : (aiInput ? aiInput.value : '')).trim();
        if (question === '') return;
        const now = Date.now();
        if (question === lastPromptSent && now - lastPromptTime < 800) {
            return;
        }
        lastPromptSent = question;
        lastPromptTime = now;
        isSubmitting = true;
        appendAiMessage('You', question);
        if (aiInput && valueOverride !== undefined) {
            aiInput.value = '';
        }
        codexFetch(adminUrl('/codexapi/chat'), {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                prompt: question,
                file: activePath,
                code: editor.getValue(),
                history: chatHistory,
                csrf_token: csrfToken,
            })
        })
        .then(res => res.json())
        .then(data => {
            recordChatMessage('user', question);
            handleAiResponse(data);
        })
        .catch(() => appendAiMessage('Codex', 'Unable to reach AI proxy.'))
        .finally(() => { isSubmitting = false; });
    }

    window.codexSendMessage = function(evt) {
        if (evt) {
            evt.preventDefault();
            evt.stopPropagation();
        }
        sendPrompt();
        return false;
    };
    if (aiQuickButtons && aiQuickButtons.length) {
        aiQuickButtons.forEach(btn => {
            btn.addEventListener('click', (e) => {
                e.preventDefault();
                e.stopPropagation();
                if (typeof e.stopImmediatePropagation === 'function') {
                    e.stopImmediatePropagation();
                }
                const prompt = btn.getAttribute('data-codex-prompt') || '';
                sendPrompt(prompt);
            });
        });
    }
    window.codexToggleTools = function(evt) {
        if (!toolMenu) return false;
        if (evt) {
            evt.preventDefault();
            evt.stopPropagation();
        }
        toolMenu.classList.toggle('d-none');
        return false;
    };
    if (toolMenu) {
        document.addEventListener('click', (e) => {
            if (toolMenu.classList.contains('d-none')) return;
            if (!toolMenu.contains(e.target) && e.target !== toolMenuTrigger) {
                toolMenu.classList.add('d-none');
            }
        });
    }

    if (toolMenu) {
        toolMenu.addEventListener('click', (e) => {
            const opt = e.target.closest('.codex-tool-option');
            if (!opt) return;
            e.preventDefault();
            e.stopPropagation();
            if (typeof e.stopImmediatePropagation === 'function') {
                e.stopImmediatePropagation();
            }
            const text = opt.getAttribute('data-tool-text') || '';
            sendPrompt(text);
            toolMenu.classList.add('d-none');
        });
    }

    if (aiMessages) {
        aiMessages.addEventListener('click', (e) => {
            const copyBtn = e.target.closest('.codex-copy-code');
            if (copyBtn && copyBtn.dataset.copy) {
                e.preventDefault();
                const raw = decodeURIComponent(copyBtn.dataset.copy || '');
                if (navigator.clipboard && navigator.clipboard.writeText) {
                    navigator.clipboard.writeText(raw).catch(() => {});
                } else {
                    const temp = document.createElement('textarea');
                    temp.value = raw;
                    document.body.appendChild(temp);
                    temp.select();
                    try { document.execCommand('copy'); } catch (err) {}
                    document.body.removeChild(temp);
                }
            }
        });
    }

    if (sendBtn) {
        sendBtn.addEventListener('click', function(e) {
            e.preventDefault();
            sendPrompt();
        });
    }

    aiForm.addEventListener('submit', function(e) {
        e.preventDefault();
        e.stopPropagation();
    });

    if (aiInput) {
        aiInput.addEventListener('keydown', (e) => {
            if ((e.metaKey || e.ctrlKey) && e.key === 'Enter') {
                e.preventDefault();
                e.stopPropagation();
                sendPrompt();
            }
        });
    }

    if (rootSwitcher) {
        rootSwitcher.addEventListener('click', function(e) {
            const btn = e.target.closest('button[data-rootkey]');
            if (!btn) return;
            if (btn.classList.contains('active')) return;
            setActiveRoot(btn.dataset.rootkey);
        });
    }

    ensureMonaco()
        .then(() => {
            initMonaco();
            registerAutocompleteProviders();
            if (treeList && !treeList.children.length) {
                loadRootTree(activeRootKey, true);
            }
        })
        .catch((err) => {
            console.error('Monaco failed to load', err);
            setStatus('Monaco editor failed to load.', 'danger');
        });

    function fixSelection() {
        if (!editor) return;
        const selection = editor.getSelection();
        if (!selection || selection.isEmpty()) {
            setStatus('Select code to fix.', 'danger');
            return;
        }
        const snippet = editor.getModel().getValueInRange(selection);
        codexFetch(adminUrl('/codexapi/fix'), {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                selection: snippet,
                file: activePath,
                csrf_token: csrfToken,
            })
        })
        .then(res => res.json())
        .then(data => {
            if (data.error || !data.patch) {
                setStatus(data.error || 'No patch returned.', 'danger');
                return;
            }
            previewPatch(data.patch, { source: 'Fix Selection' })
                .then(message => setStatus(message || 'Patch applied', 'success'))
                .catch(err => {
                    if (err && err !== 'Patch cancelled by user') {
                        setStatus(err, 'danger');
                    } else {
                        setStatus('Patch cancelled', 'warning');
                    }
                });
        });
    }

    function handleAiResponse(data) {
        const reply = data.reply || data.error || 'No response';
        appendAiMessage('Codex', reply);
        recordChatMessage('assistant', reply);
        const commands = extractToolCommands(reply);
        if (commands.length) {
            executeToolCommands(commands);
        }
    }

    function extractToolCommands(text) {
        const commands = [];
        const fenceMatch = text.match(/```json([\s\S]*?)```/);
        const raw = fenceMatch ? fenceMatch[1] : text;
        try {
            const parsed = JSON.parse(raw.trim());
            if (Array.isArray(parsed)) {
                parsed.forEach(cmd => cmd && cmd.tool && commands.push(cmd));
            } else if (parsed && parsed.tool) {
                commands.push(parsed);
            }
        } catch (e) {
            // ignore
        }
        return commands;
    }

    function executeToolCommands(commands, index = 0) {
        if (index >= commands.length) {
            appendAiMessage('System', 'Tool calls complete.');
            return;
        }
        const cmd = commands[index];
        const entry = createToolLogEntry(cmd);
        appendAiMessage('System', 'Running tool: ' + cmd.tool);
        runTool(cmd).then(result => {
            const resultText = typeof result === 'string' ? result : JSON.stringify(result);
            appendAiMessage('Tool Result', resultText);
            finalizeToolLogEntry(entry, 'success', shortenResult(resultText));
            executeToolCommands(commands, index + 1);
        }).catch(err => {
            finalizeToolLogEntry(entry, 'error', err);
            appendAiMessage('Tool Error', err);
        });
    }

    function runTool(cmd) {
        switch (cmd.tool) {
            case 'read_file':
                return codexFetch(adminUrl('/codexapi/read_file'), {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ path: cmd.args?.path || activePath, csrf_token: csrfToken }),
                }).then(r => r.json()).then(data => data.content || JSON.stringify(data));
            case 'write_file':
                if (!confirmDestructive('write file', cmd.args?.path || activePath)) {
                    return Promise.reject('User cancelled write_file');
                }
                return codexFetch(adminUrl('/codexapi/write_file'), {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        path: cmd.args?.path || activePath,
                        content: cmd.args?.content || editor.getValue(),
                        csrf_token: csrfToken,
                    }),
                }).then(r => r.json()).then(() => 'File written');
            case 'apply_patch':
                return previewPatch(cmd.args?.patch || '', { source: 'AI Tool' });
            case 'search_files':
                return codexFetch(adminUrl('/codexapi/search_files'), {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ query: cmd.args?.query || '', csrf_token: csrfToken }),
                }).then(r => r.json()).then(data => (data.results || []).join('<br>'));
            case 'run_sql':
                return codexFetch(adminUrl('/codexapi/run_sql'), {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ sql: cmd.args?.sql || '', csrf_token: csrfToken }),
                }).then(r => r.json()).then(data => JSON.stringify(data.result || [], null, 2));
            case 'run_php':
                return codexFetch(adminUrl('/codexapi/run_php'), {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ code: cmd.args?.code || '', csrf_token: csrfToken }),
                }).then(r => r.json()).then(data => data.output || JSON.stringify(data));
            default:
                return Promise.resolve('Unknown tool ' + cmd.tool);
        }
    }

    if (diffCancelBtn) {
        diffCancelBtn.addEventListener('click', () => cancelPendingPatch('Patch cancelled by user'));
    }
    if (diffApplyBtn) {
        diffApplyBtn.addEventListener('click', applyPendingPatch);
    }

    function submitPatchToBackend(patch) {
        if (!patch) {
            return Promise.reject('Empty patch.');
        }
        return codexFetch(adminUrl('/codexapi/apply_patch'), {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                path: activePath,
                patch: patch,
                csrf_token: csrfToken,
            }),
        })
        .then(res => res.json())
        .then(data => {
            if (data.error) throw data.error;
            loadFile(activePath);
            return 'Patch applied';
        });
    }

    function previewPatch(patch, meta = {}) {
        if (!patch) {
            return Promise.reject('Empty patch.');
        }
        return new Promise((resolve, reject) => {
            const original = editor.getValue();
            const updated = applyPatchLocally(original, patch);
            if (updated === null) {
                setStatus('Unable to preview patch; applying directly.', 'warning');
                submitPatchToBackend(patch).then(resolve).catch(reject);
                return;
            }
            ensureDiffEditor();
            if (diffModels.original) diffModels.original.dispose();
            if (diffModels.modified) diffModels.modified.dispose();
            diffModels.original = monaco.editor.createModel(original, activeLanguage);
            diffModels.modified = monaco.editor.createModel(updated, activeLanguage);
            diffEditor.setModel({
                original: diffModels.original,
                modified: diffModels.modified,
            });
            if (diffTitle) {
                diffTitle.textContent = meta.title || 'Patch Preview';
            }
            if (diffSubtitle) {
                diffSubtitle.textContent = meta.subtitle || `Source: ${meta.source || 'AI'}`;
            }
            if (diffPanel) {
                diffPanel.classList.remove('d-none');
            }
            pendingPatchAction = { patch, resolve, reject };
        });
    }

    function cancelPendingPatch(message) {
        if (pendingPatchAction && pendingPatchAction.reject) {
            pendingPatchAction.reject(message);
        }
        pendingPatchAction = null;
        hideDiffPanel();
    }

    function applyPendingPatch() {
        if (!pendingPatchAction) {
            hideDiffPanel();
            return;
        }
        if (diffApplyBtn) diffApplyBtn.disabled = true;
        submitPatchToBackend(pendingPatchAction.patch)
            .then(msg => {
                loadFile(activeDisplayPath || activePath);
                if (pendingPatchAction.resolve) pendingPatchAction.resolve(msg);
                hideDiffPanel();
            })
            .catch(err => {
                if (pendingPatchAction.reject) pendingPatchAction.reject(err);
                setStatus(err, 'danger');
                hideDiffPanel();
            })
            .finally(() => {
                if (diffApplyBtn) diffApplyBtn.disabled = false;
                pendingPatchAction = null;
            });
    }

    function hideDiffPanel() {
        if (diffPanel) {
            diffPanel.classList.add('d-none');
        }
        if (diffModels.original) {
            diffModels.original.dispose();
            diffModels.original = null;
        }
        if (diffModels.modified) {
            diffModels.modified.dispose();
            diffModels.modified = null;
        }
    }

    function applyPatchLocally(originalText, patchText) {
        try {
            const originalLines = originalText.split('\n');
            let patchedLines = originalLines.slice();
            const lines = patchText.split('\n');
            let offset = 0;
            for (let i = 0; i < lines.length; i++) {
                const match = lines[i].match(/^@@ -(\d+)(?:,(\d+))? \+(\d+)(?:,(\d+))? @@/);
                if (!match) continue;
                const start = (parseInt(match[1], 10) || 1) - 1 + offset;
                const removeCount = parseInt(match[2] || '1', 10);
                i++;
                const additions = [];
                for (; i < lines.length && !lines[i].startsWith('@@'); i++) {
                    const line = lines[i];
                    if (line === '\\ No newline at end of file') continue;
                    const prefix = line[0];
                    const content = line.substring(1);
                    if (prefix === '+') {
                        additions.push(content);
                    } else if (prefix === ' ') {
                        additions.push(content);
                    }
                }
                i--;
                patchedLines.splice(start, removeCount, ...additions);
                offset += additions.length - removeCount;
            }
            return patchedLines.join('\n');
        } catch (e) {
            return null;
        }
    }

    function createToolLogEntry(cmd) {
        if (!toolLog) return null;
        if (!toolLog.dataset.hasEntries) {
            toolLog.innerHTML = '';
            toolLog.dataset.hasEntries = '1';
        }
        const wrapper = document.createElement('div');
        wrapper.className = 'codex-tool-entry running';
        wrapper.innerHTML = `
            <span class="codex-tool-pill"></span>
            <div>
                <div class="fw-semibold">${cmd.tool}</div>
                <div class="small text-muted codex-tool-note">Executing…</div>
            </div>
        `;
        toolLog.appendChild(wrapper);
        toolLog.scrollTop = toolLog.scrollHeight;
        return wrapper;
    }

    function finalizeToolLogEntry(entry, state, note) {
        if (!entry) return;
        entry.classList.remove('running', 'success', 'error');
        entry.classList.add(state || 'success');
        const noteNode = entry.querySelector('.codex-tool-note');
        if (noteNode) {
            noteNode.textContent = note || (state === 'error' ? 'Failed' : 'Done');
        }
    }

    function shortenResult(resultText) {
        const str = resultText || '';
        return str.length > 120 ? str.slice(0, 117) + '…' : str;
    }

    function confirmDestructive(action, target) {
        if (!target) return true;
        return window.confirm(`Codex is about to ${action}:\n${target}\nProceed?`);
    }

    function recordChatMessage(role, content) {
        if (!content) return;
        chatHistory.push({ role, content });
        trimChatHistory();
    }

    function trimChatHistory() {
        while (chatHistory.length > CHAT_HISTORY_LIMIT) {
            chatHistory.shift();
        }
    }

    function registerAutocompleteProviders() {
        if (autocompleteRegistered || typeof monaco === 'undefined') return;
        const languages = ['php', 'javascript', 'html', 'css'];
        languages.forEach(lang => {
            monaco.languages.registerCompletionItemProvider(lang, {
                triggerCharacters: ['.', '>', ':', '$', '"', "'", '/'],
                provideCompletionItems(model, position) {
                    if (!model) {
                        return { suggestions: [] };
                    }
                    const snippet = model.getValueInRange({
                        startLineNumber: Math.max(1, position.lineNumber - 40),
                        startColumn: 1,
                        endLineNumber: position.lineNumber,
                        endColumn: position.column,
                    }).slice(-1200);
                    if (!snippet.trim()) {
                        return { suggestions: [] };
                    }
                    const cursor = model.getOffsetAt(position);
                    return requestAutocomplete(snippet, lang, cursor)
                        .then(items => ({ suggestions: formatCompletionItems(items) }))
                        .catch(() => ({ suggestions: [] }));
                }
            });
        });
        autocompleteRegistered = true;
    }

    function requestAutocomplete(snippet, language, cursor) {
        if (!snippet) return Promise.resolve([]);
        return codexFetch(adminUrl('/codexapi/autocomplete'), {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                snippet,
                language,
                file: activePath,
                cursor,
                csrf_token: csrfToken,
            }),
        }).then(res => res.json());
    }

    function formatCompletionItems(payload) {
        let items = payload;
        if (payload && Array.isArray(payload.suggestions)) {
            items = payload.suggestions;
        }
        if (!Array.isArray(items)) {
            return [];
        }
        return items.map((item, index) => {
            const candidate = item || {};
            const label = candidate.label || candidate.text || `suggestion-${index + 1}`;
            return {
                label,
                kind: resolveCompletionKind(candidate.kind),
                insertText: candidate.insertText || candidate.text || label,
                detail: candidate.detail || candidate.description || '',
                documentation: candidate.documentation || '',
                sortText: candidate.sortText || String(index).padStart(3, '0'),
            };
        });
    }

    function resolveCompletionKind(kind) {
        if (typeof kind === 'number') {
            return kind;
        }
        const map = {
            function: monaco.languages.CompletionItemKind.Function,
            method: monaco.languages.CompletionItemKind.Method,
            variable: monaco.languages.CompletionItemKind.Variable,
            class: monaco.languages.CompletionItemKind.Class,
            interface: monaco.languages.CompletionItemKind.Interface,
            module: monaco.languages.CompletionItemKind.Module,
            property: monaco.languages.CompletionItemKind.Property,
            keyword: monaco.languages.CompletionItemKind.Keyword,
            snippet: monaco.languages.CompletionItemKind.Snippet,
            text: monaco.languages.CompletionItemKind.Text,
        };
        return map[String(kind || '').toLowerCase()] || monaco.languages.CompletionItemKind.Text;
    }

})();
</script>
