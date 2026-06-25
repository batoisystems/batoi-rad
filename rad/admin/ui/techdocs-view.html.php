<?php
$summary = $this->runData['data']['summary'] ?? [];
$microservices = $this->runData['data']['microservices'] ?? [];
$routes = $this->runData['data']['routes'] ?? [];
$controllers = $this->runData['data']['controllers'] ?? [];
$routesByMs = $this->runData['data']['routes_by_ms'] ?? [];
$controllersByMs = $this->runData['data']['controllers_by_ms'] ?? [];
$navsets = $this->runData['data']['navsets'] ?? [];
$navitemsBySet = $this->runData['data']['navitems_by_set'] ?? [];
$apiEndpoints = $this->runData['data']['api_endpoints'] ?? [];
$roles = $this->runData['data']['roles'] ?? [];
$workspaces = $this->runData['data']['workspaces'] ?? [];
$bindingsIndex = $this->runData['data']['bindings_index'] ?? [];
$vendors = $this->runData['data']['vendors'] ?? [];
$templates = $this->runData['data']['templates'] ?? [];
$radAdminUrl = $this->runData['route']['rad_admin_url'] ?? '';

$msMap = [];
foreach ($microservices as $ms) {
    $msMap[$ms['id']] = $ms;
}
$roleMap = [];
foreach ($roles as $role) {
    $roleMap[$role['id']] = $role['s_role_name'] ?? ('Role #' . $role['id']);
}
$workspaceCount = count($workspaces);
?>
<div class="card border-0 shadow-sm mb-4">
    <div class="card-body d-flex flex-column flex-lg-row align-items-lg-center gap-3">
        <div>
            <p class="text-muted mb-0">Auto-assembled view of your application: microservicelets, routes, controllers, navigation, templates, APIs, roles, workspaces, and vendor dependencies.</p>
        </div>
        <div class="ms-lg-auto d-flex gap-2 flex-wrap">
            <a href="<?php echo $radAdminUrl; ?>/techdocs/accesscontrol" class="btn btn-outline-info btn-sm"><i class="bi bi-shield-lock me-1"></i>Access Control</a>
            <a href="<?php echo $radAdminUrl; ?>/techdocs/dotphrases" class="btn btn-outline-secondary btn-sm"><i class="bi bi-three-dots me-1"></i>Dot Phrases</a>
            <a href="<?php echo $radAdminUrl; ?>/microservice/view" class="btn btn-outline-primary btn-sm"><i class="bi bi-boxes me-1"></i>Microservicelets</a>
            <a href="<?php echo $radAdminUrl; ?>/apiendpoint/view" class="btn btn-outline-secondary btn-sm"><i class="bi bi-plug me-1"></i>API Catalog</a>
            <a href="<?php echo $radAdminUrl; ?>/techdocs/export/html" class="btn btn-outline-success btn-sm"><i class="bi bi-filetype-html me-1"></i>Export HTML</a>
            <a href="<?php echo $radAdminUrl; ?>/techdocs/export/pdf" class="btn btn-outline-dark btn-sm"><i class="bi bi-file-pdf me-1"></i>Export PDF</a>
        </div>
    </div>
</div>

<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-white">
        <h3 class="h6 mb-0">Visualizations</h3>
        <p class="text-muted small mb-0">Microservicelets, vendor libraries, route scopes, and roles/workspaces.</p>
    </div>
    <div class="card-body">
        <div class="row g-3">
            <?php
            $tdCharts = [
                ['id' => 'ms', 'title' => 'Routes & Controllers per Microservicelet'],
                ['id' => 'vendor', 'title' => 'Vendor Library Status'],
                ['id' => 'routes', 'title' => 'Routes by Scope'],
                ['id' => 'roles', 'title' => 'Roles & Workspaces'],
            ];
            foreach ($tdCharts as $chart) { ?>
            <div class="col-12 col-md-6 col-xl-3">
                <div class="border rounded p-3 h-100">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span class="fw-semibold small"><?php echo htmlspecialchars($chart['title']); ?></span>
                        <button type="button" class="btn btn-outline-secondary btn-sm" data-td-chart-download="<?php echo htmlspecialchars($chart['id']); ?>">
                            <i class="bi bi-download"></i>
                        </button>
                    </div>
                    <canvas id="td-chart-<?php echo htmlspecialchars($chart['id']); ?>" height="180"></canvas>
                </div>
            </div>
            <?php } ?>
        </div>
    </div>
