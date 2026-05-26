<?php
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
$postThroughAjaxUrl = $this->runData['config']['sys']['base_url'].'/rad-admin/route/codesave/'.$this->runData['data']['route']['ms_name'].'/'.$this->runData['data']['route']['id'] . $branchQuery;
$aiAssistanceUrl = $this->runData['config']['sys']['base_url'].'/rad-admin/route/aiassist/'.$this->runData['data']['route']['ms_name'].'/'.$this->runData['data']['route']['id'] . $branchQuery;
$versions = $this->runData['data']['versions'] ?? [];
$radAdminUrl = $this->runData['route']['rad_admin_url'];
$msName = $this->runData['data']['route']['ms_name'];
$routeId = $this->runData['data']['route']['id'];
$routeUid = $this->runData['data']['route']['uid'] ?? ($this->runData['route']['pathparts'][3] ?? '');
$msUid = $this->runData['route']['pathparts'][4] ?? '';
$timezone = $this->runData['entity']['timezone'] ?? ($this->runData['config']['sys']['timezone_default'] ?? 'UTC');
?>
<?php if ($branchMissing && $branchCanManage) { ?>
    <div class="alert alert-warning d-flex justify-content-between align-items-center flex-wrap gap-2">
        <div>
            <strong>Beta branch not initialized.</strong>
            <div class="small text-muted">Create a beta branch to start editing without affecting live traffic.</div>
        </div>
        <a href="<?php echo $radAdminUrl; ?>/route/branchcreate/<?php echo urlencode($routeUid); ?>" class="btn btn-outline-primary btn-sm">
            <i class="bi bi-branch"></i> Create Beta Branch
        </a>
    </div>
<?php } ?>
<div class="card border-0 shadow-sm mb-3">
    <div class="card-body d-flex justify-content-between align-items-center flex-wrap gap-3">
        <div>
            <div class="text-muted small">Editing branch</div>
            <div class="d-flex align-items-center gap-2">
                <span class="badge <?php echo $branch === 'beta' ? 'bg-warning text-dark' : 'bg-success'; ?>">
                    <?php echo strtoupper($branch); ?>
                </span>
                <?php if (!empty($branchStatus['s_status'])) { ?>
                    <span class="badge bg-light text-dark border">Status: <?php echo htmlspecialchars($branchStatus['s_status']); ?></span>
                <?php } ?>
                <?php if ($previewActive) { ?>
                    <span class="badge bg-info text-dark">Runtime Preview Active</span>
                <?php } ?>
            </div>
            <?php if ($previewActive && !empty($previewContext['expires_at'])) { ?>
                <div class="small text-muted mt-2">
                    Beta runtime preview is active for this route until <?php echo htmlspecialchars($previewContext['expires_at']); ?>.
                </div>
            <?php } ?>
        </div>
        <?php if ($branchCanManage) { ?>
            <div class="d-flex flex-wrap gap-2">
                <?php if ($branch === 'beta') { ?>
                    <a href="<?php echo $radAdminUrl; ?>/route/code/<?php echo urlencode($routeUid); ?>/<?php echo urlencode($msUid); ?>?branch=live" class="btn btn-outline-secondary btn-sm">
                        Open Live
                    </a>
                    <?php if ($branchCanMerge) { ?>
                        <a href="<?php echo $radAdminUrl; ?>/route/branchmerge/<?php echo urlencode($routeUid); ?>" class="btn btn-success btn-sm" onclick="return confirm('Merge beta into live?');">
                            Merge to Live
                        </a>
                    <?php } ?>
                    <a href="<?php echo $radAdminUrl; ?>/route/branchdiscard/<?php echo urlencode($routeUid); ?>" class="btn btn-outline-danger btn-sm" onclick="return confirm('Discard beta branch?');">
                        Discard Beta
                    </a>
                    <?php if ($previewCanManage) { ?>
                        <?php if ($previewActive) { ?>
                            <a href="<?php echo $radAdminUrl; ?>/route/previewstop/<?php echo urlencode($routeUid); ?>" class="btn btn-info btn-sm">
                                Stop Preview
                            </a>
                        <?php } else { ?>
                            <a href="<?php echo $radAdminUrl; ?>/route/previewstart/<?php echo urlencode($routeUid); ?>" class="btn btn-outline-info btn-sm">
                                Start Preview
                            </a>
                        <?php } ?>
                    <?php } ?>
                <?php } else { ?>
                    <?php if ($branchHasBeta) { ?>
                        <a href="<?php echo $radAdminUrl; ?>/route/code/<?php echo urlencode($routeUid); ?>/<?php echo urlencode($msUid); ?>?branch=beta" class="btn btn-warning btn-sm">
                            Open Beta
                        </a>
                    <?php } else { ?>
                        <a href="<?php echo $radAdminUrl; ?>/route/branchcreate/<?php echo urlencode($routeUid); ?>" class="btn btn-outline-primary btn-sm">
                            Create Beta Branch
                        </a>
                    <?php } ?>
                <?php } ?>
            </div>
        <?php } ?>
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
<div class="alert alert-light border d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
    <div class="d-flex align-items-center gap-2">
        <span class="text-primary"><i class="bi bi-stars"></i></span>
        <div>
            <strong>AI Assist</strong>
            <div class="small text-muted">Press <kbd>Shift</kbd> + <kbd>Space</kbd> in any editor for inline help.</div>
        </div>
    </div>
    <div class="d-flex flex-column align-items-end gap-1 text-end">
        <div class="d-flex align-items-center gap-2">
            <button type="button" class="btn btn-outline-primary btn-sm" id="route-ai-btn">
                <i class="bi bi-stars me-1"></i>Ask AI
            </button>
            <small class="text-muted" id="route-ai-status">Ready.</small>
        </div>
        <small class="text-muted" id="route-save-status">All changes saved.</small>
        <button type="button" class="btn btn-warning btn-sm mt-1" id="route-version-btn">
            <i class="bi bi-save"></i> Save & Version
        </button>
    </div>
