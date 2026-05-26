<?php
$updated = $this->runData['data']['backfill']['updated'] ?? [];
$targetRole = $this->runData['data']['backfill']['target_role'] ?? null;
?>

<div class="card shadow-sm">
    <div class="card-body">
        <p>
            Default portal role ID: <strong><?php echo $targetRole ? (int)$targetRole : 'not configured'; ?></strong>
        </p>
        <?php if (empty($updated)): ?>
            <div class="alert alert-info mb-0">
                No SaaS user required changes.
            </div>
        <?php else: ?>
            <div class="alert alert-success">
                <?php echo count($updated); ?> SaaS user(s) were updated.
            </div>
            <div class="table-responsive">
                <table class="table table-sm align-middle">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>UID</th>
                            <th>Name</th>
                            <th>Username</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($updated as $row): ?>
                        <tr>
                            <td><?php echo (int)$row['id']; ?></td>
                            <td><?php echo htmlspecialchars($row['uid'] ?? ''); ?></td>
                            <td><?php echo htmlspecialchars($row['name'] ?? ''); ?></td>
                            <td><?php echo htmlspecialchars($row['username'] ?? ''); ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>
