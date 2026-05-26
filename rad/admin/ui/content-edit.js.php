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

    $('#submit-button').click(function(e) {
        e.preventDefault();
        var isCodeview = $('.summernote').summernote('codeview.isActivated');
        if(isCodeview){
            var codeContent = $('.summernote').summernote('code');
            $('.summernote').summernote('code', codeContent);
        }
        $('#editForm').submit(); // Changed from 'editForm' to 'addForm' based on your HTML form's ID
    });
});

document.getElementById("s_title").addEventListener("input", function() {
    let sTitleValue = this.value;

    let metaTitle = document.getElementById("s_meta_title");
    metaTitle.value = sTitleValue;

    let slug = document.getElementById("s_slug");
    slug.value = sTitleValue.toLowerCase().replace(/[^a-z0-9-]/g, "-").replace(/--+/g, "-");
});
</script>