</div>

<div class="row" id="editor-container">
    <div class="col-md-6" id="left-panel">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                Route Load Code
                <div>
                    <button class="btn btn-outline-secondary btn-sm" onclick="toggleExpand('left')"><i class="bi bi-arrows-expand"></i></button>
                </div>
                <div id="toolbar-load" class="btn-group btn-group-sm" role="group" aria-label="Tooltip">
                    <button class="btn btn-outline-secondary" onclick="performUndo(editorLoad)" title="Undo"><i class="bi bi-arrow-counterclockwise"></i></button>
                    <button class="btn btn-outline-secondary" onclick="performRedo(editorLoad)" title="Redo"><i class="bi bi-arrow-clockwise"></i></button>
                    <button class="btn btn-outline-secondary" onclick="toggleComment(editorLoad)" title="Toggle Comment"><i class="bi bi-dash-square"></i></button>
                    <button class="btn btn-outline-secondary" onclick="formatCode(editorLoad)" title="Format Code"><i class="bi bi-code-square"></i></button>
                    <button class="btn btn-outline-secondary" onclick="goToLine(editorLoad)" title="Go to Line"><i class="bi bi-arrow-up-square"></i></button>
                    <button class="btn btn-outline-secondary" onclick="toggleLineWrap(editorLoad)" title="Toggle Line Wrap"><i class="bi bi-text-wrap"></i></button>
                    <button class="btn btn-outline-secondary" onclick="findAndReplace();" title="Find and Replace" data-bs-toggle="modal" data-bs-target="#findReplaceModal"><i class="bi bi-search"></i><i class="bi bi-arrow-counterclockwise"></i></button>
                </div>
                <div>
                    <a href="path_to_error_page.php" target="_blank" class="btn btn-sm btn-outline-danger me-2">
                        <i class="bi bi-exclamation-triangle-fill" title="Error Display"></i>
                    </a>
                    <a href="path_to_preview_page.php" target="_blank" class="btn btn-sm btn-outline-success">
                        <i class="bi bi-eye-fill" title="Preview"></i>
                    </a>
                </div>
            </div>
            <div class="card-body px-0 py-0">
                <div id="code_load" style="height: 600px; width: 100%;"></div>
            </div>
        </div>
    </div>

    <div class="col-md-6" id="right-panel">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <div>
                    <button class="btn btn-outline-secondary btn-sm" onclick="toggleExpand('right')"><i class="bi bi-arrows-expand"></i></button>
                </div>
                <ul class="nav nav-tabs" id="codeTab" role="tablist">
                    <li class="nav-item" role="presentation">
                        <a class="nav-link active" id="pagepart-tab" data-bs-toggle="tab" href="#pagepartContent" role="tab" aria-controls="pagepart" aria-selected="true">Pagepart Code</a>
                    </li>
                    <li class="nav-item" role="presentation">
                        <a class="nav-link" id="prepart-tab" data-bs-toggle="tab" href="#prepartContent" role="tab" aria-controls="prepart" aria-selected="false">Prepart Code</a>
                    </li>
                    <li class="nav-item" role="presentation">
                        <a class="nav-link" id="postpart-tab" data-bs-toggle="tab" href="#postpartContent" role="tab" aria-controls="postpart" aria-selected="false">Postpart Code</a>
                    </li>
                </ul>
            </div>
            <div class="tab-content card-body px-0 py-0" id="codeTabContent">
                <div class="tab-pane fade show active" id="pagepartContent" role="tabpanel" aria-labelledby="pagepart-tab">
                    <div id="toolbar-pagepart" class="btn-group btn-group-sm" role="group" aria-label="Tooltip">
                        <button class="btn btn-outline-secondary" onclick="performUndo(editorPagepart)" title="Undo"><i class="bi bi-arrow-counterclockwise"></i></button>
                        <button class="btn btn-outline-secondary" onclick="performRedo(editorPagepart)" title="Redo"><i class="bi bi-arrow-clockwise"></i></button>
                        <button class="btn btn-outline-secondary" onclick="toggleComment(editorPagepart)" title="Toggle Comment"><i class="bi bi-dash-square"></i></button>
                        <button class="btn btn-outline-secondary" onclick="formatCode(editorPagepart)" title="Format Code"><i class="bi bi-code-square"></i></button>
                        <button class="btn btn-outline-secondary" onclick="goToLine(editorPagepart)" title="Go to Line"><i class="bi bi-arrow-up-square"></i></button>
                        <button class="btn btn-outline-secondary" onclick="toggleLineWrap(editorPagepart)" title="Toggle Line Wrap"><i class="bi bi-text-wrap"></i></button>
                        <button class="btn btn-outline-secondary" onclick="findAndReplace();" title="Find and Replace" data-bs-toggle="modal" data-bs-target="#findReplaceModal"><i class="bi bi-search"></i><i class="bi bi-arrow-counterclockwise"></i></button>
                    </div>
                    <div id="code_pagepart" style="height: 600px; width: 100%;"></div>
                </div>
                <div class="tab-pane fade" id="prepartContent" role="tabpanel" aria-labelledby="prepart-tab">
                    <div id="toolbar-prepart" class="btn-group btn-group-sm" role="group" aria-label="Tooltip">
                        <button class="btn btn-outline-secondary" onclick="performUndo(editorPrepart)" title="Undo"><i class="bi bi-arrow-counterclockwise"></i></button>
                        <button class="btn btn-outline-secondary" onclick="performRedo(editorPrepart)" title="Redo"><i class="bi bi-arrow-clockwise"></i></button>
                        <button class="btn btn-outline-secondary" onclick="toggleComment(editorPrepart)" title="Toggle Comment"><i class="bi bi-dash-square"></i></button>
                        <button class="btn btn-outline-secondary" onclick="formatCode(editorPrepart)" title="Format Code"><i class="bi bi-code-square"></i></button>
                        <button class="btn btn-outline-secondary" onclick="goToLine(editorPrepart)" title="Go to Line"><i class="bi bi-arrow-up-square"></i></button>
                        <button class="btn btn-outline-secondary" onclick="toggleLineWrap(editorPrepart)" title="Toggle Line Wrap"><i class="bi bi-text-wrap"></i></button>
                        <button class="btn btn-outline-secondary" onclick="findAndReplace();" title="Find and Replace" data-bs-toggle="modal" data-bs-target="#findReplaceModal"><i class="bi bi-search"></i><i class="bi bi-arrow-counterclockwise"></i></button>
                    </div>
                    <div id="code_prepart" style="height: 600px; width: 100%;"></div>
                </div>
                <div class="tab-pane fade" id="postpartContent" role="tabpanel" aria-labelledby="postpart-tab">
                    <div id="toolbar-postpart" class="btn-group btn-group-sm" role="group" aria-label="Tooltip">
                        <button class="btn btn-outline-secondary" onclick="performUndo(editorPostpart)" title="Undo"><i class="bi bi-arrow-counterclockwise"></i></button>
                        <button class="btn btn-outline-secondary" onclick="performRedo(editorPostpart)" title="Redo"><i class="bi bi-arrow-clockwise"></i></button>
                        <button class="btn btn-outline-secondary" onclick="toggleComment(editorPostpart)" title="Toggle Comment"><i class="bi bi-dash-square"></i></button>
                        <button class="btn btn-outline-secondary" onclick="formatCode(editorPostpart)" title="Format Code"><i class="bi bi-code-square"></i></button>
                        <button class="btn btn-outline-secondary" onclick="goToLine(editorPostpart)" title="Go to Line"><i class="bi bi-arrow-up-square"></i></button>
                        <button class="btn btn-outline-secondary" onclick="toggleLineWrap(editorPostpart)" title="Toggle Line Wrap"><i class="bi bi-text-wrap"></i></button>
                        <button class="btn btn-outline-secondary" onclick="findAndReplace();" title="Find and Replace" data-bs-toggle="modal" data-bs-target="#findReplaceModal"><i class="bi bi-search"></i><i class="bi bi-arrow-counterclockwise"></i></button>
                    </div>
                    <div id="code_postpart" style="height: 600px; width: 100%;"></div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Bootstrap Modal for Find and Replace -->
