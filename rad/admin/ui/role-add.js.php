<script>
document.addEventListener('DOMContentLoaded', function() {
    var scopeSelect = document.getElementById('s_scope');
    var routeGroup = document.getElementById('route_form_group');
    var routeSelect = document.getElementById('s_default_route_id');

    function syncVisibility() {
        if (!scopeSelect) return;
        var scope = scopeSelect.value || 'platform';
        if (routeGroup) {
            routeGroup.style.display = scope === 'platform' ? 'block' : 'none';
        }
        if (scope !== 'platform' && routeSelect) {
            routeSelect.value = '';
        }
    }

    syncVisibility();
    scopeSelect?.addEventListener('change', syncVisibility);
});
</script>
