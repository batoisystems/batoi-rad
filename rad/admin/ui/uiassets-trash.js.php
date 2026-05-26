<script>
const fetchArchivedUrl = '<?php echo $fetchUrl; ?>';
const restoreUrl = '<?php echo $restoreUrl; ?>';
const purgeUrl = '<?php echo $purgeUrl; ?>';
const emptyTrashUrl = '<?php echo $emptyUrl; ?>';
const tableWrapper = document.getElementById('trash-table-wrapper');
const emptyState = document.getElementById('trash-empty');
const tableBody = document.querySelector('#trash-table tbody');
const refreshBtn = document.getElementById('refresh-trash-btn');
const emptyBtn = document.getElementById('empty-trash-btn');
const feedbackContainer = document.getElementById('trash-feedback');
let archivedRows = [];

function showTrashFeedback(message, tone = 'info') {
    if (!feedbackContainer) {
        return;
    }
    feedbackContainer.innerHTML = `
        <div class="alert alert-${tone} alert-dismissible fade show" role="alert">
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    `;
}

async function loadArchivedItems() {
    try {
        const response = await fetch(fetchArchivedUrl);
        if (!response.ok) {
            throw new Error('Unable to load archived files.');
        }
        const data = await response.json();
        archivedRows = Array.isArray(data) ? data : [];
        renderArchivedTable();
    } catch (error) {
        showTrashFeedback(error.message || 'Unable to load archived files.', 'danger');
    }
}

function renderArchivedTable() {
    if (!tableBody || !tableWrapper || !emptyState) {
        return;
    }
    if (!archivedRows.length) {
        emptyState.classList.remove('d-none');
        tableWrapper.classList.add('d-none');
        tableBody.innerHTML = '';
        return;
    }
    emptyState.classList.add('d-none');
    tableWrapper.classList.remove('d-none');
    const formatter = new Intl.DateTimeFormat(undefined, { dateStyle: 'medium', timeStyle: 'short' });
    tableBody.innerHTML = archivedRows.map(row => {
        const archivedDate = row.archived_at ? formatter.format(new Date(row.archived_at * 1000)) : '';
        return `
            <tr>
                <td>${escapeHtml(row.name || '')}</td>
                <td><code>${escapeHtml(row.original || '')}</code></td>
                <td>${escapeHtml(row.size_readable || '')}</td>
                <td>${archivedDate}</td>
                <td class="text-nowrap">
                    <button class="btn btn-sm btn-outline-success trash-action" data-action="restore" data-id="${escapeHtml(row.id)}">
                        <i class="bi bi-arrow-counterclockwise me-1"></i>Restore
                    </button>
                    <button class="btn btn-sm btn-outline-danger trash-action" data-action="delete" data-id="${escapeHtml(row.id)}">
                        <i class="bi bi-trash me-1"></i>Delete
                    </button>
                </td>
            </tr>
        `;
    }).join('');
}

function escapeHtml(value) {
    if (!value) {
        return '';
    }
    return value.replace(/[&<>"']/g, function (m) {
        return ({
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#39;'
        })[m];
    });
}

async function restoreItem(id) {
    try {
        const response = await fetch(restoreUrl, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id })
        });
        if (!response.ok) {
            throw new Error('Unable to restore file.');
        }
        const data = await response.json();
        if (data && data.success !== false) {
            showTrashFeedback('File restored successfully.', 'success');
            loadArchivedItems();
        } else {
            throw new Error(data && data.message ? data.message : 'Unable to restore file.');
        }
    } catch (error) {
        showTrashFeedback(error.message || 'Unable to restore file.', 'danger');
    }
}

async function deleteItem(id) {
    if (!confirm('Permanently delete this archived file? This cannot be undone.')) {
        return;
    }
    try {
        const response = await fetch(purgeUrl, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id })
        });
        if (!response.ok) {
            throw new Error('Unable to delete file.');
        }
        const data = await response.json();
        if (data && data.success !== false) {
            showTrashFeedback('File permanently deleted.', 'success');
            loadArchivedItems();
        } else {
            throw new Error(data && data.message ? data.message : 'Unable to delete file.');
        }
    } catch (error) {
        showTrashFeedback(error.message || 'Unable to delete file.', 'danger');
    }
}

async function emptyTrash() {
    if (!confirm('Empty trash? All archived files will be permanently deleted.')) {
        return;
    }
    try {
        const response = await fetch(emptyTrashUrl, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' }
        });
        if (!response.ok) {
            throw new Error('Unable to empty trash.');
        }
        const data = await response.json();
        if (data && data.success !== false) {
            showTrashFeedback('Trash emptied successfully.', 'success');
            loadArchivedItems();
        } else {
            throw new Error(data && data.message ? data.message : 'Unable to empty trash.');
        }
    } catch (error) {
        showTrashFeedback(error.message || 'Unable to empty trash.', 'danger');
    }
}

document.addEventListener('click', function (event) {
    const actionBtn = event.target.closest('.trash-action');
    if (!actionBtn) {
        return;
    }
    const action = actionBtn.getAttribute('data-action');
    const id = actionBtn.getAttribute('data-id');
    if (!id) {
        return;
    }
    if (action === 'restore') {
        restoreItem(id);
    } else if (action === 'delete') {
        deleteItem(id);
    }
});

document.addEventListener('DOMContentLoaded', function () {
    loadArchivedItems();
    if (refreshBtn) {
        refreshBtn.addEventListener('click', function () {
            loadArchivedItems();
        });
    }
    if (emptyBtn) {
        emptyBtn.addEventListener('click', emptyTrash);
    }
});
</script>
