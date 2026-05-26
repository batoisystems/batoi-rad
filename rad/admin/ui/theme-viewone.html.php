<?php
$detail = $this->runData['data']['template_detail'] ?? [];
$stats = $detail['stats'] ?? [];
$usage = $detail['usage'] ?? [];
$history = $detail['history'] ?? [];
$versions = $detail['versions'] ?? [];
$templateName = $detail['name'] ?? '';
$templateFile = $detail['file'] ?? ($templateName . '.tpl.php');
$templatePath = $detail['path'] ?? '';
$templateCode = $detail['code'] ?? '';
$radAdminUrl = $this->runData['route']['rad_admin_url'];
$timezone = $this->runData['entity']['timezone'] ?? ($this->runData['config']['sys']['timezone_default'] ?? 'UTC');
$backUrl = $this->runData['route']['backlink'] ?? ($radAdminUrl . '/theme/view');
$duplicateUrl = $radAdminUrl . '/theme/duplicate/' . urlencode($templateName);
$saveUrl = $this->runData['config']['sys']['base_url'] . '/rad-admin/theme/savetpl/template/' . urlencode($templateName);
$aiUrl = $this->runData['config']['sys']['base_url'] . '/rad-admin/theme/aiassist/' . urlencode($templateName);
$agentContextUrl = $this->runData['config']['sys']['base_url'] . '/rad-admin/theme/agentcontext/' . urlencode($templateName);
$agentPlanUrl = $this->runData['config']['sys']['base_url'] . '/rad-admin/theme/agentplan/' . urlencode($templateName);
$agentPatchUrl = $this->runData['config']['sys']['base_url'] . '/rad-admin/theme/agentpatch/' . urlencode($templateName);
$availableTemplates = $detail['available_templates'] ?? [];
?>
<style>
    .theme-workspace-hero,
    .theme-workspace-card {
        border: 0;
        box-shadow: 0 0.125rem 0.5rem rgba(0, 0, 0, 0.06);
    }
    .theme-workspace-chip {
        display: inline-flex;
        align-items: center;
        gap: 0.35rem;
        padding: 0.3rem 0.55rem;
        border-radius: 999px;
        background: #f8f9fa;
        border: 1px solid #e9ecef;
        font-size: 0.78rem;
        color: #495057;
    }
    .theme-stepbar {
        display: grid;
        grid-template-columns: repeat(5, minmax(0, 1fr));
        gap: 0.6rem;
    }
    .theme-step {
        border: 1px solid #dee2e6;
        border-radius: 0.65rem;
        padding: 0.65rem 0.75rem;
        background: #fff;
    }
    .theme-step.is-active {
        border-color: #0d6efd;
        background: #eef5ff;
    }
    .theme-step.is-done {
        border-color: #198754;
        background: #eefaf2;
    }
    .theme-step-label {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 1.35rem;
        height: 1.35rem;
        border-radius: 50%;
        background: #e9ecef;
        color: #495057;
        font-weight: 700;
        font-size: 0.72rem;
    }
    .theme-step.is-active .theme-step-label,
    .theme-step.is-done .theme-step-label {
        color: #fff;
    }
    .theme-step.is-active .theme-step-label {
        background: #0d6efd;
    }
    .theme-step.is-done .theme-step-label {
        background: #198754;
    }
    .theme-step-title {
        font-size: 0.92rem;
        font-weight: 700;
        margin-top: 0.45rem;
    }
    .theme-step-copy {
        font-size: 0.78rem;
        line-height: 1.35;
        color: #6c757d;
        margin-top: 0.15rem;
    }
    .theme-tab-pane {
        display: none;
    }
    .theme-tab-pane.active {
        display: block;
    }
    .theme-workspace-sidebar {
        position: sticky;
        top: 1.5rem;
    }
    .theme-workspace-grid {
        display: grid;
        grid-template-columns: minmax(0, 1fr) 360px;
        gap: 1rem;
        align-items: start;
    }
    .theme-workspace-main,
    .theme-workspace-aside {
        min-width: 0;
    }
    .theme-workspace-aside {
        width: 100%;
    }
    .theme-workspace-sidebar .card {
        border-radius: 0.6rem;
    }
    .theme-workspace-sidebar .card-header {
        padding: 0.65rem 0.85rem;
    }
    .theme-workspace-sidebar .card-body {
        padding: 0.85rem;
    }
    .theme-workspace-sidebar .form-label {
        margin-bottom: 0.35rem;
        font-size: 0.84rem;
        font-weight: 600;
    }
    .theme-workspace-sidebar .small,
    .theme-workspace-sidebar .form-text {
        font-size: 0.78rem;
    }
    .theme-workspace-sidebar .btn {
        white-space: nowrap;
    }
    .theme-agent-action-row {
        display: flex;
        gap: 0.45rem;
        flex-wrap: nowrap;
    }
    .theme-agent-action-row .btn {
        flex: 1 1 0;
        min-width: 0;
        padding-left: 0.55rem;
        padding-right: 0.55rem;
    }
    .theme-agent-progress.is-busy {
        border-color: #86b7fe;
        background: #eef5ff;
    }
    .theme-agent-scroll {
        max-height: 180px;
        overflow-y: auto;
    }
    .theme-context-pill {
        display: inline-block;
        padding: 0.2rem 0.55rem;
        border-radius: 999px;
        background: #f1f3f5;
        color: #495057;
        font-size: 0.8rem;
        margin: 0 0.35rem 0.35rem 0;
    }
    .theme-editor-toolbar {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 0.75rem;
        flex-wrap: nowrap;
        overflow-x: auto;
    }
    .theme-editor-toolbar-group {
        display: flex;
        align-items: center;
        gap: 0.6rem;
        flex: 0 0 auto;
        white-space: nowrap;
    }
    .theme-editor-toolbar-group--grow {
        flex: 1 1 auto;
        min-width: 0;
    }
    .theme-inline-status {
        min-width: 140px;
        font-size: 0.82rem;
    }
    .theme-inline-divider {
        width: 1px;
        align-self: stretch;
        background: #dee2e6;
    }
    .theme-toolbar-secondary {
        display: inline-flex;
        align-items: center;
        gap: 0.35rem;
        padding: 0.15rem 0.25rem;
        border: 1px solid #dee2e6;
        border-radius: 0.45rem;
        background: #fff;
    }
    .theme-toolbar-secondary .btn {
        border: 0;
        box-shadow: none;
    }
    .theme-version-table {
        font-size: 0.84rem;
    }
    .theme-version-table th,
    .theme-version-table td {
        padding: 0.45rem 0.5rem;
        vertical-align: middle;
    }
    .theme-version-table th,
    .theme-version-nowrap {
        white-space: nowrap;
    }
    .theme-version-actions {
        display: inline-flex;
        gap: 0.3rem;
        flex-wrap: nowrap;
    }
    .theme-version-actions .btn {
        padding: 0.2rem 0.45rem;
        line-height: 1.1;
    }
    #theme-code-fullscreen:fullscreen,
    #theme-code-fullscreen:-webkit-full-screen {
        background: #111827;
        padding: 0.75rem;
        overflow: auto;
    }
    #theme-code-fullscreen:fullscreen .card,
    #theme-code-fullscreen:-webkit-full-screen .card {
        height: calc(100vh - 1.5rem);
        margin: 0;
    }
    #theme-code-fullscreen:fullscreen .card-body,
    #theme-code-fullscreen:-webkit-full-screen .card-body {
        height: calc(100% - 56px);
    }
    #theme-code-fullscreen:fullscreen #theme_code_editor,
    #theme-code-fullscreen:-webkit-full-screen #theme_code_editor {
        height: 100% !important;
    }
    @media (max-width: 1199.98px) {
        .theme-workspace-sidebar {
            position: static;
        }
        .theme-workspace-grid {
            grid-template-columns: 1fr;
        }
        .theme-stepbar {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }
    }
    @media (max-width: 767.98px) {
        .theme-stepbar {
            grid-template-columns: 1fr;
        }
        .theme-editor-toolbar {
            display: block;
            overflow-x: visible;
        }
        .theme-editor-toolbar-group {
            flex-wrap: wrap;
            white-space: normal;
            margin-bottom: 0.75rem;
        }
        .theme-inline-divider {
            display: none;
        }
        .theme-agent-action-row {
            flex-wrap: wrap;
        }
        .theme-agent-action-row .btn {
            flex: 1 1 calc(50% - 0.45rem);
        }
    }