<div class="modal compact-modal fade" id="findReplaceModal" tabindex="-1" style="z-index: 10000;">
    <div class="modal-dialog compact-dialog">
        <div class="modal-content compact-content">
            <div class="modal-header compact-header">
                <h5 class="modal-title compact-title">Find and Replace</h5>
                <button type="button" class="btn-close compact-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body compact-body">
                <form id="findReplaceForm">
                    <div class="mb-2 compact-group">
                        <label for="findTerm" class="form-label compact-label">Find</label>
                        <input type="text" class="form-control compact-control" id="findTerm">
                    </div>
                    <div class="mb-2 compact-group">
                        <label for="replaceTerm" class="form-label compact-label">Replace</label>
                        <input type="text" class="form-control compact-control" id="replaceTerm">
                    </div>
                </form>
            </div>
            <div class="modal-footer compact-footer">
                <button type="button" class="btn btn-sm btn-secondary compact-btn" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-sm btn-primary compact-btn" onclick="findAndReplace()">Find and Replace</button>
            </div>
        </div>
    </div>
</div>
<div class="card mt-4">
    <div class="card-body">
        <h5 class="card-title mb-3">Version History</h5>
        <?php
        $partLabels = [
            'load' => 'Route Load Code',
            'pagepart' => 'Pagepart Code',
            'prepart' => 'Prepart Code',
            'postpart' => 'Postpart Code',
        ];
        foreach ($partLabels as $partKey => $partLabel) {
            $list = $versions[$partKey] ?? [];
        ?>
            <div class="mb-4">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <h6 class="mb-0"><?php echo $partLabel; ?></h6>
                </div>
                <?php if (empty($list)) { ?>
                    <div class="alert alert-secondary mb-0">No versions captured yet.</div>
                <?php } else { ?>
                    <div class="table-responsive">
                        <table class="table table-sm align-middle mb-0">
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
                            <tbody>
                                <?php foreach ($list as $entry) { ?>
                                    <tr>
                                        <td class="fw-semibold"><?php echo htmlspecialchars($entry['id']); ?></td>
                                        <td><?php echo isset($entry['timestamp']) ? \Core\Sys\TimeHelper::formatUtc($entry['timestamp'], $timezone) : '—'; ?></td>
                                        <td><?php echo htmlspecialchars($entry['user'] ?? 'RAD Admin'); ?></td>
                                        <td><?php echo htmlspecialchars($entry['size_human'] ?? '—'); ?></td>
                                        <td><?php echo htmlspecialchars($entry['note'] ?? ''); ?></td>
                                        <td class="text-end">
                                            <a href="<?php echo $radAdminUrl; ?>/route/downloadversion/<?php echo urlencode($msName); ?>/<?php echo urlencode($routeId); ?>/<?php echo $partKey; ?>/<?php echo urlencode($entry['id']); ?><?php echo $branchQuery; ?>" class="btn btn-outline-primary btn-sm">
                                                <i class="bi bi-download"></i>
                                            </a>
                                            <a href="<?php echo $radAdminUrl; ?>/route/diffversion/<?php echo urlencode($msName); ?>/<?php echo urlencode($routeId); ?>/<?php echo $partKey; ?>/<?php echo urlencode($entry['id']); ?><?php echo $branchQuery; ?>" class="btn btn-outline-info btn-sm">
                                                <i class="bi bi-arrow-left-right"></i>
                                            </a>
                                            <form action="<?php echo $radAdminUrl; ?>/route/restoreversion/<?php echo urlencode($msName); ?>/<?php echo urlencode($routeId); ?>/<?php echo $partKey; ?>/<?php echo urlencode($entry['id']); ?><?php echo $branchQuery; ?>" method="post" class="d-inline">
                                                <button type="submit" class="btn btn-outline-danger btn-sm" onclick="return confirm('Restore this version?');">
                                                    <i class="bi bi-arrow-counterclockwise"></i>
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php } ?>
                            </tbody>
                        </table>
                    </div>
                <?php } ?>
            </div>
        <?php } ?>
    </div>
</div>
