<?php
// form submission url is the self url
$formSubmissionUrl = $this->runData['route']['url'];

// Reserved templates list
$reservedTemplates = [
    'api.tpl.php',
    'home.tpl.php',
    'login.tpl.php',
    'maintenance.tpl.php',
    'register.tpl.php', 
    'reset-password.tpl.php', 
    'error-page.tpl.php'
];

// Get all the template files from the directory
$themeDirectoryPath = $this->runData['config']['dir']['theme'];
$allTemplates = array_filter(scandir($themeDirectoryPath), function($filename) use ($reservedTemplates) {
    return strpos($filename, '.tpl.php') !== false && !in_array($filename, $reservedTemplates);
});

// Generate the dropdown list for templates
$templateDropdown = '';
foreach($allTemplates as $template) {
    $templateName = str_replace('.tpl.php', '', $template);
    $templateDropdown .= '<option value="' . $templateName . '">' . ucfirst($templateName) . '</option>';
}
?>

<form action="<?php print $formSubmissionUrl;?>" method="post" class="needs-validation" novalidate>
    <!-- Microservicelet Name -->
    <div class="form-group">
        <label for="s_name">Name <span class="text-danger">*</span></label>
        <input type="text" class="form-control" name="s_name" id="s_name" aria-required="true" required autocomplete="nope">
        <div class="invalid-feedback">
            Please provide a Microservicelet Name - must not use space or special characters.
        </div>
        <small id="nameHelp" class="form-text text-muted">The Microservicelet name should be alphanumeric with/without dash (-) and without any blank space. If you enter any, they will be removed.</small>
    </div>
    
    <!-- Microservicelet Description -->
    <div class="form-group">
        <label for="s_description">Description</label>
        <textarea class="form-control" name="s_description" id="s_description" aria-describedby="descriptionHelp"></textarea>
        <div class="invalid-feedback">
            Please provide a route description.
        </div>
        <small id="descriptionHelp" class="form-text text-muted">Provide a brief description of the Microservicelet.</small>
    </div>

<!-- Scope -->
<div class="form-group">
    <label for="s_scope">Scope <span class="text-danger">*</span></label>
    <select class="form-control" name="s_scope" id="s_scope">
        <option value="platform" selected>Platform (Non-SaaS)</option>
        <option value="workspace">Workspace (SaaS)</option>
        <!-- App/Member Org scopes removed -->
        <option value="global">Global (Public)</option>
    </select>
    <div class="invalid-feedback">
        Please select a scope.
    </div>
    <small class="form-text text-muted">Platform = non-SaaS; Workspace/App/Member Org = SaaS; Global = public exposure.</small>
