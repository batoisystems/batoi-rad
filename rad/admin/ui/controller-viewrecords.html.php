<?php
$controller = $this->runData['data']['controller'];
$microservice = $this->runData['data']['microservice'];
$records = $this->runData['data']['records'] ?? [];
$recordColumns = $records['record_columns'] ?? [];
$dmFieldMeta = $records['fields'] ?? [];
if (empty($recordColumns) && !empty($records['columns'])) {
    foreach ($records['columns'] as $col) {
        if (!empty($col['Field'])) {
            $recordColumns[] = $col['Field'];
        }
    }
}
if (empty($recordColumns) && !empty($dmFieldMeta)) {
    foreach ($dmFieldMeta as $fieldRow) {
        if (!empty($fieldRow['s_field_name'])) {
            $recordColumns[] = $fieldRow['s_field_name'];
        }
    }
}
$rows = $records['rows'] ?? [];
$pagination = $records['pagination'] ?? ['page' => 1, 'limit' => 25, 'total' => count($rows), 'pages' => 1];
$tableExists = !empty($records['table_exists']);
$canDelete = !empty($records['can_delete_records']);
$controllerId = (int)($controller['id'] ?? 0);
$schemaUrl = $this->runData['route']['rad_admin_url'] . '/controller/viewschema/' . $controller['uid'] . '/' . $microservice['uid'];
$detailUrl = $this->runData['route']['rad_admin_url'] . '/controller/detail/' . $controller['uid'];
$radAdminUrl = $this->runData['route']['rad_admin_url'];
$systemColumns = $records['system_columns'] ?? [];
?>

