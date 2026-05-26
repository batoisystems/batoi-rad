<?php
$roles = $this->runData['data']['roles_non_saas'] ?? [];
?>
<form action="<?php print $this->runData['route']['url'];?>" method="post" onsubmit="return validateMFA();">
    
    <!-- Access Mode -->
    <input type="hidden" name="s_login_mode[]" value="SE">
    <div class="mb-3" id="accessModeDiv">
        <label for="accessMode" class="form-label">Access Mode - Choose a Single-Sign-On Provider if you want to enable SSO.</label>
        <div class="row">
            <!-- Access Mode: Self -->
            <div class="col-sm-6 col-md-3">
                <div class="card">
                    <div class="card-body">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="s_login_mode[]" id="selfMode" value="SE" checked disabled>
                            <label class="form-check-label" for="selfMode">
                                Self
                            </label>
                        </div>
                    </div>
                </div>
            </div>
            <!-- Access Mode: Batoi -->
            <div class="col-sm-6 col-md-3">
                <div class="card">
                    <div class="card-body">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="s_login_mode[]" id="batoiMode" value="BA">
                            <label class="form-check-label" for="batoiMode">
                                Batoi
                            </label>
                        </div>
                    </div>
                </div>
            </div>
            <!-- Access Mode: Google -->
            <div class="col-sm-6 col-md-3">
                <div class="card">
                    <div class="card-body">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="s_login_mode[]" id="googleMode" value="GL">
                            <label class="form-check-label" for="googleMode">
                                Google
                            </label>
                        </div>
                    </div>
                </div>
            </div>
            <!-- Access Mode: Twitter -->
            <div class="col-sm-6 col-md-3">
                <div class="card">
                    <div class="card-body">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="s_login_mode[]" id="twitterMode" value="TW">
                            <label class="form-check-label" for="twitterMode">
                                Twitter
                            </label>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>



    <!-- Full Name -->
    <?php
    // if there is post data, then display in the form
    if (isset($this->runData['request']->post['s_fullname'])) {
        $s_fullname = $this->runData['request']->post['s_fullname'];
    } else {
        $s_fullname = '';
    }
    ?>
    <div class="mb-3">
        <label for="name" class="form-label">Full Name <span class="required-asterisk">*</span></label>
        <input type="text" class="form-control" id="name" name="s_fullname" value="<?php print $s_fullname;?>" required autocomplete="new-password">
    </div>

    <!-- Group related form elements under one container -->
    <div id="humanFieldsContainer">
        <!-- Email -->
        <div class="mb-3">
            <label for="email" class="form-label">Email <span class="required-asterisk">*</span></label>
            <input type="email" class="form-control" id="email" name="s_email" required autocomplete="new-password">
        </div>

        <!-- Username with Switcher -->
        <div class="mb-3">
            <label for="username" class="form-label">Username <span class="required-asterisk">*</span></label>
            <input type="text" class="form-control" id="username" name="s_username" required autocomplete="new-password">
            <div class="form-check mt-2">
                <input type="checkbox" class="form-check-input" id="autofillUsername" checked>
                <label class="form-check-label" for="autofillUsername">Auto-fill username with email</label>
            </div>
        </div>


        <!-- Mobile Number -->
        <div class="mb-3">
            <label for="mobile" class="form-label">Mobile Number</label>
            <input type="text" class="form-control" id="mobile" name="s_mobile">
        </div>

        <!-- Password -->
        <div class="mb-3">
            <label for="password" class="form-label">Password</label>
            <div class="input-group">
                <input type="password" class="form-control" id="password" name="s_password" required autocomplete="new-password">
                <div class="input-group-append">
                    <button id="generatePassword" type="button" class="btn btn-light border" title="Generate Random Password"><i class="bi bi-shuffle"></i></button>
                    <button id="copyPassword" type="button" class="btn btn-light border" title="Copy Password"><i class="bi bi-clipboard"></i></button>
                    <button id="togglePassword" type="button" class="btn btn-light border" title="Show/Hide Password"><i class="bi bi-eye-fill"></i></button>
                </div>
            </div>
        </div>

        <!-- MFA Enabled -->
        <div class="mb-3">
            <label class="form-label">Enable Multi-factor Authentication? <span class="required-asterisk">*</span></label>
            <div class="row">
                <!-- No Card -->
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-body">
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="s_enablemfa" id="enableMFANo" value="N" checked>
                                <label class="form-check-label" for="enableMFANo">No</label>
                            </div>
                        </div>
                    </div>
                </div>
                <!-- Yes Card -->
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-body">
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="s_enablemfa" id="enableMFAYes" value="Y">
                                <label class="form-check-label" for="enableMFAYes">Yes</label>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

