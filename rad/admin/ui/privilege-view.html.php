<?php
$roles = $this->runData['data']['privilege_roles'] ?? [];
$config = $this->runData['data']['privilege_config'] ?? [];
$entityNames = $this->runData['data']['privilege_entities'] ?? [];
$visibility = $this->runData['data']['visibility_config'] ?? ['restricted_ms_ids' => []];
$microservices = $this->runData['data']['microservices'] ?? [];
$entities = $this->runData['data']['all_entities'] ?? [];
$privKeys = $this->runData['data']['privilege_keys'] ?? [];
$filters = $this->runData['data']['filters'] ?? ['q' => ''];

function render_entity_badges(array $ids, array $nameMap): string {
    if (empty($ids)) {
        return '<span class="badge text-bg-secondary">None</span>';
    }
    $out = [];
    foreach ($ids as $id) {
        $label = $nameMap[(int)$id] ?? 'Unknown';
        $out[] = '<span class="badge text-bg-light border text-dark me-1 mb-1">' . htmlspecialchars($label) . ' <span class="text-muted">#' . (int)$id . '</span></span>';
    }
    return implode('', $out);
}
?>
<div class="card border-0 shadow-sm mb-4">
    <div class="card-body">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
            <div>
                <h2 class="h5 mb-2">RAD Admin Privileges</h2>
                <p class="text-muted mb-0">Only system admin (entity id = 1) sees this page. Configure privilege IDs and IP rules (stored in <code>rad/admin/rad-vals.config.php</code>).</p>
            </div>
            <form class="d-flex gap-2" method="get">
                <div class="input-group input-group-sm">
                    <span class="input-group-text"><i class="bi bi-search"></i></span>
                    <input type="text" name="q" class="form-control" placeholder="Filter entities" value="<?php echo htmlspecialchars($filters['q']); ?>">
                </div>
                <?php if ($filters['q'] !== '') { ?>
                    <a href="<?php echo $this->runData['route']['rad_admin_url']; ?>/privilege/view" class="btn btn-outline-secondary btn-sm">Reset</a>
                <?php } ?>
            </form>
        </div>
    </div>
</div>

