<script>
(function() {
    const app = document.getElementById('controller-schema-app');
    if (!app) {
        return;
    }

    const modalEl = document.getElementById('schemaFieldModal');
    const modal = modalEl && window.RadAdminUI ? window.RadAdminUI.getModal(modalEl) : null;
    if (modalEl && modalEl.parentElement !== document.body) {
        document.body.appendChild(modalEl);
    }
    const form = document.getElementById('schema-field-form');
    const titleEl = document.getElementById('schema-field-modal-title');
    const controllerId = parseInt(app.dataset.controllerId, 10);
    const controllerUid = app.dataset.controllerUid || '';
    const branch = app.dataset.branch || 'live';
    const branchMissing = app.dataset.branchMissing === '1';
    if (!controllerId) {
        console.error('Schema manager missing controller identifier.');
        return;
    }
    const addEndpoint = app.dataset.addEndpoint;
    const updateEndpoint = app.dataset.updateEndpoint;
    const deleteEndpoint = app.dataset.deleteEndpoint;

    const fieldIdInput = document.getElementById('schema-field-id');
    const labelInput = document.getElementById('schema-field-label');
    const nameInput = document.getElementById('schema-field-name');
    const namePreview = document.getElementById('schema-field-name-preview');
    const typeInput = document.getElementById('schema-field-type');
    const lengthInput = document.getElementById('schema-field-length');
    const precisionInput = document.getElementById('schema-field-precision');
    const scaleInput = document.getElementById('schema-field-scale');
    const helpInput = document.getElementById('schema-field-help');
    const requiredInput = document.getElementById('schema-field-required');
    const indexInput = document.getElementById('schema-field-index');
    const lengthGroup = document.querySelector('.schema-length-group');
    const precisionGroup = document.querySelector('.schema-precision-group');
    const scaleGroup = document.querySelector('.schema-scale-group');
    const optionsGroup = document.querySelector('.schema-options-group');
    const optionsInput = document.getElementById('schema-field-options');
    const fkTableGroup = document.querySelector('.schema-fk-table-group');
    const fkTableInput = document.getElementById('schema-field-fk-table');
    const fkColumnGroup = document.querySelector('.schema-fk-column-group');
    const fkColumnInput = document.getElementById('schema-field-fk-column');
    const sourceGroup = document.querySelector('.schema-source-group');
    const sourceInput = document.getElementById('schema-field-source');
    const customSqlGroup = document.querySelector('.schema-custom-sql-group');
    const customSqlInput = document.getElementById('schema-field-custom-sql');
    const fieldTypeMeta = {};
    Array.from(typeInput.options).forEach(opt => {
        if (!opt.value) return;
        try {
            fieldTypeMeta[opt.value] = JSON.parse(opt.getAttribute('data-meta') || '{}') || {};
        } catch (e) {
            fieldTypeMeta[opt.value] = {};
        }
    });

    const stripPrefix = (value) => (value || '').replace(/^a_/, '');

    function updateNamePreview() {
        if (!namePreview || !nameInput) return;
        const base = nameInput.value.trim();
        namePreview.textContent = base ? 'a_' + base : 'a_';
    }
    if (nameInput) {
        nameInput.addEventListener('input', updateNamePreview);
    }

    document.getElementById('open-field-modal').addEventListener('click', () => {
        if (branch === 'beta' && branchMissing) {
            alert('Create a beta schema before adding fields.');
            return;
        }
        resetForm();
        titleEl.textContent = 'Add Field';
        modal.show();
    });

    function toggleGroup(group, show) {
        if (!group) return;
        if (show) {
            group.classList.remove('d-none');
        } else {
            group.classList.add('d-none');
        }
    }

    function applyTypeConstraints(existingDefinition = {}) {
        if (!typeInput) return;
        const meta = fieldTypeMeta[typeInput.value] || {};
        const ui = meta.ui || {};
        toggleGroup(lengthGroup, !!meta.supports_length);
        toggleGroup(precisionGroup, !!meta.supports_precision);
        toggleGroup(scaleGroup, !!meta.supports_scale);
        toggleGroup(optionsGroup, !!ui.supports_options);
        toggleGroup(fkTableGroup, !!ui.supports_foreign_key);
        toggleGroup(fkColumnGroup, !!ui.supports_foreign_key);
        toggleGroup(sourceGroup, !!ui.supports_source);
        toggleGroup(customSqlGroup, !!ui.supports_custom_sql);
        if (!meta.supports_length) {
            lengthInput.value = '';
        } else if (!lengthInput.value) {
            lengthInput.value = existingDefinition.length || meta.default_length || '';
        }
        if (!meta.supports_precision) {
            precisionInput.value = '';
        } else if (!precisionInput.value) {
            precisionInput.value = existingDefinition.precision || meta.default_precision || '';
        }
        if (!meta.supports_scale) {
            scaleInput.value = '';
        } else if (!scaleInput.value) {
            scaleInput.value = existingDefinition.scale || meta.default_scale || '';
        }
        if (!ui.supports_options && optionsInput) {
            optionsInput.value = '';
        }
        if (!ui.supports_foreign_key) {
            if (fkTableInput) fkTableInput.value = '';
            if (fkColumnInput) fkColumnInput.value = '';
        }
        if (!ui.supports_source && sourceInput) {
            sourceInput.value = '';
        }
        if (!ui.supports_custom_sql && customSqlInput) {
            customSqlInput.value = '';
        }
    }

    function resetForm() {
        fieldIdInput.value = '';
        labelInput.value = '';
        if (nameInput) nameInput.value = '';
        typeInput.selectedIndex = 0;
        lengthInput.value = '';
        precisionInput.value = '';
        scaleInput.value = '';
        helpInput.value = '';
        requiredInput.checked = false;
        if (indexInput) {
            indexInput.checked = false;
        }
        if (optionsInput) optionsInput.value = '';
        if (fkTableInput) fkTableInput.value = '';
        if (fkColumnInput) fkColumnInput.value = '';
        if (sourceInput) sourceInput.value = '';
        if (customSqlInput) customSqlInput.value = '';
        updateNamePreview();
        applyTypeConstraints();
    }

    function slugify(value) {
        let slug = (value || '').toLowerCase();
        slug = slug.replace(/[^a-z0-9_]+/g, '_');
        slug = slug.replace(/^_+|_+$/g, '');
        return slug || 'field';
    }

    function collectPayload() {
        const label = labelInput.value.trim();
        if (!label) {
            alert('Field label is required.');
            return null;
        }
        let baseName = nameInput ? nameInput.value.trim() : '';
        if (!baseName) {
            baseName = slugify(label);
            if (nameInput) {
                nameInput.value = baseName;
                updateNamePreview();
            }
        }
        if (!/^[a-z0-9_]+$/.test(baseName)) {
            alert('Field name may contain only lowercase letters, numbers, and underscores.');
            return null;
        }
        const payload = {
            controller_id: controllerId,
            controller_uid: controllerUid,
            field_id: fieldIdInput.value ? parseInt(fieldIdInput.value, 10) : undefined,
            label,
            field_name: baseName,
            field_type_id: typeInput.value,
            help_text: helpInput.value.trim(),
            nullable: requiredInput.checked ? 0 : 1,
            create_index: indexInput && indexInput.checked ? 1 : 0,
        };
        if (!payload.field_type_id) {
            alert('Select a field type.');
            return null;
        }
        const meta = fieldTypeMeta[payload.field_type_id] || {};
        if (meta.supports_length) {
            const lenVal = parseInt(lengthInput.value, 10);
            if (!lenVal || lenVal <= 0) {
                alert('Length is required for this field type.');
                return null;
            }
            payload.length = lenVal;
        }
        if (meta.supports_precision) {
            const precisionVal = parseInt(precisionInput.value, 10);
            if (!precisionVal || precisionVal <= 0) {
                alert('Precision is required for this field type.');
                return null;
            }
            payload.precision = precisionVal;
        }
        if (meta.supports_scale) {
            const scaleVal = parseInt(scaleInput.value, 10);
            if (Number.isNaN(scaleVal) || scaleVal < 0) {
                alert('Scale must be zero or greater.');
                return null;
            }
            payload.scale = scaleVal;
        }
        return payload;
    }

    function parseOptions(raw) {
        if (!raw) {
            return [];
        }
        const lines = raw.split(/\r?\n/);
        const options = [];
        lines.forEach(line => {
            const trimmed = line.trim();
            if (!trimmed) {
                return;
            }
            const parts = trimmed.split('|');
            const value = parts[0].trim();
            const label = parts[1] ? parts[1].trim() : value;
            if (!value) {
                return;
            }
            options.push({ value, label });
        });
        return options;
    }

    form.addEventListener('submit', function(e) {
        e.preventDefault();
        const payload = collectPayload();
        if (!payload) {
            return;
        }
        const endpoint = payload.field_id ? updateEndpoint : addEndpoint;
        fetch(endpoint, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        })
        .then(res => res.json())
        .then(data => {
            if (!data.success) {
                alert(data.message || 'Schema update failed.');
                return;
            }
            window.location.reload();
        })
        .catch(() => alert('Unable to reach schema service.'));
    });

    document.querySelectorAll('.schema-edit-field').forEach(btn => {
        btn.addEventListener('click', function() {
            const row = this.closest('tr');
            const data = JSON.parse(row.dataset.field);
            fieldIdInput.value = data.id;
            labelInput.value = data.label || '';
            if (nameInput) nameInput.value = stripPrefix(data.name || '');
            typeInput.value = data.field_type_id;
            helpInput.value = data.help_text || '';
            requiredInput.checked = data.nullable === 0;
            if (indexInput) {
                indexInput.checked = !!data.is_indexed;
            }

            let definition = {};
            try {
                definition = data.definition ? JSON.parse(data.definition) : {};
            } catch (err) {
                definition = {};
            }
            lengthInput.value = definition.length || '';
            precisionInput.value = definition.precision || '';
            scaleInput.value = definition.scale || '';
            if (optionsInput) {
                if (definition.options) {
                    optionsInput.value = (definition.options || []).map(opt => {
                        if (typeof opt === 'string') {
                            return `${opt}|${opt}`;
                        }
                        const value = opt.value ?? opt[0] ?? '';
                        const label = opt.label ?? opt[1] ?? value;
                        return `${value}|${label}`;
                    }).join('\n');
                } else {
                    optionsInput.value = '';
                }
            }
            if (fkTableInput) {
                fkTableInput.value = definition.related_table || '';
            }
            if (fkColumnInput) {
                fkColumnInput.value = definition.related_field || '';
            }
            if (sourceInput) {
                sourceInput.value = definition.source || '';
            }
            if (customSqlInput) {
                customSqlInput.value = definition.custom_sql || '';
            }
            updateNamePreview();
            if (indexInput && typeof definition.index !== 'undefined') {
                indexInput.checked = !!definition.index;
            }

            titleEl.textContent = 'Edit Field';
            applyTypeConstraints(definition);
            modal.show();
        });
    });

    document.querySelectorAll('.schema-delete-field').forEach(btn => {
        btn.addEventListener('click', function() {
            const row = this.closest('tr');
            const data = JSON.parse(row.dataset.field);
            if (!confirm(`Delete field "${data.label}"? This cannot be undone.`)) {
                return;
            }
            fetch(deleteEndpoint, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ controller_id: controllerId, controller_uid: controllerUid, field_id: data.id })
            })
            .then(res => res.json())
            .then(data => {
                if (!data.success) {
                    alert(data.message || 'Unable to delete field.');
                    return;
                }
                window.location.reload();
            })
            .catch(() => alert('Unable to reach schema service.'));
        });
    });
    modalEl.addEventListener('shown.bs.modal', () => document.body.classList.add('schema-field-modal-open'));
    modalEl.addEventListener('hidden.bs.modal', () => document.body.classList.remove('schema-field-modal-open'));

    if (typeInput) {
        typeInput.addEventListener('change', () => applyTypeConstraints());
        applyTypeConstraints();
    }
    updateNamePreview();
})();
</script>
