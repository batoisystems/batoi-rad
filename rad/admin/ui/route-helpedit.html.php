<?php
$route = $this->runData['data']['route'] ?? [];
$ms = $this->runData['data']['ms'] ?? [];
$branch = $this->runData['data']['branch'] ?? 'live';
$branchStatus = $this->runData['data']['branch_status'] ?? [];
$branchHasBeta = !empty($this->runData['data']['branch_has_beta']);
$branchMissing = !empty($this->runData['data']['branch_missing']);
$branchCanManage = !empty($this->runData['data']['branch_can_manage']);
$branchCanMerge = !empty($this->runData['data']['branch_can_merge']);
$branchHistory = $this->runData['data']['branch_history'] ?? [];
$helpContent = (string)($this->runData['data']['help_content'] ?? '');
$helpHtml = (string)($this->runData['data']['help_rendered_html'] ?? '');
$helpSaveUrl = (string)($this->runData['data']['help_save_url'] ?? '');
$helpPreviewUrl = (string)($this->runData['data']['help_preview_url'] ?? '');
$helpGenerateUrl = (string)($this->runData['data']['help_generate_url'] ?? '');
$helpVersions = $this->runData['data']['help_versions'] ?? [];
$helpPath = (string)($this->runData['data']['help_path'] ?? '');
$radAdminUrl = $this->runData['route']['rad_admin_url'];
$routeUid = $route['uid'] ?? '';
$msUid = $ms['uid'] ?? '';
$branchQuery = $this->runData['data']['help_branch_query'] ?? ('?branch=' . ($branch === 'beta' ? 'beta' : 'live'));
$timezone = $this->runData['entity']['timezone'] ?? ($this->runData['config']['sys']['timezone_default'] ?? 'UTC');
$viewUrl = $radAdminUrl . '/route/help/' . $routeUid . $branchQuery;
$detailUrl = $radAdminUrl . '/route/detail/' . $routeUid;
?>

<?php if ($branchMissing && $branchCanManage) { ?>
    <div class="alert alert-warning d-flex justify-content-between align-items-center flex-wrap gap-2">
        <div>
            <strong>Beta branch not initialized.</strong>
            <div class="small text-muted">Create a beta branch before saving beta Help content.</div>
        </div>
        <a href="<?php echo $radAdminUrl; ?>/route/branchcreate/<?php echo urlencode($routeUid); ?>?return=helpedit" class="btn btn-outline-primary btn-sm">
            <i class="bi bi-branch"></i> Create Beta Branch
        </a>
    </div>
<?php } ?>

<div class="card border-0 shadow-sm mb-3">
    <div class="card-body d-flex justify-content-between align-items-center flex-wrap gap-3">
        <div>
            <div class="text-muted small">Editing Help branch</div>
            <div class="d-flex align-items-center gap-2">
                <span class="badge <?php echo $branch === 'beta' ? 'bg-warning text-dark' : 'bg-success'; ?>">
                    <?php echo strtoupper($branch); ?>
                </span>
                <?php if (!empty($branchStatus['s_status'])) { ?>
                    <span class="badge bg-light text-dark border">Status: <?php echo htmlspecialchars($branchStatus['s_status']); ?></span>
                <?php } ?>
            </div>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <a href="<?php echo htmlspecialchars($detailUrl); ?>" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-arrow-left-circle me-1"></i>Route Detail
            </a>
            <a href="<?php echo htmlspecialchars($viewUrl); ?>" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-eye me-1"></i>View Help
            </a>
            <?php if ($branchCanManage) { ?>
                <?php if ($branch === 'beta') { ?>
                    <a href="<?php echo $radAdminUrl; ?>/route/helpedit/<?php echo urlencode($routeUid); ?>?branch=live" class="btn btn-outline-secondary btn-sm">
                        <i class="bi bi-box-arrow-up-right me-1"></i>Open Live
                    </a>
                    <?php if ($branchCanMerge) { ?>
                        <a href="<?php echo $radAdminUrl; ?>/route/branchmerge/<?php echo urlencode($routeUid); ?>?return=helpedit" class="btn btn-success btn-sm" onclick="return confirm('Merge beta into live?');">
                            <i class="bi bi-arrow-left-right me-1"></i>Merge to Live
                        </a>
                    <?php } ?>
                    <a href="<?php echo $radAdminUrl; ?>/route/branchdiscard/<?php echo urlencode($routeUid); ?>?return=helpedit" class="btn btn-outline-danger btn-sm" onclick="return confirm('Discard beta branch?');">
                        <i class="bi bi-trash me-1"></i>Discard Beta
                    </a>
                <?php } elseif ($branchHasBeta) { ?>
                    <a href="<?php echo $radAdminUrl; ?>/route/helpedit/<?php echo urlencode($routeUid); ?>?branch=beta" class="btn btn-warning btn-sm">
                        <i class="bi bi-box-arrow-up-right me-1"></i>Open Beta
                    </a>
                <?php } else { ?>
                    <a href="<?php echo $radAdminUrl; ?>/route/branchcreate/<?php echo urlencode($routeUid); ?>?return=helpedit" class="btn btn-outline-primary btn-sm">
                        <i class="bi bi-branch me-1"></i>Create Beta Branch
                    </a>
                <?php } ?>
            <?php } ?>
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

