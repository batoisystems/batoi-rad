<?php
$detail = $this->runData['data']['template'] ?? [];
$relative = $detail['relative'] ?? '';
$content = $detail['content'] ?? '';
$encoded = rtrim(strtr(base64_encode($relative), '+/', '-_'), '=');
$backUrl = $this->runData['route']['rad_admin_url'] . '/uitpl/viewone/' . $encoded;
?>
<div class="container-fluid py-3">
    <div class="card mb-3">
        <div class="card-body d-flex justify-content-between flex-wrap gap-2">
            <div>
                <div class="text-muted small">Editing</div>
                <div class="fw-semibold"><?php echo htmlspecialchars($relative); ?></div>
            </div>
            <div class="d-flex gap-2">
                <a href="<?php echo $backUrl; ?>" class="btn btn-outline-secondary">Back</a>
                <button type="submit" form="uitpl-edit-form" class="btn btn-primary">Save & Version</button>
            </div>
        </div>
    </div>
    <div class="card">
        <div class="card-body">
            <form method="post" id="uitpl-edit-form">
                <input type="hidden" name="template_content" id="uitpl-editor-input" value="<?php echo htmlspecialchars($content); ?>">
                <div id="uitpl-editor"
                     data-language="php"
                     data-content="<?php echo htmlspecialchars($content, ENT_QUOTES, 'UTF-8'); ?>"
                     style="height: 620px;">
                </div>
            </form>
        </div>
    </div>
</div>
