<?php
// form submission URL is the self URL
$formSubmissionUrl = $this->runData['route']['url'];
?>
<form action="<?php print $formSubmissionUrl;?>" method="post" class="needs-validation" novalidate>
    
    <!-- Route Name -->
    <div class="form-group">
        <label for="s_name">Route Name</label>
        <input type="text" class="form-control" name="s_name" id="s_name" aria-required="true" required autocomplete="nope">
        <div class="invalid-feedback">
            Please provide a Route Name.
        </div>
        <small id="routeNameHelp" class="form-text text-muted">The route name should be a single segment using letters, numbers, and dashes only.</small>
    </div>

    <!-- Route Description -->
    <div class="form-group">
        <label for="s_description">Route Description</label>
        <textarea class="form-control" name="s_description" id="s_description" aria-describedby="routeDescriptionHelp"></textarea>
        <div class="invalid-feedback">
            Please provide a route description.
        </div>
        <small id="routeDescriptionHelp" class="form-text text-muted">Provide a brief description of the route.</small>
    </div>

    <div class="alert alert-light border small text-muted">
        Activity/notification templates for routes support placeholders: {route_name}, {route_id}, {route_uid}, {ms_name}, {ms_id}, {actor}, {timestamp}. Set the template on the route record to render readable logs.
    </div>

    <!-- Entity Scope -->
    <?php
    // if the access scope of microservice is public, then the entity scope input field is hidden
    if (strtolower($this->runData['data']['ms']['s_scope'] ?? '') !== 'global') {
    ?>
        <div class="form-group">
            <label for="s_entity_scope">Entity Scope</label>
            <select class="form-control" name="s_entity_scope" id="s_entity_scope" aria-describedby="entityScopeHelp">
                <option value="U">User Only</option>
                <option value="UA">Both User and API</option>
                <option value="A">API Only</option>
            </select>
            <div class="invalid-feedback">
                Please choose an Entity Scope.
            </div>
            <small id="entityScopeHelp" class="form-text text-muted">Entity scope can be for users, APIs or both.</small>
        </div>
        
        <?php if ($this->runData['data']['ms']['s_type'] == 'DYN') { ?>
        <!-- Service Definition (assuming JSON input) -->
        <div class="form-group">
            <label for="s_service_definition">Service Definition (JSON Format)</label>
            <textarea class="form-control" name="s_service_definition" id="s_service_definition" aria-describedby="serviceDefinitionHelp"></textarea>
            <div class="invalid-feedback">
                Please provide a valid service definition.
            </div>
            <small id="serviceDefinitionHelp" class="form-text text-muted">For Dynamic routes, define the service method only (example: {"method":"index"}). The URL uses /{ms_name}/{route_name}/... (workspace: /{workspace_slug_prefix}/{space_name}/{ms_name}/{route_name}/...).</small>
        </div>
        <?php } ?>

    <?php } ?>

    <!-- Submit button with an icon -->
    <button type="submit" class="btn btn-primary">
        <i class="bi bi-check-circle"></i> Add Route
    </button>
</form>