<div id="controller-records-app"
     data-controller-id="<?php echo $controllerId; ?>"
     data-fetch-endpoint="<?php echo htmlspecialchars($radAdminUrl . '/controller/datamodelfetch'); ?>"
     data-save-endpoint="<?php echo htmlspecialchars($radAdminUrl . '/controller/datamodelsave'); ?>"
     data-delete-endpoint="<?php echo htmlspecialchars($radAdminUrl . '/controller/datamodeldelete'); ?>"
     data-columns='<?php echo htmlspecialchars(json_encode($records['columns'] ?? []), ENT_QUOTES, 'UTF-8'); ?>'
     data-record-columns='<?php echo htmlspecialchars(json_encode($recordColumns, JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8'); ?>'
     data-fields='<?php echo htmlspecialchars(json_encode($dmFieldMeta ?? []), ENT_QUOTES, 'UTF-8'); ?>'
     data-system-columns='<?php echo htmlspecialchars(json_encode($systemColumns, JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8'); ?>'
     data-table-exists="<?php echo $tableExists ? '1' : '0'; ?>"
     data-can-delete="<?php echo $canDelete ? '1' : '0'; ?>"
     data-controller-uid="<?php echo htmlspecialchars($controller['uid']); ?>">

    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
        <div>
            <h2 class="h4 mb-0">Records · <?php echo htmlspecialchars($controller['s_name']); ?></h2>
            <div class="text-muted small">
                Table <code><?php echo htmlspecialchars($records['table'] ?? ''); ?></code> &middot;
                Microservice <?php echo htmlspecialchars($microservice['s_name']); ?>
            </div>
        </div>
        <div class="btn-group">
            <a href="<?php echo htmlspecialchars($detailUrl); ?>" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left-circle"></i> Controller Detail
            </a>
            <a href="<?php echo htmlspecialchars($schemaUrl); ?>" class="btn btn-outline-primary">
                <i class="bi bi-diagram-3"></i> Manage Schema
            </a>
        </div>
    </div>

    <?php if (!$tableExists) { ?>
        <div class="alert alert-warning mb-4">
            <strong>Table missing.</strong> Create it via the Application Data Sync utility before managing records.
        </div>
    <?php } ?>

    <div class="row g-3">
        <div class="col-lg-8">
            <div class="card h-100">
                <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <div>
                        <strong>Records</strong>
                        <div class="text-muted small">Page <?php echo $pagination['page']; ?> of <?php echo $pagination['pages']; ?> &middot; <?php echo $pagination['total']; ?> total</div>
                    </div>
                    <div class="d-flex gap-2">
                        <button class="btn btn-sm btn-outline-secondary" id="records-refresh" <?php echo $tableExists ? '' : 'disabled'; ?>>
                            <i class="bi bi-arrow-clockwise"></i> Refresh
                        </button>
                        <button class="btn btn-sm btn-primary" id="records-add" <?php echo $tableExists ? '' : 'disabled'; ?>>
                            <i class="bi bi-plus-circle"></i> Add Record
                        </button>
                    </div>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0" id="records-table">
                            <thead class="table-light">
                                <tr>
                                    <?php foreach ($recordColumns as $col) { ?>
                                        <th><?php echo htmlspecialchars($col); ?></th>
                                    <?php } ?>
                                    <th style="width:120px;">Actions</th>
                                </tr>
                            </thead>
                            <tbody id="records-table-body">
                                <?php if (empty($rows)) { ?>
                                    <tr>
                                        <td colspan="<?php echo count($recordColumns) + 1; ?>" class="text-center text-muted py-4">
                                            <?php echo $tableExists ? 'No records yet.' : 'Table unavailable.'; ?>
                                        </td>
                                    </tr>
                                <?php } else { ?>
                                    <?php foreach ($rows as $row) { ?>
                                        <tr data-row-id="<?php echo (int)$row['id']; ?>">
                                            <?php foreach ($recordColumns as $col) { ?>
                                                <td><?php echo isset($row[$col]) ? htmlspecialchars((string)$row[$col]) : ''; ?></td>
                                            <?php } ?>
                                            <td>
                                                <div class="btn-group btn-group-sm" role="group">
                                                    <button class="btn btn-outline-secondary records-edit" data-row='<?php echo htmlspecialchars(json_encode($row, JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8'); ?>'>
                                                        <i class="bi bi-pencil"></i>
                                                    </button>
                                                    <?php if ($canDelete) { ?>
                                                    <button class="btn btn-outline-danger records-delete" data-row-id="<?php echo (int)$row['id']; ?>">
                                                        <i class="bi bi-trash"></i>
                                                    </button>
                                                    <?php } ?>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php } ?>
                                <?php } ?>
                            </tbody>
                        </table>
                    </div>
                    <div class="d-flex justify-content-between align-items-center mt-3 flex-wrap gap-2">
                        <div class="text-muted small" id="records-summary">
                            Showing <?php echo min($pagination['limit'], max(0, $pagination['total'] - ($pagination['page'] - 1) * $pagination['limit'])); ?> of <?php echo $pagination['total']; ?> record(s)
                        </div>
                        <div class="d-flex align-items-center gap-2">
                            <label class="text-muted small mb-0" for="records-limit">Per page</label>
                            <select class="form-select form-select-sm" id="records-limit" style="width:auto;">
                                <?php foreach ([10,25,50,100] as $option) { ?>
                                    <option value="<?php echo $option; ?>" <?php echo ($pagination['limit'] ?? 25) == $option ? 'selected' : ''; ?>><?php echo $option; ?></option>
                                <?php } ?>
                            </select>
                            <div class="btn-group btn-group-sm" role="group">
                                <button class="btn btn-outline-secondary" id="records-prev"><i class="bi bi-chevron-left"></i></button>
                                <button class="btn btn-outline-secondary disabled" id="records-page">Page <?php echo $pagination['page']; ?></button>
                                <button class="btn btn-outline-secondary" id="records-next"><i class="bi bi-chevron-right"></i></button>
                            </div>
                        </div>
                    </div>
                </div>
                <?php if (!$canDelete) { ?>
                    <div class="card-footer text-muted small">
                        Record deletion is limited to the primary entity user.
                    </div>
                <?php } ?>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="card mb-3">
                <div class="card-header">
                    <strong>Column Reference</strong>
                    <div class="text-muted small">System columns are read-only in the editor.</div>
                </div>
                <div class="card-body" style="max-height:360px;overflow:auto;">
                    <?php if (empty($recordColumns)) { ?>
                        <p class="text-muted mb-0">No columns detected. Define schema fields to start capturing data.</p>
                    <?php } else { ?>
                        <ul class="list-group list-group-flush">
                            <?php foreach ($recordColumns as $col) { ?>
                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                    <span>
                                        <code><?php echo htmlspecialchars($col); ?></code>
                                        <?php if (in_array(strtolower($col), array_map('strtolower', $systemColumns), true)) { ?>
                                            <span class="badge bg-secondary ms-2">System</span>
                                        <?php } ?>
                                    </span>
                                </li>
                            <?php } ?>
                        </ul>
                    <?php } ?>
                </div>
            </div>
            <div class="alert alert-info small">
                Fields are generated from the schema designer. Update field labels or help text there to improve authoring experience.
            </div>
        </div>
    </div>
