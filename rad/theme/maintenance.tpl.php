<!-- <!DOCTYPE html> -->
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
    <!-- Page Content -->
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
                                    <?php print $this->runData['entity']['user']['fullname'];?>
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

            <!-- Content -->
            <div class="container flex-grow-1">
                <?php
                if (in_array($this->runData['route']['id'], [3, 4, 5, 16])) {
                    // print '<pre>';print_r($this->runData['route']);print '</pre>';
                     
                ?>
                <div class="row">
                    <!-- Sidebar -->
                    <div class="col-md-3 px-0 py-0 sidenav">
                        <!-- Project Details -->
                        <div class="card h-100">
                            <div class="card-header" style="background-color: white;">
                                <div
                                    class="d-flex flex-column justify-content-center align-items-center w-100 card-title my-4 text-center">
                                    <h5 data-bs-toggle="collapse" href="#collapseDescription" role="button"
                                        aria-expanded="false" aria-controls="collapseDescription">
                                        <p><i class="bi bi-folder"></i></p>
                                        <p>
                                            <?php print $this->runData['data']['project']['a_name'];?>
                                        </p>
                                    </h5>
                                    <p><i class="bi bi-chevron-down" data-bs-toggle="collapse" href="#collapseDescription" role="button" aria-expanded="false" aria-controls="collapseDescription"></i>
                                    </p>
                                </div>
                                <p class="collapse text-center" id="collapseDescription" style="font-size:0.8rem;">
                                    <?php print nl2br($this->runData['data']['project']['a_description']);?>
                                </p>
                            </div>

                            <ul class="list-group list-group-flush">
                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                    <span><i class="bi bi-calendar4-range"></i> Start Date</span>
                                    <span><?php print date('F j, Y', strtotime($this->runData['data']['project']['createstamp'])); ?></span>
                                </li>
                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                    <span><i class="bi bi-person-badge"></i> Project Admin(s)</span>
                                    <span><?php print $this->runData['data']['project']['a_project_admin_name']; ?></span>
                                </li>
                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                    <span><i class="bi bi-graph-up"></i> Status</span>
                                    <span><?php print $this->runData['data']['project']['status']; ?></span>
                                </li>
                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                    <span><i class="bi bi-files"></i> Documents</span>
                                    <span><?php print count($this->runData['data']['tasks']);?></span>
                                </li>
                                <!-- <li class="list-group-item d-flex justify-content-between align-items-center">
                                    <span><i class="bi bi-card-checklist"></i> Annotations</span>
                                    <span><?php print $this->runData['data']['project']['annotations_count']; ?></span>
                                </li> -->
                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                    <span><i class="bi bi-people"></i> Total Reviewed</span>
                                    <span><?php print $this->runData['data']['project']['reviewed_tasks_count']; ?></span>
                                </li>
                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                    <span><i class="bi bi-people-fill"></i> Total Approved</span>
                                    <span><?php print $this->runData['data']['project']['approved_tasks_count']; ?></span>
                                </li>
                                <!-- <li class="list-group-item d-flex justify-content-between align-items-center">
                                    <span><i class="bi bi-file-earmark-diff"></i> Unassigned Files</span>
                                    <span><?php //print $this->runData['data']['project']['unassigned_files']; ?></span>
                                </li> -->
                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                    <span><i class="bi bi-list-check"></i> To-Dos</span>
                                    <span>0<?php //print $this->runData['data']['project']['todos_count']; ?></span>
                                </li>
                                <!-- Similar structure for the rest of the list items -->
                            </ul>

                            <!-- if entity role is admin or project admin, and the route[1] is not 16 (project settings) display the following row block -->
                            <?php //print '<pre>'; print_r($this->runData); print '</pre>'; ?>
                            <?php if( ($this->runData['entity']['role_id'] == '1' || $this->runData['entity']['role_id'] == '2') && ($this->runData['route']['id'] != 16) ) { ?>
                            <!-- Edit Settings button -->
                            <div
                                class="row ${1| ,row-cols-2,row-cols-3, auto,justify-content-md-center,|} my-4 text-center">
                                <div class="col-12">
                                    <a href="<?php print $this->runData['config']['sys']['base_url'];?>/app/16/<?php print $this->runData['data']['project']['uid'];?>"
                                        class="btn btn-outline-danger"><i class="bi bi-pencil-square"></i> Edit Project
                                        Settings</a>
                                </div>
                            </div>
                            <?php } ?>


                        </div>
                        <!-- End Project Details -->

                    </div>



                    <!-- Main Content -->
                    <div class="col-md-9">
                        <div class="container mt-0">
                            <?php
                            // Fetch or calculate number of tasks. Replace with your actual logic.
                            // This is a dummy example. $numOfTasks must be the actual number of tasks
                            $numOfTasks = count($this->runData['data']['tasks']);

                            if ( ($numOfTasks == 0) && ($this->runData['route']['alert'] == 'info') ) {
                                if($this->runData['entity']['role_id'] == '3') {
                                    $this->runData['route']['alert_message'] = 'Currently, there are no documents associated with this project in your account. This could be due to one of two reasons. Either no documents have been uploaded to this project yet, or your account does not have the necessary access permissions to view them. If you believe this to be a mistake, please contact your administrator or the project lead to verify your access rights or confirm document uploads.';
                                } else {
                                    $this->runData['route']['alert_message'] = 'It appears that there are currently no documents associated with this project in your account. This is likely because no documents have been uploaded yet. If you have relevant documents to add, please proceed to the <strong><a href="'.$this->runData['config']['sys']['base_url'].'/app/4/'.$this->runData['data']['project']['uid'].'" class="text-primary" style="text-decoration:none;">upload page</a></strong> to contribute. Let us start enriching this project with valuable information!';
                                }
                            }
                            ?>
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

                            <?php } ?>

                            <?php
                // find this class name and its methods
                // $class = new ReflectionClass($this);
                // $methods = $class->getMethods();
                // $methodNames = [];
                // foreach ($methods as $method) {
                //     $methodNames[] = $method->name;
                //     // get methid type
                //     $methodType = $method->isPublic() ? 'public' : 'private';
                // }
                // print '<pre>';print_r($methodNames);print '</pre>';exit;
                ?>

                            <?php $this->includePart('page'); ?>

                            <?php
                if (in_array($this->runData['route']['id'], [3, 4, 5, 16])) {
                ?>
                        </div>
                    </div>
                </div>
                <?php
                }
                ?>
            </div>
            <!-- End Content -->



            <!-- Footer -->
            <footer class="py-3 my-4 border-top">
                <div class="container-fluid px-0">
                    <p class="text-center text-small text-body-secondary" style="font-size:11px;">&copy;
                        <?php echo date('Y');?> BCube Analytics Inc.
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