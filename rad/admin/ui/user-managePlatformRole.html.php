<?php
$user = $this->runData['data']['user'] ?? [];
$platformRoles = $this->runData['data']['platform_roles'] ?? [];
$roleOptions = $this->runData['data']['platform_role_options'] ?? [];
$hasConflict = !empty($this->runData['data']['platform_role_conflict']);
$history = $this->runData['data']['platform_role_history'] ?? [];
$radAdminUrl = $this->runData['route']['rad_admin_url'];
$uid = htmlspecialchars($user['uid'] ?? '');
?>

<div class="card mb-3 shadow-sm">
    <div class="card-body">
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-start gap-3">
            <div>
                <h2 class="h5 mb-1">Non-SaaS Role</h2>
                <div class="text-muted small">Platform-scope role for core application access.</div>
            </div>
            <a class="btn btn-outline-secondary" href="<?php echo $radAdminUrl; ?>/user/viewone/<?php echo $uid; ?>">
                <i class="bi bi-arrow-left"></i> Back to User
            </a>
        </div>
    </div>
</div>

<?php if ($hasConflict): ?>
    <div class="alert alert-warning d-flex align-items-start gap-2">
        <i class="bi bi-exclamation-triangle-fill mt-1"></i>
        <div>
            <div class="fw-semibold">Multiple platform roles detected</div>
            <div class="small">Select one role to keep. Others will be removed.</div>
        </div>
    </div>
<?php endif; ?>

<div class="card mb-3 shadow-sm">
    <div class="card-header bg-white">
        <h3 class="h6 mb-0">Role Audit</h3>
    </div>
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <div class="text-muted small">Created</div>
                <div><?php echo htmlspecialchars($history['created']['timestamp'] ?? '—'); ?></div>
            </div>
            <div class="text-end">
                <div class="text-muted small">Actor</div>
                <div><?php echo htmlspecialchars($history['created']['actor'] ?? 'System'); ?></div>
            </div>
        </div>
        <hr>
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <div class="text-muted small">Last Updated</div>
                <div><?php echo htmlspecialchars($history['updated']['timestamp'] ?? '—'); ?></div>
            </div>
            <div class="text-end">
                <div class="text-muted small">Actor</div>
                <div><?php echo htmlspecialchars($history['updated']['actor'] ?? 'System'); ?></div>
            </div>
        </div>
    </div>
</div>

<div class="card shadow-sm">
    <div class="card-header bg-white">
        <h3 class="h6 mb-0"><?php echo empty($platformRoles) ? 'Add Non-SaaS Role' : 'Current Non-SaaS Role'; ?></h3>
    </div>
    <div class="card-body">
        <?php if (empty($platformRoles)): ?>
            <p class="text-muted">No platform role assigned yet.</p>
        <?php else: ?>
            <div class="d-flex flex-wrap gap-2 mb-3">
                <?php foreach ($platformRoles as $role): ?>
                    <span class="badge bg-light text-dark">
                        <?php echo htmlspecialchars($role['name'] ?? 'Role'); ?> (ID <?php echo (int)($role['id'] ?? 0); ?>)
                    </span>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <?php if ($hasConflict && !empty($platformRoles)): ?>
            <div class="list-group mb-3">
                <?php foreach ($platformRoles as $role): ?>
                    <div class="list-group-item d-flex justify-content-between align-items-center">
                        <div>
                            <div class="fw-semibold"><?php echo htmlspecialchars($role['name'] ?? 'Role'); ?></div>
                            <div class="text-muted small">ID <?php echo (int)($role['id'] ?? 0); ?></div>
                        </div>
                        <form method="post" action="<?php echo $radAdminUrl; ?>/user/managePlatformRole/<?php echo $uid; ?>">
                            <input type="hidden" name="action" value="keep_role">
                            <input type="hidden" name="role_id" value="<?php echo (int)($role['id'] ?? 0); ?>">
                            <button type="submit" class="btn btn-sm btn-outline-primary">Keep This Role</button>
                        </form>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <form method="post" action="<?php echo $radAdminUrl; ?>/user/managePlatformRole/<?php echo $uid; ?>" class="row g-3 align-items-end">
            <input type="hidden" name="action" value="<?php echo $hasConflict ? 'keep_role' : 'set_role'; ?>">
            <div class="col-md-8">
                <label class="form-label">Select Platform Role</label>
                <select class="form-select" name="role_id" required>
                    <option value="">Choose a role</option>
                    <?php foreach ($roleOptions as $role): ?>
                        <option value="<?php echo (int)$role['id']; ?>">
                            <?php echo htmlspecialchars($role['s_role_name'] ?? 'Role'); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4">
                <button type="submit" class="btn btn-primary w-100">
                    <?php echo $hasConflict ? 'Keep This Role' : (empty($platformRoles) ? 'Add Role' : 'Change Role'); ?>
                </button>
            </div>
        </form>
    </div>
</div>
