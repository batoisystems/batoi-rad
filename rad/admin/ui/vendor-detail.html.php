<?php
$vendor = $this->runData['data']['vendor'] ?? [];
$fs = $vendor['filesystem'] ?? [];
$packagesDir = $this->runData['data']['packages_dir'] ?? '';
$installed = !empty($fs['installed']);
$radAdminUrl = $this->runData['route']['rad_admin_url'];
?>
<div class="row g-3">
    <div class="col-lg-8">
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start gap-3">
                    <div>
                        <h2 class="h4 mb-1"><?php //echo htmlspecialchars($vendor['s_title'] ?? $vendor['s_handle']); ?></h2>
                        <p class="text-muted mb-2"><?php echo htmlspecialchars($vendor['s_summary'] ?? 'No summary provided.'); ?></p>
                        <div class="d-flex flex-wrap gap-2">
                            <span class="badge bg-<?php echo $installed ? 'success' : 'warning'; ?>"><?php echo $installed ? 'Installed' : 'Missing'; ?></span>
                            <?php if (!empty($vendor['s_category'])) { ?><span class="badge bg-light text-dark"><?php echo htmlspecialchars($vendor['s_category']); ?></span><?php } ?>
                        </div>
                    </div>
                    <div class="text-end">
                        <?php if (!empty($vendor['s_doc_url'])) { ?>
                            <a href="<?php echo htmlspecialchars($vendor['s_doc_url']); ?>" target="_blank" class="btn btn-outline-secondary btn-sm"><i class="bi bi-journal-text me-1"></i>Docs</a>
                        <?php } ?>
                        <a href="<?php echo $radAdminUrl; ?>/vendor/edit/<?php echo $vendor['uid']; ?>" class="btn btn-outline-primary btn-sm"><i class="bi bi-pencil-square me-1"></i>Edit</a>
                        <a href="<?php echo $radAdminUrl; ?>/vendor/refresh/<?php echo $vendor['uid']; ?>" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-clockwise me-1"></i>Refresh</a>
                    </div>
                </div>
                <dl class="row mt-3 mb-0">
                    <dt class="col-sm-3 text-muted">Handle</dt>
                    <dd class="col-sm-9"><code><?php echo htmlspecialchars($vendor['s_handle']); ?></code></dd>
                    <dt class="col-sm-3 text-muted">Filesystem path</dt>
                    <dd class="col-sm-9"><code><?php echo htmlspecialchars($fs['path'] ?? $vendor['s_install_path'] ?? ''); ?></code></dd>
                    <dt class="col-sm-3 text-muted">Version</dt>
                    <dd class="col-sm-9 text-monospace"><?php echo htmlspecialchars($fs['version'] ?? $vendor['s_version_installed'] ?? 'Unknown'); ?></dd>
                    <dt class="col-sm-3 text-muted">Package size</dt>
                    <dd class="col-sm-9"><?php echo htmlspecialchars($fs['size_human'] ?? '—'); ?></dd>
                    <dt class="col-sm-3 text-muted">Last scan</dt>
                    <dd class="col-sm-9"><?php echo htmlspecialchars($vendor['s_last_scan'] ?? 'Not scanned'); ?></dd>
                </dl>
            </div>
        </div>
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white">
                <div class="d-flex justify-content-between align-items-center">
                    <h3 class="h6 mb-0">Usage Notes</h3>
                    <button type="button" class="btn btn-outline-secondary btn-sm" id="copyUsage"><i class="bi bi-clipboard"></i> Copy</button>
                </div>
            </div>
            <div class="card-body">
                <?php
                // Usage notes are admin-supplied. Preserve code formatting with a styled container.
                $usageNotes = $vendor['s_usage_notes'] ?? '';
                if ($usageNotes !== '') {
                    $usageNotes = html_entity_decode($usageNotes, ENT_QUOTES | ENT_HTML5, 'UTF-8');
                }
                if ($usageNotes !== '') {
                    echo '<div class="vendor-usage" id="usageNotes">' . $usageNotes . '</div>';
                } else {
                    echo '<p class="text-muted mb-0">Add usage instructions (use <pre><code>…</code></pre> for code).</p>';
                }
                ?>
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="card border-0 shadow-sm mb-3" id="install-library">
            <div class="card-header bg-white">
                <h3 class="h6 mb-0"><?php echo $installed ? 'Upgrade or Reinstall' : 'Install Library'; ?></h3>
            </div>
            <div class="card-body">
                <form action="<?php echo $radAdminUrl; ?>/vendor/install/<?php echo $vendor['uid']; ?>" method="post" enctype="multipart/form-data">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($this->runData['request']->csrf_token); ?>">
                    <div class="mb-3">
                        <label class="form-label">Upload ZIP package</label>
                        <input type="file" name="package_archive" class="form-control" accept=".zip">
                        <small class="text-muted">Optional. Leave blank to link an existing folder under <code>rad/vendor/<?php echo htmlspecialchars($vendor['s_handle']); ?></code>.</small>
                    </div>
                    <button class="btn btn-primary w-100" type="submit"><i class="bi bi-cloud-arrow-down me-1"></i><?php echo $installed ? 'Apply Upgrade' : 'Install'; ?></button>
                </form>
                <p class="text-muted small mt-3 mb-0">Packages are stored in <code><?php echo htmlspecialchars($packagesDir); ?></code>.</p>
            </div>
        </div>
    </div>
</div>

<style>
.vendor-usage pre {
    background: #f8f9fa;
    border: 1px solid #e9ecef;
    border-radius: .375rem;
    padding: .75rem;
    white-space: pre;
    overflow-x: auto;
    font-family: Menlo, Monaco, Consolas, "Courier New", monospace;
    font-size: .9rem;
    margin-bottom: 0;
}
.vendor-usage code {
    font-family: Menlo, Monaco, Consolas, "Courier New", monospace;
}
</style>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const btn = document.getElementById('copyUsage');
    const notes = document.getElementById('usageNotes');
    if (btn && notes) {
        btn.addEventListener('click', async () => {
            const text = notes.innerText || '';
            try {
                await navigator.clipboard.writeText(text);
                btn.innerHTML = '<i class="bi bi-clipboard-check"></i> Copied';
                setTimeout(() => { btn.innerHTML = '<i class="bi bi-clipboard"></i> Copy'; }, 2000);
            } catch (e) {
                btn.innerHTML = '<i class="bi bi-clipboard-x"></i> Failed';
                setTimeout(() => { btn.innerHTML = '<i class="bi bi-clipboard"></i> Copy'; }, 2000);
            }
        });
    }
});
</script>
