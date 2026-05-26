<?php
// print '<pre>';print_r($this->runData['data']);print '</pre>';
// form submission url is the self url
$formSubmissionUrl = $this->runData['route']['url'];
// print '<pre>';print_r($formSubmissionUrl);print '</pre>';
?>
<form action="<?php print $formSubmissionUrl;?>" method="post" class="needs-validation" novalidate>
    <!-- Tenant Name -->
    <div class="form-group">
        <label for="s_name">Tenant Name <span class="text-danger">*</span></label>
        <input type="text" class="form-control" name="s_name" id="s_name" value="<?php echo isset($this->runData['data']['tenant']['s_name']) ? $this->runData['data']['tenant']['s_name'] : ''; ?>" aria-required="true" required autocomplete="nope">
        <div class="invalid-feedback">
            The Tenant Name can have any characters including blank spaces.
        </div>
        <small id="nameHelp" class="form-text text-muted">The Tenant name can have any characters including blank spaces.</small>
    </div>

    <!-- Tenant Description -->
    <div class="form-group">
        <label for="s_description">Description</label>
        <textarea class="form-control" name="s_description" id="s_description" rows="3"><?php echo isset($this->runData['data']['tenant']['s_description']) ? $this->runData['data']['tenant']['s_description'] : ''; ?></textarea>
        <small id="descriptionHelp" class="form-text text-muted">Provide a brief description of the tenant.</small>
    </div>

    <!-- Tenant Users in Roles -->
    <div class="form-group">
        <label for="s_users">Assign Users to Roles <span class="text-danger">*</span></label>
        <?php
        // Fetch the list of SaaS roles and users
        $roles = $this->runData['db']->query("SELECT * FROM s_role WHERE s_scope IN ('workspace')");
        $users = $this->runData['db']->select('s_user', [], true);

        // Initialize s_users data
        $s_users_data = isset($this->runData['data']['tenant']['s_users']) ? $this->runData['data']['tenant']['s_users'] : '';
        $assigned_users = [];

        if ($s_users_data) {
            // Parse the s_users data to get the assigned users for each role
            $role_user_pairs = explode(';', $s_users_data);
            foreach ($role_user_pairs as $pair) {
                list($role_id, $user_ids) = explode(':', $pair);
                $assigned_users[$role_id] = explode(',', $user_ids);
            }
        }
        ?>
        <table class="table table-bordered">
            <thead>
                <tr>
                    <th rowspan="2">User</th>
                    <?php $numberOfSaaSRoles = count($roles); ?>
                    <th colspan="<?php echo $numberOfSaaSRoles; ?>">Roles</th>
                </tr>
                <tr>
                    <?php foreach ($roles as $role): ?>
                        <th><?php echo $role['s_role_name']; ?></th>
                    <?php endforeach; ?>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($users as $user): ?>
                    <tr>
                        <td><?php echo $user['s_fullname']; ?><br/><small>(<?php echo $user['s_email']; ?> / <?php echo $user['s_mobile']; ?>)</small></td>
                        <?php foreach ($roles as $role): ?>
                            <td>
                                <input type="checkbox" name="s_users[<?php echo $role['id']; ?>][]" value="<?php echo $user['id']; ?>"
                                    <?php echo (isset($assigned_users[$role['id']]) && in_array($user['id'], $assigned_users[$role['id']])) ? 'checked' : ''; ?>>
                            </td>
                        <?php endforeach; ?>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <div class="invalid-feedback">
            Please assign users to roles.
        </div>
        <small id="usersHelp" class="form-text text-muted">Select the users for each role in the tenant.</small>
    </div>

    <!-- Tenant Definition -->
    <div class="form-group">
        <label for="s_definition">Definition <span class="text-danger">*</span></label>
        <textarea class="form-control" name="s_definition" id="s_definition" rows="3" required><?php echo isset($this->runData['data']['tenant']['s_definition']) ? $this->runData['data']['tenant']['s_definition'] : '{}'; ?></textarea>
        <div class="invalid-feedback">
            Please provide the definition in JSON format.
        </div>
        <small id="definitionHelp" class="form-text text-muted">Provide the definition in JSON format.</small>
    </div>

    <!-- Submit button with an icon -->
    <button type="submit" class="btn btn-primary">
        <i class="bi bi-check-circle"></i> Save
    </button>
    <a href="<?php print $this->runData['route']['rad_admin_url'] . '/tenant/view';?>" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left-circle"></i> Cancel and Go Back</a>
</form>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Set default value for the definition field if empty
    var s_definition = document.getElementById('s_definition');
    if (!s_definition.value) {
        s_definition.value = '{}';
    }
});
</script>
