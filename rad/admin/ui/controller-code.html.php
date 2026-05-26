<?php
// print '<pre>';print_r($this->runData['data']);print '</pre>';die('In Page Part');
$codeEditorDir = $this->runData['config']['dir']['admin'].'/assets/monaco';
$codeEditorLibUrl = $this->runData['config']['sys']['base_url'].'/rad-assets/monaco';
$branch = $this->runData['data']['branch'] ?? 'live';
$branchStatus = $this->runData['data']['branch_status'] ?? [];
$branchHasBeta = !empty($this->runData['data']['branch_has_beta']);
$branchMissing = !empty($this->runData['data']['branch_missing']);
$branchCanManage = !empty($this->runData['data']['branch_can_manage']);
$branchCanMerge = !empty($this->runData['data']['branch_can_merge']);
$branchHistory = $this->runData['data']['branch_history'] ?? [];
$previewCanManage = !empty($this->runData['data']['preview_can_manage']);
$previewActive = !empty($this->runData['data']['preview_active']);
$previewContext = $this->runData['data']['preview_context'] ?? [];
$branchQuery = '?branch=' . ($branch === 'beta' ? 'beta' : 'live');
$postThroughAjaxUrl = $this->runData['config']['sys']['base_url'].'/rad-admin/controller/codesave/'.$this->runData['route']['ms_name'].'/'.$this->runData['data']['controller']['s_name'] . $branchQuery;
$aiAssistanceUrl = $this->runData['config']['sys']['base_url'].'/rad-admin/controller/aiassist/'.$this->runData['route']['ms_name'].'/'.$this->runData['data']['controller']['s_name'] . $branchQuery;
$agentContextUrl = $this->runData['config']['sys']['base_url'].'/rad-admin/controller/agentcontext/'.$this->runData['route']['ms_name'].'/'.$this->runData['data']['controller']['s_name'] . $branchQuery;
$agentPlanUrl = $this->runData['config']['sys']['base_url'].'/rad-admin/controller/agentplan/'.$this->runData['route']['ms_name'].'/'.$this->runData['data']['controller']['s_name'] . $branchQuery;
$agentPatchUrl = $this->runData['config']['sys']['base_url'].'/rad-admin/controller/agentpatch/'.$this->runData['route']['ms_name'].'/'.$this->runData['data']['controller']['s_name'] . $branchQuery;
$timezone = $this->runData['entity']['timezone'] ?? ($this->runData['config']['sys']['timezone_default'] ?? 'UTC');
$controllerType = strtoupper((string)($this->runData['data']['controller']['s_type'] ?? 'BL'));
$controllerTypeLabel = $controllerType === 'DM' ? 'Data Model' : 'Business Class';
$sourceFileLabel = $this->runData['data']['controller']['s_source_file'] ?? ($this->runData['data']['controller']['s_name'] . '.cls.php');
$resolvedPathLabel = $this->runData['data']['controller_runtime']['resolved_path'] ?? '';
$versions = $this->runData['data']['versions'] ?? [];
$radAdminUrl = $this->runData['route']['rad_admin_url'];
$msSlug = $this->runData['route']['pathparts'][3] ?? $this->runData['route']['ms_name'];
$controllerSlug = $this->runData['data']['controller']['s_name'];
// print $postThroughAjaxUrl.'<br>'.$aiAssistanceUrl.'<br>';
?>
<style>
    #code_class.controller-expanded {
        height: 80vh !important;
    }
    #controller-editor-shell:fullscreen,
    #controller-editor-shell:-webkit-full-screen {
        background: #111827;
        padding: 0.75rem;
        overflow: auto;
    }
    #controller-editor-shell:fullscreen .card,
    #controller-editor-shell:-webkit-full-screen .card {
        height: calc(100vh - 1.5rem);
        margin: 0;
    }
    #controller-editor-shell:fullscreen .card-body,
    #controller-editor-shell:-webkit-full-screen .card-body {
        height: calc(100% - 56px);
    }
    #controller-editor-shell:fullscreen #code_class,
    #controller-editor-shell:-webkit-full-screen #code_class {
        height: 100% !important;
    }
    .controller-agent-card .list-group-item {
        padding: 0.5rem 0.75rem;
    }
    .controller-agent-scroll {
        max-height: 220px;
        overflow-y: auto;
    }
    .controller-agent-progress.is-busy {
        border-color: #86b7fe;
        background: #eef5ff;
    }
    .controller-agent-progress-spinner {
        width: 0.9rem;
        height: 0.9rem;
    }
    .controller-workspace-nav .nav-link {
        font-weight: 600;
    }
    .controller-workspace-nav .nav-link .badge {
        font-size: 0.7rem;
    }
    .controller-tab-pane {
        display: none;
    }
    .controller-tab-pane.active {
        display: block;
    }
    .controller-workspace-left .controller-tab-pane.active {
        display: block;
    }
    .controller-workspace-sidebar {
        position: sticky;
        top: 1.5rem;
    }
    .controller-hero-card {
        border: 0;
        box-shadow: 0 0.125rem 0.5rem rgba(0, 0, 0, 0.06);
    }
    .controller-hero-card .card-body {
        padding: 1rem 1.1rem;
    }
    .controller-meta-chip {
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
    .controller-stepbar {
        display: grid;
        grid-template-columns: repeat(5, minmax(0, 1fr));
        gap: 0.6rem;
    }
    .controller-step {
        border: 1px solid #dee2e6;
        border-radius: 0.65rem;
        padding: 0.65rem 0.75rem;
        background: #fff;
    }
    .controller-step.is-active {
        border-color: #0d6efd;
        background: #eef5ff;
    }
    .controller-step.is-done {
        border-color: #198754;
        background: #eefaf2;
    }
    .controller-step-label {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 1.35rem;
        height: 1.35rem;
        border-radius: 50%;
        background: #e9ecef;
        font-size: 0.72rem;
        font-weight: 700;
        color: #495057;
    }
    .controller-step.is-active .controller-step-label {
        background: #0d6efd;
        color: #fff;
    }
    .controller-step.is-done .controller-step-label {
        background: #198754;
        color: #fff;
    }
    .controller-step-title {
        font-weight: 700;
        margin-top: 0.45rem;
        font-size: 0.92rem;
    }
    .controller-step-copy {
        font-size: 0.78rem;
        color: #6c757d;
        margin-top: 0.15rem;
        line-height: 1.35;
    }
    .controller-inline-status {
        min-width: 140px;
        font-size: 0.82rem;
    }
    .controller-context-pill {
        display: inline-block;
        padding: 0.2rem 0.55rem;
        border-radius: 999px;
        background: #f1f3f5;
        color: #495057;
        font-size: 0.8rem;
        margin: 0 0.35rem 0.35rem 0;
    }
    .controller-action-strip {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        flex-wrap: nowrap;
        overflow-x: auto;
        padding-bottom: 0.1rem;
    }
    .controller-action-strip::-webkit-scrollbar {
        height: 6px;
    }
    .controller-action-strip::-webkit-scrollbar-thumb {
        background: rgba(108, 117, 125, 0.35);
        border-radius: 999px;
    }
    .controller-action-strip .btn,
    .controller-action-strip .btn-group {
        flex: 0 0 auto;
        white-space: nowrap;
    }
    .controller-editor-toolbar {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 0.75rem;
        flex-wrap: nowrap;
        overflow-x: auto;
    }
    .controller-editor-toolbar::-webkit-scrollbar {
        height: 6px;
    }
    .controller-editor-toolbar::-webkit-scrollbar-thumb {
        background: rgba(108, 117, 125, 0.35);
        border-radius: 999px;
    }
    .controller-editor-toolbar-group {
        display: flex;
        align-items: center;
        gap: 0.6rem;
        flex: 0 0 auto;
        white-space: nowrap;
    }
    .controller-editor-toolbar-group--grow {
        flex: 1 1 auto;
        min-width: 0;
    }
    .controller-editor-toolbar .btn-group,
    .controller-editor-toolbar .btn {
        flex: 0 0 auto;
    }
    .controller-toolbar-secondary {
        display: inline-flex;
        align-items: center;
        gap: 0.35rem;
        padding: 0.15rem 0.25rem;
        border: 1px solid #dee2e6;
        border-radius: 0.45rem;
        background: #fff;
    }
    .controller-toolbar-secondary .btn {
        border: 0;
        box-shadow: none;
    }
    .controller-inline-divider {
        width: 1px;
        align-self: stretch;
        background: #dee2e6;
    }
    .controller-workspace-sidebar .card {
        border-radius: 0.6rem;
    }
    .controller-workspace-sidebar .card-header {
        padding: 0.65rem 0.85rem;
    }
    .controller-workspace-sidebar .card-body {
        padding: 0.85rem;
    }
    .controller-workspace-sidebar .form-label {
        margin-bottom: 0.35rem;
        font-size: 0.84rem;
        font-weight: 600;
    }
    .controller-workspace-sidebar .form-text,
    .controller-workspace-sidebar .small {
        font-size: 0.78rem;
    }
    .controller-workspace-sidebar .btn {
        white-space: nowrap;
    }
    .controller-agent-action-row {
        display: flex;
        gap: 0.45rem;
        flex-wrap: nowrap;
    }
    .controller-agent-action-row .btn {
        flex: 1 1 0;
        min-width: 0;
        padding-left: 0.55rem;
        padding-right: 0.55rem;
    }
    .controller-workspace-sidebar .controller-agent-scroll {
        max-height: 180px;
    }
    .controller-workspace-sidebar .list-group-item {
        padding-left: 0;
        padding-right: 0;
    }
    .controller-code-card .card-header {
        padding: 0.7rem 0.85rem;
    }
    .controller-code-card .card-header > span {
        font-size: 0.95rem;
        font-weight: 600;
    }
    .controller-version-card .card-body {
        padding: 0.9rem 1rem;
    }
    .controller-version-card .card-title {
        font-size: 1rem;
    }
    .controller-version-table {
        font-size: 0.84rem;
    }
    .controller-version-table th,
    .controller-version-table td {
        padding: 0.45rem 0.5rem;
        vertical-align: middle;
    }
    .controller-version-table th {
        white-space: nowrap;
    }
    .controller-version-nowrap {
        white-space: nowrap;
    }
    .controller-version-actions {
        display: inline-flex;
        gap: 0.3rem;
        flex-wrap: nowrap;
    }
    .controller-version-actions .btn {
        padding: 0.2rem 0.45rem;
        line-height: 1.1;
    }
    @media (max-width: 1199.98px) {
        .controller-workspace-sidebar {
            position: static;
        }
        .controller-stepbar {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }
    }
    @media (max-width: 767.98px) {
        .controller-stepbar {
            grid-template-columns: 1fr;
        }
        .controller-editor-toolbar {
            display: block;
            overflow-x: visible;
        }
        .controller-editor-toolbar-group {
            margin-bottom: 0.75rem;
            flex-wrap: wrap;
            white-space: normal;
        }
        .controller-editor-toolbar-group--grow {
            min-width: auto;
        }
        .controller-inline-divider {
            display: none;
        }
        .controller-agent-action-row {
            flex-wrap: wrap;
        }
        .controller-agent-action-row .btn {
            flex: 1 1 calc(50% - 0.45rem);
        }
    }
</style>
<?php if ($branchMissing && $branchCanManage) { ?>
    <div class="alert alert-warning d-flex justify-content-between align-items-center flex-wrap gap-2">
        <div>
            <strong>Beta branch not initialized.</strong>
            <div class="small text-muted">Create a beta branch to start editing without affecting live traffic.</div>
        </div>
        <a href="<?php echo $this->runData['route']['rad_admin_url']; ?>/controller/branchcreate/<?php echo urlencode($this->runData['data']['controller']['uid'] ?? ''); ?>" class="btn btn-outline-primary btn-sm">
            <i class="bi bi-branch"></i> Create Beta Branch
        </a>
    </div>
<?php } ?>
<div class="card controller-hero-card mb-3">
    <div class="card-body d-flex justify-content-between align-items-center flex-wrap gap-3">
        <div>
            <div class="d-flex flex-wrap gap-2 mb-2">
                <span class="controller-meta-chip">
                    <i class="bi bi-box"></i>
                    <?php echo htmlspecialchars($this->runData['route']['ms_name']); ?>
                </span>
                <span class="controller-meta-chip">
                    <i class="bi bi-cpu"></i>
                    <?php echo htmlspecialchars($controllerTypeLabel); ?>
                </span>
                <span class="controller-meta-chip">
                    <i class="bi bi-git"></i>
                    <?php echo strtoupper($branch); ?>
                </span>
                <?php if ($previewActive) { ?>
                    <span class="controller-meta-chip">
                        <i class="bi bi-eye"></i>
                        Runtime Preview Active
                    </span>
                <?php } ?>
                <span class="controller-meta-chip">
                    <i class="bi bi-file-earmark-code"></i>
                    <?php echo htmlspecialchars($sourceFileLabel); ?>
                </span>
            </div>
            <h5 class="mb-1">Controller Workspace</h5>
            <div class="text-muted small">
                Work in a guided flow: capture the task, review the plan, inspect the patch, then apply and version without leaving the page.
            </div>
            <?php if ($resolvedPathLabel !== '') { ?>
                <div class="small text-muted mt-2">
                    <strong>Resolved path:</strong> <?php echo htmlspecialchars($resolvedPathLabel); ?>
                </div>
            <?php } ?>
            <?php if ($previewActive && !empty($previewContext['expires_at'])) { ?>
                <div class="small text-muted mt-1">
                    Beta runtime preview is active for this microservice until <?php echo htmlspecialchars($previewContext['expires_at']); ?>.
                </div>
            <?php } ?>
        </div>
        <div class="controller-action-strip">
            <button type="button" class="btn btn-outline-secondary btn-sm" id="controller-open-versions-top">
                <i class="bi bi-clock-history me-1"></i>Versions
            </button>
            <?php if ($branchCanManage) { ?>
                <div class="btn-group btn-group-sm" role="group" aria-label="Branch actions">
                    <?php if ($branch === 'beta') { ?>
                        <a href="<?php echo $this->runData['route']['rad_admin_url']; ?>/controller/code/<?php echo urlencode($this->runData['route']['pathparts'][3] ?? $this->runData['route']['ms_name']); ?>/<?php echo urlencode($this->runData['data']['controller']['s_name']); ?>?branch=live" class="btn btn-outline-secondary">
                            Open Live
                        </a>
                        <?php if ($branchCanMerge) { ?>
                            <a href="<?php echo $this->runData['route']['rad_admin_url']; ?>/controller/branchmerge/<?php echo urlencode($this->runData['data']['controller']['uid'] ?? ''); ?>" class="btn btn-success" onclick="return confirm('Merge beta into live?');">
                                Merge
                            </a>
                        <?php } ?>
                        <?php if ($previewCanManage) { ?>
                            <?php if ($previewActive) { ?>
                                <a href="<?php echo $this->runData['route']['rad_admin_url']; ?>/controller/previewstop/<?php echo urlencode($this->runData['data']['controller']['uid'] ?? ''); ?>" class="btn btn-info">
                                    Stop Preview
                                </a>
                            <?php } else { ?>
                                <a href="<?php echo $this->runData['route']['rad_admin_url']; ?>/controller/previewstart/<?php echo urlencode($this->runData['data']['controller']['uid'] ?? ''); ?>" class="btn btn-outline-info">
                                    Start Preview
                                </a>
                            <?php } ?>
                        <?php } ?>
                    <?php } elseif ($branchHasBeta) { ?>
                        <a href="<?php echo $this->runData['route']['rad_admin_url']; ?>/controller/code/<?php echo urlencode($this->runData['route']['pathparts'][3] ?? $this->runData['route']['ms_name']); ?>/<?php echo urlencode($this->runData['data']['controller']['s_name']); ?>?branch=beta" class="btn btn-warning">
                            Open Beta
                        </a>
                    <?php } else { ?>
                        <a href="<?php echo $this->runData['route']['rad_admin_url']; ?>/controller/branchcreate/<?php echo urlencode($this->runData['data']['controller']['uid'] ?? ''); ?>" class="btn btn-outline-primary">
                            Create Beta
                        </a>
                    <?php } ?>
                </div>
            <?php } ?>
        </div>
    </div>
</div>
<div class="card border-0 shadow-sm mb-3">
    <div class="card-body">
        <div class="controller-stepbar">
            <div class="controller-step is-active" id="controller-step-context">
                <div class="controller-step-label">1</div>
                <div class="controller-step-title">Capture Task</div>
                <div class="controller-step-copy">Describe the change and choose the scope.</div>
            </div>
            <div class="controller-step" id="controller-step-plan">
                <div class="controller-step-label">2</div>
                <div class="controller-step-title">Review Plan</div>
                <div class="controller-step-copy">Check files, risks, and implementation steps first.</div>
            </div>
            <div class="controller-step" id="controller-step-patch">
                <div class="controller-step-label">3</div>
                <div class="controller-step-title">Review Patch</div>
                <div class="controller-step-copy">Inspect the proposed diff without changing the editor yet.</div>
            </div>
            <div class="controller-step" id="controller-step-apply">
                <div class="controller-step-label">4</div>
                <div class="controller-step-title">Apply Safely</div>
                <div class="controller-step-copy">Write the file only after explicit approval and checksum validation.</div>
            </div>
            <div class="controller-step" id="controller-step-version">
                <div class="controller-step-label">5</div>
                <div class="controller-step-title">Versioned</div>
                <div class="controller-step-copy">Capture the resulting code as the latest restorable snapshot.</div>
            </div>
        </div>
    </div>
</div>
<?php if (!empty($branchHistory)) { ?>
    <div class="card border-0 shadow-sm mb-3">
        <div class="card-body">
            <h6 class="mb-2">Branch Timeline</h6>
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <thead class="small text-muted">
                        <tr>
                            <th>Status</th>
                            <th>Note</th>
                            <th>When</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($branchHistory as $entry) { ?>
                            <tr>
                                <td class="fw-semibold"><?php echo htmlspecialchars($entry['s_status'] ?? ''); ?></td>
                                <td><?php echo htmlspecialchars($entry['s_note'] ?? ''); ?></td>
                                <td class="text-muted small"><?php echo htmlspecialchars($entry['createstamp'] ?? ''); ?></td>
                            </tr>
                        <?php } ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
<?php } ?>
<div class="row">
    <div class="col-xl-8 controller-workspace-left">
        <ul class="nav nav-tabs controller-workspace-nav mb-3" id="controller-workspace-tabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" type="button" id="controller-tab-editor" data-controller-tab="editor" aria-selected="true">
                    <i class="bi bi-code-square me-1"></i>Code Editor
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" type="button" id="controller-tab-patch" data-controller-tab="patch" aria-selected="false">
                    <i class="bi bi-file-diff me-1"></i>Patch Review
                    <span class="badge bg-secondary ms-1 d-none" id="controller-tab-patch-badge">Ready</span>
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" type="button" id="controller-tab-versions" data-controller-tab="versions" aria-selected="false">
                    <i class="bi bi-clock-history me-1"></i>Version History
                </button>
            </li>
        </ul>

        <div class="controller-tab-pane active" id="controller-pane-editor">
            <div id="controller-editor-shell">
            <div class="card controller-code-card">
                <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
                    <span>Microservicelet Controller Code</span>
                    <div class="controller-editor-toolbar">
                        <div class="controller-editor-toolbar-group controller-editor-toolbar-group--grow">
                            <div class="btn-group btn-group-sm" role="group" aria-label="Controller editor toolbar">
                                <button class="btn btn-outline-secondary" onclick="controllerPerformUndo()" title="Undo"><i class="bi bi-arrow-counterclockwise"></i></button>
                                <button class="btn btn-outline-secondary" onclick="controllerPerformRedo()" title="Redo"><i class="bi bi-arrow-clockwise"></i></button>
                                <button class="btn btn-outline-secondary" onclick="controllerToggleComment()" title="Toggle Comment"><i class="bi bi-slash-square"></i></button>
                                <button class="btn btn-outline-secondary" onclick="controllerFormatCode()" title="Format Code"><i class="bi bi-code-square"></i></button>
                                <button class="btn btn-outline-secondary" onclick="controllerGoToLine()" title="Go to Line"><i class="bi bi-arrow-up-square"></i></button>
                                <button class="btn btn-outline-secondary" onclick="controllerToggleWrap()" title="Toggle Line Wrap"><i class="bi bi-text-wrap"></i></button>
                                <button class="btn btn-outline-secondary" onclick="controllerFindReplace()" title="Find & Replace"><i class="bi bi-search"></i></button>
                            </div>
                            <small class="text-muted text-nowrap controller-inline-status" id="controller-save-status">All changes saved.</small>
                        </div>
                        <div class="controller-inline-divider"></div>
                        <div class="controller-editor-toolbar-group">
                            <button type="button" class="btn btn-sm btn-warning" id="controller-version-btn">
                                <i class="bi bi-save"></i> Save & Version
                            </button>
                            <div class="controller-toolbar-secondary">
                                <button type="button" class="btn btn-sm btn-outline-secondary" id="controller-open-versions-editor" title="Version History">
                                    <i class="bi bi-clock-history"></i>
                                </button>
                                <button type="button" class="btn btn-sm btn-outline-secondary" id="controller-fullscreen-btn" onclick="controllerToggleExpand()" title="Full screen mode">
                                    <i class="bi bi-arrows-angle-expand" id="controller-fullscreen-icon"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="card-body px-0 py-0">
                    <div id="code_class" style="height: 760px; width: 100%;"></div>
                </div>
            </div>
            </div>
        </div>

        <div class="controller-tab-pane" id="controller-pane-patch">
            <div class="card" id="controller-agent-diff-panel">
                <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <div>
                        <strong>Agent Patch Preview</strong>
                        <div class="small text-muted" id="controller-agent-diff-summary">No patch generated yet.</div>
                    </div>
                    <div class="d-flex gap-2">
                        <button type="button" class="btn btn-outline-secondary" id="controller-agent-diff-discard">
                            <i class="bi bi-x-lg"></i> Discard
                        </button>
                        <button type="button" class="btn btn-primary" id="controller-agent-apply">
                            <i class="bi bi-check-lg"></i> <span id="controller-agent-apply-label">Apply + Lint + Version</span>
                        </button>
                    </div>
                </div>
                <div class="card-body">
                    <div class="small text-muted mb-2" id="controller-agent-diff-meta">Generate a patch to review it here while keeping the code editor unchanged in the Code Editor tab.</div>
                    <div class="small mb-3" id="controller-agent-diff-warnings">
                        <div class="alert alert-light border mb-0 small">Patch review opens in this tab. Nothing is written back into the editor until you explicitly apply.</div>
                    </div>
                    <div id="controller-agent-diff-editor" style="height: 480px; width: 100%; border: 1px solid #dee2e6; border-radius: 0.5rem;"></div>
                </div>
            </div>
        </div>

        <div class="controller-tab-pane" id="controller-pane-versions">
            <div class="card controller-version-card" id="controller-version-history">
                <div class="card-body">
                    <h5 class="card-title mb-3">Version History</h5>
                    <div id="controller-versions-empty" class="alert alert-secondary mb-0<?php echo empty($versions) ? '' : ' d-none'; ?>">No versions captured yet.</div>
                    <div id="controller-versions-table-wrap"<?php echo empty($versions) ? ' class="d-none"' : ''; ?>>
                        <div class="table-responsive">
                            <table class="table table-sm align-middle mb-0 controller-version-table">
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
                                <tbody id="controller-versions-body">
                                    <?php foreach ($versions as $entry) { ?>
                                        <tr data-version-id="<?php echo htmlspecialchars($entry['id']); ?>">
                                            <td class="fw-semibold controller-version-nowrap"><?php echo htmlspecialchars($entry['id']); ?></td>
                                            <td class="controller-version-nowrap"><?php echo isset($entry['timestamp']) ? \Core\Sys\TimeHelper::formatUtc($entry['timestamp'], $timezone) : '—'; ?></td>
                                            <td class="controller-version-nowrap"><?php echo htmlspecialchars($entry['user'] ?? 'RAD Admin'); ?></td>
                                            <td class="controller-version-nowrap"><?php echo htmlspecialchars($entry['size_human'] ?? '—'); ?></td>
                                            <td><?php echo htmlspecialchars($entry['note'] ?? ''); ?></td>
                                            <td class="text-end controller-version-nowrap">
                                                <span class="controller-version-actions">
                                                <a href="<?php echo $radAdminUrl; ?>/controller/downloadversion/<?php echo urlencode($msSlug); ?>/<?php echo urlencode($controllerSlug); ?>/<?php echo urlencode($entry['id']); ?><?php echo $branchQuery; ?>" class="btn btn-outline-primary btn-sm" title="Download Version">
                                                    <i class="bi bi-download"></i>
                                                </a>
                                                <a href="<?php echo $radAdminUrl; ?>/controller/diffversion/<?php echo urlencode($msSlug); ?>/<?php echo urlencode($controllerSlug); ?>/<?php echo urlencode($entry['id']); ?><?php echo $branchQuery; ?>" class="btn btn-outline-info btn-sm" title="Compare Version">
                                                    <i class="bi bi-arrow-left-right"></i>
                                                </a>
                                                <form action="<?php echo $radAdminUrl; ?>/controller/restoreversion/<?php echo urlencode($msSlug); ?>/<?php echo urlencode($controllerSlug); ?>/<?php echo urlencode($entry['id']); ?><?php echo $branchQuery; ?>" method="post" class="d-inline">
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
    <div class="col-xl-4 mt-3 mt-xl-0">
        <div class="controller-workspace-sidebar">
        <div class="card controller-agent-card mb-3">
            <div class="card-header d-flex justify-content-between align-items-center">
                <div>
                    <strong>Agent Workspace</strong>
                    <div class="small text-muted">Prompt, plan, patch, apply</div>
                </div>
                <span class="badge bg-light text-dark border"><?php echo strtoupper(htmlspecialchars($branch)); ?></span>
            </div>
            <div class="card-body d-flex flex-column gap-3">
                <div class="small text-muted">
                    Use this panel as a guided workflow. Start with a task, review the plan, then generate a patch only when the proposed scope looks correct.
                </div>
                <div>
                    <label for="controller-agent-task" class="form-label">Task</label>
                    <textarea class="form-control" id="controller-agent-task" rows="4" placeholder="Describe what you want changed in this controller..."></textarea>
                    <div class="form-text">Be specific about behavior, constraints, and whether related routes or models should be considered.</div>
                </div>
                <div>
                    <label for="controller-agent-scope" class="form-label">Scope</label>
                    <select class="form-select" id="controller-agent-scope">
                        <option value="controller_only">Current controller only</option>
                        <option value="controller_routes">Controller + related routes</option>
                        <option value="microservice">Whole microservicelet context</option>
                    </select>
                </div>
                <div class="controller-agent-action-row">
                    <button type="button" class="btn btn-outline-secondary" id="controller-agent-refresh">
                        <i class="bi bi-arrow-repeat"></i> Refresh
                    </button>
                    <button type="button" class="btn btn-primary" id="controller-agent-plan">
                        <i class="bi bi-magic"></i> Plan
                    </button>
                    <button type="button" class="btn btn-success" id="controller-agent-patch">
                        <i class="bi bi-file-earmark-code"></i> Patch
                    </button>
                </div>
                <div id="controller-agent-progress" class="controller-agent-progress border rounded p-3 bg-light">
                    <div class="d-flex align-items-center gap-2 mb-1">
                        <div id="controller-agent-progress-spinner" class="spinner-border spinner-border-sm text-primary controller-agent-progress-spinner d-none" role="status" aria-hidden="true"></div>
                        <strong class="small text-uppercase text-muted">Processing</strong>
                    </div>
                    <div id="controller-agent-status" class="small text-muted">Context not loaded yet.</div>
                    <div id="controller-agent-progress-detail" class="small text-muted mt-1">Idle.</div>
                </div>
            </div>
        </div>
        <div class="card mb-3">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <strong class="small text-uppercase text-muted">Workspace Context</strong>
                    <span class="small text-muted" id="controller-agent-context-meta">Loading…</span>
                </div>
                <div class="small mb-2" id="controller-agent-context-summary">Preparing controller workspace context…</div>
                <div class="mb-2" id="controller-agent-context-tags"></div>
                <div class="controller-agent-scroll">
                    <ul class="list-group list-group-flush small" id="controller-agent-context-list"></ul>
                </div>
            </div>
        </div>
        <div class="card mb-3">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <strong class="small text-uppercase text-muted">Implementation Plan</strong>
                    <span class="small text-muted" id="controller-agent-plan-meta">No plan yet</span>
                </div>
                <div class="small mb-2" id="controller-agent-plan-summary">Generate a plan to review scope, risks, files, and checks before changing code.</div>
                <div class="controller-agent-scroll">
                    <ol class="small mb-3 ps-3" id="controller-agent-plan-steps">
                        <li>Agent planning is idle.</li>
                    </ol>
                    <div class="small mb-2"><strong>Risks</strong></div>
                    <ul class="small ps-3 mb-3" id="controller-agent-plan-risks">
                        <li>No plan generated yet.</li>
                    </ul>
                    <div class="small mb-2"><strong>Suggested Files</strong></div>
                    <ul class="small ps-3 mb-0" id="controller-agent-plan-files">
                        <li>No files proposed yet.</li>
                    </ul>
                </div>
            </div>
        </div>
        <div class="card border-0 bg-light">
            <div class="card-body small text-muted">
                <strong class="d-block text-dark mb-2">Operating Rules</strong>
                <ul class="mb-0 ps-3">
                    <li>Patch generation is limited to the current controller file.</li>
                    <li>Apply runs checksum protection and captures a version snapshot.</li>
                    <li>Work on beta when you want review isolation before touching live code.</li>
                </ul>
            </div>
        </div>
        </div>
        </div>
    </div>
</div>
