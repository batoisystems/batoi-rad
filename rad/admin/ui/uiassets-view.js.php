<script>
const searchInput = document.getElementById('asset-search');
const feedbackContainer = document.getElementById('asset-feedback');
const presetSearch = <?php echo json_encode($filters['q'] ?? ''); ?>;
const adminBaseUrl = '<?php echo $this->runData['route']['rad_admin_url']; ?>';
let assetData = [];
let assetLookup = new Map();

async function refreshViews() {
    const url = '<?php print $fetchUrl;?>';
    try {
        const response = await fetch(url);
        if (!response.ok) {
            throw new Error('Unable to fetch assets.');
        }
        const files = await response.json();
        if (files.error) {
            assetData = [];
            assetLookup = new Map();
            renderAssets([]);
            return;
        }
        assetData = Array.isArray(files) ? files : [];
        assetLookup = new Map(assetData.map(file => [file.relative, file]));
        applyAssetFilter();
    } catch (error) {
        console.error('Fetch failed: ', error);
        assetData = [];
        assetLookup = new Map();
        renderAssets([]);
        showFeedback(error.message || 'Unable to load assets.', 'danger');
    }
}

function applyAssetFilter() {
    const term = searchInput ? searchInput.value.toLowerCase() : '';
    const filtered = assetData.filter(file => {
        const haystack = (file.name + ' ' + (file.relative || '')).toLowerCase();
        return haystack.includes(term);
    });
    renderAssets(filtered);
}

function renderAssets(files) {
    const folderContainer = document.querySelector('#folder-view .file-browser');
    const tableBody = document.querySelector('#list-view tbody');
    if (!folderContainer || !tableBody) {
        return;
    }

    if (!files.length) {
        folderContainer.innerHTML = '<div class="alert alert-warning mb-0">No files found.</div>';
        tableBody.innerHTML = '';
        return;
    }

    const folderViewHtml = files.map(file => `
        <div class="card mb-3 h-100">
            <div class="card-body text-center d-flex flex-column">
                <div class="file-icon mb-2 text-secondary">${file.icon}</div>
                <p class="file-name mb-1"><a href="${file.link}">${escapeHtml(file.name)}</a></p>
                <div class="small text-muted mb-1">${file.isDir ? 'Folder' : (file.extension || 'File')} · ${file.sizeReadable || ''}</div>
                <div class="small text-muted mb-2">${file.lastUpdated || ''}</div>
                <div class="d-flex flex-wrap gap-1 justify-content-center mt-auto">
                    ${renderActionButtons(file, 'card')}
                </div>
            </div>
        </div>
    `).join('');

    const listViewHtml = files.map(file => `
        <tr>
            <td class="text-secondary">${file.icon}</td>
            <td><a href="${file.link}">${escapeHtml(file.name)}</a></td>
            <td>${file.isDir ? 'Folder' : (file.extension || 'File')}</td>
            <td data-bytes="${file.size || 0}">${file.sizeReadable || '—'}</td>
            <td data-sort="${file.lastUpdatedRaw || 0}">${file.lastUpdated || ''}</td>
            <td>${renderActionButtons(file, 'list')}</td>
        </tr>
    `).join('');

    folderContainer.innerHTML = folderViewHtml;
    tableBody.innerHTML = listViewHtml;
    defaultSort();
}

function renderActionButtons(file, variant) {
    const actions = [];
    if (file.isDir) {
        actions.push({ action: 'open', icon: 'bi-folder2-open', label: 'Open' });
        actions.push({ action: 'rename', icon: 'bi-input-cursor-text', label: 'Rename' });
        actions.push({ action: 'move', icon: 'bi-arrows-move', label: 'Move' });
        actions.push({ action: 'archive', icon: 'bi-archive', label: 'Archive' });
    } else {
        if (file.isTextEditable) {
            actions.push({ action: 'edit', icon: 'bi-pencil-square', label: 'Edit' });
        }
        actions.push({ action: 'preview', icon: 'bi-eye', label: 'Preview' });
        actions.push({ action: 'download', icon: 'bi-download', label: 'Download' });
        actions.push({ action: 'rename', icon: 'bi-input-cursor-text', label: 'Rename' });
        actions.push({ action: 'move', icon: 'bi-arrows-move', label: 'Move' });
        actions.push({ action: 'archive', icon: 'bi-archive', label: 'Archive' });
    }

    const btnClass = variant === 'list' ? 'btn btn-sm btn-light border asset-action' : 'btn btn-sm btn-outline-secondary asset-action';
    return actions.map(action => `
        <button type="button"
            class="${btnClass}"
            data-action="${action.action}"
            data-path="${escapeHtml(file.relative)}"
            title="${action.label}">
            <i class="bi ${action.icon}"></i>
        </button>
    `).join('');
}

function escapeHtml(str) {
    if (!str) {
        return '';
    }
    return str.replace(/[&<>"']/g, function (m) {
        return ({
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#39;'
        })[m];
    });
}

function encodePathForUrl(path) {
    if (!path) {
        return '';
    }
    return path.split('/').map(encodeURIComponent).join('/');
}

function defaultSort() {
    var defaultSort = $('.sortable[data-sort="name"]');
    if (defaultSort.length) {
        sortTable(defaultSort, true);
    }
}

function sortTable(columnElement, isDefault) {
    if (!columnElement.length) {
        return;
    }
    var table = columnElement.parents('table').eq(0);
    var rows = table.find('tr:gt(0)').toArray().sort(comparer(columnElement.index()));
    $('.sort-icon').text('');

    if (isDefault) {
        columnElement[0].asc = true;
    } else {
        columnElement[0].asc = !columnElement[0].asc;
    }

    columnElement.find('.sort-icon').text(columnElement[0].asc ? '↑' : '↓');

    if (!columnElement[0].asc) {
        rows = rows.reverse();
    }
    table.children('tbody').empty().html(rows);
}

