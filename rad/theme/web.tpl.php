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
    $this->includePart('pre');
    ?>
</head>

<body>
    <!-- Page Content // this is content that comes -->
    <div class="d-flex flex-column min-vh-100">
        <div class="container-fluid px-0 flex-grow-1">

            <!-- Navbar -->
            <header class="py-1 mb-5 border-bottom">
                <div class="container-fluid d-grid gap-3 align-items-center"
                    style="grid-template-columns: auto auto 1fr;">
                    <div class="dropdown compact-navbar">
                        <a href="<?php print $this->runData['config']['sys']['base_url'].'/app/1';?>"
                            class="d-flex align-items-center col-lg-4 mb-2 mb-lg-0 link-body-emphasis text-decoration-none dropdown-toggle"
                            data-bs-toggle="dropdown" aria-expanded="false">
                            <img src="<?php print $this->runData['route']['assets_url'];?>/img/logo-icon.svg" alt="<?php print $this->runData['config']['sys']['project_title'];?>" height="32">
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

                    <div class="d-flex align-items-center">
                        <h3 class="mb-0" style="font-size: 18px; color: #062334; font-family: Arial, sans-serif;">
                            <?php print $this->runData['route']['h1'];?>
                        </h3>
                    </div>

                    <div class="d-flex align-items-center" style="justify-self: end;">
                        <?php if (isset($this->runData['route']['backlink'])) { ?>
                        <a href="<?php print $this->runData['route']['backlink'];?>"
                            class="d-block link-body-emphasis text-decoration-none">
                            <i class="bi-chevron-left me-4" style="font-size: 1.2rem; color: #062334;"></i>
                        </a>
                        <?php } ?>
                        <a href="<?php print $this->runData['config']['sys']['base_url'].'/app/1';?>"
                            class="d-block link-body-emphasis text-decoration-none">
                            <i class="bi-boxes me-4" style="font-size: 1.2rem; color: #062334;"></i>
                        </a>
                        <a href="#" class="d-block link-body-emphasis text-decoration-none">
                            <i class="bi-bell me-4" style="font-size: 1.2rem; color: #062334;"></i>
                        </a>
                        <div class="flex-shrink-0 dropdown compact-navbar">
                            <a href="#" class="d-block link-body-emphasis text-decoration-none dropdown-toggle"
                                data-bs-toggle="dropdown" aria-expanded="false">
                                <i class="bi-person-circle" style="font-size: 1.7rem; color: #062334;" class="rounded-circle"></i>
                            </a>
                            <ul class="dropdown-menu text-small shadow">
                                <li style="text-align: center;font-weight:bold;">
                                    <?php print $this->runData['entity']['fullname'];?>
                                </li>
                                <li>
                                    <hr class="dropdown-divider">
                                </li>
                                <li><a class="dropdown-item"
                                        href="<?php print $this->runData['config']['sys']['base_url'].'/app/14';?>"><i class="bi bi-key menu-icon"></i>
                                        Change Password</a></li>
                                <li><a class="dropdown-item"
                                        href="<?php print $this->runData['config']['sys']['base_url'].'/login/logout';?>"><i class="bi bi-person-dash menu-icon"></i>
                                        Logout</a></li>
                            </ul>
                        </div>
                    </div>
                </div>
            </header>

            <!-- Content // -->
            <div class="container flex-grow-1">
                <div class="row">
                    <div class="col-lg-12">
                        <div class="container mt-0">
                            <?php if (isset($this->runData['route']['alert'])) { ?>
                            <div class="alert alert-<?php echo $this->runData['route']['alert']; ?>">
                                <?php
                                    switch($this->runData['route']['alert']){
                                        case 'success':
                                            echo '<i class="bi bi-check-circle-fill"></i> ';
                                            break;
                                        case 'danger':
                                            echo '<i class="bi bi-exclamation-circle-fill"></i> ';
                                            break;
                                        case 'info':
                                            echo '<i class="bi bi-exclamation-circle-fill"></i> ';
                                            break;
                                        // Add more cases for other alert types as needed
                                        default:
                                            break;
                                    }
                                    ?>
                                <?php echo $this->runData['route']['alert_message']; ?>
                            </div>
                            <?php } ?>

                           

                            <?php $this->includePart('page'); ?>

                        </div>
                    </div>
                </div>
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