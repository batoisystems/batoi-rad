<?php
$templates = $this->runData['data']['templates'] ?? [];
$filters = $this->runData['data']['filters'] ?? ['q' => ''];
$total = count($templates);
$latest = $templates[0]['modified'] ?? null;
$timezone = $this->runData['entity']['timezone'] ?? ($this->runData['config']['sys']['timezone_default'] ?? 'UTC');
?>
<div class="apex-menucards">
<div class="container">
    <div class="row">
        <div class="col-12">
            <div class="card mb-4">
                <div class="card-body">
                    <div class="btn-toolbar rad-stacked-toolbar" role="toolbar">
                        <div class="btn-group" role="group" aria-label="UI Template actions">
                            <a href="<?php echo $this->runData['route']['rad_admin_url']; ?>/uitpl/add" class="btn btn-primary text-white">
                                <i class="bi bi-plus-circle me-2"></i>Add UI Template
                            </a>
                        </div>
                    </div>
                    <div class="text-muted small mt-2">Root: <code>rad/data/uitpl</code></div>
                </div>
            </div>
        </div>

        <div class="col-12">
            <div class="row g-3 mb-4">
                <div class="col-md-6">
                    <div class="card h-100 border-0 bg-primary-subtle">
                        <div class="card-body">
                            <div class="text-muted small">Templates</div>
                            <div class="fs-3 fw-bold"><?php echo $total; ?></div>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="card h-100 border-0 bg-info-subtle">
                        <div class="card-body">
                            <div class="text-muted small">Most Recent</div>
                            <div class="fw-semibold"><?php echo $latest ? \Core\Sys\TimeHelper::formatUtc($latest, $timezone) : 'NA'; ?></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-12">
            <div class="card mb-4">
                <div class="card-body">
                    <div class="d-flex justify-content-between flex-wrap gap-2 mb-3">
                        <div>
                            <div class="text-muted small">Embedded screen templates for microservice routes.</div>
                        </div>
                    </div>
                    <form class="row g-2 align-items-end mb-3" method="get">
                        <div class="col-md-6 col-lg-4">
                            <label class="form-label small text-muted mb-1">Search by path</label>
                            <div class="input-group input-group-sm">
                                <span class="input-group-text"><i class="bi bi-search"></i></span>
                                <input type="text" class="form-control" name="q" placeholder="Filter templates" value="<?php echo htmlspecialchars($filters['q']); ?>">
                            </div>
                        </div>
                        <div class="col-md-6 col-lg-3 d-flex gap-2">
                            <button type="submit" class="btn btn-primary btn-sm flex-grow-1"><i class="bi bi-funnel"></i> Apply</button>
                            <?php if ($filters['q'] !== '') { ?>
                                <a class="btn btn-outline-secondary btn-sm flex-grow-1" href="<?php echo $this->runData['route']['rad_admin_url']; ?>/uitpl/view">Reset</a>
                            <?php } ?>
                        </div>
                    </form>
                    <?php if ($total === 0) { ?>
                        <div class="alert alert-warning mb-0">No templates found. Use “Add UI Template” to create one.</div>
                    <?php } else { ?>
                        <div class="table-responsive">
                            <table class="table table-hover align-middle">
                                <thead>
                                    <tr>
                                        <th>Template</th>
                                        <th class="text-end">Size</th>
                                        <th class="text-end">Modified</th>
                                        <th></th>
                                    </tr>
                                </thead>
                                <tbody>
                                <?php foreach ($templates as $tpl) {
                                    $relative = $tpl['relative'];
                                    $encoded = rtrim(strtr(base64_encode($relative), '+/', '-_'), '=');
                                    $viewUrl = $this->runData['route']['rad_admin_url'] . '/uitpl/viewone/' . $encoded;
                                ?>
                                    <tr>
                                        <td>
                                            <div class="fw-semibold"><?php echo htmlspecialchars($relative); ?></div>
                                            <div class="text-muted small">rad/data/uitpl/<?php echo htmlspecialchars($relative); ?></div>
                                        </td>
                                        <td class="text-end"><?php echo number_format($tpl['size'] / 1024, 1); ?> KB</td>
                                        <td class="text-end"><?php echo \Core\Sys\TimeHelper::formatUtc($tpl['modified'], $timezone); ?></td>
                                        <td class="text-end">
                                            <a class="btn btn-sm btn-outline-primary" href="<?php echo $viewUrl; ?>">View</a>
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
    </div>
</div>
</div>
