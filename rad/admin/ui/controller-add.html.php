<?php
// print '<pre>';print_r($this->runData);print '</pre>';
// form submission url is the self url
$formSubmissionUrl = $this->runData['route']['url'];
// print '<pre>';print_r($formSubmissionUrl);print '</pre>';
?>
<form action="<?php print $formSubmissionUrl;?>" method="post" class="needs-validation" novalidate>
        
        <!-- Business Class / Data Model Name -->
        <div class="form-group">
            <label for="s_name">Business Class / Data Model Name</label>
            <input type="text"
                   class="form-control"
                   name="s_name"
                   id="s_name"
                   aria-required="true"
                   required
                   maxlength="25"
                   autocomplete="nope"
                   value="<?php echo htmlspecialchars($this->runData['request']->post['s_name'] ?? ''); ?>">
            <div class="invalid-feedback">
                Please provide a name.
            </div>
            <small id="controllerNameHelp" class="form-text text-muted">Use up to 25 alphanumeric characters or underscores. Business Class files may retain their original filename metadata.</small>
        </div>

        <!-- Description -->
        <div class="form-group">
            <label for="s_description">Description</label>
            <textarea class="form-control" name="s_description" id="s_description" aria-describedby="controllerDescriptionHelp"><?php echo htmlspecialchars($this->runData['request']->post['s_description'] ?? ''); ?></textarea>
            <div class="invalid-feedback">
                Please provide a description.
            </div>
            <small id="controllerDescriptionHelp" class="form-text text-muted">Provide a brief description of the business class or data model.</small>
        </div>

        <!-- Type -->
        <div class="form-group">
            <label for="s_type">Type</label>
            <select class="form-control" name="s_type" id="s_type" aria-describedby="controllerTypeHelp">
                <?php $selectedType = $this->runData['request']->post['s_type'] ?? 'BL'; ?>
                <option value="BL" <?php echo $selectedType === 'BL' ? 'selected' : ''; ?>>Business Class</option>
                <option value="DM" <?php echo $selectedType === 'DM' ? 'selected' : ''; ?>>Data Model</option>
            </select>
            <div class="invalid-feedback">
                Please choose a type.
            </div>
            <small id="controllerTypeHelp" class="form-text text-muted">Business Class creates a PHP class; Data Model creates an a_* table.</small>
        </div>

        <!-- Submit button with an icon -->
        <button type="submit" class="btn btn-primary">
            <i class="bi bi-check-circle"></i> Add
        </button>
        <a href="<?php print $this->runData['route']['rad_admin_url'] . '/controller/view/'.$this->runData['data']['ms']['uid'];?>" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left-circle"></i> Cancel and Go Back</a>
</form>
