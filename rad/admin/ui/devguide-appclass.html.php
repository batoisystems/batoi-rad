<?php
$data = $this->runData['data']['class_source'] ?? null;
$meta = $data['meta'] ?? null;
$source = $data['source'] ?? '';
$radAdminUrl = $this->runData['route']['rad_admin_url'] ?? '';
?>

<?php if (!$meta) { ?>
    <div class="alert alert-warning">
        Class source is unavailable. Return to <a href="<?php echo htmlspecialchars($radAdminUrl); ?>/devguide/appclasses">Application Classes</a>.
    </div>
<?php } else { ?>
    <div class="d-flex justify-content-between align-items-start mb-3">
        <div>
            <h4 class="mb-1"><?php echo htmlspecialchars($meta['short_name'] ?? ''); ?></h4>
            <div class="text-muted small mb-1"><?php echo htmlspecialchars($meta['name'] ?? ''); ?></div>
            <div class="text-muted small">File: <code><?php echo htmlspecialchars($meta['file'] ?? ''); ?></code></div>
        </div>
        <a class="btn btn-outline-secondary btn-sm" href="<?php echo htmlspecialchars($radAdminUrl); ?>/devguide/appclasses">
            <i class="bi bi-arrow-left me-1"></i>Back to classes
        </a>
    </div>

    <div class="card shadow-sm">
        <div class="card-header d-flex justify-content-between align-items-center">
            <span class="fw-semibold">Source (read-only)</span>
            <button class="btn btn-outline-primary btn-sm" type="button" id="copySource">
                <i class="bi bi-clipboard me-1"></i>Copy
            </button>
        </div>
        <div class="card-body" style="background:#0b1221;">
            <pre id="codeBlock" class="mb-0" style="color:#d1e3ff; white-space:pre; overflow:auto; max-height:75vh;"><?php echo htmlspecialchars($source); ?></pre>
        </div>
    </div>

    <script>
        (function() {
            const btn = document.getElementById('copySource');
            const block = document.getElementById('codeBlock');
            if (btn && block) {
                btn.addEventListener('click', async () => {
                    try {
                        await navigator.clipboard.writeText(block.textContent);
                        btn.textContent = 'Copied';
                        setTimeout(() => { btn.innerHTML = '<i class="bi bi-clipboard me-1"></i>Copy'; }, 2000);
                    } catch (e) {
                        btn.textContent = 'Copy failed';
                        setTimeout(() => { btn.innerHTML = '<i class="bi bi-clipboard me-1"></i>Copy'; }, 2000);
                    }
                });
            }
        })();
    </script>
<?php } ?>
