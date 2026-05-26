<?php
$assetPath = $this->runData['data']['asset_relative'] ?? '';
$assetFile = $this->runData['data']['asset_filename'] ?? basename($assetPath);
$assetPublicUrl = $this->runData['data']['asset_public_url'] ?? '';
$extension = $this->runData['data']['asset_extension'] ?? '';

$encodedSegments = array_map('rawurlencode', array_filter(explode('/', $assetPath), function ($segment) {
    return $segment !== '';
}));
$encodedPath = implode('/', $encodedSegments);
$downloadUrl = $this->runData['route']['rad_admin_url'] . '/uiassets/download/' . $encodedPath;
$previewUrl = $this->runData['route']['rad_admin_url'] . '/uiassets/preview/' . $encodedPath;
$saveUrl = $this->runData['config']['sys']['base_url'] . '/rad-admin/uiassets/savefile';
$aiUrl = $this->runData['config']['sys']['base_url'] . '/rad-admin/uiassets/aiassist';
?>

<div class="container-fluid py-3">
    <div class="card mb-3">
        <div class="card-body d-flex flex-wrap justify-content-between align-items-start gap-3">
            <div>
                <div class="text-muted small">Editing Asset</div>
                <div class="fw-semibold fs-5"><?php echo htmlspecialchars($assetFile); ?></div>
                <div class="small text-muted">Path: <code><?php echo htmlspecialchars('/assets/' . $assetPath); ?></code></div>
                <div class="small text-muted">Public URL: <code id="asset-public-url"><?php echo htmlspecialchars($assetPublicUrl); ?></code></div>
            </div>
            <div class="btn-group btn-group-sm" role="group">
                <a class="btn btn-outline-secondary" href="<?php echo htmlspecialchars($downloadUrl); ?>" target="_blank">
                    <i class="bi bi-download me-1"></i>Download
                </a>
                <a class="btn btn-outline-secondary" href="<?php echo htmlspecialchars($previewUrl); ?>" target="_blank">
                    <i class="bi bi-eye me-1"></i>Preview
                </a>
                <button class="btn btn-outline-secondary" type="button" id="asset-copy-url">
                    <i class="bi bi-link-45deg me-1"></i>Copy URL
                </button>
            </div>
        </div>
    </div>

    <div class="alert alert-light border d-flex flex-wrap justify-content-between align-items-center gap-3">
        <div class="d-flex align-items-center gap-2">
            <span class="text-primary"><i class="bi bi-magic"></i></span>
            <div>
                <strong>AI Assist</strong>
                <div class="small text-muted">Place the cursor inside the editor and press <kbd>Shift</kbd> + <kbd>Space</kbd> to request inline help.</div>
            </div>
        </div>
        <div class="d-flex align-items-center gap-2">
            <button type="button" class="btn btn-outline-primary btn-sm" id="asset-ai-btn">
                <i class="bi bi-stars me-1"></i>Ask AI
            </button>
            <small class="text-muted" id="asset-ai-status">Ready.</small>
        </div>
    </div>

    <div class="card">
            <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
                <div class="btn-group btn-group-sm" role="group">
                    <button class="btn btn-outline-secondary" id="asset-format-btn">
                        <i class="bi bi-code me-1"></i>Format
                    </button>
                    <button class="btn btn-outline-secondary" id="asset-wrap-btn">
                        <i class="bi bi-text-wrap me-1"></i>Wrap
                    </button>
                    <button class="btn btn-outline-secondary" id="asset-undo-btn" title="Undo">
                        <i class="bi bi-arrow-counterclockwise"></i>
                    </button>
                    <button class="btn btn-outline-secondary" id="asset-redo-btn" title="Redo">
                        <i class="bi bi-arrow-clockwise"></i>
                    </button>
                </div>
                <div class="small text-muted" id="asset-save-status">No changes yet.</div>
            </div>
        <div class="card-body p-0">
            <div id="asset-editor"
                 data-path="<?php echo htmlspecialchars($assetPath); ?>"
                 data-extension="<?php echo htmlspecialchars($extension); ?>"
                 data-save-url="<?php echo htmlspecialchars($saveUrl); ?>"
                 data-ai-url="<?php echo htmlspecialchars($aiUrl); ?>"
                 style="height: 620px;">
            </div>
        </div>
    </div>
</div>
