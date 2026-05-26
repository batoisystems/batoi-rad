<?php
// print '<pre>';print_r($this->runData['data']['user']);print '</pre>';die('here');
$authInfo = [
    'email' => $this->runData['data']['user']['s_email'] ?? '',
    'mobile' => $this->runData['data']['user']['s_mobile'] ?? '',
    'enable_mfa' => $this->runData['data']['user']['s_enable_mfa'] ?? 'N',
    'access_ips' => $this->runData['data']['user']['s_access_ips'] ?? '',
];
?>
<form action="<?php print $this->runData['route']['url'];?>" method="post" onsubmit="return validateForm();">
    <!-- Entity ID -->
    <input type="hidden" name="s_entity_id" value="<?php print $this->runData['data']['user']['id'];?>">
    <!-- Access Mode -->
    <input type="hidden" name="s_login_mode[]" value="SE">
    <div class="mb-3" id="loginModeDiv">
        <label for="loginMode" class="form-label">Login Mode - Choose a Single-Sign-On Provider if you want to enable SSO.</label>
        <div class="row">
            <!-- Access Mode: Self -->
            <div class="col-sm-6 col-md-3">
                <div class="card">
                    <div class="card-body">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="s_login_mode[]" id="selfMode" value="SE" checked disabled>
                            <label class="form-check-label" for="selfMode">Self</label>
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
                            <label class="form-check-label" for="batoiMode">Batoi</label>
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
                            <label class="form-check-label" for="googleMode">Google</label>
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
                            <label class="form-check-label" for="twitterMode">Twitter</label>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Full Name -->
    <?php
    $s_fullname = isset($this->runData['request']->post['s_fullname']) ? $this->runData['request']->post['s_fullname'] : (isset($this->runData['data']['user']['s_name']) ? $this->runData['data']['user']['s_name'] : '');
    ?>
    <div class="mb-3">
        <label for="name" class="form-label">Full Name <span class="required-asterisk">*</span></label>
        <input type="text" class="form-control" id="name" name="s_fullname" value="<?php print $s_fullname;?>" required autocomplete="new-password">
    </div>
    
    <!-- Email -->
    <?php
    $s_email = isset($this->runData['request']->post['s_email']) ? $this->runData['request']->post['s_email'] : (isset($authInfo['email']) ? $authInfo['email'] : '');
    ?>
    <div class="mb-3">
        <label for="s_email" class="form-label">Email <span class="required-asterisk">*</span></label>
        <input type="email" class="form-control" id="s_email" name="s_email" value="<?php print $s_email;?>" required autocomplete="new-password">
    </div>

    <!-- Username -->
    <?php
    $s_username = isset($this->runData['request']->post['s_username']) ? $this->runData['request']->post['s_username'] : (isset($this->runData['data']['user']['s_identity']) ? $this->runData['data']['user']['s_identity'] : '');
    ?>
    <div class="mb-3">
        <label for="username" class="form-label">Username <span class="required-asterisk">*</span></label>
        <input type="text" class="form-control" id="username" name="s_username" value="<?php print $s_username;?>" required autocomplete="new-password">
    </div>

    <!-- Mobile Number -->
    <?php
    $s_mobile = isset($this->runData['request']->post['s_mobile']) ? $this->runData['request']->post['s_mobile'] : (isset($authInfo['mobile']) ? $authInfo['mobile'] : '');
    ?>
    <div class="mb-3">
        <label for="s_mobile" class="form-label">Mobile Number</label>
        <input type="text" class="form-control" id="s_mobile" name="s_mobile" value="<?php print $s_mobile;?>">
    </div>

    <!-- Password -->
    <div class="mb-3">
        <label for="password" class="form-label">Password</label>
        <div class="input-group">
            <input type="password" class="form-control" id="password" name="s_password" autocomplete="new-password">
            <div class="input-group-append">
                <button id="generatePassword" type="button" class="btn btn-light border" title="Generate Random Password"><i class="bi bi-shuffle"></i></button>
                <button id="copyPassword" type="button" class="btn btn-light border" title="Copy Password"><i class="bi bi-clipboard"></i></button>
                <button id="togglePassword" type="button" class="btn btn-light border" title="Show/Hide Password"><i class="bi bi-eye-fill"></i></button>
            </div>
        </div>
    </div>

    <!-- MFA Enabled -->
    <?php
    $s_enablemfa = isset($this->runData['request']->post['s_enablemfa']) ? $this->runData['request']->post['s_enablemfa'] : (isset($authInfo['enable_mfa']) ? $authInfo['enable_mfa'] : 'N');
    ?>
    <div class="mb-3">
        <label class="form-label">Enable Multi-factor Authentication? <span class="required-asterisk">*</span></label>
        <div class="row">
            <!-- No Card -->
            <div class="col-md-6">
                <div class="card">
                    <div class="card-body">
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="s_enablemfa" id="enableMFANo" value="N" <?php if ($s_enablemfa === 'N') { print 'checked'; } ?>>
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
                            <input class="form-check-input" type="radio" name="s_enablemfa" id="enableMFAYes" value="Y" <?php if ($s_enablemfa === 'Y') { print 'checked'; } ?>>
                            <label class="form-check-label" for="enableMFAYes">Yes</label>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- IP Addresses -->
    <?php
    $s_access_ips = isset($this->runData['request']->post['s_access_ips']) ? $this->runData['request']->post['s_access_ips'] : (isset($authInfo['access_ips']) ? $authInfo['access_ips'] : '');
    ?>
    <div class="mb-3">
        <label for="accessIps" class="form-label">Access IPs</label>
        <textarea class="form-control" id="accessIps" name="s_access_ips" rows="3" placeholder="Enter multiple IP addresses separated by commas"><?php print $s_access_ips;?></textarea>
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

    // Function to validate if MFA is enabled
    function validateMFA() {
        const mfaEnabled = document.getElementById('enableMFAYes').checked;
        const emailField = document.getElementById('s_email');
        const mobileField = document.getElementById('s_mobile');

        if (mfaEnabled && emailField.value === '' && mobileField.value === '') {
            alert('When MFA is enabled, at least one of Email or Mobile Number must be filled out.');
            return false; // prevent form submission
        }

        return true; // allow form submission
    }

    // Function to validate IP addresses
    function validateIPs() {
        const ipsField = document.getElementById('accessIps');
        const ips = ipsField.value.split(',');
        const ipPattern = /^(25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)\.(25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)\.(25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)\.(25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)$/;

        for (const ip of ips) {
            if (!ipPattern.test(ip.trim())) {
                alert('Invalid IP address format: ' + ip.trim());
                return false;
            }
        }
        return true;
    }

    // Main form validation function
    function validateForm() {
        return validateMFA() && validateIPs();
    }
</script>
