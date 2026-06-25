document.addEventListener('DOMContentLoaded', function () {
    var content = document.getElementById('content');
    var menu = document.getElementById('menu');

    function setActive(page) {
        if (!menu) {
            return;
        }
        menu.querySelectorAll('[data-page]').forEach(function (link) {
            link.classList.toggle('active', link.getAttribute('data-page') === page);
        });
    }

    function loadPage(page, pushState) {
        if (!content) {
            return;
        }
        fetch(page + '.php', { credentials: 'same-origin' })
            .then(function (response) {
                if (!response.ok) {
                    throw new Error('Unable to load page.');
                }
                return response.text();
            })
            .then(function (html) {
                content.innerHTML = html;
                setActive(page);
                if (pushState !== false) {
                    history.pushState({ page: page }, '', page);
                }
            })
            .catch(function () {
                content.innerHTML = '<div class="rad-alert rad-alert-danger">Unable to load this page.</div>';
            });
    }

    if (menu) {
        menu.addEventListener('click', function (event) {
            var link = event.target.closest('[data-page]');
            if (!link) {
                return;
            }
            event.preventDefault();
            loadPage(link.getAttribute('data-page') || 'home', true);
        });
    }

    window.addEventListener('popstate', function (event) {
        loadPage(event.state && event.state.page ? event.state.page : 'home', false);
    });

    loadPage(location.pathname.replace(/^\/+/, '') || 'home', false);
});