function comparer(index) {
    return function(a, b) {
        var valA = getCellValue(a, index),
            valB = getCellValue(b, index);
        if (index === 3) {
            var bytesA = parseFloat($(a).children('td').eq(index).attr('data-bytes')) || 0;
            var bytesB = parseFloat($(b).children('td').eq(index).attr('data-bytes')) || 0;
            return bytesA - bytesB;
        }
        return $.isNumeric(valA) && $.isNumeric(valB) ? valA - valB : valA.toString().localeCompare(valB);
    };
}

function getCellValue(row, index) {
    return $(row).children('td').eq(index).text();
}

function showFeedback(message, tone = 'info') {
    if (!feedbackContainer) {
        return;
    }
    feedbackContainer.innerHTML = `
        <div class="alert alert-${tone} alert-dismissible fade show" role="alert">
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    `;
}

async function performMutation(url, payload, successMessage) {
    try {
        const response = await fetch(url, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        });
        if (!response.ok) {
            throw new Error('Request failed.');
        }
        const data = await response.json();
        if (data && data.success !== false) {
            showFeedback(successMessage, 'success');
            refreshViews();
        } else {
            throw new Error(data && data.message ? data.message : 'Action failed.');
        }
    } catch (error) {
        showFeedback(error.message || 'Action failed.', 'danger');
    }
}

function handleAssetAction(action, path) {
    const file = assetLookup.get(path);
    switch (action) {
        case 'open':
            if (file && file.link) {
                window.location.href = file.link;
            }
            break;
        case 'download':
            window.location.href = `${adminBaseUrl}/uiassets/download/${encodePathForUrl(path)}`;
            break;
        case 'preview':
            window.open(`${adminBaseUrl}/uiassets/preview/${encodePathForUrl(path)}`, '_blank');
            break;
        case 'edit':
            window.location.href = `${adminBaseUrl}/uiassets/editfile?path=${encodeURIComponent(path)}`;
            break;
        case 'rename': {
            const newName = prompt('Enter new file name', file ? file.name : '');
            if (!newName) { return; }
            const parent = path.lastIndexOf('/') !== -1 ? path.substring(0, path.lastIndexOf('/')) : '';
            const newPath = parent ? `${parent}/${newName.trim()}` : newName.trim();
            performMutation(`${adminBaseUrl}/uiassets/rename`, { path, newPath }, 'Item renamed successfully.');
            break;
        }
        case 'move': {
            const destination = prompt('Move to folder (relative to /assets)', '');
            if (destination === null) { return; }
            performMutation(`${adminBaseUrl}/uiassets/move`, { path, destination: destination.trim() }, 'Item moved successfully.');
            break;
        }
        case 'archive':
            performMutation(`${adminBaseUrl}/uiassets/archive`, { path }, 'Item moved to trash.');
            break;
        default:
            break;
    }
}

document.addEventListener('click', function (event) {
    const actionBtn = event.target.closest('.asset-action');
    if (!actionBtn) {
        return;
    }
    event.preventDefault();
    const action = actionBtn.getAttribute('data-action');
    const path = actionBtn.getAttribute('data-path');
    if (action && path) {
        handleAssetAction(action, path);
    }
});

$(document).ready(function() {
    if (searchInput) {
        searchInput.value = presetSearch || '';
        searchInput.addEventListener('input', applyAssetFilter);
    }
    $('.sortable').on('click', function() {
        sortTable($(this), false);
    });
    refreshViews();
});

document.addEventListener('DOMContentLoaded', function () {
    let uploadProgress = document.getElementById('progress-bar');
    let uploadMessage = document.getElementById('upload-message');
    const MAX_UPLOAD_BYTES = 50 * 1024 * 1024;

    function handleFiles(files) {
        if (!files.length) {
            return;
        }

        for (let i = 0; i < files.length; i++) {
            const file = files[i];
            if (file.name.startsWith('.')) {
                showFeedback('Hidden files cannot be uploaded.', 'warning');
                return;
            }
            if (file.size > MAX_UPLOAD_BYTES) {
                showFeedback(`"${file.name}" exceeds the 50MB upload limit.`, 'danger');
                return;
            }
        }

        let formData = new FormData();
        for (let i = 0; i < files.length; i++) {
            formData.append('files[]', files[i]);
        }

        let xhr = new XMLHttpRequest();
        xhr.open('POST', '<?php print $uploadUrl;?>', true);
        if (uploadProgress) {
            uploadProgress.value = 0;
        }

        xhr.upload.addEventListener('progress', function (e) {
            if (e.lengthComputable) {
                let percentComplete = (e.loaded / e.total) * 100;
                if (uploadProgress) {
                    uploadProgress.value = percentComplete;
                }
            }
        });

        xhr.addEventListener('load', function () {
            if (xhr.status === 200) {
                if (uploadMessage) {
                    uploadMessage.innerHTML = 'Files uploaded successfully!';
                }
                showFeedback('Files uploaded successfully.', 'success');
                refreshViews();
            } else {
                if (uploadMessage) {
                    uploadMessage.innerHTML = 'An error occurred. Please try again.';
                }
                showFeedback(xhr.responseText || 'Upload failed.', 'danger');
            }
        });

        xhr.addEventListener('error', function () {
            if (uploadMessage) {
                uploadMessage.innerHTML = 'An error occurred while uploading. Please try again.';
            }
            showFeedback('Upload failed. Please try again.', 'danger');
        });

        xhr.send(formData);
    }

    let inputElement = document.getElementById('fileElem');
    if (inputElement) {
        inputElement.addEventListener('change', function () {
            handleFiles(this.files);
            this.value = '';
        });
    }
});
</script>
