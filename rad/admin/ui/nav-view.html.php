<?php
$navsets = $this->runData['data']['navsets'] ?? [];
$selectedNavsetId = (int)($this->runData['data']['selected_navset'] ?? 0);
$navitems = $this->runData['data']['navitems'] ?? [];
$locations = $this->runData['data']['locations'] ?? [];
$scopes = $this->runData['data']['scopes'] ?? [];
$microservices = $this->runData['data']['microservices'] ?? [];
$roles = $this->runData['data']['roles'] ?? [];
$filters = $this->runData['data']['filters'] ?? ['q' => ''];
$roleMap = [];
foreach ($roles as $roleRow) {
    $roleMap[(int)$roleRow['id']] = $roleRow['s_role_name'] ?? ('Role #' . $roleRow['id']);
}
$selectedNavset = null;
foreach ($navsets as $candidate) {
    if ((int)$candidate['id'] === $selectedNavsetId) {
        $selectedNavset = $candidate;
        break;
    }
}
$navItemById = [];
foreach ($navitems as $item) {
    $navItemById[(int)$item['id']] = $item;
}
$radUrl = $this->runData['route']['rad_admin_url'];
?>

<div id="nav-admin-app"
     data-navset="<?php echo $selectedNavsetId; ?>"
     data-navset-save="<?php echo htmlspecialchars($radUrl . '/nav/savenavset'); ?>"
     data-navset-delete="<?php echo htmlspecialchars($radUrl . '/nav/deletenavset'); ?>"
     data-navset-order="<?php echo htmlspecialchars($radUrl . '/nav/sortnavsets'); ?>"
     data-item-save="<?php echo htmlspecialchars($radUrl . '/nav/saveitem'); ?>"
     data-item-archive="<?php echo htmlspecialchars($radUrl . '/nav/archive'); ?>"
     data-item-activate="<?php echo htmlspecialchars($radUrl . '/nav/activate'); ?>"
     data-item-order="<?php echo htmlspecialchars($radUrl . '/nav/saveorder'); ?>"
     data-view-base="<?php echo htmlspecialchars($radUrl . '/nav/view'); ?>">

    <div class="row g-4">
        <div class="col-lg-4">
                <div class="card h-100 shadow-sm">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <div>
                            <h5 class="mb-0">Navigation Sets</h5>
                            <span class="text-muted small">Drag to reorder • Group menus</span>
                        </div>
                        <button class="btn btn-sm btn-primary" id="navset-add-btn" data-bs-toggle="modal" data-bs-target="#navsetModal">
                            <i class="bi bi-plus-circle"></i> Add
                        </button>
                    </div>
                    <div class="card-body p-0">
                        <div class="p-3 border-bottom">
                            <form class="d-flex gap-2" method="get" action="<?php echo $radUrl; ?>/nav/view">
                                <div class="input-group input-group-sm">
                                    <span class="input-group-text"><i class="bi bi-search"></i></span>
                                    <input type="text" name="q" class="form-control" placeholder="Search nav set" value="<?php echo htmlspecialchars($filters['q'] ?? ''); ?>">
                                </div>
                                <?php if (!empty($filters['q'])) { ?>
                                    <a href="<?php echo $radUrl; ?>/nav/view" class="btn btn-outline-secondary btn-sm">Reset</a>
                                <?php } ?>
                            </form>
                        </div>
                        <?php if (empty($navsets)) { ?>
                            <div class="p-4 text-center text-muted">
                                <p class="mb-2 fw-semibold">No Navigation Sets yet.</p>
                                <button class="btn btn-sm btn-primary" id="navset-add-btn-empty" data-bs-toggle="modal" data-bs-target="#navsetModal">
                                    <i class="bi bi-plus-circle me-1"></i>Add Navigation Set
                            </button>
                        </div>
                    <?php } else { ?>
                        <div class="list-group list-group-flush navset-list">
<?php foreach ($navsets as $navset) {
                                $isSelected = (int)$navset['id'] === $selectedNavsetId;
                                $rolesAssigned = array_filter(array_map(static fn($roleId) => $roleMap[$roleId] ?? ('Role #' . $roleId), $navset['access_roles'] ?? []));
                                ?>
                                <div class="list-group-item navset-row d-flex justify-content-between align-items-center <?php echo $isSelected ? 'active' : ''; ?>" draggable="true" data-navset-id="<?php echo (int)$navset['id']; ?>">
                                    <div class="d-flex align-items-start">
                                        <div class="me-2 text-muted drag-handle" style="cursor:grab;"><i class="bi bi-grip-vertical"></i></div>
                                        <div>
                                            <div class="fw-semibold"><?php echo htmlspecialchars($navset['s_name'] ?? 'Untitled'); ?></div>
                                            <?php if (!empty($rolesAssigned)) { ?>
                                                <div class="small opacity-75">
                                                    <i class="bi bi-people-fill me-1"></i><?php echo htmlspecialchars(implode(', ', $rolesAssigned)); ?>
                                                </div>
                                            <?php } ?>
                                        </div>
                                    </div>
                                    <div class="btn-group btn-group-sm">
                                        <a href="<?php echo htmlspecialchars($radUrl . '/nav/view/' . $navset['id']); ?>" class="btn btn-outline-light<?php echo $isSelected ? ' active' : ''; ?>">
                                            <i class="bi bi-eye"></i>
                                        </a>
                                        <button class="btn btn-outline-light navset-edit"
                                                data-bs-toggle="modal"
                                                data-bs-target="#navsetModal"
                                                data-navset="<?php echo htmlspecialchars(json_encode($navset, JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8'); ?>">
                                            <i class="bi bi-pencil"></i>
                                        </button>
                                    </div>
                                </div>
                            <?php } ?>
                        </div>
                    <?php } ?>
                </div>
            </div>
        </div>
        <div class="col-lg-8">
            <?php if (!$selectedNavset) { ?>
                <div class="card shadow-sm">
                    <div class="card-body text-center text-muted py-5">
                        <p class="mb-2 fw-semibold">Select or create a nav set to configure entries.</p>
                        <p class="mb-0">Nav sets define where menus render and who can see them.</p>
                    </div>
                </div>
            <?php } else { ?>
                <div class="card shadow-sm mb-4">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                            <div>
                                <h5 class="mb-1"><?php echo htmlspecialchars($selectedNavset['s_name']); ?></h5>
                                <div class="text-muted small">
                                    <span><i class="bi bi-hash me-1"></i>ID <?php echo (int)$selectedNavset['id']; ?></span>
                                </div>
                            </div>
                            <div class="btn-group">
                                <button class="btn btn-outline-secondary navset-edit"
                                        data-bs-toggle="modal"
                                        data-bs-target="#navsetModal"
                                        data-navset="<?php echo htmlspecialchars(json_encode($selectedNavset, JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8'); ?>">
                                    <i class="bi bi-pencil-square"></i> Edit Nav Set
                                </button>
                                <button class="btn btn-outline-danger navset-delete" data-navset-id="<?php echo (int)$selectedNavset['id']; ?>">
                                    <i class="bi bi-archive"></i> Archive
                                </button>
                            </div>
                        </div>
                        <?php if (!empty($selectedNavset['s_description'])) { ?>
                            <p class="mt-3 mb-0"><?php echo nl2br(htmlspecialchars($selectedNavset['s_description'])); ?></p>
                        <?php } ?>
                    </div>
                </div>

                <div class="card shadow-sm">
                    <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                        <div>
                            <h5 class="mb-0">Navigation Items</h5>
                            <span class="text-muted small">Drag to reorder. Items inherit nav set scope &amp; roles unless overridden.</span>
                        </div>
                        <div class="btn-group">
                            <button class="btn btn-sm btn-outline-secondary" id="navitem-refresh"><i class="bi bi-arrow-repeat"></i> Refresh</button>
                            <button class="btn btn-sm btn-primary" id="navitem-add-btn" data-bs-toggle="modal" data-bs-target="#navitemModal">
                                <i class="bi bi-plus-circle"></i> Add Item
                            </button>
                        </div>
                    </div>
                    <div class="card-body p-0">
                        <?php if (empty($navitems)) { ?>
                            <div class="p-4 text-center text-muted">
                                No menu entries yet. Add your first link or route.
                            </div>
                        <?php } else { ?>
                            <div class="table-responsive">
                                <table class="table table-hover align-middle mb-0">
                                    <thead class="table-light">
                                    <tr>
                                        <th style="width:35%">Label</th>
                                        <th>Destination</th>
                                        <th>Parent</th>
                                        <th class="text-end">Actions</th>
                                    </tr>
                                    </thead>
                                    <tbody id="nav-items-body">
                                    <?php foreach ($navitems as $item) {
                                        $isInactive = $item['livestatus'] !== '1';
                                        $parentLabel = '';
                                        $parentId = $item['s_parent_nav_id'] ?? ($item['s_parent_id'] ?? 0);
                                        if (!empty($parentId) && isset($navItemById[(int)$parentId])) {
                                            $parentLabel = $navItemById[(int)$parentId]['s_name'] ?? ('#' . $parentId);
                                        }
                                        ?>
                                        <tr data-id="<?php echo (int)$item['id']; ?>" draggable="true" class="<?php echo $isInactive ? 'table-secondary' : ''; ?>">
                                            <td>
                                                <div class="fw-semibold"><?php echo htmlspecialchars($item['s_name']); ?></div>
                                                <div class="text-muted small">#<?php echo (int)$item['id']; ?></div>
                                            </td>
                                            <td>
                                                <div class="text-truncate" style="max-width:240px;"><?php echo htmlspecialchars($item['s_href']); ?></div>
                                                <div class="small text-muted"><?php echo htmlspecialchars(strtoupper($item['s_target'] ?? '_self')); ?> · <?php echo htmlspecialchars(ucfirst($item['s_device'] ?? 'all')); ?></div>
                                            </td>
                                            <td><?php echo $parentLabel ? htmlspecialchars($parentLabel) : '<span class="text-muted">None</span>'; ?></td>
                                            <td class="text-end">
                                                <div class="btn-group btn-group-sm">
                                                    <button class="btn btn-outline-secondary navitem-edit"
                                                            data-bs-toggle="modal"
                                                            data-bs-target="#navitemModal"
                                                            data-navitem="<?php echo htmlspecialchars(json_encode($item, JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8'); ?>">
                                                        <i class="bi bi-pencil"></i>
                                                    </button>
                                                    <?php if ($isInactive) { ?>
                                                        <button class="btn btn-outline-success navitem-activate" data-nav-id="<?php echo (int)$item['id']; ?>">
                                                            <i class="bi bi-check-circle"></i>
                                                        </button>
                                                    <?php } else { ?>
                                                        <button class="btn btn-outline-danger navitem-archive" data-nav-id="<?php echo (int)$item['id']; ?>">
                                                            <i class="bi bi-archive"></i>
                                                        </button>
                                                    <?php } ?>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php } ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php } ?>
                    </div>
                </div>
            <?php } ?>
        </div>
    </div>
