<?php
$user = $this->runData['data']['user'];
$roles = $this->runData['data']['roles_saas'] ?? [];
$nonSaaSRoles = $this->runData['data']['roles_non_saas'] ?? [];
$spaces = $this->runData['data']['spaces'] ?? [];
$microservices = $this->runData['data']['microservices'] ?? [];
$associatedSpaceIds = [];
$hasNonSaas = !empty($nonSaaSRoles);
$hasSaas = !empty($roles);
?>

<div class="container">
    <h1><?php echo $this->runData['route']['h1']; ?></h1>
    <p class="text-muted">Assign a primary platform role and optional SaaS workspace/MS roles (one SaaS role per workspace).</p>

    <?php if (!$hasNonSaas): ?>
        <div class="alert alert-warning">
            <strong>No Non-SaaS roles found.</strong> Create a platform-scope role first to keep users compliant with the “one non-SaaS per user” rule.
        </div>
    <?php endif; ?>
    <?php if (!$hasSaas): ?>
        <div class="alert alert-info">
            <strong>No SaaS roles available.</strong> Add workspace scoped roles to enable workspace assignments.
        </div>
    <?php endif; ?>

    <form action="<?php echo $this->runData['route']['url']; ?>" method="post">
        <div class="mb-3">
            <label class="form-label">Role Type</label>
            <div class="form-check">
                <input class="form-check-input" type="radio" name="role_kind" id="roleKindNonSaas" value="non_saas" checked>
                <label class="form-check-label" for="roleKindNonSaas">Non-SaaS (platform)</label>
            </div>
            <div class="form-check">
                <input class="form-check-input" type="radio" name="role_kind" id="roleKindSaas" value="saas">
                <label class="form-check-label" for="roleKindSaas">SaaS (workspace/ms)</label>
            </div>
        </div>

        <div class="mb-3" data-role-kind="non_saas">
            <label for="role_id_global" class="form-label">Global Role (Non-SaaS)</label>
            <select class="form-control" id="role_id_global" name="role_id" required>
                <?php if (empty($nonSaaSRoles)): ?>
                    <option value="">No non-SaaS roles available</option>
                <?php else: ?>
                    <?php foreach ($nonSaaSRoles as $role): ?>
                        <option value="<?php echo $role['id']; ?>"><?php echo $role['s_role_name']; ?> (Non-SaaS)</option>
                    <?php endforeach; ?>
                <?php endif; ?>
            </select>
            <input type="hidden" name="space_id" value="0">
        </div>

        <div class="mb-3" data-role-kind="saas" style="display:none;">
            <label for="space_id" class="form-label">Workspace</label>
            <select class="form-control" id="space_id" name="space_id">
                <?php
                $availableSpaces = array_filter($spaces, function ($space) use ($associatedSpaceIds) {
                    return !in_array($space['id'], $associatedSpaceIds, true);
                });
                ?>
                <?php if (empty($availableSpaces)): ?>
                    <option value="">All workspaces already have roles</option>
                <?php else: ?>
                    <?php foreach ($availableSpaces as $space): ?>
                        <option value="<?php echo $space['id']; ?>"><?php echo $space['s_name']; ?></option>
                    <?php endforeach; ?>
                <?php endif; ?>
            </select>
        </div>
        <div class="mb-3" data-role-kind="saas" style="display:none;">
            <label for="role_id_saas" class="form-label">Workspace Role (SaaS)</label>
            <select class="form-control" id="role_id_saas" name="role_id">
                <?php if (empty($roles)): ?>
                    <option value="">No SaaS roles available</option>
                <?php else: ?>
                    <?php foreach ($roles as $role): ?>
                        <option value="<?php echo $role['id']; ?>"><?php echo $role['s_role_name']; ?> (<?php echo htmlspecialchars($role['s_scope']); ?>)</option>
                    <?php endforeach; ?>
                <?php endif; ?>
            </select>
        </div>
        <div class="mb-3" data-role-kind="saas" style="display:none;">
            <label for="ms_id" class="form-label">Microservice (required for ms-scoped roles)</label>
            <select class="form-control" id="ms_id" name="ms_id">
                <option value="">(Optional unless role scope = ms)</option>
                <?php foreach ($microservices as $ms): ?>
                    <option value="<?php echo $ms['id']; ?>"><?php echo $ms['s_name']; ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <button type="submit" class="btn btn-primary">Add Role</button>
        <a href="<?php echo isset($this->runData['route']['backlink']) ? $this->runData['route']['backlink'] : $this->runData['route']['rad_admin_url'] . '/user/view'; ?>" class="btn btn-outline-secondary">Cancel</a>
    </form>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const radios = document.querySelectorAll('input[name="role_kind"]');
    const toggleVisibility = () => {
        const kind = document.querySelector('input[name="role_kind"]:checked')?.value || 'non_saas';
        document.querySelectorAll('[data-role-kind]').forEach(el => {
            el.style.display = el.getAttribute('data-role-kind') === kind ? 'block' : 'none';
        });
    };
    radios.forEach(r => r.addEventListener('change', toggleVisibility));
    toggleVisibility();
});
</script>
