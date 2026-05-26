<?php
$space = $this->runData['data']['space'] ?? [];
$payload = $this->runData['data']['sniff_payload'] ?? [];
$roles = $payload['roles'] ?? [];
$assignments = $payload['assignments'] ?? [];
$jsonPayload = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
?>

<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <a href="<?php echo htmlspecialchars($this->runData['route']['backlink']); ?>" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left-circle"></i> Back to Workspace
    </a>
    <button type="button" class="btn btn-primary" id="copySniffPayload">
        <i class="bi bi-clipboard"></i> Copy JSON
    </button>
</div>

<div class="card mb-3">
    <div class="card-body d-flex justify-content-between align-items-center flex-wrap gap-3">
        <div>
            <h2 class="h5 mb-1">Meta Sniff: <?php echo htmlspecialchars($space['s_name'] ?? ''); ?></h2>
            <div class="text-muted small">ID: <?php echo (int)($space['id'] ?? 0); ?> · UID: <code><?php echo htmlspecialchars($space['uid'] ?? ''); ?></code></div>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <span class="badge bg-light text-dark">Slug: <?php echo htmlspecialchars($space['s_slug'] ?? ''); ?></span>
            <span class="badge bg-info text-dark"><?php echo count($roles); ?> roles</span>
            <span class="badge bg-secondary"><?php echo count($assignments); ?> assignments</span>
        </div>
    </div>
</div>

<div class="card mb-3">
    <div class="card-header bg-white">
        <strong>JSON Payload</strong>
        <div class="small text-muted">Includes workspace metadata, distinct roles, and current workspace assignments.</div>
    </div>
    <div class="card-body">
        <pre class="bg-dark text-light rounded p-3 mb-0" style="white-space: pre-wrap;" id="sniffPayloadBlock"><?php echo htmlspecialchars($jsonPayload ?? '', ENT_QUOTES, 'UTF-8'); ?></pre>
    </div>
</div>

<?php if (!empty($roles)) { ?>
<div class="card mb-3">
    <div class="card-header bg-white d-flex justify-content-between align-items-center">
        <strong>Roles</strong>
        <span class="text-muted small"><?php echo count($roles); ?> total</span>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-sm align-middle mb-0">
                <thead>
                    <tr>
                        <th>Role</th>
                        <th>Type/Scope</th>
                        <th>ID</th>
                        <th>UID</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($roles as $role) { ?>
                    <tr>
                        <td class="fw-semibold"><?php echo htmlspecialchars($role['role'] ?? ''); ?></td>
                        <td><?php echo htmlspecialchars($role['type'] ?? ''); ?></td>
                        <td><?php echo (int)($role['id'] ?? 0); ?></td>
                        <td><code><?php echo htmlspecialchars($role['uid'] ?? ''); ?></code></td>
                    </tr>
                    <?php } ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php } ?>

<script>
document.addEventListener('DOMContentLoaded', function () {
    var copyBtn = document.getElementById('copySniffPayload');
    var payloadBlock = document.getElementById('sniffPayloadBlock');
    if (!copyBtn || !payloadBlock) {
        return;
    }
    copyBtn.addEventListener('click', function () {
        var text = payloadBlock.textContent || '';
        navigator.clipboard.writeText(text).then(function () {
            copyBtn.classList.remove('btn-primary');
            copyBtn.classList.add('btn-success');
            copyBtn.innerHTML = '<i class="bi bi-check2"></i> Copied';
            setTimeout(function () {
                copyBtn.classList.remove('btn-success');
                copyBtn.classList.add('btn-primary');
                copyBtn.innerHTML = '<i class="bi bi-clipboard"></i> Copy JSON';
            }, 1200);
        });
    });
});
</script>
