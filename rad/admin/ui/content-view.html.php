<?php
$content = $this->runData['data']['content'];
$stats = $this->runData['data']['content_stats'] ?? ['total' => 0, 'active' => 0, 'archived' => 0, 'static' => 0, 'journal' => 0, 'common' => 0];
$radAdminUrl = $this->runData['route']['rad_admin_url'];
$filters = $this->runData['data']['filters'] ?? ['q' => '', 'status' => '', 'type' => ''];
$perPagePref = (int)($this->runData['data']['per_page_pref'] ?? 25);
?>

<?php if ($stats['total'] === 0): ?>
<div class="text-center py-5">
    <i class="bi bi-file-earmark-text text-muted" style="font-size: 6rem;"></i>
    <p class="lead mt-3">No content blocks yet. Create your first one to reuse across microservicelets.</p>
    <a href="<?php echo $radAdminUrl; ?>/content/add" class="btn btn-primary">
        <i class="bi bi-plus-circle me-1"></i>Add Content Block
    </a>
</div>
<?php else: ?>
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
        <div class="text-muted small">
            Showing <span id="content-visible-count"><?php echo $stats['total']; ?></span> of <?php echo $stats['total']; ?> blocks
        </div>
        <a href="<?php echo $radAdminUrl; ?>/content/add" class="btn btn-primary">
            <i class="bi bi-plus-circle me-1"></i>Add Content Block
        </a>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-md-3">
            <div class="card h-100 shadow-sm content-metric-card">
                <div class="card-body">
                    <div class="text-muted text-uppercase small">Total</div>
                    <div class="display-6 fw-semibold"><?php echo $stats['total']; ?></div>
                    <div class="text-muted small">Content blocks</div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card h-100 shadow-sm content-metric-card">
                <div class="card-body">
                    <div class="text-muted text-uppercase small">Active</div>
                    <div class="display-6 fw-semibold text-success"><?php echo $stats['active']; ?></div>
                    <div class="text-muted small">Currently available</div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card h-100 shadow-sm content-metric-card">
                <div class="card-body">
                    <div class="text-muted text-uppercase small">Static Blocks</div>
                    <div class="display-6 fw-semibold text-primary"><?php echo $stats['static']; ?></div>
                    <div class="text-muted small">UI fragments</div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card h-100 shadow-sm content-metric-card">
                <div class="card-body">
                    <div class="text-muted text-uppercase small">Common/Journal</div>
                    <div class="display-6 fw-semibold text-info"><?php echo $stats['common'] + $stats['journal']; ?></div>
                    <div class="text-muted small">Reusable entries</div>
                </div>
            </div>
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-body">
            <div class="row g-3 align-items-end">
                <div class="col-lg-4">
                    <label class="form-label">Search</label>
                    <input type="text" class="form-control" id="content-filter-search" name="q" placeholder="Title, slug, content..." value="<?php echo htmlspecialchars($filters['q']); ?>">
                </div>
                <div class="col-md-2 col-lg-2">
                    <label class="form-label">Status</label>
                    <select class="form-select" id="content-filter-status" name="status">
                        <option value="">All</option>
                        <option value="active" <?php echo $filters['status'] === 'active' ? 'selected' : ''; ?>>Active</option>
                        <option value="archived" <?php echo $filters['status'] === 'archived' ? 'selected' : ''; ?>>Archived</option>
                    </select>
                </div>
                <div class="col-md-2 col-lg-2">
                    <label class="form-label">Type</label>
                    <select class="form-select" id="content-filter-type" name="type">
                        <option value="">All</option>
                        <option value="i" <?php echo $filters['type'] === 'i' ? 'selected' : ''; ?>>Static</option>
                        <option value="j" <?php echo $filters['type'] === 'j' ? 'selected' : ''; ?>>Journal</option>
                        <option value="c" <?php echo $filters['type'] === 'c' ? 'selected' : ''; ?>>Common</option>
                    </select>
                </div>
                <div class="col-md-2 col-lg-2">
                    <label class="form-label">Rows per page</label>
                    <select class="form-select" id="content-page-size" data-pref="<?php echo $perPagePref; ?>">
                        <?php foreach ([10, 25, 50, 100, 200] as $size) { ?>
                            <option value="<?php echo $size; ?>" <?php echo $perPagePref === $size ? 'selected' : ''; ?>><?php echo $size; ?></option>
                        <?php } ?>
                    </select>
                </div>
                <div class="col-md-2 col-lg-2 text-lg-end">
                    <button class="btn btn-outline-secondary w-100" id="content-filter-reset" type="reset">
                        <i class="bi bi-arrow-counterclockwise me-1"></i>Reset
                    </button>
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table align-middle" id="content-table">
                    <thead>
                        <tr>
                            <th>Title</th>
                            <th>Slug</th>
                            <th>Type</th>
                            <th>Microservicelet</th>
                            <th>Updated</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($content as $row): ?>
                        <tr
                            data-search="<?php echo htmlspecialchars($row['search_blob']); ?>"
                            data-status="<?php echo htmlspecialchars($row['livestatus_slug']); ?>"
                            data-type="<?php echo htmlspecialchars($row['type_slug']); ?>"
                        >
                            <td>
                                <div class="fw-semibold"><?php echo htmlspecialchars($row['s_title'] ?? 'Untitled'); ?></div>
                                <div class="text-muted small">ID: <?php echo (int)($row['id'] ?? 0); ?> · UID: <?php echo htmlspecialchars($row['uid'] ?? '-'); ?></div>
                                <div class="text-muted small">Created by <?php echo htmlspecialchars($row['created_name']); ?></div>
                            </td>
                            <td>
                                <code><?php echo htmlspecialchars($row['s_slug']); ?></code>
                            </td>
                            <td>
                                <span class="badge bg-light text-dark"><?php echo htmlspecialchars($row['type_label']); ?></span>
                            </td>
                            <td>
                                <?php if (!empty($row['ms_meta']['uid'])): ?>
                                    <a href="<?php echo $radAdminUrl; ?>/microservice/detail/<?php echo htmlspecialchars($row['ms_meta']['uid']); ?>" class="text-decoration-none">
                                        <?php echo htmlspecialchars($row['ms_meta']['name']); ?>
                                    </a>
                                    <div class="text-muted small">ID: <?php echo (int)($row['s_ms_id'] ?? 0); ?> · UID: <?php echo htmlspecialchars($row['ms_meta']['uid']); ?></div>
                                <?php else: ?>
                                    <span class="text-muted">Not linked</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="fw-semibold"><?php echo htmlspecialchars($row['formatted_updated']); ?></div>
                                <div class="text-muted small">By <?php echo htmlspecialchars($row['updated_name']); ?></div>
                            </td>
                            <td class="text-end">
                                <div class="btn-group content-actions">
                                    <a href="<?php echo $radAdminUrl; ?>/content/edit/<?php echo htmlspecialchars($row['uid']); ?>" class="btn btn-outline-primary btn-sm">
                                        <i class="bi bi-pencil"></i>
                                    </a>
                                    <?php if ($row['livestatus_slug'] === 'active'): ?>
                                        <a href="<?php echo $radAdminUrl; ?>/content/archive/<?php echo htmlspecialchars($row['uid']); ?>" class="btn btn-outline-danger btn-sm">
                                            <i class="bi bi-archive"></i>
                                        </a>
                                    <?php else: ?>
                                        <a href="<?php echo $radAdminUrl; ?>/content/restore/<?php echo htmlspecialchars($row['uid']); ?>" class="btn btn-outline-success btn-sm">
                                            <i class="bi bi-arrow-counterclockwise"></i>
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mt-3">
                <div class="text-muted small" id="content-page-summary"></div>
                <div class="btn-group" role="group">
                    <button class="btn btn-outline-secondary btn-sm" id="content-page-prev">
                        <i class="bi bi-arrow-left"></i>
                    </button>
                    <button class="btn btn-outline-secondary btn-sm" id="content-page-next">
                        <i class="bi bi-arrow-right"></i>
                    </button>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>