</div>

<?php
$radAssetsUrl = $this->runData['route']['rad_assets_url'] ?? '';
?>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const metricsUrl = <?php echo json_encode($radAdminUrl . '/techdocs/metrics'); ?>;
    const charts = {};
    const downloadButtons = Array.from(document.querySelectorAll('[data-td-chart-download]'));
    downloadButtons.forEach(btn => btn.disabled = true);

    const colors = {
        primary: '#0d6efd',
        success: '#2ecc71',
        warning: '#f39c12',
        danger: '#e74c3c',
        info: '#3498db',
        gray: '#95a5a6',
    };

    fetch(metricsUrl, { headers: { 'Accept': 'application/json' } })
        .then(resp => resp.ok ? resp.json() : Promise.reject(new Error('Metrics load failed')))
        .then(renderCharts)
        .catch((err) => {
            console.error('TechDocs metrics error', err);
        });

    function renderCharts(data) {
        const msLabels = (data.microservices || []).map(m => m.name);
        const msRoutes = (data.microservices || []).map(m => m.routes);
        const msControllers = (data.microservices || []).map(m => m.controllers);

        charts.ms = window.RadAdminCharts.render(document.getElementById('td-chart-ms'), {
            type: 'bar',
            data: {
                labels: msLabels,
                datasets: [{
                    label: 'Routes',
                    data: msRoutes,
                    backgroundColor: colors.primary,
                }, {
                    label: 'Controllers',
                    data: msControllers,
                    backgroundColor: colors.info,
                }]
            },
            options: {plugins: {legend: {position: 'bottom'}}, scales: {x: {stacked: true}, y: {stacked: true}}}
        });

        charts.vendor = window.RadAdminCharts.render(document.getElementById('td-chart-vendor'), {
            type: 'doughnut',
            data: {
                labels: ['Installed', 'Missing'],
                datasets: [{
                    data: [data.vendor?.installed || 0, data.vendor?.missing || 0],
                    backgroundColor: [colors.success, colors.warning],
                }]
            },
            options: {plugins: {legend: {position: 'bottom'}}}
        });

        charts.routes = window.RadAdminCharts.render(document.getElementById('td-chart-routes'), {
            type: 'pie',
            data: {
                labels: ['UA', 'U', 'A'],
                datasets: [{
                    data: [
                        data.routes_by_scope?.UA || 0,
                        data.routes_by_scope?.U || 0,
                        data.routes_by_scope?.A || 0,
                    ],
                    backgroundColor: [colors.info, colors.primary, colors.gray],
                }]
            },
            options: {plugins: {legend: {position: 'bottom'}}}
        });

        charts.roles = window.RadAdminCharts.render(document.getElementById('td-chart-roles'), {
            type: 'bar',
            data: {
                labels: ['Roles', 'Workspaces'],
                datasets: [{
                    label: 'Count',
                    data: [data.roles || 0, data.workspaces || 0],
                    backgroundColor: [colors.success, colors.primary],
                }]
            },
            options: {plugins: {legend: {display: false}}}
        });

        downloadButtons.forEach(btn => btn.disabled = false);
    }

    const triggerDownload = (key) => {
        const chart = charts[key];
        if (!chart || !chart.canvas) {
            alert('Chart not ready yet. Please wait for data to load.');
            return;
        }
        try {
            const dataUrl = chart.canvas.toDataURL('image/png');
            if (!dataUrl) {
                throw new Error('toDataURL returned empty');
            }
            const link = document.createElement('a');
            link.href = dataUrl;
            link.download = 'techdocs-' + key + '.png';
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
            const newWin = window.open(dataUrl, '_blank');
            if (newWin) { newWin.opener = null; }
        } catch (e) {
            console.error('Chart download failed', e);
            alert('Unable to download chart PNG right now. Please try again after the page fully loads.');
        }
    };

    document.querySelectorAll('[data-td-chart-download]').forEach(btn => {
        btn.addEventListener('click', (e) => {
            e.preventDefault();
            e.stopPropagation();
            const key = btn.getAttribute('data-td-chart-download');
            triggerDownload(key);
        });
    });
});
</script>
<div class="row g-3 mb-4">
    <?php
    $statCards = [
        ['label' => 'Microservicelets', 'value' => $summary['microservices'] ?? 0, 'icon' => 'bi bi-boxes', 'tone' => 'primary'],
        ['label' => 'Routes', 'value' => $summary['routes'] ?? 0, 'icon' => 'bi bi-signpost-2', 'tone' => 'secondary'],
        ['label' => 'Controllers', 'value' => $summary['controllers'] ?? 0, 'icon' => 'bi bi-diagram-3', 'tone' => 'info'],
        ['label' => 'Nav Sets', 'value' => $summary['navsets'] ?? 0, 'icon' => 'bi bi-list-nested', 'tone' => 'dark'],
        ['label' => 'Templates', 'value' => $summary['templates'] ?? 0, 'icon' => 'bi bi-filetype-php', 'tone' => 'warning'],
        ['label' => 'APIs', 'value' => $summary['apis'] ?? 0, 'icon' => 'bi bi-plug', 'tone' => 'success'],
        ['label' => 'Roles', 'value' => $summary['roles'] ?? 0, 'icon' => 'bi bi-person-badge', 'tone' => 'danger'],
        ['label' => 'Workspaces', 'value' => $summary['workspaces'] ?? 0, 'icon' => 'bi bi-buildings', 'tone' => 'primary'],
    ];
    foreach ($statCards as $card) { ?>
    <div class="col-6 col-lg-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body d-flex align-items-center gap-3">
                <span class="badge bg-<?php echo $card['tone']; ?> rounded-circle p-3"><i class="<?php echo $card['icon']; ?>"></i></span>
                <div>
                    <div class="text-muted text-uppercase small"><?php echo $card['label']; ?></div>
                    <div class="h4 mb-0"><?php echo (int)$card['value']; ?></div>
                </div>
            </div>
        </div>
    </div>
    <?php } ?>
