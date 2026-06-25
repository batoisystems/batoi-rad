<?php
$codex = $this->runData['data']['codex'] ?? [];
$defaultRoot = $codex['default_root'] ?? '';
$microservices = $codex['microservices'] ?? [];
$roots = $codex['roots'] ?? [];
$activeRoot = $codex['active_root'] ?? 'ms';
if (!defined('CODEX_MONACO_LOADER')) {
    define('CODEX_MONACO_LOADER', true);
    ?>
    <script src="<?php echo htmlspecialchars(\RadAdmin\RadAdminAssets::monacoBaseUrl($this->runData), ENT_QUOTES, 'UTF-8'); ?>/loader.js"></script>
    <script>
    require.config({ paths: { 'vs': '<?php echo htmlspecialchars(\RadAdmin\RadAdminAssets::monacoBaseUrl($this->runData), ENT_QUOTES, 'UTF-8'); ?>' }});
    </script>
    <script src="<?php echo htmlspecialchars(\RadAdmin\RadAdminAssets::monacoBaseUrl($this->runData), ENT_QUOTES, 'UTF-8'); ?>/editor/editor.main.nls.js"></script>
    <script src="<?php echo htmlspecialchars(\RadAdmin\RadAdminAssets::monacoBaseUrl($this->runData), ENT_QUOTES, 'UTF-8'); ?>/editor/editor.main.js"></script>
    <?php
}
?>
<div class="codex-shell" data-admin-url="<?php echo htmlspecialchars($this->runData['route']['rad_admin_url'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
    <div class="codex-panel codex-tree">
        <div class="codex-panel-header d-flex justify-content-between align-items-center">
            <div>
                <h6 class="mb-0">File Explorer</h6>
            </div>
            <div class="btn-group btn-group-sm">
                <button class="btn btn-outline-secondary" id="codex-refresh-tree" type="button">
                    <i class="bi bi-arrow-repeat"></i>
                </button>
                <button class="btn btn-outline-secondary" id="codex-toggle-tree" type="button" aria-label="Toggle explorer panel"
                        onclick="return window.codexTogglePanel ? window.codexTogglePanel('tree', event) : false;">
                    <i class="bi bi-chevron-double-left"></i>
                </button>
            </div>
        </div>
        <?php if (!empty($roots)) { ?>
            <div class="codex-root-switcher btn-group btn-group-sm w-100" id="codex-root-switcher" role="group" aria-label="Root selector">
                <?php foreach ($roots as $key => $info) { ?>
                    <button type="button"
                            class="btn btn-outline-primary <?php echo $key === $activeRoot ? 'active' : ''; ?>"
                            data-rootkey="<?php echo htmlspecialchars($key); ?>">
                        <?php echo htmlspecialchars($info['label']); ?>
                    </button>
                <?php } ?>
            </div>
        <?php } ?>
        <div class="codex-tree-search mt-2">
            <label class="visually-hidden" for="codex-tree-filter">Search files</label>
            <div class="input-group input-group-sm">
                <span class="input-group-text"><i class="bi bi-search"></i></span>
                <input type="text" id="codex-tree-filter" class="form-control" placeholder="Filter files or folders">
                <button class="btn btn-outline-secondary" type="button" id="codex-tree-filter-clear"><i class="bi bi-x-lg"></i></button>
            </div>
            <div class="codex-tree-meta text-muted small mt-1">
                <span id="codex-tree-count">0 files</span>
                <span class="mx-1 text-muted">•</span>
                <span id="codex-tree-folder-count">0 folders</span>
            </div>
        </div>
        <div class="codex-tree-root" data-root="<?php echo htmlspecialchars($defaultRoot); ?>" data-rootkey="<?php echo htmlspecialchars($activeRoot); ?>">
            <ul class="list-unstyled mb-0" id="codex-tree">
                <?php if (!empty($microservices)) { ?>
                    <?php foreach ($microservices as $ms) { ?>
                        <li class="codex-tree-item codex-tree-folder" data-type="ms" data-path="<?php echo htmlspecialchars($ms); ?>">
                            <i class="bi bi-folder2-open me-1"></i><span><?php echo htmlspecialchars($ms); ?></span>
                            <ul class="list-unstyled ms-3 mt-2 collapse"></ul>
                        </li>
                    <?php } ?>
                <?php } else { ?>
                    <li class="text-muted small">Select a root to load files.</li>
                <?php } ?>
            </ul>
        </div>
    </div>

    <div class="codex-panel codex-editor position-relative">
        <div class="codex-panel-header codex-editor-header">
            <div class="codex-header-top d-flex justify-content-between align-items-center">
                <div class="me-3">
                    <h6 class="mb-0" id="codex-active-file">No file selected</h6>
                    <small class="text-muted" id="codex-active-path"></small>
                </div>
                <div class="d-flex align-items-center gap-2 flex-wrap codex-header-actions">
                    <button class="btn btn-sm btn-warning" id="codex-save-version">
                        <i class="bi bi-save2"></i> Save & Version
                    </button>
                    <div class="btn-group btn-group-sm flex-nowrap" role="group">
                        <button class="btn btn-outline-secondary" id="codex-undo"><i class="bi bi-arrow-counterclockwise"></i></button>
                        <button class="btn btn-outline-secondary" id="codex-redo"><i class="bi bi-arrow-clockwise"></i></button>
                        <button class="btn btn-outline-secondary" id="codex-format"><i class="bi bi-code-square"></i></button>
                        <button class="btn btn-outline-secondary" id="codex-wrap"><i class="bi bi-text-wrap"></i></button>
                        <button class="btn btn-outline-danger" id="codex-fix"><i class="bi bi-magic"></i> Fix Selection</button>
                    </div>
                </div>
            </div>
            <div class="codex-header-status text-muted small" id="codex-save-status">Idle</div>
        </div>
        <div id="codex-editor" class="codex-editor-canvas"></div>
        <div id="codex-diff-panel" class="codex-diff-panel d-none">
            <div class="codex-diff-header d-flex justify-content-between align-items-center">
                <div>
                    <h6 class="mb-0" id="codex-diff-title">Patch Preview</h6>
                    <small class="text-muted" id="codex-diff-subtitle">Review changes before applying</small>
                </div>
                <div class="btn-group btn-group-sm">
                    <button type="button" class="btn btn-outline-secondary" id="codex-diff-cancel">
                        <i class="bi bi-x-lg"></i> Discard
                    </button>
                    <button type="button" class="btn btn-primary" id="codex-diff-apply">
                        <i class="bi bi-check-lg"></i> Apply Patch
                    </button>
                </div>
            </div>
            <div id="codex-diff-editor" class="codex-diff-editor"></div>
        </div>
    </div>

        <div class="codex-panel codex-ai" id="codex-ai-panel">
            <div class="codex-panel-header d-flex justify-content-between align-items-center">
                <div>
                    <h6 class="mb-0">Chat &amp; Tools</h6>
            </div>
            <button class="btn btn-sm btn-outline-secondary" id="codex-toggle-ai" type="button" aria-label="Toggle Codex panel"
                    onclick="return window.codexTogglePanel ? window.codexTogglePanel('ai', event) : false;">
                <i class="bi bi-chevron-double-right"></i>
            </button>
        </div>
        <div class="codex-ai-body">
            <div class="codex-ai-card codex-ai-messages-card">
                <h6 class="fw-semibold small text-muted text-uppercase mb-2">Conversation</h6>
                <div class="codex-ai-messages" id="codex-ai-messages">
                </div>
            </div>
            <form id="codex-ai-form" class="codex-ai-input" onsubmit="return false;">
                <div class="codex-ai-composer">
                    <textarea class="form-control w-100" rows="3" placeholder="Ask Codex to refactor or review..." id="codex-ai-text"></textarea>
                </div>
                <div class="codex-composer-bar d-flex align-items-center mt-2">
                    <div class="btn-group codex-quick-inline" role="group" aria-label="Quick actions">
                        <button type="button" class="btn btn-light codex-icon-btn codex-quick-btn" title="Suggest improvements" data-codex-prompt="Suggest improvements for readability, performance, and security.">
                            <i class="bi bi-stars"></i>
                        </button>
                        <button type="button" class="btn btn-light codex-icon-btn codex-quick-btn" title="Review file" data-codex-prompt="Review this file for bugs and best practices." id="codex-quick-review">
                            <i class="bi bi-search"></i>
                        </button>
                        <button type="button" class="btn btn-light codex-icon-btn codex-quick-btn" title="Summarize file" data-codex-prompt="Summarize what this file does.">
                            <i class="bi bi-list"></i>
                        </button>
                    </div>
                    <div class="dropup ms-2 position-relative">
                        <button type="button" class="btn btn-light codex-icon-btn" id="codex-tool-trigger"
                                onclick="return window.codexToggleTools ? window.codexToggleTools(event) : false;" title="Tools">
                            <i class="bi bi-sliders"></i>
                        </button>
                        <div class="codex-tool-menu dropup-menu shadow-sm d-none" id="codex-tool-menu">
                            <div class="list-group list-group-flush codex-tool-options">
                                <button type="button" class="list-group-item list-group-item-action codex-tool-option" data-tool-text="read_file">read_file</button>
                                <button type="button" class="list-group-item list-group-item-action codex-tool-option" data-tool-text="write_file">write_file</button>
                                <button type="button" class="list-group-item list-group-item-action codex-tool-option" data-tool-text="apply_patch">apply_patch</button>
                                <button type="button" class="list-group-item list-group-item-action codex-tool-option" data-tool-text="search_files">search_files</button>
                                <button type="button" class="list-group-item list-group-item-action codex-tool-option" data-tool-text="run_sql">run_sql</button>
                                <button type="button" class="list-group-item list-group-item-action codex-tool-option" data-tool-text="run_php">run_php</button>
                            </div>
                        </div>
                    </div>
                    <button type="button" class="btn btn-primary codex-send-btn ms-auto" aria-label="Send message"
                            onclick="return window.codexSendMessage ? window.codexSendMessage(event) : false;">
                        <i class="bi bi-send"></i>
                    </button>
                </div>
                <small class="text-muted d-block mt-2 helper-text">Codex remembers the last few replies.</small>
            </form>
            <div class="codex-ai-card codex-ai-tools">
                <h6 class="fw-semibold small text-muted text-uppercase mb-2">Tool Activity</h6>
                <div class="codex-tool-log small text-muted" id="codex-tool-log">
                    <em>No tool activity yet.</em>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.codex-shell {
    display: grid;
    grid-template-columns: 260px minmax(0, 1fr) 320px;
    gap: 1rem;
    height: calc(100vh - 160px);
    min-height: 520px;
    align-items: stretch;
}
.codex-shell.tree-panel-collapsed {
    grid-template-columns: 64px minmax(0, 1fr) 320px;
}
.codex-shell.ai-panel-collapsed {
    grid-template-columns: 260px minmax(0, 1fr) 64px;
}
.codex-panel {
    border: 1px solid #dee2e6;
    border-radius: 0.75rem;
    background: #fff;
    display: flex;
    flex-direction: column;
    overflow: hidden;
    min-height: 0;
}
.codex-panel-header {
    padding: 0.75rem 1rem;
    border-bottom: 1px solid #f1f3f5;
    background: #f8f9fa;
}
.codex-editor-header {
    display: flex;
    flex-direction: column;
    gap: 0.35rem;
}
.codex-header-top {
    min-height: 40px;
}
.codex-header-actions {
    flex-wrap: nowrap;
    gap: 0.4rem;
}
.codex-header-actions .btn-group {
    flex-wrap: nowrap;
}
.codex-header-status {
    min-height: 20px;
    padding-top: 0.1rem;
}
.codex-tree {
    overflow-y: auto;
}
.codex-root-switcher {
    display: flex;
    padding: 0.5rem 1rem 0;
    gap: 0.35rem;
    flex-wrap: wrap;
}
.codex-root-switcher .btn {
    flex: 1 1 auto;
}
.codex-tree-root {
    padding: 0.75rem 1rem;
}
.codex-tree-search .input-group-text,
.codex-tree-search .btn {
    border-radius: 0.5rem;
}
.codex-tree-search input {
    border-radius: 0.5rem;
}
.codex-tree-meta {
    font-size: 0.78rem;
}
.codex-tree-item {
    cursor: pointer;
    padding: 0.25rem 0;
    color: #495057;
}
.codex-tree-item.filtered-out {
    display: none;
}
.codex-tree-item span {
    user-select: none;
}
.codex-tree-item:hover {
    color: #0d6efd;
}
.collapse {
    display: none;
}
.collapse.show {
    display: block;
}
.codex-editor {
    min-height: 520px;
    overflow: hidden;
}
.codex-editor-canvas {
    flex: 1;
    min-height: 480px;
}
.codex-ai {
    font-size: 0.95rem;
    overflow: hidden;
    display: flex;
    flex-direction: column;
    height: 100%;
    min-height: 0;
}

