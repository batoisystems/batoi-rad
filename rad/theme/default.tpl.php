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
    echo \Core\Sys\ThemeAssets::renderHead($this->runData);
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
                        <span><?php print $this->runData['config']['sys']['project_title'];?></span>
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
                            <li><a class="rad-menu-item" data-uif-role="item" href="<?php print $this->runData['config']['sys']['base_url'].'/spaces/100';?>">My Profile</a></li>
                            <li><a class="rad-menu-item" data-uif-role="item" href="<?php print $this->runData['config']['sys']['base_url'].'/spaces/103';?>">Sessions</a></li>
                            <li><a class="rad-menu-item" data-uif-role="item" href="<?php print $this->runData['config']['sys']['base_url'].'/spaces/104';?>">Preferences</a></li>
                            <li><a class="rad-menu-item" data-uif-role="item" href="<?php print $this->runData['config']['sys']['base_url'].'/spaces/105';?>">Notifications</a></li>
                            <li><a class="rad-menu-item" data-uif-role="item" href="<?php print $this->runData['config']['sys']['base_url'].'/spaces/101';?>">MFA Settings</a></li>
                            <li><a class="rad-menu-item" data-uif-role="item" href="<?php print $this->runData['config']['sys']['base_url'].'/spaces/102';?>">Change Password</a></li>
                            <li><hr class="rad-menu-divider"></li>
                            <li><a class="rad-menu-item" data-uif-role="item" href="<?php print $this->runData['config']['sys']['base_url'].'/login/logout';?>">Logout</a></li>
                        </ul>
                    </div>
                </div>
            </div>
        </header>

        <main class="rad-content">
            <div class="rad-content-inner">
                <?php $this->includePart('page'); ?>

                <?php if (!empty($this->runData['route']['debug_block'])): ?>
                    <?php
                    $debugBlock = $this->runData['route']['debug_block'];
                    $payload = $debugBlock['payload'] ?? [];
                    $stats = $payload['checkpoint_stats'] ?? [];
                    unset($payload['checkpoint_stats']);
                    $debugJson = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
                    ?>
                    <section class="rad-debug-panel">
                        <div class="rad-debug-head">
                            <div>
                                <span class="rad-debug-title">Debug (dev_debug_flag=Y)</span>
                                <span class="rad-debug-meta"><?php echo htmlspecialchars($debugBlock['generated_at'] ?? ''); ?></span>
                            </div>
                            <span class="rad-debug-badge">debug_block=1</span>
                        </div>
                        <div class="rad-debug-body">
                            <p class="rad-debug-request">Request: <?php echo htmlspecialchars($debugBlock['request_uri'] ?? ''); ?></p>
                            <?php if (!empty($stats)): ?>
                                <div class="rad-table-wrap">
                                    <table class="rad-table">
                                        <thead>
                                            <tr>
                                                <th>Checkpoint</th>
                                                <th>Delta (ms)</th>
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
                            <pre class="rad-code-block"><?php echo htmlspecialchars($debugJson ?? '', ENT_QUOTES, 'UTF-8'); ?></pre>
                        </div>
                    </section>
                <?php endif; ?>
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
