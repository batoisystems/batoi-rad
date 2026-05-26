<?php
// print '<pre>';print_r($this->runData['data']);print '</pre>';
// form submission url is the self url
$formSubmissionUrl = $this->runData['route']['url'];
// print '<pre>';print_r($formSubmissionUrl);print '</pre>';
?>
<form action="<?php print $formSubmissionUrl;?>" method="post" class="needs-validation" novalidate>
        <input type="hidden" name="config_id" value="<?php echo $this->runData['data']['config']['id']; ?>">
        <!-- Parameter Name - the field will be readonly if the s_config_origin is S -->
        <?php
        if (isset($this->runData['data']['config']['s_config_origin']) && ($this->runData['data']['config']['s_config_origin'] == 'S')) {
            $readonly = 'readonly';
        }
        else {
            $readonly = '';
        }
        ?>
        <div class="form-group">
            <label for="s_config_handle">Parameter Name</label>
            <!-- create readonly field -->
            <input type="text" class="form-control" name="s_config_handle" id="s_config_handle" value="<?php echo $this->runData['data']['config']['s_config_handle']; ?>" aria-required="true" required autocomplete="nope" <?php echo $readonly; ?>>
            <div class="invalid-feedback">
                Please provide a name for the Parameter - must not use space or special characters Underscore is accepted.            </div>
            <small id="nameHelp" class="form-text text-muted">The Parameter name should be alphanumeric with/without underscore (_) and without any blank space. If you enter any, they will be removed.</small>
        </div>

        <!-- Parameter Value -->
        <div class="form-group">
            <label for="s_config_value">Parameter Value</label>
            <input type="text" class="form-control" name="s_config_value" id="s_config_value" value="<?php echo $this->runData['data']['config']['s_config_value']; ?>" aria-required="true" required autocomplete="nope">
            <div class="invalid-feedback">
                Please provide a value for the Parameter - can be any character, but must not contain any code (script).
            </div>
            <small id="valueHelp" class="form-text text-muted">Any script entered into the input form will be truncated automatically.</small>
        </div>

        <!-- Parameter Description -->
        <div class="form-group">
            <label for="s_description">Parameter Description</label>
            <textarea class="form-control" name="s_description" id="s_description" aria-describedby="descriptionHelp"><?php echo $this->runData['data']['config']['s_description']; ?></textarea>
            <div class="invalid-feedback">
                Please provide a description for the Parameter.
            </div>
            <small id="descriptionHelp" class="form-text text-muted">Provide a brief description of the Parameter.</small>
        </div>

        <!-- Submit button with an icon -->
        <button type="submit" class="btn btn-primary">
            <i class="bi bi-check-circle"></i> Save
        </button>
        <?php
        if (isset($this->runData['data']['config']['s_config_origin']) && ($this->runData['data']['config']['s_config_origin'] == 'A')) {
            // go back URL is the same as the view URL
            $goBackUrl = $this->runData['route']['rad_admin_url'] . '/config/view';
        }
        else {
            // go back URL is the same as the view URL and /S
            $goBackUrl = $this->runData['route']['rad_admin_url'] . '/config/view/S';
        }
        ?>
        <a href="<?php print $goBackUrl;?>" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left-circle"></i> Cancel and Go Back</a>
</form>