.codex-ai-body {
    display: flex;
    flex-direction: column;
    flex: 1;
    height: 100%;
    min-height: 0;
    padding: 0.35rem 0.65rem 0.45rem;
    gap: 0.4rem;
    overflow-y: auto;
}
.codex-ai-messages {
    flex: 1 1 auto;
    overflow-y: auto;
    border: 1px solid #f1f3f5;
    border-radius: 0.5rem;
    padding: 0.5rem 0.75rem;
    background: #fcfcfd;
    font-size: 0.85rem;
    line-height: 1.35;
    min-height: 140px;
}
.codex-ai-tools code {
    font-size: 0.85rem;
}
.codex-ai-input textarea {
    resize: none;
    min-height: 110px;
    max-height: 140px;
    font-size: 0.9rem;
    padding: 0.55rem 0.65rem;
}
.codex-ai-input {
    flex: 0 0 auto;
    margin: 0;
}
.codex-ai-composer {
    align-items: stretch;
    gap: 0.5rem;
}
.codex-composer-bar {
    background: #f8f9fb;
    border: 1px solid #e5e7eb;
    border-radius: 0.5rem;
    padding: 0.35rem 0.5rem;
    gap: 0.35rem;
}

.codex-ai-card {
    border: 0;
    border-radius: 0;
    padding: 0;
    background: transparent;
    box-shadow: none;
    margin: 0;
}
.codex-ai-context {
    background: #f5f7ff;
}
.codex-ai-quick .btn {
    min-width: 110px;
}

