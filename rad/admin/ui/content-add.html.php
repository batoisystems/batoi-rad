<?php
$formSubmissionUrl = $this->runData['route']['url'];
$msOptions = $this->runData['data']['content_ms'] ?? [];
?>

<form id="addForm" name="addForm" action="<?php echo $formSubmissionUrl; ?>" method="post" class="needs-validation" novalidate>
    <div class="card mb-3">
        <div class="card-body">
            <div class="row g-3">
                <div class="col-lg-8">
                    <label for="s_title" class="form-label">Title <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" name="s_title" id="s_title" required autocomplete="off">
                    <div class="invalid-feedback">Please provide a title.</div>
                </div>
                <div class="col-lg-4">
                    <label for="s_type" class="form-label">Type <span class="text-danger">*</span></label>
                    <select class="form-select" name="s_type" id="s_type" required>
                        <option value="I">Static Block</option>
                        <option value="J">Journal Block</option>
                        <option value="C">Common Block</option>
                    </select>
                    <div class="invalid-feedback">Choose a content type.</div>
                </div>
            </div>

            <div class="mt-3">
                <label for="s_content" class="form-label">Content Block Body <span class="text-danger">*</span></label>
                <textarea
                    class="form-control"
                    name="s_content"
                    id="s_content"
                    rows="12"
                    required
                    data-uif="editor"
                    data-uif-mode="html"
                    data-uif-toolbar="undo redo bold italic strike heading quote code ul ol link image table preview source"
                    data-uif-editor-height="400px"
                    data-uif-editor-status="true"></textarea>
                <div class="invalid-feedback">Content block body cannot be empty.</div>
            </div>
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-header">Placement & Metadata</div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-6">
                    <label for="s_ms_id" class="form-label">Microservicelet <span class="text-danger">*</span></label>
                    <select class="form-select" name="s_ms_id" id="s_ms_id" required>
                        <?php foreach ($msOptions as $ms): ?>
                            <option value="<?php echo (int)$ms['id']; ?>">
                                <?php echo htmlspecialchars($ms['s_name']); ?> · ID: <?php echo (int)$ms['id']; ?> · UID: <?php echo htmlspecialchars($ms['uid'] ?? ''); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <div class="invalid-feedback">Select a microservicelet.</div>
                </div>
                <div class="col-md-6">
                    <label for="s_slug" class="form-label">Slug <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" name="s_slug" id="s_slug" required>
                    <div class="invalid-feedback">Slug is required.</div>
                </div>
                <div class="col-md-6">
                    <label for="s_meta_title" class="form-label">Meta Title <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" name="s_meta_title" id="s_meta_title" required>
                    <div class="invalid-feedback">Meta title is required.</div>
                </div>
                <div class="col-md-6">
                    <label for="s_meta_description" class="form-label">Meta Description</label>
                    <textarea class="form-control" name="s_meta_description" id="s_meta_description" rows="2"></textarea>
                </div>
                <div class="col-12">
                    <label for="s_definition" class="form-label">Definition (JSON)</label>
                    <textarea class="form-control" name="s_definition" id="s_definition" rows="3" placeholder='{"key":"value"}'></textarea>
                    <small class="text-muted">Optional JSON metadata for templates.</small>
                </div>
            </div>
        </div>
    </div>

    <div class="text-end">
        <button id="submit-button" type="submit" class="btn btn-primary">
            <i class="bi bi-check-circle"></i> Save Block
        </button>
    </div>
</form>
