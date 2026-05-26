<?php
$diffData = $this->runData['data']['diff'] ?? [];
$diff = $diffData['diff'] ?? [];
$job = $diffData['job'] ?? '';
$version = $diffData['version']['id'] ?? '';
?>

<div class="container-fluid py-3">
    <div class="card mb-3">
        <div class="card-body d-flex justify-content-between flex-wrap gap-2">
            <div>
                <div class="text-muted small">Diff</div>
                <div class="fw-semibold"><?php echo htmlspecialchars($job); ?></div>
                <div class="small text-muted">Version: <code><?php echo htmlspecialchars($version); ?></code></div>
            </div>
            <a href="<?php echo $this->runData['route']['backlink']; ?>" class="btn btn-outline-secondary">Back</a>
        </div>
    </div>
    <div class="card">
        <div class="card-body">
            <?php if (empty($diff)) { ?>
                <div class="alert alert-warning mb-0">No diff available.</div>
            <?php } else { ?>
                <div class="table-responsive">
                    <table class="table table-sm font-monospace">
                        <thead>
                            <tr>
                                <th style="width:60px;">Old</th>
                                <th style="width:60px;">New</th>
                                <th>Content</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($diff as $row) {
                            $type = $row['type'] ?? '';
                            $old = $row['old'] ?? '';
                            $new = $row['new'] ?? '';
                            $oldLine = $row['old_line'] ?? '';
                            $newLine = $row['new_line'] ?? '';
                            $tone = $type === 'insert' ? 'table-success' : ($type === 'delete' ? 'table-danger' : ($type === 'replace' ? 'table-warning' : ''));
                            $content = $type === 'delete' ? $old : $new;
                        ?>
                            <tr class="<?php echo $tone; ?>">
                                <td><?php echo htmlspecialchars((string)$oldLine); ?></td>
                                <td><?php echo htmlspecialchars((string)$newLine); ?></td>
                                <td><?php echo htmlspecialchars($content); ?></td>
                            </tr>
                        <?php } ?>
                        </tbody>
                    </table>
                </div>
            <?php } ?>
        </div>
    </div>
</div>