.codex-ai-context-path {
    font-size: 0.82rem;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.codex-ai-toolchips .badge {
    font-weight: 500;
    margin: 0.15rem;
    padding: 0.35rem 0.6rem;
}
.codex-ai-composer textarea {
    resize: none;
}
.codex-icon-btn {
    border: 1px solid #e5e7eb;
    padding: 0.25rem 0.4rem;
    font-size: 0.95rem;
    color: #495057;
    border-radius: 0.4rem;
    width: 38px;
    height: 34px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
}
.codex-icon-btn:hover {
    color: #0d6efd;
    background: #eef2ff;
    border-color: #d0d7ff;
}
.codex-quick-inline .btn {
    padding-left: 0.5rem;
    padding-right: 0.5rem;
}
.codex-quick-group {
    gap: 0.25rem !important;
}
.codex-tool-menu {
    position: absolute;
    bottom: calc(100% + 8px);
    right: 0;
    min-width: 180px;
    max-height: 220px;
    overflow-y: auto;
    background: #fff;
    border: 1px solid #dce0e5;
    border-radius: 0.6rem;
    box-shadow: 0 12px 30px rgba(15, 23, 42, 0.18);
    z-index: 20;
}
.codex-tool-options .list-group-item {
    font-size: 0.9rem;
    padding: 0.45rem 0.65rem;
    border: 0;
}
.codex-tool-options .list-group-item + .list-group-item {
    border-top: 1px solid #eff1f4;
}
.codex-tool-options .list-group-item:hover,
.codex-tool-options .list-group-item:focus {
    background: #f3f6ff;
}
}

