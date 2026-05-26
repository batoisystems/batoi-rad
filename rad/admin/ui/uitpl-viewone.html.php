<?php
$detail = $this->runData['data']['template'] ?? [];
$relative = $detail['relative'] ?? '';
$stats = $detail['stats'] ?? [];
$versions = $detail['versions'] ?? [];
$encoded = rtrim(strtr(base64_encode($relative), '+/', '-_'), '=');
$editUrl = $this->runData['route']['rad_admin_url'] . '/uitpl/edit/' . $encoded;
$previewUrl = $this->runData['route']['rad_admin_url'] . '/uitpl/preview/' . $encoded;
$backUrl = $this->runData['route']['rad_admin_url'] . '/uitpl/view';
$timezone = $this->runData['entity']['timezone'] ?? ($this->runData['config']['sys']['timezone_default'] ?? 'UTC');
?>
<div class="container-fluid py-3">
    <div class="card mb-4">
        <div class="card-body d-flex flex-wrap justify-content-between gap-3">
            <div>
                <h3 class="mb-1"><?php echo htmlspecialchars($relative); ?></h3>
                <div class="text-muted small">Path: <code>rad/data/uitpl/<?php echo htmlspecialchars($relative); ?></code></div>
                <div class="small text-muted">Size: <?php echo htmlspecialchars($stats['size_human'] ?? ''); ?> · Modified: <?php echo !empty($stats['modified']) ? \Core\Sys\TimeHelper::formatUtc($stats['modified'], $timezone) : 'NA'; ?></div>
            </div>
            <div class="d-flex gap-2">
                <a class="btn btn-outline-secondary" href="<?php echo $backUrl; ?>">Back</a>
                <a class="btn btn-outline-secondary" href="<?php echo $previewUrl; ?>" target="_blank">
                    <i class="bi bi-eye me-2"></i>Preview
                </a>
                <a class="btn btn-primary text-white" href="<?php echo $editUrl; ?>"><i class="bi bi-pencil-square me-2"></i>Edit</a>
            </div>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-header d-flex justify-content-between align-items-center">
            <div class="fw-semibold">Live Preview</div>
            <a class="btn btn-sm btn-outline-secondary" href="<?php echo $previewUrl; ?>" target="_blank">Open in new tab</a>
        </div>
        <div class="card-body p-0">
            <iframe title="UI Template Preview" src="<?php echo $previewUrl; ?>" style="width:100%; height:420px; border:0;"></iframe>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-header d-flex justify-content-between align-items-center">
            <div class="fw-semibold">Versions</div>
        </div>
        <div class="card-body">
            <?php if (empty($versions)) { ?>
                <div class="alert alert-warning mb-0">No versions captured yet. Save from the editor to create the first version.</div>
            <?php } else { ?>
                <div class="table-responsive">
                    <table class="table table-sm align-middle">
                        <thead>
                            <tr>
                                <th>Version</th>
                                <th>Timestamp</th>
                                <th>User</th>
                                <th class="text-end">Size</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($versions as $version) {
                                $verId = $version['id'] ?? '';
                                $diffUrl = $this->runData['route']['rad_admin_url'] . '/uitpl/diffversion/' . $encoded . '/' . urlencode($verId);
                                $downloadUrl = $this->runData['route']['rad_admin_url'] . '/uitpl/downloadversion/' . $encoded . '/' . urlencode($verId);
                                $restoreUrl = $this->runData['route']['rad_admin_url'] . '/uitpl/restoreversion/' . $encoded . '/' . urlencode($verId);
                            ?>
                            <tr>
                                <td><code><?php echo htmlspecialchars($verId); ?></code></td>
                                <td><?php echo !empty($version['timestamp']) ? \Core\Sys\TimeHelper::formatUtc($version['timestamp'], $timezone) : 'NA'; ?></td>
                                <td><?php echo htmlspecialchars($version['user'] ?? ''); ?></td>
                                <td class="text-end"><?php echo htmlspecialchars($version['size_human'] ?? ''); ?></td>
                                <td class="text-end">
                                    <a class="btn btn-sm btn-outline-primary" href="<?php echo $diffUrl; ?>">Diff</a>
                                    <a class="btn btn-sm btn-outline-secondary" href="<?php echo $downloadUrl; ?>">Download</a>
                                    <form action="<?php echo $restoreUrl; ?>" method="post" class="d-inline">
                                        <button class="btn btn-sm btn-outline-warning" type="submit">Restore</button>
                                    </form>
                                </td>
                            </tr>
                            <?php } ?>
                        </tbody>
                    </table>
                </div>
            <?php } ?>
        </div>
    </div>
</div>
