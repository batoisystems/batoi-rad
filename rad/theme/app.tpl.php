<!DOCTYPE html>
<html lang="en">
<head>
  <!-- Required meta tags -->
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title><?php print $this->runData['route']['meta_title'];?></title>
  <?php
    echo '<link href="'.$this->runData['route']['assets_url'].'/vendor/bootstrap/bootstrap-5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">';
    echo '<link href="'.$this->runData['route']['assets_url'].'/css/app.css" rel="stylesheet">';
    echo '<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">';
    $this->includePart('pre');
    ?>

  <!-- Custom CSS (Optional) -->
  <style>
    /* Highlight the active menu item */
    .nav-link.active {
      font-weight: bold;
    }
  </style>
</head>
<body>
  <!-- Navigation Menu -->
  <nav class="navbar navbar-expand-lg navbar-light bg-light">
    <div class="container-fluid">
      <a class="navbar-brand" href="#">MyApp</a>
      <button
        class="navbar-toggler"
        type="button"
        data-bs-toggle="collapse"
        data-bs-target="#navbarNav"
      >
        <span class="navbar-toggler-icon"></span>
      </button>
      <div class="collapse navbar-collapse" id="navbarNav">
        <ul class="navbar-nav" id="menu">
          <li class="nav-item">
            <a class="nav-link active" href="#" data-page="home">Home</a>
          </li>
          <li class="nav-item">
            <a class="nav-link" href="#" data-page="about">About</a>
          </li>
          <li class="nav-item">
            <a class="nav-link" href="#" data-page="contact">Contact</a>
          </li>
        </ul>
      </div>
    </div>
  </nav>

  <!-- Content Area -->
  <div class="container mt-4" id="content">
    <!-- Dynamic content will be loaded here -->
  </div>

  <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
  <?php echo '<script src="'.$this->runData['route']['assets_url'].'/vendor/bootstrap/bootstrap-5.3.0/dist/js/bootstrap.bundle.min.js"></script>';?>
  <script src="'.$this->runData['route']['assets_url'].'/js/app.js"></script>
  <?php $this->includePart('post'); ?>
</body>
</html>
