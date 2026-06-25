<script>
document.addEventListener('DOMContentLoaded', () => {
    const app = document.getElementById('nav-admin-app');
    if (!app) {
        return;
    }
    const navsetModalEl = document.getElementById('navsetModal');
    const navitemModalEl = document.getElementById('navitemModal');
    const ensureModalInBody = (modalEl) => {
        if (modalEl && modalEl.parentElement !== document.body) {
            document.body.appendChild(modalEl);
        }
    };
    ensureModalInBody(navsetModalEl);
    ensureModalInBody(navitemModalEl);
    const navsetModal = navsetModalEl && window.RadAdminUI ? window.RadAdminUI.getModal(navsetModalEl) : null;
    const navitemModal = navitemModalEl && window.RadAdminUI ? window.RadAdminUI.getModal(navitemModalEl) : null;
    const navsetForm = document.getElementById('navset-form');
    const navitemForm = document.getElementById('navitem-form');
    const navsetRoleCheckboxes = document.querySelectorAll('.navset-role-check');
    const navsetRolesField = document.getElementById('navset-roles'); // legacy fallback (not used now)
    const navsetAppField = document.getElementById('navset-ms');
    const navitemParentField = document.getElementById('navitem-parent');
    const navsetDeleteButtons = document.querySelectorAll('.navset-delete');
    const navsetList = document.querySelector('.navset-list');
    const navitemArchiveButtons = document.querySelectorAll('.navitem-archive');
    const navitemActivateButtons = document.querySelectorAll('.navitem-activate');
    const navitemRefreshButton = document.getElementById('navitem-refresh');
    const navitemsBody = document.getElementById('nav-items-body');
    const selectedNavsetId = parseInt(app.dataset.navset || '0', 10) || 0;
    const navsetOrderUrl = app.dataset.navsetOrder || '';

    const serializeRoles = (selectEl) => {
        if (!selectEl) {
            return [];
        }
        return Array.from(selectEl.selectedOptions).map(option => option.value);
    };

    const postJson = (url, payload) => {
        return fetch(url, {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify(payload || {})
        }).then(res => res.json());
    };

    // Nav set modal handling
    const resetNavSetForm = () => {
        if (!navsetForm) return;
        navsetForm.reset();
        (document.getElementById('navset-id') || {}).value = '';
        if (navsetRoleCheckboxes.length) {
            navsetRoleCheckboxes.forEach(cb => cb.checked = false);
        }
        if (navsetAppField) {
            navsetAppField.value = '0';
        }
    };
    const navsetAddBtn = document.getElementById('navset-add-btn');
    const navsetAddBtnEmpty = document.getElementById('navset-add-btn-empty');
    [navsetAddBtn, navsetAddBtnEmpty].forEach(btn => {
        if (btn) {
            btn.addEventListener('click', () => resetNavSetForm());
        }
    });

    document.querySelectorAll('.navset-edit').forEach(btn => {
        btn.addEventListener('click', () => {
            if (!navsetForm) return;
            const raw = btn.dataset.navset ? JSON.parse(btn.dataset.navset) : {};
            navsetForm.reset();
            (document.getElementById('navset-id') || {}).value = raw.id || '';
            (document.getElementById('navset-name') || {}).value = raw.s_name || '';
            if (document.getElementById('navset-description')) {
                document.getElementById('navset-description').value = raw.s_description || '';
            }
            if (navsetAppField) {
                navsetAppField.value = raw.s_ms_id || '0';
            }
            if (navsetRoleCheckboxes.length) {
                const access = raw.access_roles || [];
                navsetRoleCheckboxes.forEach(cb => {
                    cb.checked = access.includes(parseInt(cb.value, 10));
                });
            }
        });
    });
    if (navsetForm) {
        navsetForm.addEventListener('submit', (e) => {
            e.preventDefault();
            let roles = [];
            if (navsetRoleCheckboxes.length) {
                roles = Array.from(navsetRoleCheckboxes).filter(cb => cb.checked).map(cb => cb.value);
            } else if (navsetRolesField) { // fallback
                roles = Array.from(navsetRolesField.selectedOptions || []).map(opt => opt.value);
            }
            const payload = {
                id: (document.getElementById('navset-id') || {}).value || undefined,
                s_name: (document.getElementById('navset-name') || {}).value || '',
                s_description: (document.getElementById('navset-description') || {}).value || '',
                s_ms_id: navsetAppField ? navsetAppField.value : '0',
                roles: roles
            };
            postJson(app.dataset.navsetSave, payload).then(resp => {
                if (!resp.success) {
                    alert(resp.message || 'Unable to save nav set.');
                    return;
                }
                if (navsetModal) {
                    navsetModal.hide();
                }
                const redirectId = resp.navset && resp.navset.id ? resp.navset.id : (payload.id || selectedNavsetId);
                window.location = `${app.dataset.viewBase}/${redirectId}`;
            }).catch(() => alert('Unable to save nav set.'));
        });
    }

    // Drag and drop ordering for navsets
    if (navsetList && navsetOrderUrl) {
        let dragged = null;
        navsetList.querySelectorAll('.navset-row').forEach(item => {
            item.draggable = true;
            item.addEventListener('dragstart', (e) => {
                dragged = item;
                item.classList.add('dragging');
                e.dataTransfer.effectAllowed = 'move';
            });
            item.addEventListener('dragend', () => {
                if (dragged) {
                    dragged.classList.remove('dragging');
                    dragged = null;
                    saveNavsetOrder();
                }
            });
            item.addEventListener('dragover', (e) => {
                e.preventDefault();
                const target = e.target.closest('.navset-row');
                if (!target || target === dragged) return;
                const rect = target.getBoundingClientRect();
                const before = (e.clientY - rect.top) < (rect.height / 2);
                navsetList.insertBefore(dragged, before ? target : target.nextSibling);
            });
            });

        const saveNavsetOrder = () => {
            const ids = Array.from(navsetList.querySelectorAll('.navset-row')).map(el => el.dataset.navsetId).filter(Boolean);
            if (!ids.length) return;
            postJson(navsetOrderUrl, {order: ids}).catch(() => {
                alert('Unable to save navigation set order.');
            });
        };
    }
    navsetDeleteButtons.forEach(btn => {
        btn.addEventListener('click', () => {
            const navsetId = parseInt(btn.dataset.navsetId || '0', 10);
            if (!navsetId) {
                return;
            }
            if (!confirm('Archive this nav set? Menus will stop rendering until reactivated.')) {
                return;
            }
            postJson(app.dataset.navsetDelete, {navset_id: navsetId}).then(resp => {
                if (!resp.success) {
                    alert(resp.message || 'Unable to archive nav set.');
                    return;
                }
                window.location = `${app.dataset.viewBase}`;
            }).catch(() => alert('Unable to archive nav set.'));
        });
    });

    // Nav item modal handling
    const resetNavItemForm = () => {
        if (!navitemForm) return;
            navitemForm.reset();
            (document.getElementById('navitem-id') || {}).value = '';
            (document.getElementById('navitem-navset-id') || {}).value = selectedNavsetId || '';
            if (navitemParentField) {
                navitemParentField.value = '0';
            }
        };
    const navitemAddBtn = document.getElementById('navitem-add-btn');
    if (navitemAddBtn) {
        navitemAddBtn.addEventListener('click', () => {
            resetNavItemForm();
        });
    }

    document.querySelectorAll('.navitem-edit').forEach(btn => {
        btn.addEventListener('click', () => {
            if (!navitemForm) return;
            const data = btn.dataset.navitem ? JSON.parse(btn.dataset.navitem) : {};
            navitemForm.reset();
            (document.getElementById('navitem-id') || {}).value = data.id || '';
            (document.getElementById('navitem-navset-id') || {}).value = data.s_navset_id || selectedNavsetId || '';
            (document.getElementById('navitem-name') || {}).value = data.s_name || '';
            (document.getElementById('navitem-href') || {}).value = data.s_href || '';
            (document.getElementById('navitem-icon') || {}).value = data.s_icon || '';
            (document.getElementById('navitem-target') || {}).value = data.s_target || '_self';
            (document.getElementById('navitem-badge') || {}).value = data.s_badge || '';
            (document.getElementById('navitem-condition') || {}).value = data.s_condition || '';
            (document.getElementById('navitem-device') || {}).value = data.s_device || 'all';
            if (navitemParentField) {
                // Exclude self from parent choices and restore selection
                Array.from(navitemParentField.options).forEach(opt => {
                    const val = opt.value;
                    opt.disabled = (val && val === String(data.id || ''));
                });
                navitemParentField.value = data.s_parent_nav_id || '0';
            }
        });
    });
    if (navitemForm) {
        navitemForm.addEventListener('submit', (e) => {
            e.preventDefault();
            const metaInput = (document.getElementById('navitem-meta') || {}).value || '';
            if (metaInput.trim() !== '') {
                try { JSON.parse(metaInput); }
                catch (err) {
                    alert('Meta must be valid JSON.');
                    return;
                }
            }
            const payload = {
                id: (document.getElementById('navitem-id') || {}).value || undefined,
                s_navset_id: (document.getElementById('navitem-navset-id') || {}).value || selectedNavsetId,
                s_name: (document.getElementById('navitem-name') || {}).value || '',
                s_href: (document.getElementById('navitem-href') || {}).value || '',
                s_icon: (document.getElementById('navitem-icon') || {}).value || '',
                s_target: (document.getElementById('navitem-target') || {}).value || '_self',
                s_badge: (document.getElementById('navitem-badge') || {}).value || '',
                s_device: (document.getElementById('navitem-device') || {}).value || 'all',
                s_parent_nav_id: navitemParentField ? navitemParentField.value : '0',
                s_meta: metaInput || '',
                s_meta_title: (document.getElementById('navitem-meta-title') || {}).value || '',
            };
            postJson(app.dataset.itemSave, payload).then(resp => {
                if (!resp.success) {
                    alert(resp.message || 'Unable to save nav item.');
                    return;
                }
                if (navitemModal) {
                    navitemModal.hide();
                }
                window.location.reload();
            }).catch(() => alert('Unable to save nav item.'));
        });
    }

    navitemArchiveButtons.forEach(btn => {
        btn.addEventListener('click', () => {
            const navId = parseInt(btn.dataset.navId || '0', 10);
            if (!navId) {
                return;
            }
            if (!confirm('Archive this nav item?')) {
                return;
            }
            postJson(app.dataset.itemArchive, {id: navId}).then(resp => {
                if (!resp.success) {
                    alert(resp.message || 'Unable to archive item.');
                    return;
                }
                window.location.reload();
            }).catch(() => alert('Unable to archive item.'));
        });
    });
    navitemActivateButtons.forEach(btn => {
        btn.addEventListener('click', () => {
            const navId = parseInt(btn.dataset.navId || '0', 10);
            if (!navId) {
                return;
            }
            postJson(app.dataset.itemActivate, {id: navId}).then(resp => {
                if (!resp.success) {
                    alert(resp.message || 'Unable to activate item.');
                    return;
                }
                window.location.reload();
            }).catch(() => alert('Unable to activate item.'));
        });
    });
    if (navitemRefreshButton) {
        navitemRefreshButton.addEventListener('click', () => window.location.reload());
    }

    // Drag and drop ordering
    if (navitemsBody) {
        let dragRow = null;
        navitemsBody.querySelectorAll('tr[draggable="true"]').forEach(row => {
            row.addEventListener('dragstart', (event) => {
                dragRow = row;
                row.classList.add('dragging');
                event.dataTransfer.effectAllowed = 'move';
            });
            row.addEventListener('dragover', (event) => {
                event.preventDefault();
                const target = event.currentTarget;
                if (dragRow && dragRow !== target) {
                    const bounding = target.getBoundingClientRect();
                    const offset = bounding.y + bounding.height / 2;
                    if (event.clientY - offset > 0) {
                        target.after(dragRow);
                    } else {
                        target.before(dragRow);
                    }
                }
            });
            row.addEventListener('dragend', () => {
                if (dragRow) {
                    dragRow.classList.remove('dragging');
                    dragRow = null;
                    persistOrder();
                }
            });
        });

        const persistOrder = () => {
            const ids = Array.from(navitemsBody.querySelectorAll('tr[data-id]'))
                .map(tr => tr.dataset.id)
                .filter(Boolean);
            if (!ids.length) {
                return;
            }
            postJson(app.dataset.itemOrder, {navset_id: selectedNavsetId, order: ids})
                .catch(() => console.warn('Unable to persist nav order.'));
        };
    }

    const handleModalBackdrop = (modalEl) => {
        if (!modalEl) return;
        modalEl.addEventListener('shown.bs.modal', () => {
            document.querySelectorAll('.modal-backdrop').forEach(backdrop => {
                backdrop.classList.add('nav-modal-backdrop');
            });
            modalEl.classList.add('nav-modal');
            document.body.classList.add('nav-modal-active');
        });
        modalEl.addEventListener('hidden.bs.modal', () => {
            document.querySelectorAll('.modal-backdrop.nav-modal-backdrop').forEach(backdrop => {
                backdrop.classList.remove('nav-modal-backdrop');
            });
            modalEl.classList.remove('nav-modal');
            if (!document.querySelector('.modal.show')) {
                document.body.classList.remove('nav-modal-active');
            }
        });
    };
    handleModalBackdrop(navsetModalEl);
    handleModalBackdrop(navitemModalEl);
});
</script>