</style>

<div class="card theme-workspace-hero mb-3">
    <div class="card-body d-flex justify-content-between align-items-center flex-wrap gap-3">
        <div>
            <div class="d-flex flex-wrap gap-2 mb-2">
                <span class="theme-workspace-chip"><i class="bi bi-file-earmark-code"></i><?php echo htmlspecialchars($templateFile); ?></span>
                <span class="theme-workspace-chip"><i class="bi bi-aspect-ratio"></i><?php echo htmlspecialchars($stats['lines'] ?? '—'); ?> lines</span>
                <span class="theme-workspace-chip"><i class="bi bi-hdd"></i><?php echo htmlspecialchars($stats['size_human'] ?? '—'); ?></span>
                <span class="theme-workspace-chip"><i class="bi bi-diagram-3"></i><?php echo count($usage); ?> usages</span>
            </div>
            <h5 class="mb-1">Theme Template Workspace</h5>
            <div class="small text-muted">Edit the template, review the patch, and version the result from one workspace.</div>
            <div class="small text-muted mt-2"><strong>Path:</strong> <?php echo htmlspecialchars($templatePath); ?></div>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            <button type="button" class="btn btn-outline-secondary btn-sm" id="theme-open-versions-top">
                <i class="bi bi-clock-history me-1"></i>Versions
            </button>
            <a href="<?php echo $duplicateUrl; ?>" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-files me-1"></i>Duplicate
            </a>
        </div>
    </div>
