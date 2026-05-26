<div class="container my-5">
  <div class="row">
    <div class="col-6">
      <h1 class="mb-4"><i class="bi bi-file-earmark-xml"></i> XML Input</h1>
      <form id="xmlForm" enctype="multipart/form-data" method="post" action="process.php">
        <div class="mb-3">
          <label for="xmlFile" class="form-label"><i class="bi bi-upload"></i> Upload XML File</label>
          <input type="file" class="form-control" id="xmlFile" name="xmlFile">
        </div>
        <div class="mb-3">
          <label for="xmlText" class="form-label"><i class="bi bi-file-text"></i> Or paste XML here</label>
          <textarea class="form-control" id="xmlText" name="xmlText" rows="5"></textarea>
        </div>
        <div class="mb-3">
          <label for="xmlUrl" class="form-label"><i class="bi bi-globe"></i> Or enter XML URL</label>
          <input type="text" class="form-control" id="xmlUrl" name="xmlUrl" placeholder="http://example.com/file.xml">
        </div>
        <button type="submit" class="btn btn-primary"><i class="bi bi-check-circle"></i> Submit</button>
      </form>
    </div>
    <div class="col-6">
      <h1 class="mb-4"><i class="bi bi-file-earmark-check"></i> Formatted XML</h1>
      <div id="loading" class="d-none">
        <div class="spinner-border" role="status">
          <span class="visually-hidden">Loading...</span>
        </div>
      </div>
      <pre id="formattedXml"></pre>
    </div>
  </div>
</div>