<div class="card border-0 shadow-sm mb-3">
    <div class="card-body d-flex justify-content-between align-items-center flex-wrap gap-3">
        <div>
            <div class="text-muted small text-uppercase mb-1">Help File</div>
            <code><?php echo htmlspecialchars($helpPath); ?></code>
        </div>
        <div class="d-flex flex-column align-items-end gap-1">
            <small class="text-muted" id="route-help-save-status">Ready.</small>
            <div class="d-flex gap-2">
                <button type="button" class="btn btn-outline-dark btn-sm" id="route-help-generate-btn">
                    <i class="bi bi-stars me-1"></i>Generate Draft
                </button>
                <button type="button" class="btn btn-outline-secondary btn-sm" id="route-help-preview-btn">
                    <i class="bi bi-arrow-clockwise me-1"></i>Refresh Preview
                </button>
                <button type="button" class="btn btn-primary btn-sm" id="route-help-save-btn">
                    <i class="bi bi-floppy me-1"></i>Save Help
                </button>
                <button type="button" class="btn btn-warning btn-sm" id="route-help-save-version-btn">
                    <i class="bi bi-save me-1"></i>Save &amp; Version
                </button>
            </div>
        </div>
    </div>
</div>

<div class="row g-3">
    <div class="col-lg-6">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <strong>Markdown</strong>
                <span class="small text-muted"><?php echo htmlspecialchars($route['s_name'] ?? ''); ?></span>
            </div>
            <div class="card-body">
                <textarea class="form-control font-monospace" id="route-help-editor" rows="28"><?php echo htmlspecialchars($helpContent); ?></textarea>
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <strong>Rendered Preview</strong>
                <a href="<?php echo htmlspecialchars($viewUrl); ?>" class="btn btn-outline-secondary btn-sm">
                    <i class="bi bi-box-arrow-up-right me-1"></i>Open View Page
                </a>
            </div>
            <div class="card-body route-help-rendered" id="route-help-preview">
                <?php echo $helpHtml; ?>
            </div>
        </div>
    </div>
</div>

<div class="card border-0 shadow-sm mt-4">
    <div class="card-header bg-white d-flex justify-content-between align-items-center">
        <strong>Authoring Guide</strong>
        <span class="small text-muted">Use role markers when a route is used differently by different roles.</span>
    </div>
    <div class="card-body">
        <p class="text-muted mb-3">Write the shared user guide content first, then add role-specific sections only where needed.</p>
        <pre class="bg-light border rounded p-3 mb-0"><code>## Common Steps

...

&lt;!-- role:admin --&gt;
## Admin Only
...
&lt;!-- /role:admin --&gt;</code></pre>
    </div>
</div>

