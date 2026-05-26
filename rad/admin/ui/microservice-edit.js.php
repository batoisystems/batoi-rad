<script>
document.addEventListener('DOMContentLoaded', function () {
    var legacyRolesGroup = document.getElementById('roles-container');
    if (!legacyRolesGroup) {
        return;
    }

    legacyRolesGroup.innerHTML = '<div class="alert alert-info mb-0">Use Permission Bindings to assign roles to this microservice or its routes.</div>';
});
</script>