</div>

<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-white d-flex justify-content-between align-items-center">
        <div>
            <h3 class="h6 mb-0">Microservicelets</h3>
            <p class="text-muted small mb-0">Base units of the app. Each entry aggregates its routes, controllers, and role bindings.</p>
        </div>
        <a href="<?php echo $radAdminUrl; ?>/microservice/view" class="btn btn-outline-secondary btn-sm">Manage</a>
    </div>
    <div class="card-body">
        <?php if (empty($microservices)) { ?>
            <p class="text-muted mb-0">No microservicelets recorded.</p>
        <?php } else { ?>
            <div class="table-responsive">
                <table class="table align-middle">
                    <thead class="small text-muted">
                        <tr>
                            <th>Name</th>
                            <th>Type</th>
                            <th>Routes</th>
                            <th>Controllers</th>
                            <th>Roles</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($microservices as $ms) {
                            $msRoutes = $routesByMs[$ms['id']] ?? [];
                            $msControllers = $controllersByMs[$ms['id']] ?? [];
                            $msRoles = $bindingsIndex['ms'][$ms['id']] ?? [];
                        ?>
                        <tr>
                            <td>
                                <div class="fw-semibold"><?php echo htmlspecialchars($ms['s_name'] ?? ''); ?></div>
                                <div class="text-muted small"><?php echo htmlspecialchars($ms['s_description'] ?? ''); ?></div>
                            </td>
                            <td class="text-muted small"><?php echo htmlspecialchars($ms['s_type'] ?? ''); ?></td>
                            <td class="text-muted small"><?php echo count($msRoutes); ?> route(s)</td>
                            <td class="text-muted small"><?php echo count($msControllers); ?> controller(s)</td>
                            <td class="text-muted small">
                                <?php if (empty($msRoles)) { ?>
                                    — 
                                <?php } else { ?>
                                    <?php foreach ($msRoles as $bind) {
                                        $roleName = $bind['role']['s_role_name'] ?? ($roleMap[$bind['s_role_id']] ?? 'Role');
                                    ?>
                                        <span class="badge bg-light text-dark me-1 mb-1"><?php echo htmlspecialchars($roleName); ?></span>
                                    <?php } ?>
                                <?php } ?>
                            </td>
                        </tr>
                        <?php } ?>
                    </tbody>
                </table>
            </div>
        <?php } ?>
    </div>
