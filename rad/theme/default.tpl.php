<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="<?php print $this->runData['route']['meta_description'];?>">
    <meta name="author" content="<?php print $this->runData['config']['sys']['author'];?>">
    
    <title>
        <?php print $this->runData['route']['meta_title'];?>
    </title>
    <link rel="canonical" href="<?php print $this->runData['route']['url'];?>">
    <?php
    echo '<link href="'.$this->runData['route']['assets_url'].'/vendor/bootstrap/bootstrap-5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">';
    echo '<link href="'.$this->runData['route']['assets_url'].'/css/app.css" rel="stylesheet">';
    echo '<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">';
    echo '<link rel="preconnect" href="https://fonts.googleapis.com">';
    echo '<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>';
    echo '<link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700&family=Fraunces:wght@600;700&display=swap" rel="stylesheet">';
    $this->includePart('pre');
    ?>
    <style>
        :root {
            --app-bg: #f6f7fb;
            --app-surface: #ffffff;
            --app-ink: #0b1b2b;
            --app-muted: #5f6b7a;
            --app-accent: #0f6aa9;
            --app-accent-soft: #e6f1fb;
            --app-border: #e3e7ee;
            --app-shadow: 0 10px 30px rgba(12, 34, 56, 0.08);
            --app-radius: 14px;
        }

        body {
            background: var(--app-bg);
            color: var(--app-ink);
            font-family: "Manrope", "Segoe UI", sans-serif;
        }

        h1, h2, h3, h4, h5 {
            font-family: "Fraunces", "Manrope", serif;
        }

        .app-shell {
            min-height: 100vh;
            padding-top: var(--app-topbar-height, 88px);
        }

        .app-topbar {
            background: var(--app-surface);
            border-bottom: 1px solid var(--app-border);
            box-shadow: 0 2px 14px rgba(13, 31, 49, 0.04);
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 1050;
        }

        .brand-block {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 8px 12px;
            border-radius: 999px;
            background: var(--app-accent-soft);
        }

        .brand-block img {
            height: 32px;
            width: 32px;
        }

        .page-title {
            font-size: 1.35rem;
            font-weight: 700;
            margin: 0;
        }

        .top-actions .bi {
            font-size: 1.2rem;
            color: var(--app-ink);
        }

        .surface-card {
            background: var(--app-surface);
            border: 1px solid var(--app-border);
            border-radius: var(--app-radius);
            box-shadow: var(--app-shadow);
        }

        .page-shell {
            padding-top: 16px;
        }

        .profile-page {
            padding-top: 8px;
            max-width: 1100px;
            margin: 0 auto;
        }

        .profile-nav {
            margin-top: 8px;
        }

        .profile-tabs {
            border-bottom: 1px solid var(--app-border);
        }

        .profile-tabs .nav-link {
            border: 0;
            border-bottom: 2px solid transparent;
            color: var(--app-muted);
            font-weight: 600;
        }

        .profile-tabs .nav-link.active {
            color: var(--app-ink);
            border-bottom-color: var(--app-accent);
            background: transparent;
        }

        .profile-nav-card {
            background: var(--app-surface);
            border: 1px solid var(--app-border);
            border-radius: 12px;
            box-shadow: 0 6px 18px rgba(12, 34, 56, 0.06);
        }

        .side-kv .list-group-item {
            border: 0;
            border-bottom: 1px solid var(--app-border);
            padding: 0.65rem 0.9rem;
            font-size: 0.92rem;
        }

        .side-kv .list-group-item:last-child {
            border-bottom: 0;
        }

        .side-kv .label {
            color: var(--app-muted);
            font-weight: 600;
        }

        .compact-navbar .dropdown-menu {
            border-radius: 12px;
            border-color: var(--app-border);
            box-shadow: var(--app-shadow);
        }

        .compact-navbar .dropdown-menu .dropdown-item {
            display: flex;
            align-items: center;
            gap: 10px;
            white-space: nowrap;
        }

        .compact-navbar .dropdown-menu .menu-icon {
            font-size: 1.05rem;
            color: var(--app-ink);
        }
    </style>
</head>

