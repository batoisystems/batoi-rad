<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title><?php print $this->runData['route']['meta_title'];?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1">
    <meta name="description" content="<?php print $this->runData['route']['meta_description'];?>">
    <meta name="author" content="<?php print $this->runData['config']['sys']['author'];?>">
    <meta http-equiv="Content-Security-Policy" content="script-src 'self' 'unsafe-inline' 'unsafe-eval';">
    <link rel="canonical" href="<?php print $this->runData['route']['url'];?>">
    <?php
    echo \Core\Sys\ThemeAssets::renderHead($this->runData, ['apex' => true]);
    $this->includePart('pre');
    ?>
</head>

<body>
    <?php $this->includePart('page'); ?>
     
    <?php $this->includePart('post'); ?>
    <?php echo \Core\Sys\ThemeAssets::renderBody($this->runData); ?>
</body>

</html>