.codex-tool-log {
    max-height: 120px;
    min-height: 80px;
    overflow-y: auto;
    border: 1px dashed #e9ecef;
    border-radius: 0.5rem;
    padding: 0.5rem;
    background: #fcfcfd;
    font-size: 0.84rem;
    line-height: 1.35;
}

.codex-tool-entry {
    display: flex;
    align-items: flex-start;
    gap: 0.5rem;
    margin-bottom: 0.35rem;
}

.codex-tool-entry:last-child {
    margin-bottom: 0;
}

.codex-tool-pill {
    width: 10px;
    height: 10px;
    border-radius: 999px;
    margin-top: 0.4rem;
    background: #adb5bd;
}

.codex-tool-entry.running .codex-tool-pill {
    background: #f7b924;
    box-shadow: 0 0 0 0 rgba(247, 185, 36, 0.5);
    animation: codexPulse 1.5s infinite;
}

.codex-tool-entry.success .codex-tool-pill {
    background: #0cbc87;
    box-shadow: none;
}

.codex-tool-entry.error .codex-tool-pill {
    background: #f06548;
    box-shadow: none;
}

@keyframes codexPulse {
    0% {
        box-shadow: 0 0 0 0 rgba(247, 185, 36, 0.6);
    }
    70% {
        box-shadow: 0 0 0 0.65rem rgba(247, 185, 36, 0);
    }
    100% {
        box-shadow: 0 0 0 0 rgba(247, 185, 36, 0);
    }
}

