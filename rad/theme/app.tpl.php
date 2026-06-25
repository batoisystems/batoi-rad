<!DOCTYPE html>
<html lang="en">
<head>
  <!-- Required meta tags -->
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title><?php print $this->runData['route']['meta_title'];?></title>
  <?php
    echo \Core\Sys\ThemeAssets::renderHead($this->runData, ['app' => false]);
    $this->includePart('pre');
    ?>
</head>
<body class="rad-app-page">
  <nav class="rad-app-nav">
    <div class="rad-app-nav-inner">
      <a class="rad-app-brand" href="#">MyApp</a>
        <ul class="rad-app-menu" id="menu">
          <li>
            <a class="rad-app-link active" href="#" data-page="home">Home</a>
          </li>
          <li>
            <a class="rad-app-link" href="#" data-page="about">About</a>
          </li>
          <li>
            <a class="rad-app-link" href="#" data-page="contact">Contact</a>
          </li>
        </ul>
    </div>
  </nav>

  <div class="rad-app-content" id="content">
  </div>

  <?php echo \Core\Sys\ThemeAssets::renderBody($this->runData); ?>
  <script src="<?php echo $this->runData['route']['assets_url']; ?>/js/app.js"></script>
  <?php $this->includePart('post'); ?>
</body>
</html>
