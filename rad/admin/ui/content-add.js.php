<script src="<?php print $this->runData['route']['rad_assets_url'].'/summernote/dist/summernote-lite.js';?>"></script>
<script>
$(document).ready(function() {
    $('#s_content').summernote({
        tabsize: 4,
        height: 400,
        toolbar: [
            ['style', ['style']],
            ['font', ['bold', 'italic', 'underline', 'strikethrough', 'superscript', 'subscript', 'clear']],
            ['fontsize', ['fontsize']],
            ['color', ['color']],
            ['para', ['ul', 'ol', 'paragraph', 'height']],
            ['insert', ['link', 'picture', 'video', 'table', 'hr']],
            ['view', ['fullscreen', 'codeview', 'help']],
        ],
        codeviewFilter: false,
        codeviewIframeFilter: false,
        callbacks: {
        onPaste: function(e) {
                var bufferText = ((e.originalEvent || e).clipboardData || window.clipboardData).getData('Text');
                e.preventDefault();
                document.execCommand('insertText', false, bufferText);
            }
        }
    });

    // Listen to form submit
    $('#submit-button').click(function(e) {
        e.preventDefault(); // Prevent the default form submit
        var isCodeview = $('.summernote').summernote('codeview.isActivated'); // Check if code view is activated
        if(isCodeview){
        var codeContent = $('.summernote').summernote('code'); // Get the content in code view
        $('.summernote').summernote('code', codeContent); // Update the content in UI view
        }
        $('#addForm').submit(); // Submit the form
    });
});
</script>

<script>
// Real-time filling of Meta Title and Slug
document.getElementById("s_title").addEventListener("input", function() {
    let sTitleValue = this.value;

    // Fill Meta Title
    let metaTitle = document.getElementById("s_meta_title");
    metaTitle.value = sTitleValue; // No substring method needed

    // Fill Slug
    let slug = document.getElementById("s_slug");
    slug.value = sTitleValue.toLowerCase().replace(/[^a-z0-9-]/g, "-").replace(/--+/g, "-");
});
</script>
