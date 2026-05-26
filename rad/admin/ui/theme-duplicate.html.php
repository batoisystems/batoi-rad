<?php
$duplicate = $this->runData['data']['duplicate'] ?? [];
$source = $duplicate['source'] ?? '';
$stats = $duplicate['stats'] ?? [];
$suggested = $duplicate['suggested_name'] ?? ($source . '_copy');
$radAdminUrl = $this->runData['route']['rad_admin_url'];
$backUrl = $this->runData['route']['backlink'] ?? ($radAdminUrl . '/theme/viewone/' . urlencode($source));
?>
<div class="container">
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
        <div>
            <a class="text-decoration-none small" href="<?php echo $backUrl; ?>">
                <i class="bi bi-arrow-left"></i> Back to Template
            </a>
            <h2 class="mb-1 mt-2">Duplicate <?php echo htmlspecialchars($source); ?></h2>
            <div class="text-muted small">Clone the template with a new filename.</div>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-lg-7">
            <div class="card mb-4">
                <div class="card-body">
                    <h5 class="card-title mb-3">New Template Details</h5>
                    <form method="post" class="needs-validation" novalidate>
                        <div class="mb-3">
                            <label for="template_name" class="form-label">Template name</label>
                            <div class="input-group">
                                <span class="input-group-text">.tpl.php</span>
                                <input type="text"
                                       class="form-control"
                                       id="template_name"
                                       name="template_name"
                                       value="<?php echo htmlspecialchars($suggested); ?>"
                                       required>
                                <div class="invalid-feedback">Please provide a valid template name.</div>
                            </div>
                            <div class="form-text">Only lowercase letters, numbers, and underscores are allowed.</div>
                        </div>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-files me-1"></i>Duplicate Template
                        </button>
                    </form>
                </div>
            </div>
        </div>
        <div class="col-lg-5">
            <div class="card mb-4 h-100">
                <div class="card-body">
                    <h5 class="card-title mb-3">Source Snapshot</h5>
                    <ul class="list-unstyled mb-0">
                        <li class="mb-2"><strong>File:</strong> <?php echo htmlspecialchars($source . '.tpl.php'); ?></li>
                        <li class="mb-2"><strong>Size:</strong> <?php echo $stats['size_human'] ?? '—'; ?></li>
                        <li class="mb-2"><strong>Lines:</strong> <?php echo $stats['lines'] ?? '—'; ?></li>
                        <li class="mb-2"><strong>Updated:</strong> <?php echo $stats['modified'] ?? '—'; ?></li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>
