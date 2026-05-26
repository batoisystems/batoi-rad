<script>
document.addEventListener('DOMContentLoaded', function () {
    var legacyRolesGroup = document.getElementById('roles-container');
    if (!legacyRolesGroup) {
        return;
    }

    legacyRolesGroup.innerHTML = '<div class="alert alert-info mb-0">Role access is now configured from the Permission Bindings screen.</div>';
});
</script>
