<?php
$user = $this->runData['data']['user'] ?? [];
$assignments = $this->runData['data']['workspace_assignments'] ?? [];
$availableSpaces = $this->runData['data']['available_spaces'] ?? [];
$roles = $this->runData['data']['roles_saas'] ?? [];
$microservices = $this->runData['data']['microservices'] ?? [];
$hasPlatformRole = !empty($this->runData['data']['has_platform_role']);
$history = $this->runData['data']['workspace_role_history'] ?? [];
$radAdminUrl = $this->runData['route']['rad_admin_url'];
$uid = htmlspecialchars($user['uid'] ?? '');
?>

<div class="card mb-3 shadow-sm">
    <div class="card-body">
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-start gap-3">
            <div>
                <h2 class="h5 mb-1">Workspace Roles</h2>
                <div class="text-muted small">Assign one SaaS role per workspace (workspace or microservice scope).</div>
            </div>
            <a class="btn btn-outline-secondary" href="<?php echo $radAdminUrl; ?>/user/viewone/<?php echo $uid; ?>">
                <i class="bi bi-arrow-left"></i> Back to User
            </a>
        </div>
    </div>
</div>

<?php if (!$hasPlatformRole): ?>
    <div class="alert alert-warning d-flex align-items-start gap-2">
        <i class="bi bi-shield-exclamation mt-1"></i>
        <div>
            <div class="fw-semibold">Non-SaaS role required</div>
            <div class="small">Assign a platform role before adding workspace roles.</div>
            <a class="btn btn-sm btn-outline-primary mt-2" href="<?php echo $radAdminUrl; ?>/user/managePlatformRole/<?php echo $uid; ?>">Set Non-SaaS Role</a>
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
                <div class="text-muted small"><?php echo htmlspecialchars($history['action'] ?? 'Last updated'); ?></div>
                <div><?php echo htmlspecialchars($history['timestamp'] ?? '—'); ?></div>
            </div>
            <div class="text-end">
                <div class="text-muted small">Actor</div>
                <div><?php echo htmlspecialchars($history['actor'] ?? 'System'); ?></div>
            </div>
        </div>
    </div>
</div>

