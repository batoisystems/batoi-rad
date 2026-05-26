<?php
$hooks = $this->runData['data']['test_hooks'] ?? [];
$hookScope = $this->runData['data']['test_hook_scope'] ?? null;
$hookRef = $this->runData['data']['test_hook_ref'] ?? null;
$canGenerate = $hookScope && $hookRef;
?>
<div class="card">
    <div class="card-body">
        <h6 class="card-title mb-2">Test Plans</h6>
        <?php if (empty($hooks)) { ?>
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                <div class="text-muted small mb-0">No test plans available for this scope.</div>
                <?php if ($canGenerate) { ?>
                    <form method="post" action="<?php echo $this->runData['route']['rad_admin_url']; ?>/testplan/generate" class="mb-0">
                        <input type="hidden" name="scope" value="<?php echo htmlspecialchars($hookScope); ?>">
                        <input type="hidden" name="ref_id" value="<?php echo (int)$hookRef; ?>">
                        <button type="submit" class="btn btn-sm btn-primary">
                            <i class="bi bi-stars me-1"></i>Create auto plan
                        </button>
                    </form>
                <?php } ?>
            </div>
        <?php } else { ?>
            <div class="list-group list-group-flush">
                <?php foreach ($hooks as $plan) { ?>
                    <div class="list-group-item">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <div class="fw-semibold"><?php echo htmlspecialchars($plan['s_name']); ?></div>
                                <div class="text-muted small"><?php echo htmlspecialchars($plan['s_description'] ?? ''); ?></div>
                            </div>
                            <div class="d-flex gap-2">
                                <a href="<?php echo $this->runData['route']['rad_admin_url']; ?>/testplan/viewone/<?php echo (int)$plan['id']; ?>" class="btn btn-sm btn-outline-primary">Open</a>
                                <a href="<?php echo $this->runData['route']['rad_admin_url']; ?>/testplan/createtestrun/<?php echo (int)$plan['id']; ?>" class="btn btn-sm btn-primary">New Run</a>
                            </div>
                        </div>
                    </div>
                <?php } ?>
            </div>
            <?php if ($canGenerate) { ?>
                <div class="mt-3">
                    <form method="post" action="<?php echo $this->runData['route']['rad_admin_url']; ?>/testplan/generate" class="d-inline">
                        <input type="hidden" name="scope" value="<?php echo htmlspecialchars($hookScope); ?>">
                        <input type="hidden" name="ref_id" value="<?php echo (int)$hookRef; ?>">
                        <button type="submit" class="btn btn-sm btn-outline-secondary">
                            <i class="bi bi-stars me-1"></i>Auto-build from this scope
                        </button>
                    </form>
                    <a href="<?php echo $this->runData['route']['rad_admin_url']; ?>/testplan/add?scope=<?php echo urlencode($hookScope); ?>&ref_id=<?php echo (int)$hookRef; ?>" class="btn btn-sm btn-outline-primary ms-2">
                        <i class="bi bi-plus-circle me-1"></i>New plan
                    </a>
                </div>
            <?php } ?>
        <?php } ?>
    </div>
</div>
