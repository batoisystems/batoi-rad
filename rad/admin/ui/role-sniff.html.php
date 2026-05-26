<?php
$payload = $this->runData['data']['sniff_payload'] ?? [];
$mode = $this->runData['data']['sniff_mode'] ?? 'single';
$jsonPayload = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

if ($mode === 'collection') {
    $roles = $payload['roles'] ?? [];
    $platformPayload = [
        'object' => $payload['object'] ?? ['kind' => 'role_collection'],
        'filters' => $payload['filters'] ?? [],
        'roles' => $payload['roles_by_scope']['platform'] ?? [],
        'stats' => [
            'total' => count($payload['roles_by_scope']['platform'] ?? []),
            'scope' => 'platform',
        ],
    ];
    $workspacePayload = [
        'object' => $payload['object'] ?? ['kind' => 'role_collection'],
        'filters' => $payload['filters'] ?? [],
        'roles' => $payload['roles_by_scope']['workspace'] ?? [],
        'stats' => [
            'total' => count($payload['roles_by_scope']['workspace'] ?? []),
            'scope' => 'workspace',
        ],
    ];
    $platformJson = json_encode($platformPayload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    $workspaceJson = json_encode($workspacePayload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
?>
<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <a href="<?php echo htmlspecialchars($this->runData['route']['backlink']); ?>" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left-circle"></i> Back to Roles
    </a>
    <div class="d-flex flex-wrap gap-2">
        <button type="button" class="btn btn-primary sniff-copy-btn" data-copy-target="sniffPayloadBlock">
            <i class="bi bi-clipboard"></i> Copy All JSON
        </button>
        <button type="button" class="btn btn-outline-dark sniff-copy-btn" data-copy-target="platformPayloadBlock">
            <i class="bi bi-clipboard"></i> Copy Platform Roles
        </button>
        <button type="button" class="btn btn-outline-primary sniff-copy-btn" data-copy-target="workspacePayloadBlock">
            <i class="bi bi-clipboard"></i> Copy Workspace Roles
        </button>
    </div>
</div>

<div class="card mb-3">
    <div class="card-body d-flex justify-content-between align-items-center flex-wrap gap-3">
        <div>
            <h2 class="h5 mb-1">Meta Sniff: Role Catalog</h2>
            <div class="text-muted small">Includes the role list for the current filter state.</div>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <span class="badge bg-secondary"><?php echo (int)($payload['stats']['total'] ?? count($roles)); ?> total</span>
            <span class="badge bg-dark"><?php echo (int)($payload['stats']['platform'] ?? 0); ?> platform</span>
            <span class="badge bg-primary"><?php echo (int)($payload['stats']['workspace'] ?? 0); ?> workspace</span>
        </div>
    </div>
</div>

<div class="card mb-3">
    <div class="card-header bg-white">
        <strong>JSON Payload</strong>
        <div class="small text-muted">Full role list payload for the current catalog filter.</div>
    </div>
    <div class="card-body">
        <pre class="bg-dark text-light rounded p-3 mb-0" style="white-space: pre-wrap;" id="sniffPayloadBlock"><?php echo htmlspecialchars($jsonPayload ?? '', ENT_QUOTES, 'UTF-8'); ?></pre>
    </div>
</div>

<div class="row g-3 mb-3">
    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <strong>Platform Roles JSON</strong>
                <span class="text-muted small"><?php echo count($platformPayload['roles']); ?> roles</span>
            </div>
            <div class="card-body">
                <pre class="bg-light rounded p-3 mb-0" style="white-space: pre-wrap;" id="platformPayloadBlock"><?php echo htmlspecialchars($platformJson ?? '', ENT_QUOTES, 'UTF-8'); ?></pre>
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <strong>Workspace Roles JSON</strong>
                <span class="text-muted small"><?php echo count($workspacePayload['roles']); ?> roles</span>
            </div>
            <div class="card-body">
                <pre class="bg-light rounded p-3 mb-0" style="white-space: pre-wrap;" id="workspacePayloadBlock"><?php echo htmlspecialchars($workspaceJson ?? '', ENT_QUOTES, 'UTF-8'); ?></pre>
            </div>
        </div>
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
<?php } else { ?>
<?php
$role = $this->runData['data']['role'] ?? [];
$assignments = $payload['assignments'] ?? [];
$defaultRoute = $payload['default_route'] ?? null;
?>
<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <a href="<?php echo htmlspecialchars($this->runData['route']['backlink']); ?>" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left-circle"></i> Back to Role
    </a>
    <button type="button" class="btn btn-primary sniff-copy-btn" data-copy-target="sniffPayloadBlock">
        <i class="bi bi-clipboard"></i> Copy JSON
    </button>
</div>

<div class="card mb-3">
    <div class="card-body d-flex justify-content-between align-items-center flex-wrap gap-3">
        <div>
            <h2 class="h5 mb-1">Meta Sniff: <?php echo htmlspecialchars($role['s_role_name'] ?? ''); ?></h2>
            <div class="text-muted small">ID: <?php echo (int)($role['id'] ?? 0); ?> · UID: <code><?php echo htmlspecialchars($role['uid'] ?? ''); ?></code></div>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <span class="badge bg-light text-dark">Type/Scope: <?php echo htmlspecialchars($role['s_scope'] ?? ''); ?></span>
            <?php if (!empty($role['s_code'])) { ?>
                <span class="badge bg-light text-dark">Code: <?php echo htmlspecialchars($role['s_code']); ?></span>
            <?php } ?>
            <span class="badge bg-info text-dark"><?php echo count($assignments); ?> assignments</span>
        </div>
    </div>
</div>

<div class="card mb-3">
    <div class="card-header bg-white">
        <strong>JSON Payload</strong>
        <div class="small text-muted">Includes role metadata, default route, and assignment records.</div>
    </div>
    <div class="card-body">
        <pre class="bg-dark text-light rounded p-3 mb-0" style="white-space: pre-wrap;" id="sniffPayloadBlock"><?php echo htmlspecialchars($jsonPayload ?? '', ENT_QUOTES, 'UTF-8'); ?></pre>
    </div>
</div>

<?php if ($defaultRoute) { ?>
<div class="card mb-3">
    <div class="card-header bg-white">
        <strong>Default Route</strong>
    </div>
    <div class="card-body">
        <div class="text-muted small">Name</div>
        <div class="fw-semibold"><?php echo htmlspecialchars($defaultRoute['name'] ?? ''); ?></div>
        <div class="text-muted small mt-2">ID: <?php echo (int)($defaultRoute['id'] ?? 0); ?> · UID: <code><?php echo htmlspecialchars($defaultRoute['uid'] ?? ''); ?></code></div>
    </div>
</div>
<?php } ?>
<?php } ?>

<script>
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.sniff-copy-btn').forEach(function (button) {
        button.addEventListener('click', function () {
            var targetId = button.getAttribute('data-copy-target');
            var payloadBlock = document.getElementById(targetId);
            if (!payloadBlock) {
                return;
            }
            var text = payloadBlock.textContent || '';
            navigator.clipboard.writeText(text).then(function () {
                var originalHtml = button.innerHTML;
                var originalClasses = button.className;
                button.classList.remove('btn-primary', 'btn-outline-primary', 'btn-outline-dark');
                button.classList.add('btn-success');
                button.innerHTML = '<i class="bi bi-check2"></i> Copied';
                setTimeout(function () {
                    button.className = originalClasses;
                    button.innerHTML = originalHtml;
                }, 1200);
            });
        });
    });
});
</script>
