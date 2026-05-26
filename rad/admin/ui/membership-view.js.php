<script>
(() => {
    const filterRoleOptions = (roleSelect, scope) => {
        if (!roleSelect) return;
        roleSelect.querySelectorAll('option').forEach(option => {
            const roleScope = option.dataset.scope;
            if (!roleScope) return;
            option.hidden = scope === 'workspace' ? roleScope !== 'workspace' : roleScope !== 'ms';
        });
        if (roleSelect.selectedOptions.length && roleSelect.selectedOptions[0].hidden) {
            roleSelect.selectedIndex = 0;
        }
    };

    const filterMsOptions = (msSelect, spaceId) => {
        if (!msSelect) return;
        msSelect.querySelectorAll('option').forEach(option => {
            const optionSpace = option.dataset.spaceId;
            if (!optionSpace) return;
            option.hidden = optionSpace !== String(spaceId);
        });
        if (msSelect.selectedOptions.length && msSelect.selectedOptions[0].hidden) {
            msSelect.selectedIndex = 0;
        }
    };

    document.querySelectorAll('.scope-selector').forEach(selector => {
        selector.addEventListener('change', function () {
            const targetId = this.dataset.target;
            const msSelect = document.getElementById(targetId);
            const roleSelect = this.closest('form')?.querySelector('.role-selector');
            const msWrapper = msSelect?.closest('.col-6') || msSelect?.closest('.col-md-6') || msSelect?.parentElement;
            if (this.value === 'ms') {
                msSelect?.classList.remove('d-none');
                if (msWrapper) {
                    msWrapper.classList.remove('d-none');
                }
                filterMsOptions(msSelect, msSelect?.dataset.spaceId || '');
            } else {
                msSelect?.classList.add('d-none');
                if (msWrapper) {
                    msWrapper.classList.add('d-none');
                }
                if (msSelect) {
                    msSelect.selectedIndex = 0;
                }
            }
            filterRoleOptions(roleSelect, this.value);
        });
    });

    document.querySelectorAll('.role-selector').forEach(select => {
        const scope = select.closest('form')?.querySelector('.scope-selector')?.value || 'workspace';
        filterRoleOptions(select, scope);
    });

    document.querySelectorAll('.ms-selector').forEach(select => {
        const spaceId = select.dataset.spaceId || select.closest('tr')?.dataset.spaceId || '';
        if (spaceId) {
            filterMsOptions(select, spaceId);
        }
    });

    const selectAll = document.getElementById('bulk-select-all');
    const rowCheckboxes = document.querySelectorAll('.bulk-select');
    const selectedInput = document.getElementById('selected-memberships-input');
    const bulkSubmit = document.querySelector('[data-bulk-submit]');
    const bulkAction = document.querySelector('select[name="bulk_action"]');
    const bulkRole = document.querySelector('[data-bulk-role]');
    const bulkScope = document.querySelector('[data-bulk-scope]');
    const bulkMs = document.querySelector('[data-bulk-ms]');
    const bulkFrom = document.querySelector('[data-bulk-from]');
    const bulkTo = document.querySelector('[data-bulk-to]');

    const updateSelected = () => {
        const selected = Array.from(rowCheckboxes)
            .filter(cb => cb.checked)
            .map(cb => cb.value);
        selectedInput.value = selected.join(',');
        if (bulkSubmit) {
            bulkSubmit.disabled = selected.length === 0;
        }
    };

    const toggleBulkFields = () => {
        const isRole = bulkAction?.value === 'set_role';
        [bulkRole, bulkScope, bulkMs, bulkFrom, bulkTo].forEach(el => {
            if (!el) return;
            el.classList.toggle('d-none', !isRole);
        });
        if (bulkRole) {
            bulkRole.required = isRole;
        }
        if (isRole) {
            const scope = bulkScope?.value || 'workspace';
            filterRoleOptions(bulkRole, scope);
            if (scope === 'ms') {
                bulkMs?.classList.remove('d-none');
            } else {
                bulkMs?.classList.add('d-none');
                if (bulkMs) bulkMs.selectedIndex = 0;
            }
        }
    };

    bulkAction?.addEventListener('change', toggleBulkFields);
    bulkScope?.addEventListener('change', () => {
        filterRoleOptions(bulkRole, bulkScope.value);
        if (bulkScope.value === 'ms') {
            bulkMs?.classList.remove('d-none');
        } else {
            bulkMs?.classList.add('d-none');
            if (bulkMs) bulkMs.selectedIndex = 0;
        }
    });

    if (selectAll) {
        selectAll.addEventListener('change', function () {
            rowCheckboxes.forEach(cb => cb.checked = selectAll.checked);
            updateSelected();
        });
    }

    rowCheckboxes.forEach(cb => cb.addEventListener('change', function () {
        if (!this.checked && selectAll) {
            selectAll.checked = false;
        }
        updateSelected();
    }));

    const inviteModal = document.getElementById('inviteModal');
    const inviteEntityInput = inviteModal?.querySelector('[name="invite_entity"]') || null;
    const inviteEntityId = inviteModal?.querySelector('[name="invite_entity_id"]') || null;
    const inviteSpaceSelect = inviteModal?.querySelector('[name="invite_space_id"]') || null;
    const inviteWarning = document.getElementById('invite-existing-warning');
    const inviteScopeSelect = inviteModal?.querySelector('[name="invite_scope_level"]') || null;
    const inviteMsSelect = document.getElementById('invite-ms-id');
    const inviteMsWrapper = document.getElementById('invite-ms-wrapper');
    const invitePicker = document.getElementById('invite-entity-picker');
    const inviteResults = document.querySelector('[data-invite-entity-results]');
    const inviteCreateToggle = document.getElementById('invite_create_user');
    const inviteNewName = document.getElementById('invite-new-name');
    const inviteNewIdentity = document.getElementById('invite-new-identity');
    const inviteNewEmail = document.getElementById('invite-new-email');
    const inviteNewPassword = document.getElementById('invite-new-password');
    const inviteAutoPass = document.getElementById('invite_new_autopass');
    const addMemberPicker = document.getElementById('add-member-picker');
    const addMemberInput = addMemberPicker?.querySelector('[name="invite_entity"]') || null;
    const addMemberEntityId = document.querySelector('#add-member-form [name="invite_entity_id"]');
    const addMemberResults = document.querySelector('[data-add-member-results]');
    const addMemberWarning = document.getElementById('add-member-existing-warning');
    const addMemberSpaceId = document.querySelector('#add-member-form [name="invite_space_id"]')?.value || '';

    let inviteDebounce = null;
    let addMemberDebounce = null;
    const csrf = document.querySelector('meta[name="rad-csrf"]')?.getAttribute('content') || '';

    const clearInviteResults = () => {
        if (!inviteResults) return;
        inviteResults.innerHTML = '';
        inviteResults.classList.add('d-none');
    };

    const setInviteSelection = (id, label) => {
        if (inviteEntityId) inviteEntityId.value = id;
        if (inviteEntityInput) inviteEntityInput.value = label;
        clearInviteResults();
        checkInviteConflict();
    };

    const renderInviteResults = (items) => {
        if (!inviteResults) return;
        if (!items.length) {
            clearInviteResults();
            return;
        }
        inviteResults.innerHTML = '';
        items.forEach(item => {
            const btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'list-group-item list-group-item-action d-flex justify-content-between align-items-center';
            const label = `${item.s_name || 'User'} (@${item.s_identity || ''}) — #${item.id}`;
            btn.textContent = label;
            btn.addEventListener('click', () => setInviteSelection(item.id, label));
            inviteResults.appendChild(btn);
        });
        inviteResults.classList.remove('d-none');
    };

    const searchInviteEntities = async (term) => {
        if (!invitePicker || term.length < 2) {
            clearInviteResults();
            return;
        }
        try {
            const endpoint = invitePicker.dataset.searchEndpoint || '';
            const res = await fetch(`${endpoint}?q=${encodeURIComponent(term)}`, {
                headers: csrf ? {'X-CSRF-Token': csrf} : {},
            });
            if (!res.ok) {
                clearInviteResults();
                return;
            }
            const data = await res.json();
            renderInviteResults(Array.isArray(data) ? data : []);
        } catch (e) {
            clearInviteResults();
        }
    };

    const clearAddMemberResults = () => {
        if (!addMemberResults) return;
        addMemberResults.innerHTML = '';
        addMemberResults.classList.add('d-none');
    };

    const renderAddMemberResults = (items) => {
        if (!addMemberResults) return;
        if (!items.length) {
            clearAddMemberResults();
            return;
        }
        addMemberResults.innerHTML = '';
        items.forEach(item => {
            const btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'list-group-item list-group-item-action d-flex justify-content-between align-items-center';
            const label = `${item.s_name || 'User'} (@${item.s_identity || ''}) — #${item.id}`;
            btn.textContent = label;
            btn.addEventListener('click', () => {
                if (addMemberEntityId) addMemberEntityId.value = item.id;
                if (addMemberInput) addMemberInput.value = label;
                clearAddMemberResults();
                checkAddMemberConflict();
            });
            addMemberResults.appendChild(btn);
        });
        addMemberResults.classList.remove('d-none');
    };

    const searchAddMemberEntities = async (term) => {
        if (!addMemberPicker || term.length < 2) {
            clearAddMemberResults();
            return;
        }
        try {
            const endpoint = addMemberPicker.dataset.searchEndpoint || '';
            const res = await fetch(`${endpoint}?q=${encodeURIComponent(term)}`, {
                headers: csrf ? {'X-CSRF-Token': csrf} : {},
            });
            if (!res.ok) {
                clearAddMemberResults();
                return;
            }
            const data = await res.json();
            renderAddMemberResults(Array.isArray(data) ? data : []);
        } catch (e) {
            clearAddMemberResults();
        }
    };

    const checkInviteConflict = () => {
        if (!inviteWarning || !inviteSpaceSelect) return;
        const spaceId = inviteSpaceSelect.value;
        const entityId = inviteEntityId?.value || inviteEntityInput?.value.trim();
        if (!/^\d+$/.test(entityId || '') || !spaceId) {
            inviteWarning.classList.add('d-none');
            return;
        }
        const match = document.querySelector(`tr[data-entity-id="${entityId}"][data-space-id="${spaceId}"]`);
        if (match) {
            inviteWarning.classList.remove('d-none');
        } else {
            inviteWarning.classList.add('d-none');
        }
    };

    const checkAddMemberConflict = () => {
        if (!addMemberWarning) return;
        const entityId = addMemberEntityId?.value || addMemberInput?.value.trim();
        if (!/^\d+$/.test(entityId || '') || !addMemberSpaceId) {
            addMemberWarning.classList.add('d-none');
            return;
        }
        const match = document.querySelector(`tr[data-entity-id="${entityId}"][data-space-id="${addMemberSpaceId}"]`);
        addMemberWarning.classList.toggle('d-none', !match);
    };

    inviteEntityInput?.addEventListener('input', () => {
        if (inviteEntityId) inviteEntityId.value = '';
        const term = inviteEntityInput.value.trim();
        clearTimeout(inviteDebounce);
        inviteDebounce = setTimeout(() => searchInviteEntities(term), 250);
        checkInviteConflict();
    });

    addMemberInput?.addEventListener('input', () => {
        if (addMemberEntityId) addMemberEntityId.value = '';
        const term = addMemberInput.value.trim();
        clearTimeout(addMemberDebounce);
        addMemberDebounce = setTimeout(() => searchAddMemberEntities(term), 250);
        checkAddMemberConflict();
    });

    inviteEntityInput?.addEventListener('focus', () => {
        const term = inviteEntityInput.value.trim();
        if (term.length >= 2) {
            searchInviteEntities(term);
        }
    });

    addMemberInput?.addEventListener('focus', () => {
        const term = addMemberInput.value.trim();
        if (term.length >= 2) {
            searchAddMemberEntities(term);
        }
    });

    inviteSpaceSelect?.addEventListener('change', checkInviteConflict);
    inviteSpaceSelect?.addEventListener('change', () => {
        if (inviteMsSelect) {
            filterMsOptions(inviteMsSelect, inviteSpaceSelect.value || '');
        }
    });

    inviteScopeSelect?.addEventListener('change', function () {
        if (!inviteMsSelect || !inviteMsWrapper) return;
        if (this.value === 'ms') {
            inviteMsWrapper.classList.remove('d-none');
            filterMsOptions(inviteMsSelect, inviteSpaceSelect?.value || '');
        } else {
            inviteMsWrapper.classList.add('d-none');
            inviteMsSelect.selectedIndex = 0;
        }
    });

    inviteCreateToggle?.addEventListener('change', function () {
        const isCreating = this.checked;
        invitePicker?.classList.toggle('d-none', isCreating);
        [inviteNewName, inviteNewIdentity, inviteNewEmail, inviteNewPassword].forEach(el => {
            el?.classList.toggle('d-none', !isCreating);
        });
        const newNameInput = document.querySelector('[name="invite_new_name"]');
        const newIdentityInput = document.querySelector('[name="invite_new_identity"]');
        if (newNameInput) {
            newNameInput.required = isCreating;
        }
        if (newIdentityInput) {
            newIdentityInput.required = isCreating;
        }
        if (inviteEntityInput) {
            inviteEntityInput.required = !isCreating;
        }
        if (isCreating) {
            clearInviteResults();
        }
    });

    inviteAutoPass?.addEventListener('change', function () {
        const passwordInput = document.querySelector('[name="invite_new_password"]');
        if (!passwordInput) return;
        if (this.checked) {
            passwordInput.value = '';
            passwordInput.disabled = true;
        } else {
            passwordInput.disabled = false;
        }
    });

    document.addEventListener('click', (e) => {
        if (invitePicker && !invitePicker.contains(e.target)) {
            clearInviteResults();
        }
        if (addMemberPicker && !addMemberPicker.contains(e.target)) {
            clearAddMemberResults();
        }
    });

    document.addEventListener('DOMContentLoaded', () => {
        toggleBulkFields();
        checkInviteConflict();
        checkAddMemberConflict();
        inviteScopeSelect?.dispatchEvent(new Event('change'));
        if (inviteCreateToggle?.checked) {
            inviteCreateToggle.dispatchEvent(new Event('change'));
        }
        if (inviteAutoPass?.checked) {
            inviteAutoPass.dispatchEvent(new Event('change'));
        }

        if (!inviteModal) return;

        const moveInviteModalToBody = () => {
            if (inviteModal.parentElement !== document.body) {
                document.body.appendChild(inviteModal);
            }
        };

        moveInviteModalToBody();
        inviteModal.addEventListener('show.bs.modal', moveInviteModalToBody);
        inviteModal.addEventListener('shown.bs.modal', () => {
            document.querySelectorAll('.modal-backdrop').forEach(backdrop => {
                backdrop.classList.add('invite-members-backdrop');
            });
        });
        inviteModal.addEventListener('hidden.bs.modal', () => {
            document.querySelectorAll('.modal-backdrop.invite-members-backdrop').forEach(backdrop => {
                backdrop.classList.remove('invite-members-backdrop');
            });
        });
    });
})();
</script>