<div class="card mb-3 shadow-sm">
    <div class="card-header bg-white d-flex justify-content-between align-items-center">
        <h3 class="h6 mb-0">Current Workspace Access</h3>
    </div>
    <div class="card-body">
        <?php if (empty($assignments)): ?>
            <div class="text-muted">No workspace roles assigned yet.</div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table align-middle">
                    <thead>
                        <tr>
                            <th>Workspace</th>
                            <th>Role</th>
                            <th>Scope</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($assignments as $space): ?>
                            <?php foreach (($space['roles'] ?? []) as $role): ?>
                                <tr>
                                    <td>
                                        <div class="fw-semibold"><?php echo htmlspecialchars($space['name'] ?? 'Workspace'); ?></div>
                                        <div class="text-muted small">ID: <?php echo (int)($space['id'] ?? 0); ?></div>
                                    </td>
                                    <td>
                                        <?php echo htmlspecialchars($role['name'] ?? 'Role'); ?>
                                        <?php if (!empty($role['ms_name'])): ?>
                                            <div class="text-muted small">MS: <?php echo htmlspecialchars($role['ms_name']); ?></div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="badge bg-light text-dark">
                                            <?php echo htmlspecialchars($role['scope_level'] ?? 'workspace'); ?>
                                        </span>
                                    </td>
                                    <td class="text-end">
                                        <form method="post" action="<?php echo $radAdminUrl; ?>/user/manageWorkspaceRole/<?php echo $uid; ?>">
                                            <input type="hidden" name="action" value="remove">
                                            <input type="hidden" name="space_id" value="<?php echo (int)($space['id'] ?? 0); ?>">
                                            <input type="hidden" name="role_id" value="<?php echo (int)($role['id'] ?? 0); ?>">
                                            <input type="hidden" name="scope_level" value="<?php echo htmlspecialchars($role['scope_level'] ?? 'workspace'); ?>">
                                            <?php if (!empty($role['ms_id'])): ?>
                                                <input type="hidden" name="ms_id" value="<?php echo (int)$role['ms_id']; ?>">
                                            <?php endif; ?>
                                            <button type="submit" class="btn btn-sm btn-outline-danger">Remove</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<div class="row g-3">
    <div class="col-lg-6">
        <div class="card shadow-sm h-100">
            <div class="card-header bg-white">
                <h3 class="h6 mb-0">Change Role for Existing Workspace</h3>
            </div>
            <div class="card-body">
                <form method="post" action="<?php echo $radAdminUrl; ?>/user/manageWorkspaceRole/<?php echo $uid; ?>" class="vstack gap-3">
                    <input type="hidden" name="action" value="change">
                    <div>
                        <label class="form-label">Workspace</label>
                        <select class="form-select" name="space_id" required>
                            <option value="">Choose workspace</option>
                            <?php foreach ($assignments as $space): ?>
                                <option value="<?php echo (int)($space['id'] ?? 0); ?>"><?php echo htmlspecialchars($space['name'] ?? 'Workspace'); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="form-label">Role</label>
                        <select class="form-select role-select" name="role_id" required>
                            <option value="">Choose role</option>
                            <?php foreach ($roles as $role): ?>
                                <option value="<?php echo (int)$role['id']; ?>" data-scope="<?php echo htmlspecialchars($role['s_scope']); ?>">
                                    <?php echo htmlspecialchars($role['s_role_name'] ?? 'Role'); ?> (<?php echo htmlspecialchars($role['s_scope']); ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="ms-select" style="display:none;">
                        <label class="form-label">Microservice (required for ms scope)</label>
                        <select class="form-select" name="ms_id">
                            <option value="">Choose microservice</option>
                            <?php foreach ($microservices as $ms): ?>
                                <option value="<?php echo (int)$ms['id']; ?>"><?php echo htmlspecialchars($ms['s_name'] ?? 'Microservice'); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <button type="submit" class="btn btn-primary">Change Role</button>
                </form>
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card shadow-sm h-100">
            <div class="card-header bg-white">
                <h3 class="h6 mb-0">Add Workspace Association</h3>
            </div>
            <div class="card-body">
                <form method="post" action="<?php echo $radAdminUrl; ?>/user/manageWorkspaceRole/<?php echo $uid; ?>" class="vstack gap-3">
                    <input type="hidden" name="action" value="add">
                    <div>
                        <label class="form-label">Workspace</label>
                        <select class="form-select" name="space_id" required>
                            <option value="">Choose workspace</option>
                            <?php foreach ($availableSpaces as $space): ?>
                                <?php
                                $spaceId = (int)($space['id'] ?? 0);
                                $isAssigned = in_array($spaceId, $assignedSpaceIds ?? [], true);
                                ?>
                                <?php if (!$isAssigned): ?>
                                    <option value="<?php echo $spaceId; ?>" data-assigned="0">
                                        <?php echo htmlspecialchars($space['s_name'] ?? 'Workspace'); ?>
                                    </option>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </select>
                        <div class="text-muted small mt-2">Workspace-scoped roles are limited to one per workspace. MS roles can be added per microservice.</div>
                    </div>
                    <div>
                        <label class="form-label">Role</label>
                        <select class="form-select role-select" name="role_id" required>
                            <option value="">Choose role</option>
                            <?php foreach ($roles as $role): ?>
                                <option value="<?php echo (int)$role['id']; ?>" data-scope="<?php echo htmlspecialchars($role['s_scope']); ?>">
                                    <?php echo htmlspecialchars($role['s_role_name'] ?? 'Role'); ?> (<?php echo htmlspecialchars($role['s_scope']); ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="ms-select" style="display:none;">
                        <label class="form-label">Microservice (required for ms scope)</label>
                        <select class="form-select" name="ms_id">
                            <option value="">Choose microservice</option>
                            <?php foreach ($microservices as $ms): ?>
                                <option value="<?php echo (int)$ms['id']; ?>"><?php echo htmlspecialchars($ms['s_name'] ?? 'Microservice'); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <button type="submit" class="btn btn-outline-primary">Add Workspace Role</button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const updateScopeFields = (wrapper) => {
        const select = wrapper.querySelector('.role-select');
        const msWrap = wrapper.querySelector('.ms-select');
        if (!select || !msWrap) {
            return;
        }
        const scope = select.options[select.selectedIndex]?.dataset?.scope || '';
        msWrap.style.display = scope === 'ms' ? 'block' : 'none';
    };

    document.querySelectorAll('form').forEach(form => {
        if (!form.querySelector('.role-select')) {
            return;
        }
        updateScopeFields(form);
        form.querySelector('.role-select').addEventListener('change', () => updateScopeFields(form));
    });
});
</script>
