<?php
$maxUploadSize = ini_get('upload_max_filesize');
$maxUploadSizeBytes = intval($maxUploadSize) * 1024 * 1024; // Convert from M to Bytes
$processXmlrUrl = $this->runData['route']['rad_admin_url'] . '/xmlreader/process';
?>
<script>
const maxUploadSize = <?php echo $maxUploadSizeBytes; ?>; // Max upload size in bytes

$('#xmlForm').on('submit', function(e) {
  const fileInput = document.getElementById('xmlFile');
  const xmlText = document.getElementById('xmlText').value;
  const xmlUrl = document.getElementById('xmlUrl').value;

  // Check if any of the methods is used to provide XML data
  if ((fileInput.files.length === 0 && !xmlText && !xmlUrl) || (fileInput.files.length > 0 && xmlText && xmlUrl)) {
    alert('Please provide XML data through only one method: File upload, Text area, or URL.');
    return false;
  }

  // Validate file size for file upload method
  if (fileInput.files.length > 0) {
    const fileSize = fileInput.files[0].size;
    if (fileSize > maxUploadSize) {
      alert('File size exceeds the maximum limit of ' + (maxUploadSize / 1024 / 1024).toFixed(2) + ' MB');
      return false;
    }
  }
  
  // Optionally, you can add other client-side checks for textarea and URL here

  e.preventDefault();
  $('#loading').removeClass('d-none');
  const formData = new FormData(this);

  $.ajax({
    type: 'POST',
    url: '<?php print $processXmlrUrl; ?>',
    data: formData,
    cache: false,
    contentType: false,
    processData: false,
    success: function(response) {
      console.log(response);
      $('#loading').addClass('d-none');
      $('#formattedXml').html(response);
    },
    error: function() {
      $('#loading').addClass('d-none');
      $('#formattedXml').text('An error occurred.');
    }
  });
});
</script>
