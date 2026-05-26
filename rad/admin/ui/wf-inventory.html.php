<?php
$states = $this->runData['data']['wf_states'] ?? [];
$actions = $this->runData['data']['wf_actions'] ?? [];
$radAdminUrl = $this->runData['route']['rad_admin_url'] ?? '';
?>

<div class="card shadow-sm border-0 mb-4">
    <div class="card-body d-flex flex-wrap justify-content-between align-items-center gap-2">
        <div>
            <div class="text-muted small">States and transitions available for binding to data controllers.</div>
        </div>
        <div class="btn-group">
            <a href="<?php echo $radAdminUrl; ?>/wfstate/add" class="btn btn-primary btn-sm"><i class="bi bi-plus-circle me-1"></i>Add State</a>
            <a href="<?php echo $radAdminUrl; ?>/wfaction/add" class="btn btn-outline-primary btn-sm"><i class="bi bi-diagram-3 me-1"></i>Add Transition</a>
        </div>
    </div>
</div>

<div class="row g-3">
    <div class="col-12 col-lg-6">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white">
                <h6 class="mb-0">States</h6>
                <div class="text-muted small">Initial and terminal states highlighted.</div>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm mb-0 align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>Name</th>
                                <th class="text-end">Order</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($states)) { ?>
                                <tr><td colspan="2" class="text-muted text-center small py-3">No states defined.</td></tr>
                            <?php } ?>
                            <?php foreach ($states as $state) { ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($state['s_name'] ?? ''); ?></td>
                                    <td class="text-end text-muted"><?php echo htmlspecialchars($state['s_flow_order'] ?? ''); ?></td>
                                </tr>
                            <?php } ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    <div class="col-12 col-lg-6">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white">
                <h6 class="mb-0">Transitions</h6>
                <div class="text-muted small">Allowed moves between states.</div>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm mb-0 align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>Name</th>
                                <th>From → To</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($actions)) { ?>
                                <tr><td colspan="2" class="text-muted text-center small py-3">No transitions defined.</td></tr>
                            <?php } ?>
                            <?php foreach ($actions as $action) { ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($action['s_name'] ?? ''); ?></td>
                                    <td>
                                        <span class="fw-semibold"><?php echo htmlspecialchars($action['from_state'] ?? ''); ?></span>
                                        <i class="bi bi-arrow-right-short"></i>
                                        <span class="fw-semibold"><?php echo htmlspecialchars($action['to_state'] ?? ''); ?></span>
                                    </td>
                                </tr>
                            <?php } ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
