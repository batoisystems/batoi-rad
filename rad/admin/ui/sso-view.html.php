<?php
$providers = $this->runData['data']['providers'] ?? [];
$summary = $this->runData['data']['summary'] ?? [];
$radAdminUrl = $this->runData['route']['rad_admin_url'] ?? '';
?>
<div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center mb-3 gap-3">
    <div>
        <!-- <h2 class="h4 mb-0">SSO Providers</h2> -->
        <div class="text-muted small">Configure OIDC/OAuth providers used by login, then test each provider before enabling for users.</div>
    </div>
    <div class="btn-group">
        <a href="<?php echo $radAdminUrl; ?>/sso/setup" class="btn btn-outline-primary"><i class="bi bi-magic me-1"></i>Setup Wizard</a>
        <a href="<?php echo $radAdminUrl; ?>/sso/configassistant" class="btn btn-outline-dark"><i class="bi bi-file-earmark-code me-1"></i>Config Assistant</a>
        <a href="<?php echo $radAdminUrl; ?>/sso/add" class="btn btn-primary"><i class="bi bi-plus-circle me-1"></i>Add Provider</a>
        <a href="<?php echo $radAdminUrl; ?>/ssoclient/view" class="btn btn-outline-info"><i class="bi bi-diagram-2 me-1"></i>SSO Server Clients</a>
        <a href="<?php echo $radAdminUrl; ?>/sso/issue" class="btn btn-outline-secondary"><i class="bi bi-box-arrow-in-right me-1"></i>Issue Assertion</a>
    </div>
</div>

<div class="row g-3 mb-3">
    <div class="col-6 col-lg-3">
        <div class="card shadow-sm h-100">
            <div class="card-body">
                <div class="text-muted small">Total Providers</div>
                <div class="h3 mb-0"><?php echo (int)($summary['total'] ?? 0); ?></div>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="card shadow-sm h-100">
            <div class="card-body">
                <div class="text-muted small">Active</div>
                <div class="h3 mb-0 text-success"><?php echo (int)($summary['active'] ?? 0); ?></div>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="card shadow-sm h-100">
            <div class="card-body">
                <div class="text-muted small">Inactive</div>
                <div class="h3 mb-0 text-secondary"><?php echo (int)($summary['inactive'] ?? 0); ?></div>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="card shadow-sm h-100">
            <div class="card-body">
                <div class="text-muted small">Needs Attention</div>
                <div class="h3 mb-0 text-warning"><?php echo (int)($summary['needs_attention'] ?? 0); ?></div>
            </div>
        </div>
    </div>
</div>

<div class="alert alert-info small">
    <strong>Recommended setup:</strong> Add provider, confirm callback URL at the identity provider, test login from this page, then set status to <code>Active</code>.
</div>

<div class="card shadow-sm">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0 align-middle">
                <thead class="table-light">
                <tr>
                    <th>Name</th>
                    <th>Type</th>
                    <th>Callback</th>
                    <th>Readiness</th>
                    <th>Status</th>
                    <th class="text-end">Actions</th>
                </tr>
                </thead>
                <tbody>
                <?php if (empty($providers)) { ?>
                    <tr><td colspan="6" class="text-center text-muted py-4">No providers configured yet.</td></tr>
                <?php } ?>
                <?php foreach ($providers as $row) {
                    $diag = $row['_diagnostics'] ?? ['missing' => [], 'warnings' => []];
                    $urls = $row['_urls'] ?? ['callback_url' => '', 'init_url' => ''];
                    $hasIssues = !empty($diag['missing']) || !empty($diag['warnings']);
                    ?>
                    <tr>
                        <td>
                            <div class="fw-semibold"><?php echo htmlspecialchars($row['s_provider_name'] ?? ''); ?></div>
                            <?php if (!empty($row['s_notes'])) { ?>
                                <div class="small text-muted"><?php echo htmlspecialchars($row['s_notes']); ?></div>
                            <?php } ?>
                        </td>
                        <td><?php echo htmlspecialchars($row['type_label'] ?? ''); ?></td>
                        <td class="small text-muted text-break"><?php echo htmlspecialchars($urls['callback_url'] ?? ''); ?></td>
                        <td>
                            <?php if (!$hasIssues) { ?>
                                <span class="badge bg-success">Ready</span>
                            <?php } else { ?>
                                <span class="badge bg-warning text-dark">Check</span>
                                <?php if (!empty($diag['missing'])) { ?>
                                    <div class="small text-danger mt-1">Missing: <?php echo htmlspecialchars(implode(', ', $diag['missing'])); ?></div>
                                <?php } ?>
                                <?php if (!empty($diag['warnings'])) { ?>
                                    <div class="small text-muted mt-1"><?php echo htmlspecialchars($diag['warnings'][0]); ?></div>
                                <?php } ?>
                            <?php } ?>
                        </td>
                        <td>
                            <span class="badge bg-<?php echo ($row['s_status'] ?? 'active') === 'inactive' ? 'secondary' : 'success'; ?>">
                                <?php echo htmlspecialchars($row['status_label'] ?? ''); ?>
                            </span>
                        </td>
                        <td class="text-end">
                            <a class="btn btn-sm btn-outline-primary" href="<?php echo $radAdminUrl; ?>/sso/manage/<?php echo (int)($row['id'] ?? 0); ?>">Manage</a>
                            <a class="btn btn-sm btn-outline-secondary ms-1" href="<?php echo $radAdminUrl; ?>/sso/edit/<?php echo (int)($row['id'] ?? 0); ?>">Edit</a>
                            <a class="btn btn-sm btn-outline-success ms-1" href="<?php echo htmlspecialchars($urls['init_url'] ?? ''); ?>" target="_blank" rel="noopener">Test</a>
                        </td>
                    </tr>
                <?php } ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