</div>

<div class="modal fade records-modal" id="recordsModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <form id="records-form">
                <div class="modal-header">
                    <h5 class="modal-title">Record</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="row_id" id="records-row-id">
                    <input type="hidden" name="controller_id" id="records-controller-id" value="<?php echo $controllerId; ?>">
                    <div class="row g-3" id="records-form-fields"></div>
                </div>
                <div class="modal-footer">
                    <small class="text-muted me-auto">Core system columns appear read-only and default to RAD-managed values.</small>
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save</button>
                </div>
            </form>
        </div>
    </div>
</div>

<style>
.records-modal {
    z-index: 10650;
}
.modal-backdrop.records-modal-backdrop {
    z-index: 10640 !important;
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function () {
(function(){
    const app = document.getElementById('controller-records-app');
    if (!app) { return; }
    const decodeEntities = (value) => {
        if (!value || value.indexOf('&') === -1) {
            return value;
        }
        const textarea = document.createElement('textarea');
        textarea.innerHTML = value;
        return textarea.value;
    };
    const parseJSON = (value, fallback) => {
        if (!value) {
            return fallback;
        }
        try {
            return JSON.parse(decodeEntities(value));
        } catch (err) {
            console.warn('Unable to parse dataset JSON', err);
            return fallback;
        }
    };
    const normalizeArray = (value) => {
        if (Array.isArray(value)) {
            return value;
        }
        if (value && typeof value === 'object') {
            return Object.values(value);
        }
        return [];
    };
    let parsedColumns = parseJSON(app.dataset.columns || '[]', []);
    let parsedRecordColumns = parseJSON(app.dataset.recordColumns || '[]', []);
    let parsedFields = parseJSON(app.dataset.fields || '[]', []);
    const controllerHidden = document.getElementById('records-controller-id');
    const config = {
        controllerId: parseInt(app.dataset.controllerId, 10) || (controllerHidden ? parseInt(controllerHidden.value, 10) : 0),
        controllerUid: app.dataset.controllerUid || '',
        fetchUrl: app.dataset.fetchEndpoint,
        saveUrl: app.dataset.saveEndpoint,
        deleteUrl: app.dataset.deleteEndpoint,
        columns: normalizeArray(parsedColumns),
        recordColumns: normalizeArray(parsedRecordColumns),
        dmFields: normalizeArray(parsedFields),
        systemColumns: parseJSON(app.dataset.systemColumns || '[]', []),
        tableExists: app.dataset.tableExists === '1',
        canDeleteRecords: app.dataset.canDelete === '1',
        csrf: window.__RAD_CSRF || ''
    };
    const systemSet = new Set(config.systemColumns.map(col => (col || '').toLowerCase()));
    const columnMap = {};
    (config.columns || []).forEach(column => {
        if (!column || !column.Field) {
            return;
        }
        columnMap[column.Field] = column;
        columnMap[column.Field.toLowerCase()] = column;
    });
    const resolveControllerIdentifiers = () => {
        const hiddenInput = document.getElementById('records-controller-id');
        const hiddenId = hiddenInput ? parseInt(hiddenInput.value, 10) : 0;
        const id = config.controllerId || hiddenId || 0;
        const uid = config.controllerUid || app.dataset.controllerUid || '';
        if (!config.controllerId && id) {
            config.controllerId = id;
        }
        if (!config.controllerUid && uid) {
            config.controllerUid = uid;
        }
        return { id, uid };
    };
    const controllerIdentifiers = resolveControllerIdentifiers();
    if (!controllerIdentifiers.id && !controllerIdentifiers.uid) {
        console.error('Controller reference missing for records manager.');
        return;
    }
    const systemOrder = config.systemColumns.length
        ? config.systemColumns
        : ['id','uid','livestatus','versioncode','wf_status','space_id','tenant_id','createdby','createstamp','updatedby','updatestamp'];
    const modalEl = document.getElementById('recordsModal');
    if (modalEl && modalEl.parentElement !== document.body) {
        document.body.appendChild(modalEl);
    }
    const bootstrapLib = window.bootstrap || null;
    const modal = (modalEl && bootstrapLib && bootstrapLib.Modal) ? new bootstrapLib.Modal(modalEl) : null;
    const form = document.getElementById('records-form');
    const formFields = document.getElementById('records-form-fields');
    const tableBody = document.getElementById('records-table-body');
    const summaryEl = document.getElementById('records-summary');
    const limitSelect = document.getElementById('records-limit');
    const prevBtn = document.getElementById('records-prev');
    const nextBtn = document.getElementById('records-next');
    const pageBtn = document.getElementById('records-page');
    const refreshBtn = document.getElementById('records-refresh');
    const addBtn = document.getElementById('records-add');
    let currentPage = parseInt(pageBtn ? pageBtn.textContent.replace(/[^0-9]/g,'') : '1', 10) || 1;
    let totalPages = <?php echo (int)($pagination['pages'] ?? 1); ?>;
    let totalRecords = <?php echo (int)($pagination['total'] ?? 0); ?>;

    function getColumnMeta(name) {
        return columnMap[name] || columnMap[name.toLowerCase()] || null;
    }

    function getDefaultValue(name, row) {
        if (row && Object.prototype.hasOwnProperty.call(row, name)) {
            return row[name];
        }
        const meta = getColumnMeta(name);
        if (!meta) {
            return '';
        }
        if (meta.Extra && meta.Extra.indexOf('auto_increment') !== -1) {
            return '(auto)';
        }
        if (meta.Default !== undefined && meta.Default !== null) {
            return meta.Default;
        }
        return '';
    }

    function buildFieldInput(field, value) {
        const name = field.name;
        const definition = field.definition || {};
        const ui = field.ui || {};
        const control = ui.control || 'text';
        const wrapper = document.createElement('div');
        wrapper.className = 'col-md-6 mb-3';
        const labelEl = document.createElement('label');
        labelEl.className = 'form-label fw-semibold';
        labelEl.textContent = field.label || name;
        wrapper.appendChild(labelEl);
        const helpEl = document.createElement('div');
        helpEl.className = 'form-text text-muted';
        if (field.help) {
            helpEl.textContent = field.help;
        }
        const applyAttributes = (input) => {
            if (ui.attributes) {
                Object.entries(ui.attributes).forEach(([attr, attrVal]) => {
                    input.setAttribute(attr, attrVal);
                });
            }
        };
        const createBasicInput = (type = 'text') => {
            const input = document.createElement('input');
            input.type = type;
            input.className = 'form-control';
            input.name = name;
            input.value = value == null ? '' : value;
            applyAttributes(input);
            return input;
        };
        const options = buildOptions(definition);
        let controlNode;
        switch (control) {
            case 'textarea':
            case 'rich_text': {
                const textarea = document.createElement('textarea');
                textarea.name = name;
                textarea.className = 'form-control';
                textarea.rows = control === 'rich_text' ? 6 : 3;
                textarea.value = value == null ? '' : value;
                controlNode = textarea;
                break;
            }
            case 'select': {
                if (!options.length) {
                    controlNode = createBasicInput('text');
                    break;
                }
                const select = document.createElement('select');
                select.className = 'form-select';
                const multiple = !!ui.multiple;
                select.name = multiple ? name + '[]' : name;
                if (multiple) {
                    select.multiple = true;
                }
                const currentValues = multiple ? parseArrayValue(value) : [value ?? ''];
                options.forEach(opt => {
                    const optionEl = document.createElement('option');
                    optionEl.value = opt.value;
                    optionEl.textContent = opt.label;
                    if (multiple ? currentValues.includes(opt.value) : opt.value === currentValues[0]) {
                        optionEl.selected = true;
                    }
                    select.appendChild(optionEl);
                });
                controlNode = select;
                break;
            }
            case 'checkbox': {
                if (ui.multiple) {
                    if (!options.length) {
                        controlNode = createBasicInput('text');
                        break;
                    }
                    wrapper.classList.add('col-12');
                    const currentValues = parseArrayValue(value);
                    const list = document.createElement('div');
                    list.className = 'd-flex flex-wrap gap-2';
                    options.forEach(opt => {
                        const label = document.createElement('label');
                        label.className = 'form-check form-check-inline';
                        const input = document.createElement('input');
                        input.type = 'checkbox';
                        input.className = 'form-check-input';
                        input.name = name + '[]';
                        input.value = opt.value;
                        if (currentValues.includes(opt.value)) {
                            input.checked = true;
                        }
                        const span = document.createElement('span');
                        span.className = 'form-check-label';
                        span.textContent = opt.label;
                        label.appendChild(input);
                        label.appendChild(span);
                        list.appendChild(label);
                    });
                    controlNode = list;
                } else {
                    const checkbox = document.createElement('div');
                    checkbox.className = 'form-check';
                    const input = document.createElement('input');
                    input.type = 'checkbox';
                    input.className = 'form-check-input';
                    input.name = name;
                    input.value = '1';
                    input.checked = value === '1' || value === 1 || value === true;
                    const span = document.createElement('span');
                    span.className = 'form-check-label';
                    span.textContent = 'Enabled';
                    checkbox.appendChild(input);
                    checkbox.appendChild(span);
                    controlNode = checkbox;
                }
                break;
            }
            case 'radio': {
                if (!options.length) {
                    controlNode = createBasicInput('text');
                    break;
                }
                wrapper.classList.add('col-12');
                const current = value ?? '';
                const list = document.createElement('div');
                list.className = 'd-flex flex-wrap gap-2';
                options.forEach(opt => {
                    const label = document.createElement('label');
                    label.className = 'form-check form-check-inline';
                    const input = document.createElement('input');
                    input.type = 'radio';
                    input.className = 'form-check-input';
                    input.name = name;
                    input.value = opt.value;
                    if (opt.value === current) {
                        input.checked = true;
                    }
                    const span = document.createElement('span');
                    span.className = 'form-check-label';
                    span.textContent = opt.label;
                    label.appendChild(input);
                    label.appendChild(span);
                    list.appendChild(label);
                });
                controlNode = list;
                break;
            }
            case 'date':
            case 'time':
            case 'datetime-local':
            case 'color': {
                controlNode = createBasicInput(control);
                break;
            }
            case 'date-range': {
                wrapper.classList.add('col-12');
                const container = document.createElement('div');
                container.className = 'row g-2';
                const hidden = document.createElement('input');
                hidden.type = 'hidden';
                hidden.name = name;
                hidden.value = value || '';
                const [startVal, endVal] = (value || '').split('|');
                const startCol = document.createElement('div');
                startCol.className = 'col-md-6';
                const endCol = document.createElement('div');
                endCol.className = 'col-md-6';
                const startInput = document.createElement('input');
                startInput.type = 'date';
                startInput.className = 'form-control';
                startInput.value = startVal || '';
                const endInput = document.createElement('input');
                endInput.type = 'date';
                endInput.className = 'form-control';
                endInput.value = endVal || '';
                const syncRange = () => {
                    hidden.value = [startInput.value || '', endInput.value || ''].join('|');
                };
                startInput.addEventListener('change', syncRange);
                endInput.addEventListener('change', syncRange);
                startCol.appendChild(startInput);
                endCol.appendChild(endInput);
                container.appendChild(startCol);
                container.appendChild(endCol);
                wrapper.appendChild(hidden);
                controlNode = container;
                break;
            }
            case 'credit-card': {
                wrapper.classList.add('col-12');
                const container = document.createElement('div');
                container.className = 'row g-2';
                let cardData = {};
                if (value) {
                    try {
                        cardData = JSON.parse(value);
                    } catch (e) {
                        cardData = {};
                    }
                }
                const numberInput = document.createElement('input');
                numberInput.type = 'text';
                numberInput.className = 'form-control';
                numberInput.placeholder = 'Card Number';
                numberInput.value = cardData.number || '';
                const expiryInput = document.createElement('input');
                expiryInput.type = 'month';
                expiryInput.className = 'form-control';
                expiryInput.value = cardData.expiry || '';
                const cvvInput = document.createElement('input');
                cvvInput.type = 'text';
                cvvInput.className = 'form-control';
                cvvInput.placeholder = 'CVV';
                cvvInput.value = cardData.cvv || '';
                const hidden = document.createElement('input');
                hidden.type = 'hidden';
                hidden.name = name;
                const syncCard = () => {
                    hidden.value = JSON.stringify({
                        number: numberInput.value || '',
                        expiry: expiryInput.value || '',
                        cvv: cvvInput.value || '',
                    });
                };
                numberInput.addEventListener('input', syncCard);
                expiryInput.addEventListener('change', syncCard);
                cvvInput.addEventListener('input', syncCard);
                syncCard();
                const col1 = document.createElement('div');
                col1.className = 'col-md-6';
                col1.appendChild(numberInput);
                const col2 = document.createElement('div');
                col2.className = 'col-md-3';
                col2.appendChild(expiryInput);
                const col3 = document.createElement('div');
                col3.className = 'col-md-3';
                col3.appendChild(cvvInput);
                container.appendChild(col1);
                container.appendChild(col2);
                container.appendChild(col3);
                wrapper.appendChild(hidden);
                controlNode = container;
                break;
            }
            case 'file': {
                controlNode = createBasicInput('text');
                controlNode.placeholder = 'Enter file path or reference';
                break;
            }
            case 'auto-suggest': {
                const input = createBasicInput('text');
                if (options.length) {
                    const listId = `${name}-datalist`;
                    const dataList = document.createElement('datalist');
                    dataList.id = listId;
                    options.forEach(opt => {
                        const optionEl = document.createElement('option');
                        optionEl.value = opt.value;
                        optionEl.label = opt.label;
                        dataList.appendChild(optionEl);
                    });
                    input.setAttribute('list', listId);
                    wrapper.appendChild(dataList);
                } else if (definition.source) {
                    const sourceNote = document.createElement('div');
                    sourceNote.className = 'form-text text-muted';
                    sourceNote.textContent = `Source: ${definition.source}`;
                    wrapper.appendChild(sourceNote);
                }
                controlNode = input;
                break;
            }
            default: {
                controlNode = createBasicInput(control === 'tel' ? 'tel' : control);
                break;
            }
        }
        if (controlNode) {
            wrapper.appendChild(controlNode);
        }
        if (field.help) {
            wrapper.appendChild(helpEl);
        }
        if (field.readonly) {
            wrapper.querySelectorAll('input,select,textarea').forEach(el => {
                el.disabled = true;
                el.classList.add('bg-light');
            });
        }
        return wrapper;
    }

    function buildOptions(definition) {
        const opts = definition.options || [];
        return opts.map(opt => {
            if (typeof opt === 'string') {
                return { value: opt, label: opt };
            }
            if (Array.isArray(opt)) {
                return { value: opt[0], label: opt[1] ?? opt[0] };
            }
            if (opt && typeof opt === 'object') {
                return { value: opt.value ?? opt.label ?? '', label: opt.label ?? opt.value ?? '' };
            }
            return { value: '', label: '' };
        }).filter(opt => opt.value);
    }

    function parseArrayValue(value) {
        if (Array.isArray(value)) {
            return value;
        }
        if (!value) {
            return [];
        }
        if (typeof value === 'string') {
            try {
                const parsed = JSON.parse(value);
                if (Array.isArray(parsed)) {
                    return parsed;
                }
            } catch (e) {
                // fall back to CSV
            }
            if (value.includes(',')) {
                return value.split(',').map(item => item.trim()).filter(Boolean);
            }
            return [value];
        }
        return [];
    }

    function collectCustomFields() {
        const rows = [];
        if (Array.isArray(config.dmFields) && config.dmFields.length) {
            config.dmFields.forEach(field => {
                if (!field || !field.s_field_name || systemSet.has(field.s_field_name.toLowerCase())) {
                    return;
                }
                const defMeta = field.definition_meta || {};
                const helpParts = [];
                if (field.s_help_text) {
                    helpParts.push(field.s_help_text);
                }
                if (defMeta.related_table) {
                    helpParts.push(`References ${defMeta.related_table}.${defMeta.related_field || 'id'}`);
                }
                rows.push({
                    name: field.s_field_name,
                    label: field.s_field_label || field.s_field_name,
                    help: helpParts.join(' '),
                    ui: field.ui || {},
                    definition: defMeta,
                });
            });
        } else if (config.recordColumns.length) {
            config.recordColumns.forEach(name => {
                if (!name || systemSet.has(name.toLowerCase())) {
                    return;
                }
                rows.push({ name, label: name, help: '', ui: {}, definition: {} });
            });
        } else if (config.columns.length) {
            config.columns.forEach(column => {
                if (!column || !column.Field || systemSet.has(column.Field.toLowerCase())) {
                    return;
                }
                rows.push({
                    name: column.Field,
                    label: column.Field,
                    help: column.Comment || '',
                    ui: {},
                    definition: {},
                });
            });
        }
        return rows;
    }

    function openModal(row) {
        if (!modal) {
            alert('Bootstrap modal library is unavailable, cannot open the record editor.');
            return;
        }
        document.getElementById('records-row-id').value = row ? row.id : '';
        formFields.innerHTML = '';
        const customFields = collectCustomFields();
        if (customFields.length === 0) {
            const emptyNotice = document.createElement('p');
            emptyNotice.className = 'text-muted';
            emptyNotice.textContent = 'No custom fields detected for this Data Model. Add schema fields to begin capturing data.';
            formFields.appendChild(emptyNotice);
        } else {
            customFields.forEach(field => {
                const value = getDefaultValue(field.name, row);
                formFields.appendChild(buildFieldInput(field, value));
            });
        }
        systemOrder.forEach(systemName => {
            const column = getColumnMeta(systemName);
            if (!column) { return; }
            const displayName = column.Field || systemName;
            const value = getDefaultValue(displayName, row);
            const helpText = 'Core column' + (column.Default !== undefined && column.Default !== null ? ` (default: ${column.Default})` : '');
            formFields.appendChild(buildFieldInput({
                name: displayName,
                label: displayName,
                help: helpText,
                ui: { control: 'text' },
                definition: {},
                readonly: true,
            }, value));
        });
        modal.show();
    }

    function updatePaginationUI(page, pages, total) {
        currentPage = page;
        totalPages = pages;
        totalRecords = total;
        if (pageBtn) {
            pageBtn.textContent = 'Page ' + page;
        }
        if (prevBtn) prevBtn.disabled = page <= 1;
        if (nextBtn) nextBtn.disabled = page >= pages;
        if (summaryEl) {
            const limit = parseInt(limitSelect.value, 10) || 25;
            const showing = Math.min(limit, Math.max(0, total - ((page - 1) * limit)));
            summaryEl.textContent = 'Showing ' + showing + ' of ' + total + ' record(s)';
        }
    }

    function renderRows(rows) {
        if (!tableBody) return;
        tableBody.innerHTML = '';
        if (!rows || rows.length === 0) {
            const td = document.createElement('td');
            td.colSpan = (config.recordColumns.length || 1) + 1;
            td.className = 'text-center text-muted py-4';
            td.textContent = 'No records to display.';
            const tr = document.createElement('tr');
            tr.appendChild(td);
            tableBody.appendChild(tr);
            return;
        }
        rows.forEach(row => {
            const tr = document.createElement('tr');
            tr.dataset.rowId = row.id;
            config.recordColumns.forEach(col => {
                const td = document.createElement('td');
                td.textContent = row[col] == null ? '' : row[col];
                tr.appendChild(td);
            });
            const actionsTd = document.createElement('td');
            const group = document.createElement('div');
            group.className = 'btn-group btn-group-sm';
            const editBtn = document.createElement('button');
            editBtn.className = 'btn btn-outline-secondary records-edit';
            editBtn.innerHTML = '<i class="bi bi-pencil"></i>';
            editBtn.dataset.row = JSON.stringify(row);
            group.appendChild(editBtn);
            if (config.canDeleteRecords) {
                const delBtn = document.createElement('button');
                delBtn.className = 'btn btn-outline-danger records-delete';
                delBtn.innerHTML = '<i class="bi bi-trash"></i>';
                delBtn.dataset.rowId = row.id;
                group.appendChild(delBtn);
            }
            actionsTd.appendChild(group);
            tr.appendChild(actionsTd);
            tableBody.appendChild(tr);
        });
    }

    function fetchPage(page) {
        if (!config.tableExists || !config.fetchUrl) {
            return;
        }
        const identifiers = resolveControllerIdentifiers();
        const payload = {
            controller_id: identifiers.id,
            controller_uid: identifiers.uid,
            page: page,
            limit: parseInt(limitSelect.value, 10) || 25,
            csrf_token: config.csrf
        };
        fetch(config.fetchUrl, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        }).then(r => r.json())
        .then(resp => {
            if (!resp.success) {
                alert(resp.message || 'Unable to fetch records.');
                return;
            }
            renderRows(resp.rows || []);
            updatePaginationUI(resp.page || 1, resp.pages || 1, resp.total || 0);
            bindRowActions();
        }).catch(() => alert('Unable to fetch records.'));
    }

    function bindRowActions() {
        if (!tableBody) return;
        tableBody.querySelectorAll('.records-edit').forEach(btn => {
            btn.onclick = () => {
                try {
                    const row = JSON.parse(btn.dataset.row || '{}');
                    openModal(row);
                } catch (err) {
                    alert('Unable to load record for editing.');
                }
            };
        });
        tableBody.querySelectorAll('.records-delete').forEach(btn => {
            btn.onclick = () => {
                const rowId = btn.dataset.rowId;
                if (!rowId) return;
                if (!confirm('Delete record #' + rowId + '?')) {
                    return;
                }
                const identifiers = resolveControllerIdentifiers();
                fetch(config.deleteUrl, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        controller_id: identifiers.id,
                        controller_uid: identifiers.uid,
                        row_id: rowId,
                        csrf_token: config.csrf
                    })
                }).then(r => r.json())
                .then(resp => {
                    if (!resp.success) {
                        alert(resp.message || 'Unable to delete record.');
                        return;
                    }
                    fetchPage(currentPage);
                }).catch(() => alert('Unable to delete record.'));
            };
        });
    }

    if (modal && modalEl) {
        modalEl.addEventListener('shown.bs.modal', () => {
            document.querySelectorAll('.modal-backdrop').forEach(backdrop => {
                backdrop.classList.add('records-modal-backdrop');
            });
        });
        modalEl.addEventListener('hidden.bs.modal', () => {
            document.querySelectorAll('.modal-backdrop.records-modal-backdrop').forEach(backdrop => {
                backdrop.classList.remove('records-modal-backdrop');
            });
        });
    }
    if (addBtn) {
        addBtn.addEventListener('click', () => openModal(null));
    }
    if (refreshBtn) {
        refreshBtn.addEventListener('click', () => fetchPage(currentPage));
    }
    if (prevBtn) {
        prevBtn.addEventListener('click', () => {
            if (currentPage > 1) {
                fetchPage(currentPage - 1);
            }
        });
    }
    if (nextBtn) {
        nextBtn.addEventListener('click', () => {
            if (currentPage < totalPages) {
                fetchPage(currentPage + 1);
            }
        });
    }
    if (limitSelect) {
        limitSelect.addEventListener('change', () => fetchPage(1));
    }
    if (form) {
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            const elements = form.querySelectorAll('input[name],textarea[name],select[name]');
            const values = {};
            elements.forEach(el => {
                const originalName = el.name;
                if (originalName === 'row_id') {
                    return;
                }
                const isArray = originalName.endsWith('[]');
                const baseName = isArray ? originalName.slice(0, -2) : originalName;
                if (systemSet.has(baseName.toLowerCase())) {
                    return;
                }
                if (el.type === 'checkbox') {
                    if (isArray) {
                        if (!values[baseName]) {
                            values[baseName] = [];
                        }
                        if (el.checked) {
                            values[baseName].push(el.value);
                        }
                    } else {
                        values[baseName] = el.checked ? (el.value || '1') : '0';
                    }
                } else if (el.type === 'radio') {
                    if (el.checked) {
                        values[baseName] = el.value;
                    }
                } else if (el.tagName === 'SELECT' && el.multiple) {
                    const selected = Array.from(el.selectedOptions).map(opt => opt.value);
                    values[baseName] = selected;
                } else {
                    if (isArray) {
                        if (!values[baseName]) {
                            values[baseName] = [];
                        }
                        values[baseName].push(el.value);
                    } else {
                        values[baseName] = el.value;
                    }
                }
            });
        const identifiers = resolveControllerIdentifiers();
        const payload = {
            controller_id: identifiers.id,
            controller_uid: identifiers.uid,
            row_id: document.getElementById('records-row-id').value || undefined,
            values,
            csrf_token: config.csrf
        };
            fetch(config.saveUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            }).then(r => r.json())
            .then(resp => {
                if (!resp.success) {
                    alert(resp.message || 'Unable to save record.');
                    return;
                }
                modal.hide();
                fetchPage(currentPage);
            }).catch(() => alert('Unable to save record.'));
        });
    }

    bindRowActions();
})();
});
</script>