</div>

<div class="card theme-workspace-card mb-3">
    <div class="card-body">
        <div class="theme-stepbar">
            <div class="theme-step is-active" id="theme-step-context">
                <div class="theme-step-label">1</div>
                <div class="theme-step-title">Capture Task</div>
                <div class="theme-step-copy">Describe the theme change and the expected UI outcome.</div>
            </div>
            <div class="theme-step" id="theme-step-plan">
                <div class="theme-step-label">2</div>
                <div class="theme-step-title">Review Plan</div>
                <div class="theme-step-copy">Check shared usage, risks, and file scope before patching.</div>
            </div>
            <div class="theme-step" id="theme-step-patch">
                <div class="theme-step-label">3</div>
                <div class="theme-step-title">Review Patch</div>
                <div class="theme-step-copy">Inspect the proposed markup diff without changing the editor yet.</div>
            </div>
            <div class="theme-step" id="theme-step-apply">
                <div class="theme-step-label">4</div>
                <div class="theme-step-title">Apply</div>
                <div class="theme-step-copy">Write the template only after explicit approval and checksum validation.</div>
            </div>
            <div class="theme-step" id="theme-step-version">
                <div class="theme-step-label">5</div>
                <div class="theme-step-title">Versioned</div>
                <div class="theme-step-copy">Capture the new template state as a restorable version snapshot.</div>
            </div>
        </div>
    </div>
</div>

