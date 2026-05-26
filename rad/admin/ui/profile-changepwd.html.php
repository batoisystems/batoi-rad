<?php
$active = 'changepwd';
include $this->runData['config']['dir']['admin'] . '/ui/profile-nav.partial.php';
?>
<form action="<?php print $this->runData['config']['sys']['base_url'].'/rad-admin/profile/changepwd';?>" method="post" id="passwordChangeForm">
    <div class="mb-3">
        <label for="currentPassword" class="form-label">Current Password</label>
        <input type="password" class="form-control" id="currentPassword" name="currentPassword" autocomplete="new-password" placeholder="Enter Current Password" required>
    </div>
    <div class="mb-3">
        <label for="newPassword" class="form-label">New Password</label>
        <input type="password" class="form-control" id="newPassword" name="newPassword" autocomplete="new-password" placeholder="Enter New Password" required>
        <div id="passwordStrength" class="mt-2">
            <div class="progress">
                <div id="strengthBar" class="progress-bar" role="progressbar"></div>
            </div>
        </div>
    </div>
    <div class="mb-3">
        <label for="retypePassword" class="form-label">Retype New Password</label>
        <input type="password" class="form-control" id="retypePassword" name="retypePassword" autocomplete="new-password" placeholder="Retype New Password" required>
    </div>
    <button type="submit" class="btn btn-primary"><i class="bi bi-check2-circle"></i> Submit</button>
</form>

<script>
document.getElementById("newPassword").addEventListener("input", function(e) {
    var password = e.target.value;
    var strengthBar = document.getElementById("strengthBar");
    var strength = 0;
    // Add one point for each match from the list of regular expressions
    if (/[A-Z]/.test(password)) strength++; 
    if (/[a-z]/.test(password)) strength++; 
    if (/[0-9]/.test(password)) strength++;
    if (/[^a-zA-Z0-9]/.test(password)) strength++;

    switch(strength) {
    case 0:
        strengthBar.style.width = "0%";
        break
    case 1:
        strengthBar.style.width = "25%";
        strengthBar.className = "progress-bar bg-danger";
        break
    case 2:
        strengthBar.style.width = "50%";
        strengthBar.className = "progress-bar bg-warning";
        break
    case 3:
        strengthBar.style.width = "75%";
        strengthBar.className = "progress-bar bg-info";
        break
    case 4:
        strengthBar.style.width = "100%";
        strengthBar.className = "progress-bar bg-success";
        break
    }
});

document.getElementById("passwordChangeForm").addEventListener("submit", function(e) {
    var password = document.getElementById("newPassword").value;
    var confirmPassword = document.getElementById("retypePassword").value;

    if (password !== confirmPassword) {
        alert("Passwords do not match!");
        e.preventDefault();
    }
});
</script>
