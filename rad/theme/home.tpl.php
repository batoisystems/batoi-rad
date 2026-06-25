<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Welcome to the new site.">
    <meta name="author" content="<?php print $this->runData['config']['sys']['author'];?>">
    <title>Welcome</title>
    <link rel="canonical" href="<?php print $this->runData['route']['url'];?>">
    <?php
    echo \Core\Sys\ThemeAssets::renderHead($this->runData, ['app' => false]);
    ?>
</head>

<body class="rad-home-page">
    <main class="rad-home-main">
        <img src="<?php print $this->runData['route']['assets_url'];?>/img/welcome.svg" class="rad-home-image" alt="">
    </main>
    <footer class="rad-footer">
        &copy; <?php echo date('Y'); ?> <?php print $this->runData['config']['sys']['author'];?>
    </footer>

    <?php echo \Core\Sys\ThemeAssets::renderBody($this->runData); ?>
</body>

</html>
