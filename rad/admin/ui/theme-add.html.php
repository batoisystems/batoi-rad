<div class="row g-4">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-body">
                <form action="<?php echo htmlspecialchars($this->runData['route']['url']); ?>" method="post">
                    <div class="mb-3">
                        <label for="template_name" class="form-label">Template File Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="template_name" name="template_name" placeholder="e.g. dashboard" required>
                        <small class="text-muted">Use lowercase letters, numbers, or underscores only. The file is saved as <code>name.tpl.php</code>.</small>
                    </div>
                    <div class="mb-3">
                        <label for="template_content" class="form-label">Initial Content</label>
                        <textarea class="form-control" id="template_content" name="template_content" rows="12" placeholder="Optional: paste starter markup or leave blank to use the default stub."></textarea>
                    </div>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-check2-circle me-1"></i>Create Template
                    </button>
                </form>
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="card h-100">
            <div class="card-body">
                <h6 class="card-title">Tips</h6>
                <ul class="small ps-3 mb-0">
                    <li>Avoid spaces or dashes in file names; stick to <code>snake_case</code> for consistency.</li>
                    <li>You can start with an empty template or paste markup above to seed the file.</li>
                    <li>Use the Theme toolbar to jump back to the template list or upload assets.</li>
                </ul>
            </div>
        </div>
    </div>
</div>
