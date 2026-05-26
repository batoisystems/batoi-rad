<?php
$radAdminAssetsClass = __DIR__ . '/classes/RadAdminAssets.cls.php';
if (!class_exists('\\RadAdmin\\RadAdminAssets', false) && is_file($radAdminAssetsClass)) {
    require_once $radAdminAssetsClass;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="<?php print $this->runData['route']['meta_description'];?>">
    <meta name="author" content="Batoi">
<?php
$requestObj = $this->runData['request'] ?? null;
$sessionToken = (session_status() === PHP_SESSION_ACTIVE && isset($_SESSION['csrf_token']))
    ? $_SESSION['csrf_token']
    : '';
$csrfToken = $requestObj ? ($requestObj->csrf_token ?? $sessionToken) : $sessionToken;
if ($csrfToken !== '') {
    $escapedToken = htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8');
    echo '<meta name="rad-csrf" content="' . $escapedToken . '">';
    echo '<script>window.__RAD_CSRF = ' . json_encode($csrfToken) . ";</script>\n";
}
$radAdminUrl = $this->runData['route']['rad_admin_url'] ?? '';
if ($radAdminUrl === '' && !empty($this->runData['config']['sys']['base_url'])) {
    $radAdminUrl = rtrim($this->runData['config']['sys']['base_url'], '/') . '/rad-admin';
}
$radAdminUrl = rtrim($radAdminUrl, '/');
$this->runData['route']['rad_admin_url'] = $radAdminUrl;
if ($radAdminUrl !== '') {
    echo '<script>window.__RAD_ADMIN_URL = ' . json_encode($radAdminUrl) . ";</script>\n";
}
?>
    <?php
    $pageTitle = trim($this->runData['route']['meta_title'] ?? '');
    $pageHeading = trim($this->runData['route']['h1'] ?? '');
    if ($pageHeading === '') {
        $crumbs = $this->runData['route']['breadcrumb'] ?? [];
        if (!empty($crumbs)) {
            $crumbLabels = array_keys($crumbs);
            $pageHeading = (string)end($crumbLabels);
        }
        if ($pageHeading === '') {
            $section = $this->runData['route']['pathparts'][1] ?? '';
            if ($section !== '') {
                $pageHeading = ucwords(str_replace(['-', '_'], ' ', $section));
            }
        }
        if ($pageHeading === '') {
            $pageHeading = 'RAD Admin';
        }
        $this->runData['route']['h1'] = $pageHeading;
    }
    $hasErrorStatus = isset($this->runData['route']['error_status']) && $this->runData['route']['error_status'] === 'error';
    if ($pageTitle === '' || (!$hasErrorStatus && stripos($pageTitle, 'error ') === 0)) {
        $pageTitle = 'RAD Admin' . ($pageHeading !== '' ? ' | ' . $pageHeading : '');
    }
    ?>
    <title><?php print htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8'); ?></title>
    <link rel="canonical" href="<?php print $this->runData['route']['url'];?>">
    <link rel="shortcut icon" type="image/x-icon" href="https://www.batoi.com/assets/img/favicon-16x16.png" />
    <?php
    $radAssetsUrl = $this->runData['route']['rad_assets_url'] ?? '';
    if ($radAssetsUrl === '' || $radAssetsUrl === '/') {
        $baseUrl = rtrim($this->runData['config']['sys']['base_url'] ?? '', '/');
        if ($baseUrl === '') {
            $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
            $host = $_SERVER['HTTP_HOST'] ?? ($_SERVER['SERVER_NAME'] ?? 'localhost');
            $baseUrl = $scheme . '://' . $host;
        }
        $radAssetsUrl = $baseUrl . '/rad-admin/assets';
        $this->runData['route']['rad_assets_url'] = $radAssetsUrl;
    }
    echo '<link href="'.$radAssetsUrl.'/bootstrap/bootstrap-5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">';
    echo '<link href="'.$radAssetsUrl.'/rad-admin.css" rel="stylesheet">';
    echo \RadAdmin\RadAdminAssets::renderUifHead($this->runData);
    echo \RadAdmin\RadAdminAssets::renderMonacoLoaderConfig($this->runData);
    echo '<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">';

    $cssFile = $this->runData['config']['dir']['admin'].'/ui/'.$this->runData['route']['pathparts'][1].'-'.$this->runData['route']['pathparts'][2].'.css.php';
    if(file_exists($cssFile)){
        include($cssFile);
    }
    ?>
    <style>
        .rad-layout{display:grid;grid-template-columns:240px minmax(0,1fr);gap:1rem;margin:0;width:100%;min-height:calc(100vh - 180px);align-items:start;}
        .rad-sidebar{width:240px;background:#f8f9fa;border-right:1px solid #dee2e6;padding:1.5rem 1rem;transition:width .2s ease,padding .2s ease;position:relative;z-index:800;pointer-events:auto;}
        .rad-sidebar h6{font-size:.75rem;text-transform:uppercase;letter-spacing:.04em;margin-top:1.25rem;color:#6c757d;}
        .rad-sidebar .nav-link{color:#495057;border-radius:.375rem;font-size:.9rem;}
        .rad-sidebar .nav-link.active{background:#0d6efd;color:#fff;}
        .rad-sidebar nav{max-height:calc(100vh - 180px);overflow-y:auto;padding-right:.25rem;}
        .rad-sidebar h6.active-section{color:#0d6efd;}
        .rad-layout main{min-width:0;position:relative;z-index:0;}
        body.sidebar-collapsed .rad-layout{grid-template-columns:0 minmax(0,1fr);}
        body.sidebar-collapsed .rad-sidebar{width:0;padding:0;border:none;overflow:hidden;}
        .top-icon{display:inline-flex;align-items:center;justify-content:center;width:44px;height:44px;border:1px solid #d8dfe7;border-radius:50%;color:#343a40;background:#fff;text-decoration:none;font-size:1.1rem;transition:all .15s ease;}
        .top-icon:hover,.top-icon:focus{background:#0d6efd;color:#fff;border-color:#0d6efd;}
        .top-brand{display:flex;align-items:center;gap:.5rem;border:1px solid #d8dfe7;border-radius:999px;padding:.2rem 1rem;background:#fff;}
        .top-brand img{height:28px;}
        .top-brand span{font-weight:600;color:#1f3c73;font-size=.9rem;}
        .top-avatar{display:inline-flex;align-items:center;justify-content:center;width:44px;height:44px;border:2px solid #00a3b7;border-radius:50%;font-weight:600;color:#2f2f2f;background:#fff;}
        .batoi-intel-dropdown{width:min(360px,calc(100vw - 2rem));padding:1rem;border:1px solid #e9ecef;border-radius:.85rem;}
        .batoi-intel-dropdown textarea{resize:vertical;min-height:92px;}
        .batoi-intel-dropdown .form-text{font-size:.75rem;}
        .batoi-intel-dropdown .btn{white-space:nowrap;}
        .rad-sidebar{z-index:800;}
        @media (max-width:991px){
            .rad-layout{display:block;}
            .rad-sidebar{display:none;}
        }
    </style>
</head>
<body>
<script>
    (function() {
        const storageKey = 'rad_admin.sidebar_collapsed';
        try {
            if (window.localStorage && window.localStorage.getItem(storageKey) === '1') {
                document.body.classList.add('sidebar-collapsed');
            }
        } catch (e) {
            // Ignore storage errors; sidebar still works without persistence.
        }
    })();
</script>
<?php
$navSections = [
    'Dashboard' => [
        ['label' => 'Home', 'icon' => 'bi bi-house-door', 'path' => '/home/view'],
        ['label' => 'All RAD Admin', 'icon' => 'bi bi-grid-3x3-gap', 'path' => '/all/view'],
    ],
    'Build & Code' => [
        ['label' => 'Microservicelets', 'icon' => 'bi bi-boxes', 'path' => '/microservice'],
        ['label' => 'Routes', 'icon' => 'bi bi-signpost-split', 'path' => '/route/viewall'],
        ['label' => 'Business Classes', 'icon' => 'bi bi-cpu', 'path' => '/controller/viewall'],
        ['label' => 'Data Models', 'icon' => 'bi bi-table', 'path' => '/appdata'],
        ['label' => 'Content Blocks', 'icon' => 'bi bi-file-text', 'path' => '/content'],
        ['label' => 'API Endpoints', 'icon' => 'bi bi-plug', 'path' => '/apiendpoint'],
        ['label' => 'Navigation', 'icon' => 'bi bi-diagram-3', 'path' => '/nav'],
        ['label' => 'Data Explorer', 'icon' => 'bi bi-folder-symlink', 'path' => '/dataexplorer'],
        ['label' => 'Theme Templates', 'icon' => 'bi bi-snow2', 'path' => '/theme'],
        ['label' => 'Theme Assets', 'icon' => 'bi bi-front', 'path' => '/uiassets'],
        ['label' => 'UI Templates', 'icon' => 'bi bi-files', 'path' => '/uitpl/view'],
        ['label' => 'Libraries', 'icon' => 'bi bi-puzzle', 'path' => '/vendor'],
        ['label' => 'AI Code Studio', 'icon' => 'bi bi-terminal', 'path' => '/codex/view'],
        ['label' => 'Batoi Intelligence', 'icon' => 'bi bi-stars', 'path' => '/aiassist'],
    ],
    'Workspace & SaaS' => [
        ['label' => 'Workspaces', 'icon' => 'bi bi-buildings', 'path' => '/space'],
        ['label' => 'Memberships', 'icon' => 'bi bi-people-fill', 'path' => '/membership'],
        ['label' => 'Permission Bindings', 'icon' => 'bi bi-key', 'path' => '/permissionbindings'],
    ],
    'IAM & Security' => [
        ['label' => 'Users', 'icon' => 'bi bi-person-lines-fill', 'path' => '/user'],
        ['label' => 'Roles', 'icon' => 'bi bi-shield-lock', 'path' => '/role'],
        ['label' => 'Privilege Matrix', 'icon' => 'bi bi-grid-3x3-gap', 'path' => '/iam/privilegematrix'],
        ['label' => 'System MFA', 'icon' => 'bi bi-shield-lock', 'path' => '/mfa/dashboard'],
        ['label' => 'SSO Provider', 'icon' => 'bi bi-link-45deg', 'path' => '/sso/view'],
        ['label' => 'SSO Server Clients', 'icon' => 'bi bi-diagram-2', 'path' => '/ssoclient/view'],
    ],
    'Observability' => [
        ['label' => 'Telemetry', 'icon' => 'bi bi-broadcast', 'path' => '/telemetry/view'],
        ['label' => 'Access Log', 'icon' => 'bi bi-file-earmark-bar-graph', 'path' => '/accesslog'],
        ['label' => 'Errors Analytics', 'icon' => 'bi bi-binoculars', 'path' => '/errorlog'],
        ['label' => 'SQL Analytics', 'icon' => 'bi bi-database-exclamation', 'path' => '/sqllog'],
        ['label' => 'Notifications', 'icon' => 'bi bi-bell', 'path' => '/notifications/view'],
        ['label' => 'Activity Feed', 'icon' => 'bi bi-clock-history', 'path' => '/activity/view'],
        ['label' => 'Forgot Password Log', 'icon' => 'bi bi-unlock', 'path' => '/governance/forgotpasswordlog'],
        ['label' => 'Find code', 'icon' => 'bi bi-search', 'path' => '/observability/findcode'],
    ],
    'Governance' => [
        ['label' => 'System Health', 'icon' => 'bi bi-heart-pulse', 'path' => '/governance/health'],
        ['label' => 'Stray Routes', 'icon' => 'bi bi-exclamation-triangle', 'path' => '/governance/strayroutes'],
        ['label' => 'Version Explorer', 'icon' => 'bi bi-clock-history', 'path' => '/version'],
        ['label' => 'DevSecOps Report', 'icon' => 'bi bi-shield-check', 'path' => '/devsecops'],
        ['label' => 'Static Code Analysis', 'icon' => 'bi bi-search', 'path' => '/sca'],
        ['label' => 'Changelog', 'icon' => 'bi bi-journal-arrow-down', 'path' => '/governance/changelog'],
        ['label' => 'Code Insights', 'icon' => 'bi bi-graph-up', 'path' => '/governance/insights'],
    ],
    'Deployment & Upgrades' => [
        ['label' => 'Test Plans', 'icon' => 'bi bi-clipboard-check', 'path' => '/testplan/view'],
        ['label' => 'Upgrades', 'icon' => 'bi bi-arrow-repeat', 'path' => '/upgrade'],
        ['label' => 'Queue', 'icon' => 'bi bi-clock-history', 'path' => '/queue/overview'],
    ],
    'Docs & Guides' => [
        ['label' => 'Technical Docs', 'icon' => 'bi bi-journal-richtext', 'path' => '/techdocs'],
        ['label' => 'RAD Dev Guide', 'icon' => 'bi bi-journal-code', 'path' => '/devguide/view'],
    ],
    'Settings' => [
        ['label' => 'IP Access Control', 'icon' => 'bi bi-shield-lock', 'path' => '/ipaccess/view'],
        ['label' => 'Config Parameters', 'icon' => 'bi bi-gear', 'path' => '/config'],
        ['label' => 'Notification Settings', 'icon' => 'bi bi-sliders', 'path' => '/notifications/settings'],
        ['label' => 'Dot Phrases', 'icon' => 'bi bi-three-dots', 'path' => '/dotphrase'],
        ['label' => 'AI Settings', 'icon' => 'bi bi-gear', 'path' => '/aiconfig/view'],
        ['label' => 'RAD Admin Privileges', 'icon' => 'bi bi-shield-check', 'path' => '/privilege/view'],
    ],
];
$currentEntityId = (int)($this->runData['entity']['id'] ?? ($this->runData['entity']['entity_id'] ?? 0));
$privService = new \Core\Sys\PrivilegeService($this->runData['config'] ?? [], $this->runData['entity'] ?? []);
$role = $privService->role();
if ($role !== 'system_admin') {
    $navSections['Settings'] = array_values(array_filter($navSections['Settings'], function ($item) {
        return $item['path'] !== '/ipaccess/view';
    }));
    $navSections['Settings'] = array_values(array_filter($navSections['Settings'], function ($item) {
        return $item['path'] !== '/privilege/view';
    }));
    $navSections['Settings'] = array_values(array_filter($navSections['Settings'], function ($item) {
        return $item['path'] !== '/aiconfig/view';
    }));
    $navSections['Settings'] = array_values(array_filter($navSections['Settings'], function ($item) {
        return $item['path'] !== '/notifications/settings';
    }));
}
$currentPath = '/' . ($this->runData['route']['pathparts'][1] ?? 'home');
if (!empty($this->runData['route']['pathparts'][2])) {
    $currentPath .= '/' . $this->runData['route']['pathparts'][2];
}
$activeMatch = '';
foreach ($navSections as $items) {
    foreach ($items as $item) {
        $path = $item['path'];
        $match = ($currentPath === $path) || (strpos($currentPath, $path . '/') === 0);
        if ($match && strlen($path) > strlen($activeMatch)) {
            $activeMatch = $path;
        }
    }
}
$activeSections = [];
foreach ($navSections as $section => $items) {
    foreach ($items as $item) {
        $path = $item['path'];
        $match = ($currentPath === $path) || (strpos($currentPath, $path . '/') === 0);
        if ($match) {
            $activeSections[$section] = true;
            break;
        }
    }
}
$userName = $this->runData['entity']['fullname'] ?? ($this->runData['entity']['username'] ?? 'User');
$userInitial = strtoupper(substr($userName, 0, 1));
$notificationBadge = (int)($this->runData['nav']['notifications_unread'] ?? 0);
$recentNotifications = $this->runData['nav']['notifications_recent'] ?? [];
$batoiIntelUrl = $this->runData['route']['rad_admin_url'] . '/aiassist';
?>
    <div class="d-flex flex-column min-vh-100">
        <div class="container-fluid px-0 flex-grow-1">
            <header class="py-2 border-bottom bg-white">
                <div class="container-fluid d-flex justify-content-between align-items-center">
                    <div class="d-flex align-items-center gap-3">
                        <button class="btn btn-outline-secondary btn-sm d-none d-lg-inline-flex" id="sidebarToggle" type="button" title="Toggle navigation">
                            <i class="bi bi-layout-sidebar"></i>
                        </button>
                        <img src="<?php print $this->runData['route']['rad_assets_url'];?>/img/batoi-rad-framework-logo.svg" alt="Batoi RAD Framework" height="32">
                        <strong>RAD Admin</strong>
                    </div>
                    <div class="d-flex align-items-center gap-3">
                        <div class="d-none d-lg-flex align-items-center gap-2">
                            <a href="<?php print $this->runData['config']['sys']['base_url'];?>/rad-admin/home/view" class="top-icon" title="Home Dashboard">
                                <i class="bi bi-house"></i>
                            </a>
                            <a href="<?php print $this->runData['config']['sys']['base_url'];?>/rad-admin/all/view" class="top-icon" title="All RAD Admin">
                                <i class="bi bi-grid-3x3-gap"></i>
                            </a>
                            <div class="dropdown">
                                <a href="#" class="top-icon" id="batoiIntelDropdownToggle" data-bs-toggle="dropdown" data-bs-auto-close="outside" aria-expanded="false" title="Batoi Intelligence">
                                    <i class="bi bi-stars"></i>
                                </a>
                                <div class="dropdown-menu dropdown-menu-end shadow-sm batoi-intel-dropdown" aria-labelledby="batoiIntelDropdownToggle">
                                    <div class="fw-semibold mb-1">Batoi Intelligence</div>
                                    <div class="small text-muted mb-3">Open the dedicated assistant with the current page context and an optional prompt.</div>
                                    <div class="mb-2">
                                        <label for="batoiIntelPromptNav" class="form-label small fw-semibold mb-1">Prompt</label>
                                        <textarea class="form-control form-control-sm" id="batoiIntelPromptNav" placeholder="Ask Batoi Intelligence..."></textarea>
                                        <div class="form-text">The current page title and URL will be passed into the dedicated workspace.</div>
                                    </div>
                                    <div class="d-flex gap-2">
                                        <button type="button" class="btn btn-primary btn-sm flex-grow-1" id="batoiIntelOpenBtn" data-open-url="<?php echo htmlspecialchars($batoiIntelUrl, ENT_QUOTES, 'UTF-8'); ?>">
                                            <i class="bi bi-arrow-up-right-square me-1"></i>Open Batoi Intelligence
                                        </button>
                                        <a href="<?php echo htmlspecialchars($batoiIntelUrl, ENT_QUOTES, 'UTF-8'); ?>" class="btn btn-outline-secondary btn-sm">
                                            <i class="bi bi-box-arrow-up-right"></i>
                                        </a>
                                    </div>
                                </div>
                            </div>
                            <a href="https://www.batoi.com/support/docs/rad-framework" class="top-icon" title="Help" target="_blank">
                                <i class="bi bi-question-circle"></i>
                            </a>
                            <a href="<?php print $this->runData['route']['rad_admin_url'];?>/notifications/view" class="top-icon position-relative" title="Notifications">
                                <i class="bi bi-bell"></i>
                                <?php if ($notificationBadge > 0) { ?>
                                    <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger text-white"><?php echo $notificationBadge > 99 ? '99+' : $notificationBadge; ?></span>
                                <?php } ?>
                            </a>
                        </div>
                        <div class="dropdown text-end d-lg-none">
                            <a href="#" class="d-block link-body-emphasis text-decoration-none dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
                                <i class="bi bi-list" style="font-size:1.5rem;"></i>
                            </a>
                            <ul class="dropdown-menu text-small shadow">
                                <li><a class="dropdown-item small" href="<?php echo $this->runData['route']['rad_admin_url']; ?>/home/view"><i class="bi bi-house-door menu-icon"></i> Home</a></li>
                                <li><a class="dropdown-item small" href="<?php echo $this->runData['route']['rad_admin_url']; ?>/all/view"><i class="bi bi-grid-3x3-gap menu-icon"></i> All RAD Admin</a></li>
                                <li><hr class="dropdown-divider"></li>
                                <?php foreach ($navSections as $group => $items) { ?>
                                    <li><h6 class="dropdown-header text-uppercase small"><?php echo $group; ?></h6></li>
                                    <?php foreach ($items as $item) { ?>
                                <li><a class="dropdown-item small" href="<?php echo $this->runData['route']['rad_admin_url'] . $item['path']; ?>"><?php echo $item['label']; ?></a></li>
                                    <?php } ?>
                                    <li><hr class="dropdown-divider"></li>
                                <?php } ?>
                                <li><a class="dropdown-item small" href="<?php echo htmlspecialchars($batoiIntelUrl, ENT_QUOTES, 'UTF-8'); ?>"><i class="bi bi-stars menu-icon"></i> Batoi Intelligence</a></li>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item small" href="<?php print $this->runData['route']['rad_admin_url'].'/profile/overview';?>"><i class="bi bi-person-circle menu-icon"></i> My Account</a></li>
                                <li><a class="dropdown-item small" href="<?php print $this->runData['route']['rad_admin_url'].'/profile/sessions';?>"><i class="bi bi-clock-history menu-icon"></i> Sessions</a></li>
                                <li><a class="dropdown-item small" href="<?php print $this->runData['route']['rad_admin_url'].'/profile/preferences';?>"><i class="bi bi-sliders menu-icon"></i> Preferences</a></li>
                                <li><a class="dropdown-item small" href="<?php print $this->runData['route']['rad_admin_url'].'/profile/notifications';?>"><i class="bi bi-bell menu-icon"></i> Notification Preferences</a></li>
                                <li><a class="dropdown-item small" href="<?php print $this->runData['route']['rad_admin_url'].'/profile/changepwd';?>"><i class="bi bi-key menu-icon"></i> Change Password</a></li>
                                <li><a class="dropdown-item small" href="<?php print $this->runData['route']['rad_admin_url'].'/profile/mfa';?>"><i class="bi bi-shield-lock menu-icon"></i> MFA Settings</a></li>
                                <li><a class="dropdown-item small" href="<?php print $this->runData['config']['sys']['base_url'].'/login/logout';?>"><i class="bi bi-box-arrow-right menu-icon"></i> Logout</a></li>
                            </ul>
                        </div>
                        <div class="dropdown text-end d-none d-lg-block">
                            <a href="#" class="d-flex align-items-center text-decoration-none dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
                                <span class="top-avatar"><?php echo $userInitial; ?></span>
                            </a>
                            <ul class="dropdown-menu text-small shadow">
                                <li class="dropdown-item small text-center fw-semibold"><?php print $this->runData['entity']['fullname'] ?? 'User';?></li>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item small" href="<?php print $this->runData['route']['rad_admin_url'].'/profile/overview';?>"><i class="bi bi-person-circle menu-icon"></i> My Account</a></li>
                                <li><a class="dropdown-item small" href="<?php print $this->runData['route']['rad_admin_url'].'/profile/sessions';?>"><i class="bi bi-clock-history menu-icon"></i> Sessions</a></li>
                                <li><a class="dropdown-item small" href="<?php print $this->runData['route']['rad_admin_url'].'/profile/preferences';?>"><i class="bi bi-sliders menu-icon"></i> Preferences</a></li>
                                <li><a class="dropdown-item small" href="<?php print $this->runData['route']['rad_admin_url'].'/profile/notifications';?>"><i class="bi bi-bell menu-icon"></i> Notification Preferences</a></li>
                                <li><a class="dropdown-item small" href="<?php print $this->runData['route']['rad_admin_url'].'/profile/changepwd';?>"><i class="bi bi-key menu-icon"></i> Change Password</a></li>
                                <li><a class="dropdown-item small" href="<?php print $this->runData['route']['rad_admin_url'].'/profile/mfa';?>"><i class="bi bi-shield-lock menu-icon"></i> MFA Settings</a></li>
                                <li><a class="dropdown-item small" href="<?php print $this->runData['config']['sys']['base_url'].'/login/logout';?>"><i class="bi bi-box-arrow-right menu-icon"></i> Logout</a></li>
                            </ul>
                        </div>
                    </div>
                </div>
            </header>
            <div class="container-fluid rad-layout">
                <aside class="rad-sidebar d-none d-lg-block">
                    <?php foreach ($navSections as $group => $items) { ?>
                        <h6 class="<?php echo !empty($activeSections[$group]) ? 'active-section' : ''; ?>"><?php echo $group; ?></h6>
                        <nav class="nav flex-column flex-grow-1">
                            <?php foreach ($items as $item) {
                                $isActive = ($item['path'] === $activeMatch);
                            ?>
                                <a class="nav-link <?php echo $isActive ? 'active' : ''; ?>" href="<?php echo $this->runData['route']['rad_admin_url'] . $item['path']; ?>">
                                    <i class="<?php echo $item['icon']; ?> me-2"></i><?php echo $item['label']; ?>
                                </a>
                            <?php } ?>
                        </nav>
                    <?php } ?>
                </aside>
                <main class="flex-grow-1 px-3 py-4">
                    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
                        <div>
                            <nav aria-label="breadcrumb" class="mb-1">
                                <ol class="breadcrumb mb-0 small">
                                    <li class="breadcrumb-item"><a href="<?php echo $this->runData['route']['rad_admin_url']; ?>/home/view">Home</a></li>
                                    <?php
                                    $crumbs = $this->runData['route']['breadcrumb'] ?? [];
                                    if (!empty($crumbs)) {
                                        foreach ($crumbs as $label => $url) {
                                            if (strcasecmp($label, 'home') === 0) {
                                                continue;
                                            }
                                            if ($url) {
                                                echo '<li class="breadcrumb-item"><a href="'.htmlspecialchars($url).'">'.htmlspecialchars($label).'</a></li>';
                                            } else {
                                                echo '<li class="breadcrumb-item active" aria-current="page">'.htmlspecialchars($label).'</li>';
                                            }
                                        }
                                    } else {
                                        $h1 = $this->runData['route']['h1'] ?? '';
                                        if ($h1) {
                                            echo '<li class="breadcrumb-item active" aria-current="page">'.htmlspecialchars($h1).'</li>';
                                        }
                                    }
                                    ?>
                                </ol>
                            </nav>
                            <h1 class="h4 mb-0"><?php echo htmlspecialchars($this->runData['route']['h1'] ?? ''); ?></h1>
                            <?php if (!empty($this->runData['route']['subheading'])) { ?>
                                <div class="text-muted small"><?php echo htmlspecialchars($this->runData['route']['subheading']); ?></div>
                            <?php } ?>
                        </div>
                        <div class="d-flex gap-2">
                            <?php if (!empty($this->runData['route']['primary_action'])) { ?>
                                <a class="btn btn-primary btn-sm" href="<?php echo htmlspecialchars($this->runData['route']['primary_action']['href']); ?>">
                                    <?php echo htmlspecialchars($this->runData['route']['primary_action']['label']); ?>
                                </a>
                            <?php } ?>
                        </div>
                    </div>

                    <?php if ( isset($this->runData['route']['alert']) && ($this->runData['route']['alert'] != '') ): ?>
                        <div class="alert alert-<?php echo $this->runData['route']['alert'];?> d-flex align-items-start py-2 px-3 mb-3">
                            <div class="icon me-2 pt-1">
                                <?php
                                switch ($this->runData['route']['alert']) {
                                    case 'success':
                                        echo '<i class="bi bi-check-circle-fill" aria-hidden="true"></i>';
                                        break;
                                    case 'warning':
                                        echo '<i class="bi bi-exclamation-triangle-fill" aria-hidden="true"></i>';
                                        break;
                                    case 'danger':
                                        echo '<i class="bi bi-exclamation-circle-fill" aria-hidden="true"></i>';
                                        break;
                                    default:
                                        echo '<i class="bi bi-info-circle-fill" aria-hidden="true"></i>';
                                        break;
                                }
                                ?>
                            </div>
                            <div class="message flex-grow-1">
                                <?php if (!empty($this->runData['route']['alert_title'])) { ?>
                                    <div class="fw-semibold mb-1"><?php echo htmlspecialchars($this->runData['route']['alert_title']); ?></div>
                                <?php } ?>
                                <div class="small mb-0"><?php echo htmlspecialchars($this->runData['route']['alert_message'] ?? ''); ?></div>
                            </div>
                        </div>
                    <?php endif; ?>

                    <?php
                    $pagePart = $this->runData['route']['pagepart'] ?? '';
                    if ($pagePart !== '') {
                        $pagePartWithPath = $this->runData['config']['dir']['admin'].'/ui/'.$pagePart.'.html.php';
                        if(file_exists($pagePartWithPath)){
                            include($pagePartWithPath);
                        }
                    }
                    ?>
                    <?php if (!empty($this->runData['route']['debug_block'])): ?>
                        <?php
                        $debugBlock = $this->runData['route']['debug_block'];
                        $payload = $debugBlock['payload'] ?? [];
                        $stats = $payload['checkpoint_stats'] ?? [];
                        unset($payload['checkpoint_stats']);
                        $debugJson = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
                        ?>
                        <div class="card border-warning mb-4">
                            <div class="card-header bg-warning-subtle d-flex justify-content-between align-items-center">
                                <div>
                                    <span class="fw-semibold">Debug (dev_debug_flag=Y)</span>
                                    <span class="text-muted small ms-2"><?php echo htmlspecialchars($debugBlock['generated_at'] ?? ''); ?></span>
                                </div>
                                <span class="badge bg-warning text-dark">debug_block=1</span>
                            </div>
                            <div class="card-body">
                                <div class="text-muted small mb-2">Request: <?php echo htmlspecialchars($debugBlock['request_uri'] ?? ''); ?></div>
                                <?php if (!empty($stats)): ?>
                                    <div class="table-responsive mb-3">
                                        <table class="table table-sm table-striped">
                                            <thead>
                                                <tr>
                                                    <th>Checkpoint</th>
                                                    <th>Δ (ms)</th>
                                                    <th>Elapsed (ms)</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($stats as $row): ?>
                                                    <tr>
                                                        <td><?php echo htmlspecialchars($row['label'] ?? ''); ?></td>
                                                        <td><?php echo htmlspecialchars($row['delta_ms'] ?? ''); ?></td>
                                                        <td><?php echo htmlspecialchars($row['elapsed_ms'] ?? ''); ?></td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php endif; ?>
                                <pre class="mb-0 small bg-light p-3 border rounded"><?php echo htmlspecialchars($debugJson ?? '', ENT_QUOTES, 'UTF-8'); ?></pre>
                            </div>
                        </div>
                    <?php endif; ?>
                </main>
            </div>
        </div>

        <footer class="d-flex flex-wrap justify-content-between align-items-center py-3 mb-4 px-3 border-top">
            <div class="col-md-4 d-flex align-items-center">
                <a href="/" class="mb-3 me-2 mb-md-0 text-body-secondary text-decoration-none lh-1">
                    <img src="<?php print $this->runData['route']['rad_assets_url'];?>/img/batoi-rad-framework-logo.svg" height="24">
                </a>
                <span class="mb-3 mb-md-0 text-body-secondary small">&copy; <?php echo date('Y');?> <a class="text-primary text-decoration-none" href="https://www.batoi.com/framework/" target="_blank">Batoi</a></span>
            </div>
            <div class="text-muted small ms-auto me-3 flex-grow-1 text-end">
                <?php
                $clientIp = $this->runData['route']['client_ip'] ?? '';
                $clientLabel = $clientIp !== '' ? htmlspecialchars($clientIp, ENT_QUOTES, 'UTF-8') : 'Unavailable';
                ?>
                You are accessing from trusted IP <?php echo $clientLabel; ?>
            </div>
            <ul class="nav col-md-4 justify-content-end list-unstyled d-flex">
                <li class="ms-3"><a class="text-primary" href="https://linkedin.com/company/batoi" target="_blank"><i class="bi bi-linkedin"></i></a></li>
                <li class="ms-3"><a class="text-dark" href="https://twitter.com/batoisystems" target="_blank"><i class="bi bi-twitter-x"></i></a></li>
                <li class="ms-3"><a class="text-danger" href="https://mastodon.social/@batoisystems" target="_blank"><i class="bi bi-mastodon"></i></a></li>
            </ul>
        </footer>
    </div>

    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <?php
    echo '<script src="'.$this->runData['route']['rad_assets_url'].'/bootstrap/bootstrap-5.3.0/dist/js/bootstrap.bundle.min.js"></script>';
    echo \RadAdmin\RadAdminAssets::renderUifBody($this->runData);
    $jsFile = $this->runData['config']['dir']['admin'].'/ui/'.$this->runData['route']['pathparts'][1].'-'.$this->runData['route']['pathparts'][2].'.js.php';
    if(file_exists($jsFile)){
        include($jsFile);
    }
    ?>
    <script>
        (function() {
            const toggle = document.getElementById('sidebarToggle');
            if (!toggle) { return; }
            const storageKey = 'rad_admin.sidebar_collapsed';
            toggle.addEventListener('click', function () {
                const isCollapsed = document.body.classList.toggle('sidebar-collapsed');
                try {
                    if (window.localStorage) {
                        window.localStorage.setItem(storageKey, isCollapsed ? '1' : '0');
                    }
                } catch (e) {
                    // Ignore storage errors; keep runtime toggle behavior.
                }
            });
        })();
    </script>
    <script>
        (function() {
            const openBtn = document.getElementById('batoiIntelOpenBtn');
            const promptEl = document.getElementById('batoiIntelPromptNav');
            if (!openBtn) { return; }
            openBtn.addEventListener('click', function () {
                const target = openBtn.getAttribute('data-open-url');
                if (!target) { return; }
                const url = new URL(target, window.location.origin);
                const prompt = promptEl ? promptEl.value.trim() : '';
                const heading = document.querySelector('main h1');
                if (prompt !== '') {
                    url.searchParams.set('prompt', prompt);
                }
                if (heading && heading.textContent.trim() !== '') {
                    url.searchParams.set('context_title', heading.textContent.trim());
                } else if (document.title) {
                    url.searchParams.set('context_title', document.title);
                }
                url.searchParams.set('context_url', window.location.href);
                window.location.href = url.toString();
            });
        })();
    </script>
    <script>
        (function() {
            document.addEventListener('show.bs.modal', function () {
                document.body.classList.add('no-backdrop');
            });
            document.addEventListener('hidden.bs.modal', function () {
                if (!document.querySelector('.modal.show')) {
                    document.body.classList.remove('no-backdrop');
                }
            });
        })();
    </script>
</body>
</html>
