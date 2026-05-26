<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title><?php print $this->runData['route']['meta_title'];?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1">
    <meta name="description" content="<?php print $this->runData['route']['meta_description'];?>">
    <meta name="author" content="<?php print $this->runData['config']['sys']['author'];?>">
    <meta http-equiv="Content-Security-Policy" content="script-src 'self' 'unsafe-inline' 'unsafe-eval' https://cdn.jsdelivr.net https://code.jquery.com;">
    <link rel="canonical" href="<?php print $this->runData['route']['url'];?>">
    <?php
    echo '<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>';
    echo '<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">';
    echo '<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>';
    echo '<link href="'.$this->runData['route']['assets_url'].'/css/apex.css" rel="stylesheet">';
    echo '<link href="'.$this->runData['route']['assets_url'].'/css/app.css" rel="stylesheet">';
    echo '<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css">';
    $this->includePart('pre');
    ?>
</head>

<body>
    <?php $this->includePart('page'); ?>
     
    <?php $this->includePart('post'); ?>
</body>

</html>