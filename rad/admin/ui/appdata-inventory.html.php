<?php
$inventory = $this->runData['data']['system_table_inventory'] ?? [];
$present = (int)($this->runData['data']['total_present'] ?? 0);
$missing = (int)($this->runData['data']['total_missing'] ?? 0);
?>

<div class="card shadow-sm border-0 mb-4">
    <div class="card-body d-flex flex-wrap justify-content-between align-items-center gap-2">
        <div>
            <div class="text-muted small">Read-only overview of core RAD tables.</div>
        </div>
        <div class="d-flex gap-2">
            <span class="badge text-bg-success">Present: <?php echo $present; ?></span>
            <span class="badge text-bg-secondary">Missing: <?php echo $missing; ?></span>
        </div>
    </div>
    <div class="card-body border-top">
        <div class="input-group input-group-sm">
            <span class="input-group-text"><i class="bi bi-search"></i></span>
            <input type="text" class="form-control" id="system-table-search" placeholder="Search tables or fields">
            <button class="btn btn-outline-secondary" type="button" id="system-table-clear">Clear</button>
        </div>
    </div>
</div>

<div class="card shadow-sm border-0">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-sm mb-0 align-middle">
                <thead class="table-light">
                    <tr>
                        <th>Name</th>
                        <th>Description</th>
                        <th>Status</th>
                        <th>Fields</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($inventory as $row) { ?>
                        <tr data-table-row="1" data-table-name="<?php echo htmlspecialchars($row['name']); ?>" data-table-desc="<?php echo htmlspecialchars($row['description']); ?>">
                            <td><code><?php echo htmlspecialchars($row['name']); ?></code></td>
                            <td class="text-muted small"><?php echo htmlspecialchars($row['description']); ?></td>
                            <td>
                                <?php if (!$row['present']) { ?>
                                    <span class="badge text-bg-secondary">Missing</span>
                                <?php } elseif ($row['status'] === 'legacy') { ?>
                                    <span class="badge text-bg-warning">Legacy</span>
                                <?php } else { ?>
                                    <span class="badge text-bg-success">Active</span>
                                <?php } ?>
                            </td>
                            <td>
                                <?php $fieldCount = isset($row['fields']) ? count($row['fields']) : 0; ?>
                                <?php if ($fieldCount === 0) { ?>
                                    <span class="text-muted small">—</span>
                                <?php } else { ?>
                                    <button class="btn btn-sm btn-outline-primary" type="button" data-bs-toggle="collapse" data-bs-target="#fields-<?php echo htmlspecialchars($row['name']); ?>" aria-expanded="false">
                                        View <?php echo $fieldCount; ?>
                                    </button>
                                <?php } ?>
                            </td>
                        </tr>
                        <?php if (!empty($row['fields'])) { ?>
                            <tr class="collapse" id="fields-<?php echo htmlspecialchars($row['name']); ?>" data-fields-row="1" data-table-name="<?php echo htmlspecialchars($row['name']); ?>">
                                <td colspan="4" class="bg-light">
                                    <div class="table-responsive">
                                        <table class="table table-sm mb-0">
                                            <thead>
                                                <tr class="text-muted small">
                                                    <th>Field</th>
                                                    <th>Type</th>
                                                    <th>Nullable</th>
                                                    <th>Default</th>
                                                    <th>Key</th>
                                                    <th>Description</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($row['fields'] as $field) { ?>
                                                    <tr data-field-row="1" data-field-text="<?php echo htmlspecialchars($row['name'] . ' ' . $field['name'] . ' ' . $field['type'] . ' ' . ($field['comment'] ?? '')); ?>">
                                                        <td><code><?php echo htmlspecialchars($field['name']); ?></code></td>
                                                        <td class="text-muted small"><?php echo htmlspecialchars($field['type']); ?></td>
                                                        <td class="text-muted small"><?php echo $field['nullable'] ? 'YES' : 'NO'; ?></td>
                                                        <td class="text-muted small"><?php echo htmlspecialchars((string)($field['default'] ?? '')); ?></td>
                                                        <td class="text-muted small"><?php echo htmlspecialchars($field['key'] ?? ''); ?></td>
                                                        <td class="text-muted small"><?php echo htmlspecialchars($field['comment'] ?: '—'); ?></td>
                                                    </tr>
                                                <?php } ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </td>
                            </tr>
                        <?php } ?>
                    <?php } ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
(() => {
    const input = document.getElementById('system-table-search');
    const clearBtn = document.getElementById('system-table-clear');
    if (!input) return;
    const tableRows = Array.from(document.querySelectorAll('tr[data-table-row="1"]'));
    const fieldRows = Array.from(document.querySelectorAll('tr[data-field-row="1"]'));
    const fieldsContainers = Array.from(document.querySelectorAll('tr[data-fields-row="1"]'));
    const url = new URL(window.location.href);
    const paramKey = 'q';

    const applyFilter = () => {
        const query = input.value.trim().toLowerCase();
        if (query) {
            url.searchParams.set(paramKey, query);
        } else {
            url.searchParams.delete(paramKey);
        }
        window.history.replaceState({}, '', url.toString());
        tableRows.forEach(row => {
            const tableName = (row.dataset.tableName || '').toLowerCase();
            const tableDesc = (row.dataset.tableDesc || '').toLowerCase();
            const tableMatch = !query || tableName.includes(query) || tableDesc.includes(query);
            const tableId = row.dataset.tableName || '';
            const fieldsRow = fieldsContainers.find(r => r.dataset.tableName === tableId);
            let fieldMatch = false;

            if (fieldsRow) {
                const rows = fieldsRow.querySelectorAll('tr[data-field-row="1"]');
                rows.forEach(fr => {
                    const text = (fr.dataset.fieldText || '').toLowerCase();
                    const match = !query || text.includes(query);
                    fr.style.display = (tableMatch || match) ? '' : 'none';
                    if (match) {
                        fieldMatch = true;
                    }
                });
                if (query && fieldMatch) {
                    fieldsRow.classList.add('show');
                } else if (!query) {
                    fieldsRow.classList.remove('show');
                }
            }

            const showRow = tableMatch || fieldMatch;
            row.style.display = showRow ? '' : 'none';
            if (fieldsRow) {
                fieldsRow.style.display = showRow ? '' : 'none';
            }
        });
    };

    input.addEventListener('input', applyFilter);
    if (clearBtn) {
        clearBtn.addEventListener('click', () => {
            input.value = '';
            applyFilter();
            input.focus();
        });
    }
    const initial = url.searchParams.get(paramKey);
    if (initial) {
        input.value = initial;
        applyFilter();
    }
})();
</script>