<div class="theme-workspace-grid">
    <div class="theme-workspace-main">
        <ul class="nav nav-tabs mb-3" id="theme-workspace-tabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" type="button" id="theme-tab-editor" data-theme-tab="editor" aria-selected="true">
                    <i class="bi bi-code-square me-1"></i>Code Editor
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" type="button" id="theme-tab-patch" data-theme-tab="patch" aria-selected="false">
                    <i class="bi bi-file-diff me-1"></i>Patch Review
                    <span class="badge bg-secondary ms-1 d-none" id="theme-tab-patch-badge">Ready</span>
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" type="button" id="theme-tab-versions" data-theme-tab="versions" aria-selected="false">
                    <i class="bi bi-clock-history me-1"></i>Version History
                </button>
            </li>
        </ul>

        <div class="theme-tab-pane active" id="theme-pane-editor">
            <div id="theme-code-fullscreen">
                <div class="card theme-workspace-card">
                    <div class="card-header d-flex justify-content-between align-items-center gap-2">
                        <span class="fw-semibold"><?php echo htmlspecialchars($templateFile); ?></span>
                        <div class="theme-editor-toolbar">
                            <div class="theme-editor-toolbar-group theme-editor-toolbar-group--grow">
                                <div class="btn-group btn-group-sm" role="group" aria-label="Theme editor toolbar">
                                    <button class="btn btn-outline-secondary" onclick="themePerformUndo()" title="Undo"><i class="bi bi-arrow-counterclockwise"></i></button>
                                    <button class="btn btn-outline-secondary" onclick="themePerformRedo()" title="Redo"><i class="bi bi-arrow-clockwise"></i></button>
                                    <button class="btn btn-outline-secondary" onclick="themeToggleComment()" title="Toggle Comment"><i class="bi bi-slash-square"></i></button>
                                    <button class="btn btn-outline-secondary" onclick="themeFormatCode()" title="Format Code"><i class="bi bi-code-square"></i></button>
                                    <button class="btn btn-outline-secondary" onclick="themeGoToLine()" title="Go to Line"><i class="bi bi-arrow-up-square"></i></button>
                                    <button class="btn btn-outline-secondary" onclick="themeToggleWrap()" title="Toggle Line Wrap"><i class="bi bi-text-wrap"></i></button>
                                    <button class="btn btn-outline-secondary" onclick="themeFindReplace()" title="Find and Replace"><i class="bi bi-search"></i></button>
                                </div>
                                <small class="text-muted text-nowrap theme-inline-status" id="theme-save-status">All changes saved.</small>
                            </div>
                            <div class="theme-inline-divider"></div>
                            <div class="theme-editor-toolbar-group">
                                <button type="button" class="btn btn-sm btn-warning" id="theme-version-btn">
                                    <i class="bi bi-save"></i> Save & Version
                                </button>
                                <div class="theme-toolbar-secondary">
                                    <button type="button" class="btn btn-sm btn-outline-secondary" id="theme-open-versions-editor" title="Version History">
                                        <i class="bi bi-clock-history"></i>
                                    </button>
                                    <button type="button" class="btn btn-sm btn-outline-secondary" id="theme-fullscreen-btn" onclick="themeToggleFullscreen()" title="Full screen mode">
                                        <i class="bi bi-arrows-angle-expand" id="theme-fullscreen-icon"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="card-body px-0 py-0">
                        <div id="theme_code_editor" style="height: 760px; width: 100%;"></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="theme-tab-pane" id="theme-pane-patch">
            <div class="card theme-workspace-card">
                <div class="card-header d-flex justify-content-between align-items-center gap-2">
                    <div>
                        <strong>Agent Patch Preview</strong>
                        <div class="small text-muted" id="theme-agent-diff-summary">No patch generated yet.</div>
                    </div>
                    <div class="d-flex gap-2">
                        <button type="button" class="btn btn-outline-secondary" id="theme-agent-diff-discard">
                            <i class="bi bi-x-lg"></i> Discard
                        </button>
                        <button type="button" class="btn btn-primary" id="theme-agent-apply">
                            <i class="bi bi-check-lg"></i> <span id="theme-agent-apply-label">Apply + Version</span>
                        </button>
                    </div>
                </div>
                <div class="card-body">
                    <div class="small text-muted mb-2" id="theme-agent-diff-meta">Generate a patch to review it here while keeping the template editor unchanged.</div>
                    <div class="small mb-3" id="theme-agent-diff-warnings">
                        <div class="alert alert-light border mb-0 small">Patch review opens in this tab. Nothing is written back until you explicitly apply.</div>
                    </div>
                    <div id="theme-agent-diff-editor" style="height: 520px; width: 100%; border: 1px solid #dee2e6; border-radius: 0.5rem;"></div>
                </div>
            </div>
        </div>

        <div class="theme-tab-pane" id="theme-pane-versions">
            <div class="card theme-workspace-card">
                <div class="card-body">
                    <h5 class="card-title mb-3">Version History</h5>
                    <div id="theme-versions-empty" class="alert alert-secondary mb-0<?php echo empty($versions) ? '' : ' d-none'; ?>">No saved versions yet.</div>
                    <div id="theme-versions-table-wrap"<?php echo empty($versions) ? ' class="d-none"' : ''; ?>>
                        <div class="table-responsive">
                            <table class="table table-sm align-middle mb-0 theme-version-table">
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
                                <tbody id="theme-versions-body">
                                    <?php foreach ($versions as $version) { ?>
                                        <tr data-version-id="<?php echo htmlspecialchars($version['id']); ?>">
                                            <td class="fw-semibold theme-version-nowrap"><?php echo htmlspecialchars($version['id']); ?></td>
                                            <td class="theme-version-nowrap"><?php echo isset($version['timestamp']) ? \Core\Sys\TimeHelper::formatUtc($version['timestamp'], $timezone) : '—'; ?></td>
                                            <td class="theme-version-nowrap"><?php echo htmlspecialchars($version['user'] ?? 'RAD Admin'); ?></td>
                                            <td class="theme-version-nowrap"><?php echo htmlspecialchars($version['size_human'] ?? '—'); ?></td>
                                            <td><?php echo htmlspecialchars($version['note'] ?? ''); ?></td>
                                            <td class="text-end theme-version-nowrap">
                                                <span class="theme-version-actions">
                                                    <a href="<?php echo $radAdminUrl; ?>/theme/downloadversion/<?php echo urlencode($templateName); ?>/<?php echo urlencode($version['id']); ?>" class="btn btn-outline-primary btn-sm" title="Download Version">
                                                        <i class="bi bi-download"></i>
                                                    </a>
                                                    <a href="<?php echo $radAdminUrl; ?>/theme/diffversion/<?php echo urlencode($templateName); ?>/<?php echo urlencode($version['id']); ?>" class="btn btn-outline-info btn-sm" title="Compare Version">
                                                        <i class="bi bi-arrow-left-right"></i>
                                                    </a>
                                                    <form action="<?php echo $radAdminUrl; ?>/theme/restoreversion/<?php echo urlencode($templateName); ?>/<?php echo urlencode($version['id']); ?>" method="post" class="d-inline">
                                                        <button type="submit" class="btn btn-outline-danger btn-sm" title="Restore Version" onclick="return confirm('Restore this version?');">
                                                            <i class="bi bi-arrow-counterclockwise"></i>
                                                        </button>
                                                    </form>
                                                </span>
                                            </td>
                                        </tr>
                                    <?php } ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="theme-workspace-aside">
        <div class="theme-workspace-sidebar">
            <div class="card mb-3">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <div>
                        <strong>Agent Workspace</strong>
                        <div class="small text-muted">Prompt, plan, patch, apply</div>
                    </div>
                    <span class="badge bg-light text-dark border">THEME</span>
                </div>
                <div class="card-body d-flex flex-column gap-3">
                    <div class="small text-muted">Describe the UI/template change, review the plan, then generate a patch for this template only.</div>
                    <div>
                        <label for="theme-agent-task" class="form-label">Task</label>
                        <textarea class="form-control" id="theme-agent-task" rows="4" placeholder="Describe what you want changed in this template..."></textarea>
                        <div class="form-text">Mention layout, responsiveness, sections, or shared usage concerns if relevant.</div>
                    </div>
                    <div>
                        <label for="theme-agent-scope" class="form-label">Scope</label>
                        <select class="form-select" id="theme-agent-scope">
                            <option value="template_only">Current template only</option>
                            <option value="template_related">Current template + related templates</option>
                            <option value="template_usage">Template + consuming microservicelets</option>
                        </select>
                    </div>
                    <div>
                        <label for="theme-agent-related-template-select" class="form-label">Related Templates</label>
                        <div class="input-group input-group-sm">
                            <select class="form-select" id="theme-agent-related-template-select">
                                <option value="">Attach another template for context…</option>
                                <?php foreach ($availableTemplates as $availableTemplate) { ?>
                                    <option value="<?php echo htmlspecialchars($availableTemplate); ?>"><?php echo htmlspecialchars($availableTemplate); ?></option>
                                <?php } ?>
                            </select>
                            <button type="button" class="btn btn-outline-secondary" id="theme-agent-related-template-add">Add</button>
                        </div>
                        <div class="form-text">Attached templates are read for context only. Patch apply still updates the current template file only.</div>
                        <div class="mt-2 d-flex flex-wrap gap-2" id="theme-agent-related-templates"></div>
                    </div>
                    <div class="theme-agent-action-row">
                        <button type="button" class="btn btn-outline-secondary" id="theme-agent-refresh"><i class="bi bi-arrow-repeat"></i> Refresh</button>
                        <button type="button" class="btn btn-primary" id="theme-agent-plan"><i class="bi bi-magic"></i> Plan</button>
                        <button type="button" class="btn btn-success" id="theme-agent-patch"><i class="bi bi-file-earmark-code"></i> Patch</button>
                    </div>
                    <div id="theme-agent-progress" class="theme-agent-progress border rounded p-3 bg-light">
                        <div class="d-flex align-items-center gap-2 mb-1">
                            <div id="theme-agent-progress-spinner" class="spinner-border spinner-border-sm text-primary d-none" role="status" aria-hidden="true"></div>
                            <strong class="small text-uppercase text-muted">Processing</strong>
                        </div>
                        <div id="theme-agent-status" class="small text-muted">Context not loaded yet.</div>
                        <div id="theme-agent-progress-detail" class="small text-muted mt-1">Idle.</div>
                    </div>
                </div>
            </div>

            <div class="card mb-3">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <strong class="small text-uppercase text-muted">Workspace Context</strong>
                        <span class="small text-muted" id="theme-agent-context-meta">Loading…</span>
                    </div>
                    <div class="small mb-2" id="theme-agent-context-summary">Preparing theme workspace context…</div>
                    <div class="mb-2" id="theme-agent-context-tags"></div>
                    <div class="theme-agent-scroll">
                        <ul class="list-group list-group-flush small" id="theme-agent-context-list"></ul>
                    </div>
                </div>
            </div>

            <div class="card mb-3">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <strong class="small text-uppercase text-muted">Implementation Plan</strong>
                        <span class="small text-muted" id="theme-agent-plan-meta">No plan yet</span>
                    </div>
                    <div class="small mb-2" id="theme-agent-plan-summary">Generate a plan to review the template scope, risks, and usage before changing markup.</div>
                    <div class="theme-agent-scroll">
                        <ol class="small mb-3 ps-3" id="theme-agent-plan-steps"><li>Agent planning is idle.</li></ol>
                        <div class="small mb-2"><strong>Risks</strong></div>
                        <ul class="small ps-3 mb-0" id="theme-agent-plan-risks"><li>No plan generated yet.</li></ul>
                    </div>
                </div>
            </div>

            <div class="card mb-3">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <strong class="small text-uppercase text-muted">Recent History</strong>
                        <span class="small text-muted"><?php echo count($history); ?> entries</span>
                    </div>
                    <?php if (empty($history)) { ?>
                        <div class="small text-muted">No recent history entries.</div>
                    <?php } else { ?>
                        <div class="theme-agent-scroll">
                            <?php foreach (array_slice($history, 0, 5) as $event) { ?>
                                <div class="small mb-2 pb-2 border-bottom">
                                    <div class="fw-semibold"><?php echo htmlspecialchars($event['action'] ?? 'Update'); ?></div>
                                    <div class="text-muted"><?php echo isset($event['timestamp']) ? \Core\Sys\TimeHelper::formatUtc($event['timestamp'], $timezone) : '—'; ?></div>
                                </div>
                            <?php } ?>
                        </div>
                    <?php } ?>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal compact-modal fade" id="themeFindReplaceModal" tabindex="-1" style="z-index: 10000;">
  <div class="modal-dialog compact-dialog">
    <div class="modal-content compact-content">
      <div class="modal-header compact-header">
        <h5 class="modal-title compact-title">Find and Replace</h5>
        <button type="button" class="btn-close compact-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body compact-body">
        <div class="mb-2 compact-group">
            <label for="themeFindTerm" class="form-label compact-label">Find</label>
            <input type="text" class="form-control compact-control" id="themeFindTerm">
        </div>
        <div class="mb-2 compact-group">
            <label for="themeReplaceTerm" class="form-label compact-label">Replace</label>
            <input type="text" class="form-control compact-control" id="themeReplaceTerm">
        </div>
      </div>
      <div class="modal-footer compact-footer">
        <button type="button" class="btn btn-sm btn-secondary compact-btn" data-bs-dismiss="modal">Close</button>
        <button type="button" class="btn btn-sm btn-primary compact-btn" onclick="themeFindReplaceApply()">Find and Replace</button>
      </div>
    </div>
  </div>
</div>
