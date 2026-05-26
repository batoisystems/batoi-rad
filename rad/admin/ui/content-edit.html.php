<?php
$branch = $this->runData['data']['branch'] ?? 'live';
$branchStatus = $this->runData['data']['branch_status'] ?? [];
$branchHasBeta = !empty($this->runData['data']['branch_has_beta']);
$branchMissing = !empty($this->runData['data']['branch_missing']);
$branchCanManage = !empty($this->runData['data']['branch_can_manage']);
$branchCanMerge = !empty($this->runData['data']['branch_can_merge']);
$branchHistory = $this->runData['data']['branch_history'] ?? [];
$branchQuery = $branch === 'beta' ? '?branch=beta' : '';
$formSubmissionUrl = $this->runData['route']['url'] . $branchQuery;
$content = $this->runData['data']['content'][0];
$msOptions = $this->runData['data']['content_ms'] ?? [];
?>

<?php if ($branchMissing && $branchCanManage) { ?>
    <div class="alert alert-warning d-flex justify-content-between align-items-center flex-wrap gap-2">
        <div>
            <strong>Beta branch not initialized.</strong>
            <div class="small text-muted">Create a beta branch to edit without affecting live content.</div>
        </div>
        <a href="<?php echo $this->runData['route']['rad_admin_url']; ?>/content/branchcreate/<?php echo urlencode($content['uid'] ?? ''); ?>" class="btn btn-outline-primary btn-sm">
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
            </div>
        </div>
        <?php if ($branchCanManage) { ?>
            <div class="d-flex flex-wrap gap-2">
                <?php if ($branch === 'beta') { ?>
                    <a href="<?php echo $this->runData['route']['rad_admin_url']; ?>/content/edit/<?php echo urlencode($content['uid'] ?? ''); ?>" class="btn btn-outline-secondary btn-sm">
                        Open Live
                    </a>
                    <?php if ($branchCanMerge) { ?>
                        <a href="<?php echo $this->runData['route']['rad_admin_url']; ?>/content/branchmerge/<?php echo urlencode($content['uid'] ?? ''); ?>" class="btn btn-success btn-sm" onclick="return confirm('Merge beta into live?');">
                            Merge to Live
                        </a>
                    <?php } ?>
                    <a href="<?php echo $this->runData['route']['rad_admin_url']; ?>/content/branchdiscard/<?php echo urlencode($content['uid'] ?? ''); ?>" class="btn btn-outline-danger btn-sm" onclick="return confirm('Discard beta branch?');">
                        Discard Beta
                    </a>
                <?php } else { ?>
                    <?php if ($branchHasBeta) { ?>
                        <a href="<?php echo $this->runData['route']['rad_admin_url']; ?>/content/edit/<?php echo urlencode($content['uid'] ?? ''); ?>?branch=beta" class="btn btn-warning btn-sm">
                            Open Beta
                        </a>
                    <?php } else { ?>
                        <a href="<?php echo $this->runData['route']['rad_admin_url']; ?>/content/branchcreate/<?php echo urlencode($content['uid'] ?? ''); ?>" class="btn btn-outline-primary btn-sm">
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

<form id="editForm" name="editForm" action="<?php echo $formSubmissionUrl; ?>" method="post" class="needs-validation" novalidate>
    <input type="hidden" name="id" value="<?php echo (int)$content['id']; ?>">
    <div class="card mb-3">
        <div class="card-body">
            <div class="row g-3">
                <div class="col-lg-8">
                    <label for="s_title" class="form-label">Title <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" name="s_title" id="s_title" value="<?php echo htmlspecialchars($content['s_title']); ?>" required>
                    <div class="invalid-feedback">Please provide a title.</div>
                </div>
                <div class="col-lg-4">
                    <label for="s_type" class="form-label">Type <span class="text-danger">*</span></label>
                    <select class="form-select" name="s_type" id="s_type" required>
                        <option value="I" <?php if ($content['s_type'] === 'I') echo 'selected'; ?>>Static Block</option>
                        <option value="J" <?php if ($content['s_type'] === 'J') echo 'selected'; ?>>Journal Block</option>
                        <option value="C" <?php if ($content['s_type'] === 'C') echo 'selected'; ?>>Common Block</option>
                    </select>
                    <div class="invalid-feedback">Choose a content type.</div>
                </div>
            </div>

            <div class="mt-3">
                <label for="s_content" class="form-label">Content Block Body <span class="text-danger">*</span></label>
                <textarea class="form-control summernote" name="s_content" id="s_content" rows="8" required><?php echo htmlspecialchars($content['s_content']); ?></textarea>
                <div class="invalid-feedback">Content block body cannot be empty.</div>
            </div>
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-header">Placement & Metadata</div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-6">
                    <label for="s_ms_id" class="form-label">Microservicelet <span class="text-danger">*</span></label>
                    <select class="form-select" name="s_ms_id" id="s_ms_id" required>
                        <?php foreach ($msOptions as $ms): ?>
                            <option value="<?php echo (int)$ms['id']; ?>" <?php if ($content['s_ms_id'] == $ms['id']) echo 'selected'; ?>>
                                <?php echo htmlspecialchars($ms['s_name']); ?> · ID: <?php echo (int)$ms['id']; ?> · UID: <?php echo htmlspecialchars($ms['uid'] ?? ''); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <div class="invalid-feedback">Select a microservicelet.</div>
                </div>
                <div class="col-md-6">
                    <label for="s_slug" class="form-label">Slug <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" name="s_slug" id="s_slug" value="<?php echo htmlspecialchars($content['s_slug']); ?>" required>
                    <div class="invalid-feedback">Slug is required.</div>
                </div>
                <div class="col-md-6">
                    <label for="s_meta_title" class="form-label">Meta Title <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" name="s_meta_title" id="s_meta_title" value="<?php echo htmlspecialchars($content['s_meta_title']); ?>" required>
                    <div class="invalid-feedback">Meta title is required.</div>
                </div>
                <div class="col-md-6">
                    <label for="s_meta_description" class="form-label">Meta Description</label>
                    <textarea class="form-control" name="s_meta_description" id="s_meta_description" rows="2"><?php echo htmlspecialchars($content['s_meta_description']); ?></textarea>
                </div>
                <div class="col-12">
                    <label for="s_definition" class="form-label">Definition (JSON)</label>
                    <textarea class="form-control" name="s_definition" id="s_definition" rows="3"><?php echo htmlspecialchars($content['s_definition']); ?></textarea>
                </div>
            </div>
        </div>
    </div>

    <div class="text-end">
        <button id="submit-button" type="submit" class="btn btn-primary">
            <i class="bi bi-check-circle"></i> Update Block
        </button>
    </div>
</form>
