<?php
// print '<pre>';print_r($this->runData);print '</pre>';
// form submission url is the self url
$formSubmissionUrl = $this->runData['route']['url'];
// print '<pre>';print_r($formSubmissionUrl);print '</pre>';
?>
<form action="<?php print $formSubmissionUrl;?>" method="post" class="needs-validation" novalidate>
        <!-- Parameter Name -->
        <div class="form-group">
            <label for="s_config_handle">Application Parameter Name</label>
            <input type="text" class="form-control" name="s_config_handle" id="s_name" aria-required="true" required autocomplete="nope">
            <div class="invalid-feedback">
                Please provide a name for the Parameter - must not use space or special characters Underscore is accepted.
            </div>
            <small id="nameHelp" class="form-text text-muted">The Parameter name should be alphanumeric with/without underscore (_) and without any blank space. If you enter any, they will be removed.</small>
        </div>
        
        <div class="form-group">
            <label for="s_config_value">Application Parameter Value</label>
            <input type="text" class="form-control" name="s_config_value" id="s_config_value" aria-required="true" required autocomplete="nope">
            <div class="invalid-feedback">
                Please provide a value for the Parameter - can be any character, but must not contain any code (script).
            </div>
            <small id="valueHelp" class="form-text text-muted">Any script entered into the input form will be truncated automatically.</small>
        </div>

        <!-- Parameter Description -->
        <div class="form-group">
            <label for="s_description">Application Parameter Description</label>
            <textarea class="form-control" name="s_description" id="s_description" aria-describedby="descriptionHelp"></textarea>
            <div class="invalid-feedback">
                Please provide a description for the Parameter.
            </div>
            <small id="descriptionHelp" class="form-text text-muted">Provide a brief description of the Parameter.</small>
        </div>

        <!-- Submit button with an icon -->
        <button type="submit" class="btn btn-primary">
            <i class="bi bi-check-circle"></i> Save
        </button>
</form>