<div class="card border-0 shadow-sm mt-4">
    <div class="card-body">
        <h5 class="card-title mb-3">Help Version History</h5>
        <?php if (empty($helpVersions)) { ?>
            <div class="alert alert-secondary mb-0">No help versions captured yet.</div>
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
                        <?php foreach ($helpVersions as $entry) { ?>
                            <tr>
                                <td class="fw-semibold"><?php echo htmlspecialchars($entry['id'] ?? ''); ?></td>
                                <td><?php echo isset($entry['timestamp']) ? \Core\Sys\TimeHelper::formatUtc($entry['timestamp'], $timezone) : '—'; ?></td>
                                <td><?php echo htmlspecialchars($entry['user'] ?? 'RAD Admin'); ?></td>
                                <td><?php echo htmlspecialchars($entry['size_human'] ?? '—'); ?></td>
                                <td><?php echo htmlspecialchars($entry['note'] ?? ''); ?></td>
                                <td class="text-end">
                                    <a href="<?php echo $radAdminUrl; ?>/route/helpdownloadversion/<?php echo urlencode((string)($ms['s_name'] ?? '')); ?>/<?php echo urlencode((string)($route['s_name'] ?? '')); ?>/<?php echo urlencode((string)($entry['id'] ?? '')); ?><?php echo $branchQuery; ?>" class="btn btn-outline-primary btn-sm">
                                        <i class="bi bi-download me-1"></i>Download
                                    </a>
                                    <a href="<?php echo $radAdminUrl; ?>/route/helpdiffversion/<?php echo urlencode((string)($ms['s_name'] ?? '')); ?>/<?php echo urlencode((string)($route['s_name'] ?? '')); ?>/<?php echo urlencode((string)($entry['id'] ?? '')); ?><?php echo $branchQuery; ?>" class="btn btn-outline-info btn-sm">
                                        <i class="bi bi-arrow-left-right me-1"></i>Diff
                                    </a>
                                    <form action="<?php echo $radAdminUrl; ?>/route/helprestoreversion/<?php echo urlencode((string)($ms['s_name'] ?? '')); ?>/<?php echo urlencode((string)($route['s_name'] ?? '')); ?>/<?php echo urlencode((string)($entry['id'] ?? '')); ?><?php echo $branchQuery; ?>" method="post" class="d-inline">
                                        <button type="submit" class="btn btn-outline-danger btn-sm" onclick="return confirm('Restore this help version?');">
                                            <i class="bi bi-arrow-counterclockwise me-1"></i>Restore
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
</div>

<style>
.route-help-rendered h1,
.route-help-rendered h2,
.route-help-rendered h3,
.route-help-rendered h4,
.route-help-rendered h5,
.route-help-rendered h6 {
    margin-top: 1.25rem;
    margin-bottom: 0.6rem;
}
.route-help-rendered h1:first-child,
.route-help-rendered h2:first-child,
.route-help-rendered h3:first-child {
    margin-top: 0;
}
.route-help-rendered code {
    background: #f8f9fa;
    padding: 0.1rem 0.3rem;
    border-radius: 0.25rem;
}
</style>

<script>
(function () {
    const editor = document.getElementById('route-help-editor');
    const preview = document.getElementById('route-help-preview');
    const saveBtn = document.getElementById('route-help-save-btn');
    const saveVersionBtn = document.getElementById('route-help-save-version-btn');
    const previewBtn = document.getElementById('route-help-preview-btn');
    const generateBtn = document.getElementById('route-help-generate-btn');
    const saveStatus = document.getElementById('route-help-save-status');

    async function postJson(url, payload) {
        const response = await fetch(url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify(payload)
        });
        return response.json();
    }

    async function refreshPreview(silent) {
        if (!silent) {
            saveStatus.textContent = 'Refreshing preview...';
        }
        try {
            const result = await postJson('<?php echo htmlspecialchars($helpPreviewUrl, ENT_QUOTES, 'UTF-8'); ?>', {
                content: editor.value
            });
            if (result.html !== undefined) {
                preview.innerHTML = result.html;
            }
            if (!silent) {
                saveStatus.textContent = 'Preview updated.';
            }
        } catch (error) {
            saveStatus.textContent = 'Preview failed.';
        }
    }

    async function saveHelp(createVersion) {
        saveStatus.textContent = createVersion ? 'Saving and versioning...' : 'Saving...';
        try {
            const result = await postJson('<?php echo htmlspecialchars($helpSaveUrl, ENT_QUOTES, 'UTF-8'); ?>', {
                content: editor.value,
                create_version: createVersion ? 1 : 0
            });
            if (result.message) {
                saveStatus.textContent = result.message;
                return;
            }
            await refreshPreview(true);
            saveStatus.textContent = createVersion ? 'Saved and versioned.' : 'Saved.';
        } catch (error) {
            saveStatus.textContent = 'Save failed.';
        }
    }

    async function generateDraft() {
        if (!window.confirm('Generate a Help draft and replace the editor content? Unsaved changes in the editor will be overwritten.')) {
            return;
        }
        saveStatus.textContent = 'Generating draft...';
        try {
            const result = await postJson('<?php echo htmlspecialchars($helpGenerateUrl, ENT_QUOTES, 'UTF-8'); ?>', {});
            if (result.error) {
                saveStatus.textContent = result.error;
                return;
            }
            if (typeof result.suggestion === 'string' && result.suggestion.trim() !== '') {
                editor.value = result.suggestion;
            }
            if (result.html !== undefined) {
                preview.innerHTML = result.html;
            } else {
                await refreshPreview(true);
            }
            saveStatus.textContent = 'Draft generated. Review before saving.';
        } catch (error) {
            saveStatus.textContent = 'Draft generation failed.';
        }
    }

    previewBtn.addEventListener('click', refreshPreview);
    saveBtn.addEventListener('click', function () { saveHelp(false); });
    saveVersionBtn.addEventListener('click', function () { saveHelp(true); });
    generateBtn.addEventListener('click', generateDraft);
})();
</script>
