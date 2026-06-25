<script>
document.addEventListener('DOMContentLoaded', function() {
    var content = document.getElementById('s_content');
    var title = document.getElementById('s_title');

    if (content && window.BatoiUIF && window.BatoiUIF.initEditor) {
        window.BatoiUIF.initEditor(content);
    }

    if (title) {
        title.addEventListener('input', function() {
            var sTitleValue = title.value;
            var metaTitle = document.getElementById('s_meta_title');
            var slug = document.getElementById('s_slug');
            if (metaTitle) {
                metaTitle.value = sTitleValue;
            }
            if (slug) {
                slug.value = sTitleValue.toLowerCase().replace(/[^a-z0-9-]/g, '-').replace(/--+/g, '-');
            }
        });
    }
});
</script>
