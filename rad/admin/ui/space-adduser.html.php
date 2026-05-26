<?php
$space = $this->runData['data']['space'] ?? [];
$roles = $this->runData['data']['roles'] ?? [];
$assignedIds = $this->runData['data']['assigned_user_ids'] ?? [];
$availableRoles = array_values(array_filter($roles, function ($role) {
    return ($role['s_scope'] ?? 'platform') === 'workspace';
}));
$radAdminUrl = $this->runData['route']['rad_admin_url'];
?>

<div class="card border-0 shadow-sm mb-4 bg-body-tertiary">
    <div class="card-body p-4">
        <div class="row g-3 align-items-start">
            <div class="col-lg-8">
                <div class="text-uppercase text-muted small fw-semibold mb-2">Membership Assignment</div>
                <h2 class="h3 mb-2">Add a member to <?php echo htmlspecialchars((string)($space['s_name'] ?? 'this workspace')); ?></h2>
                <p class="text-muted mb-0">Search for an active user, choose a workspace role, and add the membership without leaving the workspace context.</p>
            </div>
            <div class="col-lg-4">
                <div class="card border-0 shadow-sm-sm h-100">
                    <div class="card-body">
                        <div class="small text-muted">Workspace UID</div>
                        <div class="fw-semibold"><?php echo htmlspecialchars((string)($space['uid'] ?? '')); ?></div>
                        <div class="small text-muted mt-3">Already assigned users</div>
                        <div class="fw-semibold"><?php echo number_format(count($assignedIds)); ?></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row g-4">
    <div class="col-lg-8">
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <form action="<?php echo htmlspecialchars($radAdminUrl . '/space/adduser/' . ($space['uid'] ?? '')); ?>" method="post">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($this->runData['request']->csrf_token ?? '', ENT_QUOTES); ?>">
                    <div class="mb-3">
                        <label for="space_user_search" class="form-label">Search User</label>
                        <input type="text" id="space_user_search" class="form-control" placeholder="Type name, username, or email">
                        <div class="form-text">Only active users who are not already assigned to this workspace will appear.</div>
                    </div>

                    <div class="mb-3">
                        <label for="s_entity_id" class="form-label">Select User</label>
                        <select id="s_entity_id" name="s_entity_id" class="form-select" required>
                            <option value="">Start typing to load matching users</option>
                        </select>
                    </div>

                    <div class="mb-4">
                        <label for="s_role_id" class="form-label">Workspace Role</label>
                        <select id="s_role_id" name="s_role_id" class="form-select" required>
                            <option value="">Select a workspace role</option>
                            <?php if (empty($availableRoles)) { ?>
                                <option disabled>No workspace roles available</option>
                            <?php } else { ?>
                                <?php foreach ($availableRoles as $role) { ?>
                                    <option value="<?php echo (int)$role['id']; ?>"><?php echo htmlspecialchars((string)$role['s_role_name']); ?></option>
                                <?php } ?>
                            <?php } ?>
                        </select>
                    </div>

                    <div class="d-flex flex-wrap gap-2">
                        <button type="submit" class="btn btn-primary" <?php echo empty($availableRoles) ? 'disabled' : ''; ?>>
                            <i class="bi bi-person-plus me-1"></i>Add Member
                        </button>
                        <a href="<?php echo htmlspecialchars($radAdminUrl . '/space/viewone/' . ($space['uid'] ?? '')); ?>" class="btn btn-outline-secondary">
                            <i class="bi bi-arrow-left-circle me-1"></i>Back to Workspace
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <h3 class="h5 mb-3">Guidance</h3>
                <ul class="small text-muted mb-0 ps-3">
                    <li>Use workspace roles only. Platform roles are intentionally excluded.</li>
                    <li>If no users appear, the workspace may already contain all active users.</li>
                    <li>If no roles appear, create a workspace-scoped role first.</li>
                </ul>
            </div>
        </div>
    </div>
</div>

<script>
(function() {
    const userSelect = document.getElementById('s_entity_id');
    const searchInput = document.getElementById('space_user_search');
    if (!userSelect || !searchInput) {
        return;
    }

    const fetchUsers = async (query) => {
        const url = new URL('<?php echo $radAdminUrl; ?>/space/searchusers/<?php echo htmlspecialchars((string)($space['uid'] ?? ''), ENT_QUOTES); ?>', window.location.origin);
        if (query) {
            url.searchParams.set('q', query);
        }
        try {
            const resp = await fetch(url.toString(), { credentials: 'same-origin' });
            if (!resp.ok) {
                return [];
            }
            const json = await resp.json();
            return json.data || [];
        } catch (e) {
            return [];
        }
    };

    const renderOptions = (users) => {
        userSelect.innerHTML = '';
        const placeholder = document.createElement('option');
        placeholder.value = '';
        placeholder.textContent = users.length ? 'Select a user' : 'No matching users found';
        userSelect.appendChild(placeholder);
        users.forEach((user) => {
            const option = document.createElement('option');
            option.value = user.id;
            option.textContent = `${user.s_name} (${user.s_identity})${user.email ? ' · ' + user.email : ''}`;
            userSelect.appendChild(option);
        });
    };

    let debounceTimer = null;
    const runSearch = async () => {
        const users = await fetchUsers(searchInput.value.trim());
        renderOptions(users);
    };

    searchInput.addEventListener('input', () => {
        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(runSearch, 250);
    });

    runSearch();
})();
</script>
