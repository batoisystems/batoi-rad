<?php
$route = $this->runData['data']['route'] ?? [];
$ms = $this->runData['data']['ms'] ?? [];
$branch = $this->runData['data']['branch'] ?? 'live';
$branchStatus = $this->runData['data']['branch_status'] ?? [];
$branchHasBeta = !empty($this->runData['data']['branch_has_beta']);
$branchMissing = !empty($this->runData['data']['branch_missing']);
$branchCanManage = !empty($this->runData['data']['branch_can_manage']);
$branchCanMerge = !empty($this->runData['data']['branch_can_merge']);
$helpExists = !empty($this->runData['data']['help_exists']);
$helpContent = (string)($this->runData['data']['help_content'] ?? '');
$helpHtml = (string)($this->runData['data']['help_rendered_html'] ?? '');
$radAdminUrl = $this->runData['route']['rad_admin_url'];
$routeUid = $route['uid'] ?? '';
$msUid = $ms['uid'] ?? '';
$branchQuery = $this->runData['data']['help_branch_query'] ?? ('?branch=' . ($branch === 'beta' ? 'beta' : 'live'));
$detailUrl = $radAdminUrl . '/route/detail/' . $routeUid;
$editUrl = $radAdminUrl . '/route/helpedit/' . $routeUid . $branchQuery;
?>

<?php if ($branchMissing && $branchCanManage) { ?>
    <div class="alert alert-warning d-flex justify-content-between align-items-center flex-wrap gap-2">
        <div>
            <strong>Beta branch not initialized.</strong>
            <div class="small text-muted">Create a beta branch to draft Help content without changing live documentation.</div>
        </div>
        <a href="<?php echo $radAdminUrl; ?>/route/branchcreate/<?php echo urlencode($routeUid); ?>?return=helpedit" class="btn btn-outline-primary btn-sm">
            <i class="bi bi-branch"></i> Create Beta Branch
        </a>
    </div>
<?php } ?>

<div class="card border-0 shadow-sm mb-3">
    <div class="card-body d-flex justify-content-between align-items-center flex-wrap gap-3">
        <div>
            <div class="text-muted small">Help content branch</div>
            <div class="d-flex align-items-center gap-2">
                <span class="badge <?php echo $branch === 'beta' ? 'bg-warning text-dark' : 'bg-success'; ?>">
                    <?php echo strtoupper($branch); ?>
                </span>
                <?php if (!empty($branchStatus['s_status'])) { ?>
                    <span class="badge bg-light text-dark border">Status: <?php echo htmlspecialchars($branchStatus['s_status']); ?></span>
                <?php } ?>
                <span class="badge bg-light text-dark border"><?php echo $helpExists ? 'Help file present' : 'Help file missing'; ?></span>
            </div>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <a href="<?php echo htmlspecialchars($detailUrl); ?>" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-arrow-left-circle me-1"></i>Route Detail
            </a>
            <a href="<?php echo htmlspecialchars($editUrl); ?>" class="btn btn-primary btn-sm">
                <i class="bi bi-pencil-square me-1"></i>Edit Help
            </a>
            <?php if ($branchCanManage) { ?>
                <?php if ($branch === 'beta') { ?>
                    <a href="<?php echo $radAdminUrl; ?>/route/help/<?php echo urlencode($routeUid); ?>?branch=live" class="btn btn-outline-secondary btn-sm">
                        <i class="bi bi-box-arrow-up-right me-1"></i>Open Live
                    </a>
                    <?php if ($branchCanMerge) { ?>
                        <a href="<?php echo $radAdminUrl; ?>/route/branchmerge/<?php echo urlencode($routeUid); ?>?return=help" class="btn btn-success btn-sm" onclick="return confirm('Merge beta into live?');">
                            <i class="bi bi-arrow-left-right me-1"></i>Merge to Live
                        </a>
                    <?php } ?>
                    <a href="<?php echo $radAdminUrl; ?>/route/branchdiscard/<?php echo urlencode($routeUid); ?>?return=help" class="btn btn-outline-danger btn-sm" onclick="return confirm('Discard beta branch?');">
                        <i class="bi bi-trash me-1"></i>Discard Beta
                    </a>
                <?php } elseif ($branchHasBeta) { ?>
                    <a href="<?php echo $radAdminUrl; ?>/route/help/<?php echo urlencode($routeUid); ?>?branch=beta" class="btn btn-warning btn-sm">
                        <i class="bi bi-box-arrow-up-right me-1"></i>Open Beta
                    </a>
                <?php } else { ?>
                    <a href="<?php echo $radAdminUrl; ?>/route/branchcreate/<?php echo urlencode($routeUid); ?>?return=help" class="btn btn-outline-primary btn-sm">
                        <i class="bi bi-branch me-1"></i>Create Beta Branch
                    </a>
                <?php } ?>
            <?php } ?>
        </div>
    </div>
</div>

<div class="card border-0 shadow-sm mb-4">
    <div class="card-body d-flex justify-content-between align-items-start flex-wrap gap-3">
        <div>
            <div class="text-muted small text-uppercase mb-1">Route Help</div>
            <h2 class="h4 mb-1"><?php echo htmlspecialchars($route['s_name'] ?? ''); ?></h2>
            <div class="text-muted small">
                Microservicelet: <?php echo htmlspecialchars($ms['s_name'] ?? ''); ?>
            </div>
        </div>
        <div class="text-end">
            <div class="small text-muted">Help file</div>
            <code><?php echo htmlspecialchars($this->runData['data']['help_path'] ?? ''); ?></code>
        </div>
    </div>
</div>

<?php if (!$helpExists && trim($helpContent) === '') { ?>
    <div class="card border-0 shadow-sm">
        <div class="card-body text-center py-5">
            <div class="display-6 mb-3 text-muted"><i class="bi bi-journal-text"></i></div>
            <h3 class="h5">No Help Content Yet</h3>
            <p class="text-muted mb-3">Create the route help markdown file and document what this route does, how it is accessed, and what it renders.</p>
            <a href="<?php echo htmlspecialchars($editUrl); ?>" class="btn btn-primary">
                <i class="bi bi-plus-circle me-1"></i>Create Help Content
            </a>
        </div>
    </div>
<?php } else { ?>
    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white d-flex justify-content-between align-items-center">
            <strong>Rendered Help</strong>
            <a href="<?php echo htmlspecialchars($editUrl); ?>" class="btn btn-outline-primary btn-sm">
                <i class="bi bi-pencil-square me-1"></i>Edit Help
            </a>
        </div>
        <div class="card-body route-help-rendered">
            <?php echo $helpHtml; ?>
        </div>
    </div>
<?php } ?>

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
.route-help-rendered p:last-child,
.route-help-rendered ul:last-child,
.route-help-rendered ol:last-child,
.route-help-rendered pre:last-child,
.route-help-rendered blockquote:last-child {
    margin-bottom: 0;
}
.route-help-rendered code {
    background: #f8f9fa;
    padding: 0.1rem 0.3rem;
    border-radius: 0.25rem;
}
</style>
