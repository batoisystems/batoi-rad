<script>
  $(document).ready(function () {
  // Function to load page content
  function loadPage(page) {
    // Update content area
    $('#content').load(page + '.php');

    // Update active menu item
    $('#menu .nav-link').removeClass('active');
    $('#menu .nav-link[data-page="' + page + '"]').addClass('active');

    // Update URL without reloading the page
    history.pushState({ page: page }, '', page);
  }

  // Handle menu click events
  $('#menu .nav-link').on('click', function (e) {
    e.preventDefault();
    var page = $(this).data('page');
    loadPage(page);
  });

  // Handle browser back/forward buttons
  window.onpopstate = function (event) {
    if (event.state && event.state.page) {
      loadPage(event.state.page);
    } else {
      loadPage('home');
    }
  };

  // Load the default page content on initial load
  var initialPage = location.pathname.replace('/', '') || 'home';
  loadPage(initialPage);
});
</script>