</div>

<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-white d-flex justify-content-between align-items-center">
        <div>
            <h3 class="h6 mb-0">Dependency Outline</h3>
            <p class="text-muted small mb-0">Microservicelet → routes → controllers → vendor libraries.</p>
        </div>
        <a href="<?php echo $radAdminUrl; ?>/techdocs/graph" class="btn btn-outline-secondary btn-sm"><i class="bi bi-diagram-3 me-1"></i>Graph JSON</a>
    </div>
    <div class="card-body">
        <?php
        $graph = $this->runData['data']['graph'] ?? [];
        if (empty($graph)) { ?>
            <p class="text-muted mb-0">No dependencies to display.</p>
        <?php } else { ?>
            <div class="list-group list-group-flush">
                <?php foreach ($graph as $entry) {
                    $ms = $entry['ms'] ?? [];
                    $msTitle = $ms['s_name'] ?? 'Microservice';
                    $routesForMs = $entry['routes'] ?? [];
                    $controllersForMs = $entry['controllers'] ?? [];
                    $vendorsForMs = $entry['vendors'] ?? [];
                ?>
                <div class="list-group-item">
                    <div class="fw-semibold mb-2"><?php echo htmlspecialchars($msTitle); ?></div>
                    <div class="small text-muted mb-1">Routes → Controllers → Vendors</div>
                    <div class="d-flex flex-wrap gap-2 align-items-start">
                        <div>
                            <div class="text-uppercase text-muted small">Routes</div>
                            <?php if (empty($routesForMs)) { ?>
                                <div class="text-muted small">—</div>
                            <?php } else { ?>
                                <?php foreach ($routesForMs as $route) { ?>
                                    <div class="badge bg-light text-dark me-1 mb-1"><?php echo htmlspecialchars($route['s_name'] ?? ''); ?></div>
                                <?php } ?>
                            <?php } ?>
                        </div>
                        <div>
                            <div class="text-uppercase text-muted small">Controllers</div>
                            <?php if (empty($controllersForMs)) { ?>
                                <div class="text-muted small">—</div>
                            <?php } else { ?>
                                <?php foreach ($controllersForMs as $ctrl) { ?>
                                    <div class="badge bg-primary text-white me-1 mb-1"><?php echo htmlspecialchars($ctrl['s_name'] ?? ''); ?></div>
                                <?php } ?>
                            <?php } ?>
                        </div>
                        <div>
                            <div class="text-uppercase text-muted small">Vendors</div>
                            <?php if (empty($vendorsForMs)) { ?>
                                <div class="text-muted small">—</div>
                            <?php } else { ?>
                                <?php foreach ($vendorsForMs as $vendor) { ?>
                                    <div class="badge bg-secondary text-white me-1 mb-1"><?php echo htmlspecialchars($vendor['s_title'] ?? $vendor['s_handle'] ?? ''); ?></div>
                                <?php } ?>
                            <?php } ?>
                        </div>
                    </div>
                </div>
                <?php } ?>
            </div>
        <?php } ?>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-lg-7">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <div>
                    <h3 class="h6 mb-0">Routes</h3>
                    <p class="text-muted small mb-0">Per microservicelet. Includes scope, degree, and role bindings.</p>
                </div>
                <a href="<?php echo $radAdminUrl; ?>/microservice/view" class="btn btn-outline-secondary btn-sm">View Routes</a>
            </div>
            <div class="card-body">
                <?php if (empty($routes)) { ?>
                    <p class="text-muted mb-0">No routes configured.</p>
                <?php } else { ?>
                    <div class="table-responsive">
                        <table class="table table-sm align-middle">
                            <thead class="small text-muted">
                                <tr>
                                    <th>Route</th>
                                    <th>Microservicelet</th>
                                    <th>Scope</th>
                                    <th>Roles</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($routes as $route) {
                                    $bindings = $bindingsIndex['route'][$route['id']] ?? [];
                                ?>
                                <tr>
                                    <td>
                                        <div class="fw-semibold text-monospace"><?php echo htmlspecialchars($route['s_name'] ?? ''); ?></div>
                                        <div class="text-muted small"><?php echo htmlspecialchars($route['s_description'] ?? ''); ?></div>
                                    </td>
                                    <td class="text-muted small"><?php echo htmlspecialchars($msMap[$route['s_ms_id']]['s_name'] ?? ''); ?></td>
                                    <td class="text-muted small text-uppercase"><?php echo htmlspecialchars($route['s_entity_scope'] ?? ''); ?></td>
                                    <td class="text-muted small">
                                        <?php if (empty($bindings)) { ?>
                                            —
                                        <?php } else { ?>
                                            <?php foreach ($bindings as $bind) {
                                                $roleName = $bind['role']['s_role_name'] ?? ($roleMap[$bind['s_role_id']] ?? 'Role');
                                            ?>
                                                <span class="badge bg-light text-dark me-1 mb-1"><?php echo htmlspecialchars($roleName); ?></span>
                                            <?php } ?>
                                        <?php } ?>
                                    </td>
                                </tr>
                                <?php } ?>
                            </tbody>
                        </table>
                    </div>
                <?php } ?>
            </div>
        </div>
    </div>
    <div class="col-lg-5">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <div>
                    <h3 class="h6 mb-0">Controllers</h3>
                    <p class="text-muted small mb-0">Business (BL) and Data (DM) controllers grouped by microservicelet.</p>
                </div>
                <a href="<?php echo $radAdminUrl; ?>/microservice/view" class="btn btn-outline-secondary btn-sm">Manage</a>
            </div>
            <div class="card-body">
                <?php if (empty($controllers)) { ?>
                    <p class="text-muted mb-0">No controllers defined.</p>
                <?php } else { ?>
                    <div class="list-group list-group-flush">
                        <?php foreach ($controllers as $controller) { ?>
                            <div class="list-group-item">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <div class="fw-semibold"><?php echo htmlspecialchars($controller['s_name'] ?? ''); ?></div>
                                        <div class="text-muted small"><?php echo htmlspecialchars($controller['s_description'] ?? ''); ?></div>
                                        <div class="text-muted small"><?php echo htmlspecialchars($msMap[$controller['s_ms_id']]['s_name'] ?? ''); ?></div>
                                    </div>
                                    <span class="badge bg-<?php echo ($controller['s_type'] ?? '') === 'DM' ? 'info' : 'primary'; ?>">
                                        <?php echo ($controller['s_type'] ?? '') === 'DM' ? 'Data' : 'Business'; ?>
                                    </span>
                                </div>
                            </div>
                        <?php } ?>
                    </div>
                <?php } ?>
            </div>
        </div>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-lg-6">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <div>
                    <h3 class="h6 mb-0">Navigation</h3>
                    <p class="text-muted small mb-0">Nav sets and their items; useful to see how UI maps to routes/controllers.</p>
                </div>
                <a href="<?php echo $radAdminUrl; ?>/nav/view" class="btn btn-outline-secondary btn-sm">Nav Studio</a>
            </div>
            <div class="card-body">
                <?php if (empty($navsets)) { ?>
                    <p class="text-muted mb-0">No navigation sets defined.</p>
                <?php } else { ?>
                    <div class="accordion" id="navAccordion">
                        <?php $idx = 0; foreach ($navsets as $navset) {
                            $items = $navitemsBySet[$navset['id']] ?? [];
                            $idx++;
                        ?>
                        <div class="accordion-item">
                            <h2 class="accordion-header" id="heading-<?php echo $idx; ?>">
                                <button class="accordion-button <?php echo $idx > 1 ? 'collapsed' : ''; ?>" type="button" data-bs-toggle="collapse" data-bs-target="#collapse-<?php echo $idx; ?>">
                                    <span class="me-2 fw-semibold"><?php echo htmlspecialchars($navset['s_name'] ?? ''); ?></span>
                                    <span class="badge bg-light text-dark"><?php echo count($items); ?> item(s)</span>
                                </button>
                            </h2>
                            <div id="collapse-<?php echo $idx; ?>" class="accordion-collapse collapse <?php echo $idx === 1 ? 'show' : ''; ?>" data-bs-parent="#navAccordion">
                                <div class="accordion-body">
                                    <?php if (empty($items)) { ?>
                                        <p class="text-muted small mb-0">No items in this set.</p>
                                    <?php } else { ?>
                                        <ul class="list-unstyled mb-0">
                                            <?php foreach ($items as $item) { ?>
                                                <li class="mb-1">
                                                    <span class="fw-semibold"><?php echo htmlspecialchars($item['s_title'] ?? ''); ?></span>
                                                    <?php if (!empty($item['s_route_name'])) { ?>
                                                        <span class="text-muted small ms-1">→ <?php echo htmlspecialchars($item['s_route_name']); ?></span>
                                                    <?php } ?>
                                                </li>
                                            <?php } ?>
                                        </ul>
                                    <?php } ?>
                                </div>
                            </div>
                        </div>
                        <?php } ?>
                    </div>
                <?php } ?>
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <div>
                    <h3 class="h6 mb-0">Templates (Views)</h3>
                    <p class="text-muted small mb-0">Filesystem templates powering the application experience.</p>
                </div>
                <a href="<?php echo $radAdminUrl; ?>/theme/view" class="btn btn-outline-secondary btn-sm">Theme</a>
            </div>
            <div class="card-body">
                <?php if (empty($templates)) { ?>
                    <p class="text-muted mb-0">No templates detected under <code>rad/theme</code>.</p>
                <?php } else { ?>
                    <div class="table-responsive">
                        <table class="table table-sm align-middle">
                            <thead class="small text-muted">
                                <tr>
                                    <th>Template</th>
                                    <th>Modified</th>
                                    <th class="text-end">Size</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($templates as $tpl) { ?>
                                <tr>
                                    <td class="text-monospace small"><?php echo htmlspecialchars($tpl['path']); ?></td>
                                    <td class="text-muted small"><?php echo htmlspecialchars($tpl['modified']); ?></td>
                                    <td class="text-muted small text-end"><?php echo number_format((int)$tpl['size'] / 1024, 1); ?> KB</td>
                                </tr>
                                <?php } ?>
                            </tbody>
                        </table>
                    </div>
                <?php } ?>
            </div>
        </div>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-lg-6">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <div>
                    <h3 class="h6 mb-0">API Endpoints</h3>
                    <p class="text-muted small mb-0">System and application APIs with target and type.</p>
                </div>
                <a href="<?php echo $radAdminUrl; ?>/apiendpoint/view" class="btn btn-outline-secondary btn-sm">API Catalog</a>
            </div>
            <div class="card-body">
                <?php if (empty($apiEndpoints)) { ?>
                    <p class="text-muted mb-0">No API endpoints found.</p>
                <?php } else { ?>
                    <div class="list-group list-group-flush">
                        <?php foreach ($apiEndpoints as $api) { ?>
                            <div class="list-group-item">
                                <div class="fw-semibold"><?php echo htmlspecialchars($api['s_name'] ?? ''); ?></div>
                                <div class="text-muted small text-monospace"><?php echo htmlspecialchars($api['s_slug'] ?? ''); ?></div>
                                <div class="text-muted small"><?php echo htmlspecialchars($api['s_type'] ?? ''); ?> → <?php echo htmlspecialchars($api['s_target'] ?? ''); ?></div>
                            </div>
                        <?php } ?>
                    </div>
                <?php } ?>
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <div>
                    <h3 class="h6 mb-0">Roles &amp; Workspaces</h3>
                    <p class="text-muted small mb-0">Roles, scopes, and workspace coverage.</p>
                </div>
                <a href="<?php echo $radAdminUrl; ?>/user/view" class="btn btn-outline-secondary btn-sm">Manage Roles</a>
            </div>
            <div class="card-body">
                <div class="mb-3">
                    <div class="fw-semibold">Roles</div>
                    <?php if (empty($roles)) { ?>
                        <p class="text-muted small mb-0">No roles defined.</p>
                    <?php } else { ?>
                        <ul class="list-inline mb-0">
                            <?php foreach ($roles as $role) { ?>
                                <li class="list-inline-item mb-1">
                                    <span class="badge bg-light text-dark"><?php echo htmlspecialchars($role['s_role_name'] ?? ''); ?> (<?php echo htmlspecialchars($role['s_scope'] ?? ''); ?>)</span>
                                </li>
                            <?php } ?>
                        </ul>
                    <?php } ?>
                </div>
                <div>
                    <div class="fw-semibold">Workspaces</div>
                    <?php if ($workspaceCount === 0) { ?>
                        <p class="text-muted small mb-0">No workspaces configured.</p>
                    <?php } else { ?>
                        <ul class="list-inline mb-0">
                            <?php foreach ($workspaces as $ws) { ?>
                                <li class="list-inline-item mb-1">
                                    <span class="badge bg-secondary"><?php echo htmlspecialchars($ws['s_name'] ?? ''); ?></span>
                                </li>
                            <?php } ?>
                        </ul>
                    <?php } ?>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-white d-flex justify-content-between align-items-center">
        <div>
            <h3 class="h6 mb-0">Vendor Dependencies</h3>
            <p class="text-muted small mb-0">Libraries (service_type_id = 3) referenced by the application.</p>
        </div>
        <a href="<?php echo $radAdminUrl; ?>/vendor/view" class="btn btn-outline-secondary btn-sm">Vendor Library</a>
    </div>
    <div class="card-body">
        <?php if (empty($vendors)) { ?>
            <p class="text-muted mb-0">No vendor libraries catalogued.</p>
        <?php } else { ?>
            <div class="row g-3">
                <?php foreach ($vendors as $vendor) { ?>
                    <div class="col-lg-4">
                        <div class="border rounded p-3 h-100">
                            <div class="fw-semibold"><?php echo htmlspecialchars($vendor['s_title'] ?? $vendor['s_handle'] ?? ''); ?></div>
                            <div class="text-muted small mb-2"><?php echo htmlspecialchars($vendor['s_summary'] ?? ''); ?></div>
                            <div class="text-muted small">
                                Version: <?php echo htmlspecialchars($vendor['s_version_installed'] ?? $vendor['s_version_available'] ?? 'n/a'); ?><br>
                                Path: <span class="text-monospace"><?php echo htmlspecialchars($vendor['s_install_path'] ?? ''); ?></span>
                            </div>
                        </div>
                    </div>
                <?php } ?>
            </div>
        <?php } ?>
    </div>
</div>
