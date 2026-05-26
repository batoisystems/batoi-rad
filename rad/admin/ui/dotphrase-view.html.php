<?php
$phrases = $this->runData['data']['phrases'] ?? [];
$spaces = $this->runData['data']['spaces'] ?? [];
$filters = $this->runData['data']['filters'] ?? [];
$canManage = !empty($this->runData['data']['can_manage']);
$radAdminUrl = $this->runData['route']['rad_admin_url'];
?>

<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
    <div>
        <h1 class="h4 mb-1">Dot Phrases</h1>
        <p class="text-muted mb-0">Reusable snippets by scope; platform = non-SaaS, workspace = SaaS.</p>
    </div>
    <?php if ($canManage): ?>
    <div class="btn-group">
        <a href="<?php echo $radAdminUrl; ?>/dotphrase/add" class="btn btn-primary btn-sm">
            <i class="bi bi-plus-circle me-1"></i>Add Dot Phrase
        </a>
    </div>
    <?php endif; ?>
</div>

<div class="card mb-3">
    <div class="card-body">
        <form class="row g-2 align-items-end" method="get">
            <div class="col-md-3">
                <label class="form-label">Scope</label>
                <select name="scope" class="form-select form-select-sm">
                    <option value="">All</option>
                    <?php foreach (['platform'=>'Platform','workspace'=>'Workspace'] as $k=>$label): ?>
                        <option value="<?php echo $k; ?>" <?php echo ($filters['scope'] ?? '') === $k ? 'selected' : ''; ?>><?php echo $label; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">Workspace</label>
                <select name="space_id" class="form-select form-select-sm">
                    <option value="">All</option>
                    <?php foreach ($spaces as $space): ?>
                        <option value="<?php echo $space['id']; ?>" <?php echo ((string)($filters['space_id'] ?? '') === (string)$space['id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($space['s_name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">Visibility</label>
                <select name="is_public" class="form-select form-select-sm">
                    <option value="">All</option>
                    <option value="Y" <?php echo ($filters['is_public'] ?? '') === 'Y' ? 'selected' : ''; ?>>Public</option>
                    <option value="N" <?php echo ($filters['is_public'] ?? '') === 'N' ? 'selected' : ''; ?>>Private</option>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">Search</label>
                <input type="text" name="search" class="form-control form-select-sm" value="<?php echo htmlspecialchars($filters['search'] ?? ''); ?>" placeholder="Phrase or content">
            </div>
            <div class="col-md-2 d-grid gap-2">
                <button type="submit" class="btn btn-primary btn-sm w-100"><i class="bi bi-funnel me-1"></i>Filter</button>
                <?php if (!empty($filters['scope']) || !empty($filters['space_id']) || !empty($filters['is_public']) || !empty($filters['search'])): ?>
                    <a href="<?php echo $radAdminUrl; ?>/dotphrase/view" class="btn btn-outline-secondary btn-sm w-100">Reset</a>
                <?php endif; ?>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <?php if (empty($phrases)): ?>
            <p class="text-muted mb-0">No dot phrases found.</p>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-sm align-middle">
                    <thead>
                        <tr>
                            <th>Phrase</th>
                            <th>Scope</th>
                            <th>Workspace</th>
                            <th>Visibility</th>
                            <th>Updated</th>
                            <?php if ($canManage): ?><th class="text-end">Actions</th><?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($phrases as $row): ?>
                        <tr>
                            <td>
                                <div class="fw-semibold"><?php echo htmlspecialchars($row['s_phrase']); ?></div>
                                <div class="text-muted small text-truncate" style="max-width:280px;"><?php echo htmlspecialchars($row['s_content']); ?></div>
                            </td>
                            <td><span class="badge bg-light text-dark"><?php echo htmlspecialchars($row['s_scope']); ?></span></td>
                            <td><?php echo $row['space_name'] ? htmlspecialchars($row['space_name']) : '—'; ?></td>
                            <td>
                                <?php if (($row['s_is_public'] ?? 'N') === 'Y'): ?>
                                    <span class="badge bg-success-subtle text-success">Public</span>
                                <?php else: ?>
                                    <span class="badge bg-secondary-subtle text-secondary">Private</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-muted small"><?php echo htmlspecialchars($row['updatestamp'] ?? $row['createstamp'] ?? ''); ?></td>
                            <?php if ($canManage): ?>
                            <td class="text-end">
                                <div class="btn-group btn-group-sm">
                                    <a href="<?php echo $radAdminUrl; ?>/dotphrase/edit/<?php echo $row['uid']; ?>" class="btn btn-outline-secondary"><i class="bi bi-pencil"></i></a>
                                    <a href="<?php echo $radAdminUrl; ?>/dotphrase/archive/<?php echo $row['uid']; ?>" class="btn btn-outline-danger" onclick="return confirm('Archive this dot phrase?');"><i class="bi bi-archive"></i></a>
                                </div>
                            </td>
                            <?php endif; ?>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>
