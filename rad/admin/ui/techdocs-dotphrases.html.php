<?php
$radAdminUrl = $this->runData['route']['rad_admin_url'] ?? '';
?>
<div class="card border-0 shadow-sm mb-4">
    <div class="card-body d-flex flex-column flex-lg-row align-items-lg-center gap-3">
        <div>
            <p class="mb-0 text-muted">Dot phrases are reusable text snippets callable by a short trigger (e.g., <code>.addr</code>) across platform and SaaS scopes.</p>
        </div>
        <div class="ms-lg-auto d-flex gap-2 flex-wrap">
            <a href="<?php echo $radAdminUrl; ?>/techdocs/view" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left me-1"></i>Back to Tech Docs</a>
            <a href="<?php echo $radAdminUrl; ?>/dotphrase/view" class="btn btn-outline-primary btn-sm"><i class="bi bi-three-dots me-1"></i>Manage Dot Phrases</a>
            <a href="<?php echo $radAdminUrl; ?>/devguide/appclasses" class="btn btn-outline-secondary btn-sm"><i class="bi bi-journal-code me-1"></i>Application Classes</a>
        </div>
    </div>
</div>

<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-white">
        <h3 class="h6 mb-0">What is a dot phrase?</h3>
    </div>
    <div class="card-body">
        <ul class="mb-0">
            <li>A dot phrase is a short trigger (e.g., <code>.addr</code>) that expands to predefined content.</li>
            <li>Scope-aware: <code>platform</code> (non-SaaS) phrases are global; <code>workspace</code> phrases are tied to a workspace and still require space UID on SaaS routes.</li>
            <li>Visibility: private (owner only) or public within the scope; superuser bypasses permissions but still needs space UID on SaaS routes.</li>
            <li>Use cases: canned responses, templated notes, address blocks, onboarding steps.</li>
        </ul>
    </div>
</div>

<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-white">
        <h3 class="h6 mb-0">Using in custom applications</h3>
    </div>
    <div class="card-body">
        <ol class="mb-3">
            <li>Call the app helper: <code>\Core\App\DotPhrase::resolve($phrase, $entityId, $spaceId, $scope)</code>. Provide <code>$spaceId</code> for SaaS scopes.</li>
            <li>Handle null results (phrase not found or not permitted).</li>
            <li>Optionally log usage: <code>recordUsage($dotphraseId, $entityId, $spaceId, $context)</code>.</li>
        </ol>
        <pre class="bg-light p-3 small rounded mb-3"><code>// Example (platform scope)
$dp = new \Core\App\DotPhrase($runData['db'], $runData['errorHandler']);
$row = $dp->resolve('.addr', $runData['entity']['id'] ?? null, null, 'platform');
if ($row) {
    echo $row['s_content'];
}</code></pre>
        <pre class="bg-light p-3 small rounded"><code>// Example (SaaS scope with space UID resolved to id)
$spaceId = 48; // look up by UID in s_space
$row = $dp->resolve('.welcome', $runData['entity']['id'] ?? null, $spaceId, 'workspace');</code></pre>
    </div>
</div>

<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-white">
        <h3 class="h6 mb-0">Managing via RAD Admin</h3>
    </div>
    <div class="card-body">
        <ul class="mb-0">
            <li>Go to <strong>Data &amp; Config → Dot Phrases</strong> to add/edit/archive.</li>
            <li>Set scope = platform for non-SaaS; for SaaS scopes, choose the workspace.</li>
            <li>Use Public = Yes to share within the scope; keep Private to owner-only.</li>
        </ul>
    </div>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-header bg-white">
        <h3 class="h6 mb-0">API usage</h3>
    </div>
    <div class="card-body">
        <p class="text-muted small mb-2">Available through system API services (enable the keys on the API account):</p>
        <ul class="mb-0">
            <li><code>app.dotphrase.resolve</code> (phrase, entityId, spaceId, scope)</li>
            <li><code>app.dotphrase.list|get|create|update|archive|record_usage</code></li>
        </ul>
    </div>
</div>