<div class="card border-0 shadow-sm mb-3">
    <div class="card-header bg-white">
        <h3 class="h6 mb-0">Roles & Access</h3>
    </div>
    <div class="card-body">
        <table class="table table-sm align-middle mb-0">
            <thead class="small text-muted">
                <tr>
                    <th>Role</th>
                    <th>Access</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td><strong>System Admin</strong></td>
                    <td>Full access (entity id = 1)</td>
                </tr>
                <tr>
                    <td><strong>Developer</strong></td>
                    <td>Everything except privilege/aiconfig and destructive actions (delete/destroy/archive).</td>
                </tr>
                <tr>
                    <td><strong>Analyst</strong></td>
                    <td>Read/limited edit (code/route); no destructive or admin operations.</td>
                </tr>
                <tr>
                    <td><strong>Access Admin</strong></td>
                    <td>Identity/governance bundle (teams/users/roles/API/assets).</td>
                </tr>
            </tbody>
        </table>
    </div>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-header bg-white">
        <h3 class="h6 mb-0">Manage Entity IDs</h3>
    </div>
    <div class="card-body">
        <form method="post" action="<?php echo $this->runData['route']['rad_admin_url']; ?>/privilege/view" class="row g-3">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($this->runData['request']->csrf_token); ?>">
            <div class="col-md-6">
                <label class="form-label d-flex justify-content-between align-items-center">
                    <span>Developers (comma-separated IDs)</span>
                    <button type="button" class="btn btn-sm btn-outline-primary manage-entities" data-role="developers">Edit list</button>
                </label>
                <input type="text" name="developers" class="form-control entity-input" value="<?php echo htmlspecialchars(implode(',', $config['developers'] ?? [])); ?>" data-role="developers">
                <div class="mt-1 d-flex flex-wrap gap-1 align-items-center entity-chips" data-role-display="developers"><?php echo render_entity_badges($config['developers'] ?? [], $entityNames); ?></div>
            </div>
            <div class="col-md-6">
                <label class="form-label d-flex justify-content-between align-items-center">
                    <span>Analysts (comma-separated IDs)</span>
                    <button type="button" class="btn btn-sm btn-outline-primary manage-entities" data-role="analysts">Edit list</button>
                </label>
                <input type="text" name="analysts" class="form-control entity-input" value="<?php echo htmlspecialchars(implode(',', $config['analysts'] ?? [])); ?>" data-role="analysts">
                <div class="mt-1 d-flex flex-wrap gap-1 align-items-center entity-chips" data-role-display="analysts"><?php echo render_entity_badges($config['analysts'] ?? [], $entityNames); ?></div>
            </div>
            <div class="col-md-6">
                <label class="form-label d-flex justify-content-between align-items-center">
                    <span>Access Admin (comma-separated IDs)</span>
                    <button type="button" class="btn btn-sm btn-outline-primary manage-entities" data-role="access_admin">Edit list</button>
                </label>
                <input type="text" name="access_admin" class="form-control entity-input" value="<?php echo htmlspecialchars(implode(',', $config['access_admins'] ?? [])); ?>" data-role="access_admin">
                <div class="form-text">Access Admins manage identity/governance but cannot see restricted microservicelets in RAD Admin.</div>
                <div class="mt-1 d-flex flex-wrap gap-1 align-items-center entity-chips" data-role-display="access_admin"><?php echo render_entity_badges($config['access_admins'] ?? [], $entityNames); ?></div>
            </div>
            <div class="col-12">
                <hr>
                <h6 class="h6">Microservicelet Visibility (RAD Admin only)</h6>
                <p class="text-muted small">Restricted microservicelets are hidden/blocked for access_admin/developer/analyst inside RAD Admin. Runtime access is unaffected. System admin always sees them.</p>
                <div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 g-2">
                    <?php foreach ($microservices as $ms) {
                        $checked = in_array((int)$ms['id'], $visibility['restricted_ms_ids'] ?? [], true) ? 'checked' : '';
                    ?>
                    <div class="col">
                        <div class="form-check border rounded p-2 h-100">
                            <input class="form-check-input" type="checkbox" id="ms-<?php echo (int)$ms['id']; ?>" name="restricted_ms[]" value="<?php echo (int)$ms['id']; ?>" <?php echo $checked; ?>>
                            <label class="form-check-label fw-semibold" for="ms-<?php echo (int)$ms['id']; ?>">
                                <?php echo htmlspecialchars($ms['s_name'] ?? 'Microservice'); ?>
                            </label>
                            <div class="text-muted small">UID: <?php echo htmlspecialchars($ms['uid'] ?? ''); ?></div>
                        </div>
                    </div>
                    <?php } ?>
                </div>
                <div class="form-text mt-2">Check to restrict from non-system-admin RAD Admin users.</div>
            </div>
            <div class="col-12">
                <hr>
                <h6 class="h6">Privilege Flags</h6>
                <p class="text-muted small mb-2">Toggle which roles can perform these actions inside RAD Admin. System Admin always has all privileges.</p>
                <div class="table-responsive">
                    <table class="table table-sm align-middle">
                        <thead class="small text-muted">
                            <tr>
                                <th>Privilege</th>
                                <th class="text-center">System Admin</th>
                                <th class="text-center">Developer</th>
                                <th class="text-center">Analyst</th>
                                <th class="text-center">Access Admin</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $roleKeys = ['system_admin', 'developer', 'analyst', 'access_admin'];
                            $labels = [
                                'delete' => 'Delete (destructive)',
                                'destroy' => 'Destroy/Hard-delete',
                                'rename' => 'Rename',
                                'upgrade' => 'Upgrade',
                                'manage_tokens' => 'Manage Tokens',
                                'manage_privileges' => 'Manage Privileges',
                                'privilege_view' => 'Privilege UI',
                                'idm_manage' => 'Identity/Governance',
                                'idm_view' => 'Identity View',
                                'user_manage' => 'Users',
                                'role_manage' => 'Roles',
                                'api_manage' => 'API Management',
                                'asset_upload' => 'Asset Upload',
                                'aiconfig_manage' => 'AI Config',
                                'microservice_add' => 'MS Add',
                                'microservice_edit' => 'MS Edit',
                                'controller_add' => 'Controller Add',
                                'controller_edit' => 'Controller Edit',
                                'settings' => 'Settings',
                                'code_edit' => 'Code Edit',
                                'route_add' => 'Route Add',
                                'route_edit' => 'Route Edit',
                                'view' => 'General View',
                            ];
                            foreach ($labels as $key => $label) {
                                $row = $privKeys[$key] ?? [];
                            ?>
                            <tr>
                                <td class="small"><?php echo htmlspecialchars($label); ?></td>
                                <?php foreach ($roleKeys as $roleKey) { 
                                    $checked = in_array($roleKey, $row ?? [], true);
                                    $disabled = ($roleKey === 'system_admin'); // implicit
                                ?>
                                    <td class="text-center">
                                        <?php if ($disabled) { ?>
                                            <span class="badge bg-success">Yes</span>
                                        <?php } else { ?>
                                        <div class="form-check form-switch d-inline-block">
                                            <input class="form-check-input" type="checkbox" name="privilege_map[<?php echo htmlspecialchars($key); ?>][]" value="<?php echo $roleKey; ?>" <?php echo $checked ? 'checked' : ''; ?>>
                                        </div>
                                        <?php } ?>
                                    </td>
                                <?php } ?>
                            </tr>
                            <?php } ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="col-12 text-end">
                <button class="btn btn-primary" type="submit">Save Settings</button>
            </div>
        </form>
    </div>