</div>

    <input type="hidden" name="s_type" value="DYN">
    <div class="form-group">
        <label class="form-label">Microservicelet Type</label>
        <div class="form-control-plaintext fw-semibold">Dynamic Route (DYN)</div>
        <small id="routeTypeHelp" class="form-text text-muted">New microservicelets created from this page always use dynamic routing.</small>
    </div>

    <!-- Definition - JSON -->
    <div class="form-group">
        <label for="s_definition">Definition</label>
        <textarea class="form-control" name="s_definition" id="s_definition" aria-describedby="definitionHelp"></textarea>
        <div class="invalid-feedback">
            Please provide the definition of the Microservicelet.
        </div>
        <small id="definitionHelp" class="form-text text-muted">Provide a JSON definition of the Microservicelet.</small>
    </div>

    <!-- Template dropdown -->
    <div class="form-group">
        <label for="s_template">Template <span class="text-danger">*</span></label>
        <select class="form-control" name="s_template" id="s_template">
            <?php print $templateDropdown; ?>
        </select>
        <div class="invalid-feedback">
            Please choose a template.
        </div>
        <small id="templateHelp" class="form-text text-muted">Choose a template for your Microservicelet.</small>
    </div>

    <hr class="my-4">

    <div class="card mb-4">
        <div class="card-header bg-white">
            <button class="btn btn-link text-decoration-none px-0 d-flex align-items-center gap-2" type="button" data-bs-toggle="collapse" data-bs-target="#optionalSetupCard" aria-expanded="false" aria-controls="optionalSetupCard">
                <i class="bi bi-chevron-down"></i>
                <span class="fw-semibold">Optional Setup</span>
            </button>
            <div class="small text-muted mt-1">Start with routes, business classes, and data models only if you need them now.</div>
        </div>
        <div id="optionalSetupCard" class="collapse">
            <div class="card-body">
                <div class="mb-3">
                    <h5 class="mb-1">Routes</h5>
                    <p class="text-muted small mb-2">Create initial routes with the microservicelet.</p>
                    <div id="route-list" class="d-flex flex-column gap-2"></div>
                    <button type="button" class="btn btn-outline-secondary btn-sm" id="add-route">
                        <i class="bi bi-plus-lg me-1"></i>Add Route
                    </button>
                </div>

                <div class="mb-3">
                    <h5 class="mb-1">Business Classes</h5>
                    <p class="text-muted small mb-2">Add business logic classes (BL) now; code stubs are generated.</p>
                    <div id="bl-list" class="d-flex flex-column gap-2"></div>
                    <button type="button" class="btn btn-outline-secondary btn-sm" id="add-bl">
                        <i class="bi bi-plus-lg me-1"></i>Add Business Class
                    </button>
                </div>

                <div class="mb-0">
                    <h5 class="mb-1">Data Models</h5>
                    <p class="text-muted small mb-2">Add data models (DM); tables are created in the database.</p>
                    <div id="dm-list" class="d-flex flex-column gap-2"></div>
                    <button type="button" class="btn btn-outline-secondary btn-sm" id="add-dm">
                        <i class="bi bi-plus-lg me-1"></i>Add Data Model
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Submit button with an icon -->
    <button type="submit" class="btn btn-primary">
        <i class="bi bi-check-circle"></i> Save
    </button>
</form>

<script>
(function () {
    const routeList = document.getElementById('route-list');
    const blList = document.getElementById('bl-list');
    const dmList = document.getElementById('dm-list');
    const makeRow = (fields) => {
        const row = document.createElement('div');
        row.className = 'border rounded-3 p-3 d-flex flex-wrap gap-2 align-items-end';
        row.innerHTML = fields + '<div class="ms-auto"><button type="button" class="btn btn-outline-danger btn-sm remove-row"><i class="bi bi-x"></i></button></div>';
        row.querySelector('.remove-row').addEventListener('click', () => row.remove());
        return row;
    };

    document.getElementById('add-route').addEventListener('click', () => {
        const fields = `
            <div class="flex-grow-1">
                <label class="form-label small text-muted mb-1">Route name</label>
                <input type="text" name="route_name[]" class="form-control" placeholder="default">
            </div>
            <div class="flex-grow-1">
                <label class="form-label small text-muted mb-1">Description</label>
                <input type="text" name="route_description[]" class="form-control" placeholder="Optional">
            </div>`;
        routeList.appendChild(makeRow(fields));
    });

    document.getElementById('add-bl').addEventListener('click', () => {
        const fields = `
            <div class="flex-grow-1">
                <label class="form-label small text-muted mb-1">Business Class name</label>
                <input type="text" name="bl_name[]" class="form-control" placeholder="billing">
            </div>
            <div class="flex-grow-1">
                <label class="form-label small text-muted mb-1">Description</label>
                <input type="text" name="bl_description[]" class="form-control" placeholder="Optional">
            </div>`;
        blList.appendChild(makeRow(fields));
    });

    document.getElementById('add-dm').addEventListener('click', () => {
        const fields = `
            <div class="flex-grow-1">
                <label class="form-label small text-muted mb-1">Data Model name</label>
                <input type="text" name="dm_name[]" class="form-control" placeholder="invoice">
            </div>
            <div class="flex-grow-1">
                <label class="form-label small text-muted mb-1">Description</label>
                <input type="text" name="dm_description[]" class="form-control" placeholder="Optional">
            </div>`;
        dmList.appendChild(makeRow(fields));
    });
})();
</script>
