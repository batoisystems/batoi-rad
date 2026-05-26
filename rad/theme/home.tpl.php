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
    echo '<link href="'.$this->runData['route']['assets_url'].'/vendor/bootstrap/bootstrap-5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">';
    echo '<link href="'.$this->runData['route']['assets_url'].'/css/app.css" rel="stylesheet">';
    echo '<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">';
    ?>
</head>

<body class="d-flex flex-column h-100">
    <!-- Page Content -->
    <main class="flex-grow-1 d-flex align-items-center justify-content-center">
        <div class="container text-center">
            <img src="<?php print $this->runData['route']['assets_url'];?>/img/welcome.svg" class="img-fluid" alt="">
        </div>
    </main>
    <!-- End Page Content -->
    
    <!-- Footer -->
    <footer class="py-3 mt-auto border-top">
        <div class="container">
            <p class="text-center text-muted" style="font-size:11px;">&copy; <?php echo date('Y'); ?> <?php print $this->runData['config']['sys']['author'];?></p>
        </div>
    </footer>
    <!-- End Footer -->

    <!-- Scripts -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.5.1/jquery.min.js"></script>
    <?php echo '<script src="'.$this->runData['route']['assets_url'].'/vendor/bootstrap/bootstrap-5.3.0/dist/js/bootstrap.bundle.min.js"></script>';?>
</body>

</html>