</div>

<style>
/* Keep the picker above sticky headers/sidebars */
#entityPickerModal { z-index: 2005 !important; position: fixed; }
.modal-backdrop.show.entity-picker-backdrop { z-index: 2000 !important; }
</style>
<!-- Entity picker modal -->
<div class="modal fade" id="entityPickerModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Select entities</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <div class="mb-3">
            <label class="form-label">Search</label>
            <input type="text" class="form-control" id="entity-search" placeholder="Type to filter by name or ID">
        </div>
        <div class="row row-cols-1 row-cols-md-2 g-2" id="entity-list" data-entities='<?php echo json_encode(array_map(function ($row) {
            return [
                'id' => (int)($row['id'] ?? 0),
                'name' => $row['s_name'] ?? '',
                'type' => $row['s_type'] ?? '',
            ];
        }, $entities)); ?>'>
        </div>
      </div>
      <div class="modal-footer">
        <div class="me-auto small text-muted"><span id="entity-selected-count">0</span> selected</div>
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-primary" id="entity-apply">Apply</button>
      </div>
    </div>
  </div>
</div>

<script>
(function() {
    const modalEl = document.getElementById('entityPickerModal');
    if (!modalEl) return;
    // Move modal to body to escape local stacking contexts
    if (modalEl.parentElement !== document.body) {
        document.body.appendChild(modalEl);
    }
    const raw = document.getElementById('entity-list').dataset.entities || '[]';
    const entities = JSON.parse(raw || '[]');
    let activeRole = null;
    let selected = new Set();

    const searchInput = document.getElementById('entity-search');
    const listEl = document.getElementById('entity-list');
    const selectedCount = document.getElementById('entity-selected-count');

    function renderList(filter = '') {
        const term = filter.toLowerCase();
        const html = entities.filter(e => {
            return e.name.toLowerCase().includes(term) || String(e.id).includes(term);
        }).map(e => {
            const checked = selected.has(String(e.id)) ? 'checked' : '';
            return `
                <div class="col">
                    <div class="form-check border rounded p-2 h-100">
                        <input class="form-check-input entity-option" type="checkbox" value="${e.id}" id="entity-${e.id}" ${checked}>
                        <label class="form-check-label" for="entity-${e.id}">
                            <div class="fw-semibold">${e.name || 'Unnamed'}</div>
                            <div class="text-muted small">ID: ${e.id}${e.type ? ' · ' + e.type : ''}</div>
                        </label>
                    </div>
                </div>
            `;
        }).join('');
        listEl.innerHTML = html || '<div class="col text-muted">No matches.</div>';
        selectedCount.textContent = selected.size;
    }

    function loadFromInput(role) {
        const input = document.querySelector('.entity-input[data-role="' + role + '"]');
        selected.clear();
        if (input && input.value.trim() !== '') {
            input.value.split(',').map(s => s.trim()).filter(Boolean).forEach(id => selected.add(id));
        }
    }

    function updateDisplays(role) {
        const input = document.querySelector('.entity-input[data-role="' + role + '"]');
        const chipContainer = document.querySelector('[data-role-display="' + role + '"]');
        const ids = Array.from(selected).map(Number).filter(n => n > 0);
        ids.sort((a,b) => a - b);
        if (input) {
            input.value = ids.join(',');
        }
        if (chipContainer) {
            if (ids.length === 0) {
                chipContainer.innerHTML = '<span class="badge text-bg-secondary">None</span>';
                return;
            }
            chipContainer.innerHTML = ids.map(id => {
                const entity = entities.find(e => e.id === id);
                const name = entity ? entity.name : 'Unknown';
                return '<span class="badge text-bg-light border text-dark me-1 mb-1">' + name + ' <span class="text-muted">#' + id + '</span></span>';
            }).join('');
        }
    }

    document.querySelectorAll('.manage-entities').forEach(btn => {
        btn.addEventListener('click', () => {
            activeRole = btn.dataset.role;
            loadFromInput(activeRole);
            renderList('');
            searchInput.value = '';
            const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
            modal.show();
        });
    });

    searchInput.addEventListener('input', () => renderList(searchInput.value));

    listEl.addEventListener('change', (e) => {
        if (e.target && e.target.classList.contains('entity-option')) {
            if (e.target.checked) {
                selected.add(e.target.value);
            } else {
                selected.delete(e.target.value);
            }
            selectedCount.textContent = selected.size;
        }
    });

    document.getElementById('entity-apply').addEventListener('click', () => {
        if (!activeRole) return;
        updateDisplays(activeRole);
        const modal = bootstrap.Modal.getInstance(modalEl);
        modal.hide();
    });

    modalEl.addEventListener('shown.bs.modal', () => {
        const backdrop = document.querySelector('.modal-backdrop');
        if (backdrop) {
            backdrop.classList.add('entity-picker-backdrop');
        }
    });
})();
</script>
