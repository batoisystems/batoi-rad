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
                            <li class="rad-menu-heading"><?php print $this->runData['entity']['fullname'];?></li>
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
                <?php if (isset($this->runData['route']['alert'])) { ?>
                <div class="rad-alert rad-alert-<?php echo $this->runData['route']['alert']; ?>">
                    <?php echo $this->runData['route']['alert_message']; ?>
                </div>
                <?php } ?>

                <?php $this->includePart('page'); ?>
            </div>
        </main>

        <footer class="rad-footer">
            &copy; <?php echo date('Y');?> <?php print $this->runData['config']['sys']['author'];?>
        </footer>
    </div>

    <?php echo \Core\Sys\ThemeAssets::renderBody($this->runData); ?>
    <?php $this->includePart('post'); ?>
</body>

</html>