</div>

<!-- Nav Set Modal -->
<div class="modal fade" id="navsetModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form id="navset-form">
                <div class="modal-header">
                    <h5 class="modal-title">Navigation Set</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="navset_id" id="navset-id">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Display name</label>
                            <input type="text" class="form-control" name="s_name" id="navset-name" required maxlength="255">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Link to Microservicelet (optional)</label>
                            <select class="form-select" name="s_ms_id" id="navset-ms">
                                <option value="0">None (global/standalone)</option>
                                <?php foreach ($microservices as $ms) { ?>
                                    <option value="<?php echo (int)$ms['id']; ?>"><?php echo htmlspecialchars($ms['s_name']); ?></option>
                                <?php } ?>
                            </select>
                            <div class="form-text">Nav sets can stand alone or attach to a microservicelet.</div>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Description</label>
                            <textarea class="form-control" name="s_description" id="navset-description" rows="3"></textarea>
                            <div class="form-text">Optional notes for this navigation set.</div>
                        </div>
                        <div class="col-12" id="navset-role-wrapper">
                            <label class="form-label">Roles (visibility)</label>
                            <div class="border rounded p-2" style="max-height: 180px; overflow-y: auto;">
                                <?php foreach ($roles as $roleRow) { ?>
                                    <div class="form-check">
                                        <input class="form-check-input navset-role-check" type="checkbox" value="<?php echo (int)$roleRow['id']; ?>" id="navset-role-<?php echo (int)$roleRow['id']; ?>">
                                        <label class="form-check-label" for="navset-role-<?php echo (int)$roleRow['id']; ?>">
                                            <?php echo htmlspecialchars($roleRow['s_role_name']); ?>
                                        </label>
                                    </div>
                                <?php } ?>
                            </div>
                            <div class="form-text">Leave empty for public visibility.</div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Nav Set</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Nav Item Modal -->