</div>

    <!-- Role (non-SaaS only) -->
    <div class="mb-3">
        <label for="role" class="form-label">Role (non-SaaS) <span class="required-asterisk">*</span></label>
        <select class="form-select" id="role" name="s_roleid" required autocomplete="new-password">
            <option selected disabled>Select Role</option>
            <?php if (!empty($roles)) { ?>
                <?php foreach ($roles as $role) { ?>
                    <option value="<?php echo (int)$role['id']; ?>"><?php echo htmlspecialchars($role['s_role_name']); ?></option>
                <?php } ?>
            <?php } ?>
        </select>
        <?php if (empty($roles)) { ?>
            <div class="alert alert-danger mt-2" role="alert">No non-SaaS roles found. Please add a role first.</div>
        <?php } ?>
        <div class="form-text">Assigns a primary system/API role. Workspace roles are added separately.</div>
    </div>

    <!-- IP Addresses -->
    <div class="mb-3">
        <label for="accessIps" class="form-label">Access IPs</label>
        <textarea class="form-control" id="accessIps" name="s_access_ips" rows="3" placeholder="Enter multiple IP addresses separated by commas"></textarea>
        <small class="text-muted">Example: 192.168.1.1, 192.168.1.2, ...</small>
    </div>

    <button type="submit" class="btn btn-primary"><i class="bi bi-check2-circle"></i> Submit</button>
</form>

<script>
    // Toggle password visibility
    document.getElementById('togglePassword').addEventListener('click', function() {
        const passwordField = document.getElementById('password');
        if (passwordField.type === 'password') {
            passwordField.type = 'text';
            this.innerHTML = '<i class="bi bi-eye-slash-fill"></i>';
        } else {
            passwordField.type = 'password';
            this.innerHTML = '<i class="bi bi-eye-fill"></i>';
        }
    });

    // Generate random password
    document.getElementById('generatePassword').addEventListener('click', function() {
        const charset = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*()_-+=<>?";
        let password = "";
        for (let i = 0; i < 12; i++) {
            const randomIndex = Math.floor(Math.random() * charset.length);
            password += charset[randomIndex];
        }
        document.getElementById('password').value = password;
    });

    // Copy password to clipboard
    document.getElementById('copyPassword').addEventListener('click', function() {
        const passwordField = document.getElementById('password');
        passwordField.select();
        document.execCommand('copy');
        alert('Password copied to clipboard');
    });

    const accessType = document.getElementById('accessType');
    const accessModeDiv = document.getElementById('accessModeDiv');
    const accessMode = document.getElementById('accessMode');
    function changeAccessType() {
        const nameLabel = document.querySelector("label[for='name']");
        const humanFieldsContainer = document.getElementById('humanFieldsContainer');
        const accessModeDiv = document.getElementById('accessModeDiv');

        if (!accessType.checked) { // If it's not checked, it's "Human"
            nameLabel.innerHTML = 'Full Name <span class="required-asterisk">*</span>';
            humanFieldsContainer.style.display = "block";
            accessModeDiv.style.display = "block";
        } else { // If checked, it's "API/Application"
            nameLabel.innerHTML = 'Name of API <span class="required-asterisk">*</span>';
            humanFieldsContainer.style.display = "none";
            accessModeDiv.style.display = "none";
        }
    }


    document.getElementById('email').addEventListener('input', function() {
        const isAutoFillEnabled = document.getElementById('autofillUsername').checked;
        if (isAutoFillEnabled) {
            document.getElementById('username').value = this.value;
        }
    });

    // Update username field when switcher state changes
    document.getElementById('autofillUsername').addEventListener('change', function() {
        if (this.checked) {
            document.getElementById('username').value = document.getElementById('email').value;
        }
    });

    // Function to validate if MFA is enabled
    function validateMFA() {
        const mfaEnabled = document.getElementById('enableMFAYes').checked;
        const emailField = document.getElementById('email');
        const mobileField = document.getElementById('mobile');

        if (mfaEnabled && emailField.value === '' && mobileField.value === '') {
            alert('When MFA is enabled, at least one of Email or Mobile Number must be filled out.');
            return false; // prevent form submission
        }

        return true; // allow form submission
    }
</script>
