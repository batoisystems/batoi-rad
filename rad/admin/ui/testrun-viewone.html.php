<?php
$run = $this->runData['data']['run'] ?? [];
$plan = $this->runData['data']['plan'] ?? [];
$results = $this->runData['data']['results'] ?? [];
?>

<div class="card mb-3">
    <div class="card-body">
        <div class="text-muted small">Plan: <?php echo htmlspecialchars($plan['s_name'] ?? ''); ?> • Status: <?php echo htmlspecialchars($run['s_status'] ?? ''); ?></div>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table align-middle">
                <thead>
                    <tr>
                        <th>Item</th>
                        <th>Type</th>
                        <th>Status</th>
                        <th>Comment</th>
                        <th>Duration</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($results)) { ?>
                        <tr><td colspan="6" class="text-center text-muted">No results yet.</td></tr>
                    <?php } else { foreach ($results as $res) { ?>
                        <tr>
                            <td><?php echo htmlspecialchars($res['item_name'] ?? ''); ?></td>
                            <td><?php echo htmlspecialchars($res['item_type'] ?? ''); ?></td>
                            <td><?php echo htmlspecialchars($res['s_status'] ?? ''); ?></td>
                            <td class="small text-muted"><?php echo htmlspecialchars($res['s_comment'] ?? ''); ?></td>
                            <td class="small text-muted"><?php echo $res['s_duration_ms'] ? (int)$res['s_duration_ms'].' ms' : '—'; ?></td>
                            <td>
                                <form class="d-flex gap-1 align-items-center" method="post" action="<?php echo $this->runData['route']['rad_admin_url']; ?>/testrun/updateresult/<?php echo (int)$run['id']; ?>">
                                    <input type="hidden" name="id" value="<?php echo (int)$res['id']; ?>">
                                    <select name="s_status" class="form-select form-select-sm" style="width:130px;">
                                        <?php foreach (['not_run','passed','failed','blocked'] as $st) { ?>
                                            <option value="<?php echo $st; ?>" <?php echo ($res['s_status'] ?? '') === $st ? 'selected' : ''; ?>><?php echo ucfirst($st); ?></option>
                                        <?php } ?>
                                    </select>
                                    <input type="text" name="s_comment" class="form-control form-control-sm" style="width:200px;" placeholder="Comment" value="<?php echo htmlspecialchars($res['s_comment'] ?? ''); ?>">
                                    <input type="number" name="s_duration_ms" class="form-control form-control-sm" style="width:90px;" placeholder="ms" value="<?php echo htmlspecialchars($res['s_duration_ms'] ?? ''); ?>">
                                    <button class="btn btn-sm btn-outline-primary" type="submit">Save</button>
                                </form>
                            </td>
                        </tr>
                    <?php } } ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