<div class="modal fade" id="navitemModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form id="navitem-form">
                <div class="modal-header">
                    <h5 class="modal-title">Nav Item</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="nav_id" id="navitem-id">
                    <input type="hidden" name="s_navset_id" id="navitem-navset-id" value="<?php echo $selectedNavsetId; ?>">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Label</label>
                            <input type="text" class="form-control" name="s_name" id="navitem-name" required maxlength="255">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Destination URL / Route</label>
                            <input type="text" class="form-control" name="s_href" id="navitem-href" required maxlength="512">
                            <div class="form-text">Supports absolute URLs or RAD routes. For SaaS, include the slug segment at runtime.</div>
                            <div class="form-text small text-muted">
                                Examples: Non-SaaS UID/ID: <code>/ms_name/route</code> • SaaS: <code>/ms_name/route/{spaceUid}</code>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Icon</label>
                            <input type="text" class="form-control" name="s_icon" id="navitem-icon" maxlength="120" placeholder="bi bi-house">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Target</label>
                            <select class="form-select" name="s_target" id="navitem-target">
                                <option value="_self">Same tab</option>
                                <option value="_blank">New tab</option>
                                <option value="_parent">Parent frame</option>
                                <option value="_top">Top frame</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Device visibility</label>
                            <select class="form-select" name="s_device" id="navitem-device">
                                <option value="all">All devices</option>
                                <option value="desktop">Desktop</option>
                                <option value="mobile">Mobile</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Parent item</label>
                            <select class="form-select" name="s_parent_nav_id" id="navitem-parent">
                                <option value="0">None</option>
                                <?php foreach ($navitems as $item) { ?>
                                    <option value="<?php echo (int)$item['id']; ?>" data-nav-name="<?php echo htmlspecialchars($item['s_name']); ?>">
                                        <?php echo htmlspecialchars($item['s_name']); ?>
                                    </option>
                                <?php } ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Badge label</label>
                            <input type="text" class="form-control" name="s_badge" id="navitem-badge" maxlength="64" placeholder="New">
                        </div>
                        <div class="col-12">
                            <label class="form-label">Link title</label>
                            <input type="text" class="form-control" name="s_meta_title" id="navitem-meta-title" maxlength="255" placeholder="Tooltip or accessibility label">
                        </div>
                        <div class="col-12">
                            <label class="form-label">Optional metadata (JSON)</label>
                            <input type="text" class="form-control" name="s_meta" id="navitem-meta" maxlength="255" placeholder='{\"ga\":\"nav_home\",\"pill\":\"new\"}'>
                            <div class="form-text">For theme/UI hooks or analytics. Leave blank if not needed.</div>
                        </div>
                    </div>
                </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary">Save Item</button>
                </div>
            </form>
        </div>
    </div>
</div>
