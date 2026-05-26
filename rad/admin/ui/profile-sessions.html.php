<?php
$sessions = $this->runData['data']['sessions'] ?? [];
$limit = (int)($this->runData['data']['session_limit'] ?? 25);
$total = (int)($this->runData['data']['session_total'] ?? 0);
$page = (int)($this->runData['data']['session_page'] ?? 1);
$pages = (int)($this->runData['data']['session_pages'] ?? 1);
$search = (string)($this->runData['data']['session_search'] ?? '');
$start = (string)($this->runData['data']['session_start'] ?? '');
$end = (string)($this->runData['data']['session_end'] ?? '');
$radAdminUrl = $this->runData['route']['rad_admin_url'] ?? '';
$active = 'sessions';
include $this->runData['config']['dir']['admin'] . '/ui/profile-nav.partial.php';
?>
<div class="card shadow-sm">
    <div class="card-body">
        <div class="d-flex flex-wrap justify-content-between align-items-center mb-3">
            <div>
                <h2 class="h5 mb-1">Recent Sessions</h2>
                <div class="text-muted small">Showing <?php echo $limit; ?> per page, <?php echo $total; ?> total.</div>
            </div>
            <a class="btn btn-outline-primary btn-sm" href="<?php echo $radAdminUrl; ?>/profile/overview">Back to overview</a>
        </div>
        <form method="get" action="<?php echo $radAdminUrl; ?>/profile/sessions" class="row g-2 align-items-end mb-3">
            <div class="col-md-6">
                <label class="form-label">Search</label>
                <input type="text" name="q" class="form-control" value="<?php echo htmlspecialchars($search); ?>" placeholder="Browser, OS, device, IP">
            </div>
            <div class="col-md-3">
                <label class="form-label">From</label>
                <input type="date" name="start" class="form-control" value="<?php echo htmlspecialchars($start); ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label">To</label>
                <input type="date" name="end" class="form-control" value="<?php echo htmlspecialchars($end); ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label">Per page</label>
                <select name="per_page" class="form-select">
                    <?php foreach ([10, 25, 50, 100, 200] as $value) { ?>
                        <option value="<?php echo $value; ?>" <?php echo $limit === $value ? 'selected' : ''; ?>>
                            <?php echo $value; ?>
                        </option>
                    <?php } ?>
                </select>
            </div>
            <div class="col-md-3">
                <button type="submit" class="btn btn-primary">Apply</button>
                <a class="btn btn-outline-secondary" href="<?php echo $radAdminUrl; ?>/profile/sessions">Reset</a>
            </div>
        </form>
        <?php if (!empty($sessions)) { ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead>
                        <tr>
                            <th>When</th>
                            <th>Browser</th>
                            <th>OS</th>
                            <th>Device</th>
                            <th>IP</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($sessions as $row) { ?>
                            <tr>
                                <td><?php echo htmlspecialchars($row['createstamp_display'] ?? $row['createstamp'] ?? ''); ?></td>
                                <td><?php echo htmlspecialchars($row['s_browser'] ?? ''); ?></td>
                                <td><?php echo htmlspecialchars($row['s_operating_system'] ?? ''); ?></td>
                                <td><?php echo htmlspecialchars($row['s_device_type'] ?? ''); ?></td>
                                <td><?php echo htmlspecialchars($row['s_ip'] ?? ''); ?></td>
                            </tr>
                        <?php } ?>
                    </tbody>
                </table>
            </div>
            <?php if ($pages > 1) { ?>
                <nav aria-label="Sessions pagination">
                    <ul class="pagination">
                        <?php
                        $queryBase = '';
                        if ($search !== '') {
                            $queryBase .= '&q=' . urlencode($search);
                        }
                        if ($start !== '') {
                            $queryBase .= '&start=' . urlencode($start);
                        }
                        if ($end !== '') {
                            $queryBase .= '&end=' . urlencode($end);
                        }
                        $queryBase .= '&per_page=' . $limit;
                        $prev = max(1, $page - 1);
                        $next = min($pages, $page + 1);
                        ?>
                        <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                            <a class="page-link" href="<?php echo $radAdminUrl; ?>/profile/sessions?page=<?php echo $prev . $queryBase; ?>">Previous</a>
                        </li>
                        <li class="page-item disabled">
                            <span class="page-link">Page <?php echo $page; ?> of <?php echo $pages; ?></span>
                        </li>
                        <li class="page-item <?php echo $page >= $pages ? 'disabled' : ''; ?>">
                            <a class="page-link" href="<?php echo $radAdminUrl; ?>/profile/sessions?page=<?php echo $next . $queryBase; ?>">Next</a>
                        </li>
                    </ul>
                </nav>
            <?php } ?>
        <?php } else { ?>
            <div class="text-muted small">No session history found.</div>
        <?php } ?>
    </div>
</div>
