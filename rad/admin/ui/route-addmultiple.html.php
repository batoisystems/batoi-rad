<?php
$formSubmissionUrl = $this->runData['route']['url'];
$routeNamesInput = (string)($this->runData['data']['route_names_input'] ?? '');
?>
<form action="<?php print $formSubmissionUrl; ?>" method="post" class="needs-validation" novalidate>
    <div class="form-group">
        <label for="route_names">Route Names</label>
        <textarea
            class="form-control"
            name="route_names"
            id="route_names"
            rows="12"
            aria-required="true"
            required
            autocomplete="off"
            placeholder="dashboard&#10;reports&#10;account/settings"><?php echo htmlspecialchars($routeNamesInput, ENT_QUOTES, 'UTF-8'); ?></textarea>
        <div class="invalid-feedback">
            Please provide at least one route name.
        </div>
        <small class="form-text text-muted">Enter one route name per line. Each name must be a single segment using letters, numbers, and dashes only.</small>
    </div>

    <div class="alert alert-light border small text-muted">
        All routes created from this form are saved with entity scope <strong>Both User and API</strong>.
    </div>

    <button type="submit" class="btn btn-primary">
        <i class="bi bi-list-check me-1"></i> Add Multiple Routes
    </button>
    <a href="<?php echo $this->runData['route']['rad_admin_url']; ?>/route/view/<?php echo htmlspecialchars($this->runData['data']['ms']['uid'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" class="btn btn-outline-secondary">
        Cancel
    </a>
</form>