.codex-diff-panel {
    position: absolute;
    inset: 3.5rem 1rem 1rem;
    background: #fff;
    border: 1px solid #ced4da;
    border-radius: 0.75rem;
    box-shadow: 0 1.5rem 3rem rgba(15, 23, 42, 0.25);
    display: flex;
    flex-direction: column;
    z-index: 10;
}

.codex-diff-panel.d-none {
    display: none;
}

.codex-diff-header {
    padding: 0.75rem 1rem;
    border-bottom: 1px solid #f1f3f5;
    background: #f8f9fa;
    border-radius: 0.75rem 0.75rem 0 0;
}

.codex-diff-editor {
    flex: 1;
    min-height: 320px;
}

.codex-panel.codex-ai.collapsed {
    max-width: 48px;
    min-width: 48px;
}

.codex-panel.codex-ai.collapsed .codex-ai-body,
.codex-panel.codex-ai.collapsed .codex-panel-header h6,
.codex-panel.codex-ai.collapsed .codex-panel-header small {
    display: none;
}
.codex-shell.ai-panel-collapsed .codex-panel.codex-ai {
    min-width: 64px;
    width: 64px;
}
.codex-panel.codex-tree.collapsed .codex-tree-root,
.codex-panel.codex-tree.collapsed .codex-root-switcher,
.codex-panel.codex-tree.collapsed .codex-tree-search,
.codex-panel.codex-tree.collapsed .codex-tree-meta {
    display: none !important;
}
.codex-panel.codex-tree.collapsed .codex-panel-header h6,
.codex-panel.codex-tree.collapsed .codex-panel-header small {
    display: none;
}
.codex-panel.codex-tree.collapsed {
    width: 64px;
    min-width: 64px;
}
.codex-panel.codex-tree.collapsed .codex-panel-header {
    justify-content: center;
}

@media (max-width: 1200px) {
    .codex-shell {
        grid-template-columns: 220px minmax(0, 1fr);
    }
    .codex-ai {
        grid-column: 1 / -1;
        order: 3;
    }
}
@media (max-width: 768px) {
    .codex-shell {
        grid-template-columns: 1fr;
    }
}

/* Chat message formatting */
.codex-chat-msg {
    border: 1px solid #e9ecef;
    border-radius: 0.65rem;
    padding: 0.6rem 0.75rem;
    margin-bottom: 0.55rem;
    background: #fff;
}
.codex-chat-meta .codex-chat-badge {
    display: inline-block;
    padding: 0.15rem 0.5rem;
    border-radius: 999px;
    font-size: 0.75rem;
    font-weight: 600;
}
.codex-chat-badge.role-you { background: #e7f1ff; color: #0b5ed7; }
.codex-chat-badge.role-user { background: #e7f1ff; color: #0b5ed7; }
.codex-chat-badge.role-codex,
.codex-chat-badge.role-assistant { background: #e8fff5; color: #0a8754; }
.codex-chat-badge.role-tool { background: #fff3cd; color: #947100; }
.codex-chat-badge.role-system { background: #f1f3f5; color: #495057; }

.codex-chat-body p {
    margin-bottom: 0.35rem;
}
.codex-chat-body code {
    background: #f8f9fa;
    padding: 0.05rem 0.35rem;
    border-radius: 0.35rem;
    font-size: 0.82rem;
}
.codex-chat-list {
    margin: 0 0 0.5rem 1.1rem;
    padding-left: 0.2rem;
}
.codex-chat-list li {
    margin-bottom: 0.25rem;
}
.codex-chat-code {
    background: #0f172a;
    color: #e2e8f0;
    border: 1px solid #0b1224;
}
.codex-chat-code pre {
    margin: 0;
    white-space: pre;
    overflow-x: auto;
    max-height: 260px;
    overflow-y: auto;
}
.codex-chat-code code {
    background: transparent;
    color: inherit;
    font-size: 0.82rem;
}
.codex-copy-code {
    font-size: 0.8rem;
}
.codex-chat-toggle {
    font-size: 0.85rem;
}
.codex-chat-msg.is-collapsible:not(.is-expanded) .codex-chat-body::after {
    content: '';
    display: block;
    height: 2rem;
    margin-top: -2rem;
    background: linear-gradient(180deg, rgba(255,255,255,0) 0%, #fff 100%);
}
.codex-send-btn {
    height: 42px;
    border-radius: 0.5rem;
}
.helper-text {
    font-size: 0.85rem;
}
</style>

<?php
$jsPath = $this->runData['config']['dir']['admin'] . '/ui/codex-view.js.php';
if (file_exists($jsPath)) {
    include $jsPath;
}
?>
