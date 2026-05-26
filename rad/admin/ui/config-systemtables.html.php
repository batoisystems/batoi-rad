<?php
$inventory = $this->runData['data']['system_table_inventory'] ?? [];
$present = (int)($this->runData['data']['total_present'] ?? 0);
$missing = (int)($this->runData['data']['total_missing'] ?? 0);
?>

<div class="d-flex justify-content-end gap-2 mb-3">
    <span class="badge text-bg-success">Present: <?php echo $present; ?></span>
    <span class="badge text-bg-secondary">Missing: <?php echo $missing; ?></span>
</div>

<div class="card shadow-sm border-0">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-sm mb-0 align-middle">
                <thead class="table-light">
                    <tr>
                        <th>Name</th>
                        <th>Description</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($inventory as $row) { ?>
                        <tr>
                            <td><code><?php echo htmlspecialchars($row['name']); ?></code></td>
                            <td class="text-muted small"><?php echo htmlspecialchars($row['description']); ?></td>
                        </tr>
                    <?php } ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