<body>
    <!-- Page Content -->
    <div class="d-flex flex-column app-shell">
        <div class="container-fluid px-0 flex-grow-1">

            <!-- Navbar -->
            <header class="app-topbar py-2 mb-4">
                <div class="container-fluid d-flex flex-wrap align-items-center gap-3">
                    <div class="dropdown compact-navbar">
                        <a href="<?php print $this->runData['config']['sys']['base_url'].'/app/1';?>"
                            class="brand-block link-body-emphasis text-decoration-none dropdown-toggle"
                            data-bs-toggle="dropdown" aria-expanded="false">
                            <img src="<?php print $this->runData['route']['assets_url'];?>/img/logo-icon.svg" alt="<?php print $this->runData['config']['sys']['project_title'];?>">
                            <span class="fw-semibold"><?php print $this->runData['config']['sys']['project_title'];?></span>
                        </a>
                        <ul class="dropdown-menu text-small shadow">
                            <?php if (isset($this->runData['nav'][0])) { 
                    foreach ($this->runData['nav'][0] as $nav) {
                        // Check if one of the comma-separated roles values matches with entity role
                        if (in_array($this->runData['entity']['role_id'], explode(',', $nav['roles']))) { ?>
                            <li><a class="dropdown-item" href="<?php print $nav['href'];?>">
                                    <?php print $nav['menu'];?>
                                </a></li>
                            <?php } } } ?>
                            <li>
                                <hr class="dropdown-divider">
                            </li>
                            <?php if (isset($this->runData['nav'][1])) { 
                    foreach ($this->runData['nav'][1] as $nav) {
                        // Check if one of the comma-separated roles values matches with entity role
                        if (in_array($this->runData['entity']['role_id'], explode(',', $nav['roles']))) { ?>
                            <li><a class="dropdown-item" href="<?php print $nav['href'];?>">
                                    <?php print $nav['menu'];?>
                                </a></li>
                            <?php } } } ?>
                        </ul>
                    </div>

                    <div class="flex-grow-1">
                        <h1 class="page-title"><?php print $this->runData['route']['h1'];?></h1>
                    </div>

                    <div class="d-flex align-items-center top-actions ms-auto">
                        <?php if (isset($this->runData['route']['backlink'])) { ?>
                        <a href="<?php print $this->runData['route']['backlink'];?>"
                            class="d-block link-body-emphasis text-decoration-none">
                            <i class="bi-chevron-left me-3"></i>
                        </a>
                        <?php } ?>
                        <a href="<?php print $this->runData['config']['sys']['base_url'].'/app/1';?>"
                            class="d-block link-body-emphasis text-decoration-none">
                            <i class="bi-boxes me-3"></i>
                        </a>
                        <a href="#" class="d-block link-body-emphasis text-decoration-none">
                            <i class="bi-bell me-3"></i>
                        </a>
                        <div class="flex-shrink-0 dropdown compact-navbar">
                            <a href="#" class="d-block link-body-emphasis text-decoration-none dropdown-toggle"
                                data-bs-toggle="dropdown" aria-expanded="false">
                                <i class="bi-person-circle" style="font-size: 1.6rem;" class="rounded-circle"></i>
                            </a>
                            <ul class="dropdown-menu text-small shadow">
                                <li style="text-align: center;font-weight:bold;">
                                    <?php print $this->runData['entity']['fullname'];?>
                                </li>
                                <li>
                                    <hr class="dropdown-divider">
                                </li>
                                <li><a class="dropdown-item"
                                        href="<?php print $this->runData['config']['sys']['base_url'].'/spaces/100';?>"><i class="bi bi-person-badge menu-icon"></i>
                                        My Profile</a></li>
                                <li><a class="dropdown-item"
                                        href="<?php print $this->runData['config']['sys']['base_url'].'/spaces/103';?>"><i class="bi bi-clock-history menu-icon"></i>
                                        Sessions</a></li>
                                <li><a class="dropdown-item"
                                        href="<?php print $this->runData['config']['sys']['base_url'].'/spaces/104';?>"><i class="bi bi-sliders menu-icon"></i>
                                        Preferences</a></li>
                                <li><a class="dropdown-item"
                                        href="<?php print $this->runData['config']['sys']['base_url'].'/spaces/105';?>"><i class="bi bi-bell menu-icon"></i>
                                        Notifications</a></li>
                                <li><a class="dropdown-item"
                                        href="<?php print $this->runData['config']['sys']['base_url'].'/spaces/101';?>"><i class="bi bi-shield-lock menu-icon"></i>
                                        MFA Settings</a></li>
                                <li><a class="dropdown-item"
                                        href="<?php print $this->runData['config']['sys']['base_url'].'/spaces/102';?>"><i class="bi bi-key menu-icon"></i>
                                        Change Password</a></li>
                                <li>
                                    <hr class="dropdown-divider">
                                </li>
                                <li><a class="dropdown-item"
                                        href="<?php print $this->runData['config']['sys']['base_url'].'/login/logout';?>"><i class="bi bi-person-dash menu-icon"></i>
                                        Logout</a></li>
                            </ul>
                        </div>
                    </div>
                </div>
            </header>

            <!-- create main body -->
            <div class="container page-shell pb-4 flex-grow-1">
                <div class="row">
                    <div class="col-md-12 col-lg-12">
                        <?php $this->includePart('page'); ?>
                    </div>
                </div>
                <?php if (!empty($this->runData['route']['debug_block'])): ?>
                    <?php
                    $debugBlock = $this->runData['route']['debug_block'];
                    $payload = $debugBlock['payload'] ?? [];
                    $stats = $payload['checkpoint_stats'] ?? [];
                    unset($payload['checkpoint_stats']);
                    $debugJson = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
                    ?>
                    <div class="card border-warning my-4">
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
            </div>
            <!-- End Content -->

            <!-- Footer -->
            <footer class="py-3 my-4 border-top">
                <div class="container-fluid px-0">
                    <p class="text-center text-small text-body-secondary" style="font-size:11px;">&copy;
                        <?php echo date('Y');?>
                        <?php print $this->runData['config']['sys']['author'];?>
                    </p>
                </div>
            </footer>
            <!-- End Footer -->

        </div>
        <!-- End Page Content -->

        <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.5.1/jquery.min.js"></script>
        <?php echo '<script src="'.$this->runData['route']['assets_url'].'/vendor/bootstrap/bootstrap-5.3.0/dist/js/bootstrap.bundle.min.js"></script>';?>
        <?php $this->includePart('post'); ?>
</body>

</html>
