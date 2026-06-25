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
    echo \Core\Sys\ThemeAssets::renderHead($this->runData, ['app' => false]);
    $this->includePart('pre');
    ?>
</head>

<body class="rad-shell-page">
    <div class="rad-shell">
        <header class="rad-topbar">
            <div class="rad-topbar-inner">
                <div class="rad-menu-wrap" data-uif="dropdown">
                    <button type="button" class="rad-logo-button" data-uif-role="trigger" aria-label="Open navigation">
                        <img src="<?php print $this->runData['route']['assets_url'];?>/img/logo-icon.svg" alt="<?php print $this->runData['config']['sys']['project_title'];?>">
                    </button>
                    <ul class="rad-menu" data-uif-role="panel" hidden>
                        <?php if (isset($this->runData['nav'][0])) {
                    foreach ($this->runData['nav'][0] as $nav) {
                        if (in_array($this->runData['entity']['role_id'], explode(',', $nav['roles']))) { ?>
                        <li><a class="rad-menu-item" data-uif-role="item" href="<?php print $nav['href'];?>"><?php print $nav['menu'];?></a></li>
                        <?php } } } ?>
                        <li><hr class="rad-menu-divider"></li>
                        <?php if (isset($this->runData['nav'][1])) {
                    foreach ($this->runData['nav'][1] as $nav) {
                        if (in_array($this->runData['entity']['role_id'], explode(',', $nav['roles']))) { ?>
                        <li><a class="rad-menu-item" data-uif-role="item" href="<?php print $nav['href'];?>"><?php print $nav['menu'];?></a></li>
                        <?php } } } ?>
                    </ul>
                </div>

                <h1 class="rad-title"><?php print $this->runData['route']['h1'];?></h1>

                <div class="rad-actions">
                    <?php if (isset($this->runData['route']['backlink'])) { ?>
                    <a class="rad-icon-link" href="<?php print $this->runData['route']['backlink'];?>" aria-label="Back">Back</a>
                    <?php } ?>
                    <a class="rad-icon-link" href="<?php print $this->runData['config']['sys']['base_url'].'/app/1';?>" aria-label="Apps">Apps</a>
                    <a class="rad-icon-link" href="#" aria-label="Notifications">Alerts</a>
                    <div class="rad-menu-wrap" data-uif="dropdown">
                        <button type="button" class="rad-user-button" data-uif-role="trigger" aria-label="Open account menu">Account</button>
                        <ul class="rad-menu rad-menu-right" data-uif-role="panel" hidden>
                            <li class="rad-menu-heading"><?php print $this->runData['entity']['user']['fullname'];?></li>
                            <li><hr class="rad-menu-divider"></li>
                            <li><a class="rad-menu-item" data-uif-role="item" href="<?php print $this->runData['config']['sys']['base_url'].'/app/14';?>">Change Password</a></li>
                            <li><a class="rad-menu-item" data-uif-role="item" href="<?php print $this->runData['config']['sys']['base_url'].'/login/logout';?>">Logout</a></li>
                        </ul>
                    </div>
                </div>
            </div>
        </header>

        <main class="rad-content">
            <div class="rad-content-inner">
                <?php if (in_array($this->runData['route']['id'], [3, 4, 5, 16])) {
                    $numOfTasks = count($this->runData['data']['tasks']);

                    if (($numOfTasks == 0) && (($this->runData['route']['alert'] ?? '') == 'info')) {
                        if ($this->runData['entity']['role_id'] == '3') {
                            $this->runData['route']['alert_message'] = 'Currently, there are no documents associated with this project in your account. This could be due to one of two reasons. Either no documents have been uploaded to this project yet, or your account does not have the necessary access permissions to view them. If you believe this to be a mistake, please contact your administrator or the project lead to verify your access rights or confirm document uploads.';
                        } else {
                            $this->runData['route']['alert_message'] = 'It appears that there are currently no documents associated with this project in your account. This is likely because no documents have been uploaded yet. If you have relevant documents to add, please proceed to the <strong><a href="'.$this->runData['config']['sys']['base_url'].'/app/4/'.$this->runData['data']['project']['uid'].'" class="rad-link">upload page</a></strong> to contribute. Let us start enriching this project with valuable information!';
                        }
                    }
                ?>
                <div class="rad-project-layout">
                    <aside class="rad-panel">
                        <details class="rad-project-summary">
                            <summary>
                                <span class="rad-project-icon">P</span>
                                <span><?php print $this->runData['data']['project']['a_name'];?></span>
                            </summary>
                            <p class="rad-project-description">
                                <?php print nl2br($this->runData['data']['project']['a_description']);?>
                            </p>
                        </details>

                        <ul class="rad-stat-list">
                            <li class="rad-stat-row">
                                <span class="rad-stat-label">Start Date</span>
                                <span class="rad-stat-value"><?php print date('F j, Y', strtotime($this->runData['data']['project']['createstamp'])); ?></span>
                            </li>
                            <li class="rad-stat-row">
                                <span class="rad-stat-label">Project Admin(s)</span>
                                <span class="rad-stat-value"><?php print $this->runData['data']['project']['a_project_admin_name']; ?></span>
                            </li>
                            <li class="rad-stat-row">
                                <span class="rad-stat-label">Status</span>
                                <span class="rad-stat-value"><?php print $this->runData['data']['project']['status']; ?></span>
                            </li>
                            <li class="rad-stat-row">
                                <span class="rad-stat-label">Documents</span>
                                <span class="rad-stat-value"><?php print count($this->runData['data']['tasks']);?></span>
                            </li>
                            <li class="rad-stat-row">
                                <span class="rad-stat-label">Total Reviewed</span>
                                <span class="rad-stat-value"><?php print $this->runData['data']['project']['reviewed_tasks_count']; ?></span>
                            </li>
                            <li class="rad-stat-row">
                                <span class="rad-stat-label">Total Approved</span>
                                <span class="rad-stat-value"><?php print $this->runData['data']['project']['approved_tasks_count']; ?></span>
                            </li>
                            <li class="rad-stat-row">
                                <span class="rad-stat-label">To-Dos</span>
                                <span class="rad-stat-value">0<?php //print $this->runData['data']['project']['todos_count']; ?></span>
                            </li>
                        </ul>

                        <?php if (($this->runData['entity']['role_id'] == '1' || $this->runData['entity']['role_id'] == '2') && ($this->runData['route']['id'] != 16)) { ?>
                        <div class="rad-panel-action">
                            <a href="<?php print $this->runData['config']['sys']['base_url'];?>/app/16/<?php print $this->runData['data']['project']['uid'];?>" class="rad-btn rad-btn-outline-danger">Edit Project Settings</a>
                        </div>
                        <?php } ?>
                    </aside>

                    <section>
                        <?php if (isset($this->runData['route']['alert'])) { ?>
                        <div class="rad-alert rad-alert-<?php echo $this->runData['route']['alert']; ?>">
                            <?php echo $this->runData['route']['alert_message']; ?>
                        </div>
                        <?php } ?>

                        <?php $this->includePart('page'); ?>
                    </section>
                </div>
                <?php } else { ?>
                <?php if (isset($this->runData['route']['alert'])) { ?>
                <div class="rad-alert rad-alert-<?php echo $this->runData['route']['alert']; ?>">
                    <?php echo $this->runData['route']['alert_message']; ?>
                </div>
                <?php } ?>

                <?php $this->includePart('page'); ?>
                <?php } ?>
            </div>
        </main>

        <footer class="rad-footer">
            &copy; <?php echo date('Y');?> BCube Analytics Inc.
        </footer>
    </div>

    <?php echo \Core\Sys\ThemeAssets::renderBody($this->runData); ?>
    <?php $this->includePart('post'); ?>
</body>

</html>
