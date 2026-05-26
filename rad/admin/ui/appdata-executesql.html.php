<?php
// Define form submission URL
$formSubmissionUrl = $this->runData['route']['url'];
?>

<div class="container mt-5">
    
    <?php
$summary = $this->runData['route']['sql_summary'] ?? [];
$perPagePref = (int)($this->runData['data']['per_page_pref'] ?? 50);
    $hasResult = !empty($summary);
    $status = $summary['status'] ?? '';
    $hasTable = !empty($this->runData['route']['sql_table_html']);
    ?>
    <?php if ($hasResult) { ?>
        <div class="card shadow-sm mb-4">
            <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                <div>
                    <h4 class="mb-1">Execution Result</h4>
                    <span class="badge bg-<?php echo $status === 'success' ? 'success' : 'danger'; ?>">
                        <?php echo ucfirst($status); ?>
                    </span>
                </div>
                <div class="btn-group btn-group-sm" role="group">
                    <button type="button" class="btn btn-outline-secondary" id="sql-copy-query"
                            data-query="<?php echo htmlspecialchars($summary['query'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                        <i class="bi bi-clipboard"></i> Copy Query
                    </button>
                    <?php if ($hasTable) { ?>
                        <button type="button" class="btn btn-outline-secondary" id="sql-download-csv">
                            <i class="bi bi-download"></i> Download CSV
                        </button>
                    <?php } ?>
                    <a href="<?php echo $formSubmissionUrl; ?>" class="btn btn-outline-primary">
                        <i class="bi bi-arrow-repeat"></i> Run Another
                    </a>
                </div>
            </div>
            <div class="card-body">
                <div class="row g-3 text-muted small mb-3">
                    <div class="col-6 col-md-3">
                        <div class="fw-semibold text-dark">Type</div>
                        <div><?php echo htmlspecialchars($summary['type'] ?? '—'); ?></div>
                    </div>
                    <div class="col-6 col-md-3">
                        <div class="fw-semibold text-dark">Rows affected</div>
                        <div><?php echo isset($summary['rows']) ? number_format((int)$summary['rows']) : '—'; ?></div>
                    </div>
                    <div class="col-6 col-md-3">
                        <div class="fw-semibold text-dark">Duration</div>
                        <div><?php echo isset($summary['duration_ms']) ? $summary['duration_ms'] . ' ms' : '—'; ?></div>
                    </div>
                    <div class="col-6 col-md-3">
                        <div class="fw-semibold text-dark">Executed</div>
                        <div><?php echo htmlspecialchars($summary['executed_at'] ?? '—'); ?></div>
                    </div>
                </div>
                <div class="mb-3">
                    <label class="text-muted text-uppercase small fw-semibold">Query</label>
                    <pre class="bg-light rounded p-3" id="sql-query-preview"><?php echo htmlspecialchars($summary['query'] ?? '', ENT_NOQUOTES, 'UTF-8'); ?></pre>
                </div>
                <?php if (!empty($this->runData['route']['sql_message'])) { ?>
                    <div class="alert <?php echo $status === 'success' ? 'alert-success' : 'alert-danger'; ?>">
                        <?php echo htmlspecialchars($this->runData['route']['sql_message']); ?>
                        <?php if ($status === 'error' && !empty($summary['error'])) { ?>
                            <div class="small mt-2 text-muted"><?php echo htmlspecialchars($summary['error']); ?></div>
                        <?php } ?>
                    </div>
                <?php } ?>
                <?php if ($hasTable) { ?>
                    <div class="table-responsive sql-result-table" id="sql-result-table">
                        <?php echo $this->runData['route']['sql_table_html']; ?>
                    </div>
                    <div class="d-flex justify-content-between align-items-center mt-2" id="sql-result-pager" data-page-size="<?php echo $perPagePref; ?>">
                        <small class="text-muted" id="sql-result-count">
                            <?php echo number_format((int)($summary['rows'] ?? 0)); ?> total row(s)
                        </small>
                        <div class="btn-group btn-group-sm" role="group">
                            <button type="button" class="btn btn-outline-secondary" id="sql-page-prev">
                                <i class="bi bi-chevron-left"></i>
                            </button>
                            <button type="button" class="btn btn-outline-secondary disabled" id="sql-page-info">Page 1</button>
                            <button type="button" class="btn btn-outline-secondary" id="sql-page-next">
                                <i class="bi bi-chevron-right"></i>
                            </button>
                        </div>
                    </div>
                <?php } elseif ($status === 'success') { ?>
                    <p class="text-muted mb-0">No tabular results to display.</p>
                <?php } ?>
            </div>
        </div>
        <?php if (!empty($this->runData['route']['sql_debug'])) { ?>
            <div class="card border-warning mb-4">
                <div class="card-header bg-warning-subtle">
                    <strong>SQL Console Debug (dev_debug_flag=Y)</strong>
                </div>
                <div class="card-body">
                    <pre class="small mb-0"><?php echo htmlspecialchars(json_encode($this->runData['route']['sql_debug'], JSON_PRETTY_PRINT)); ?></pre>
                </div>
            </div>
        <?php } ?>
    <?php } else { ?>
        <form action="<?php print $formSubmissionUrl; ?>" method="post" class="needs-validation" novalidate>
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($this->runData['request']->csrf_token ?? '', ENT_QUOTES, 'UTF-8'); ?>">
            <div class="form-group">
                <label for="sql_query" class="font-weight-bold">SQL Query</label>
                <textarea class="form-control" id="sql_query" name="sql_query" rows="10" required><?php echo isset($this->runData['request']->post['sql_query']) ? $this->runData['request']->post['sql_query'] : ''; ?></textarea>
                <div class="invalid-feedback">
                    Please enter a valid SQL query.
                </div>
            </div>
            <input type="hidden" name="sql_query_b64" id="sql_query_b64" value="">

            <button type="submit" class="btn btn-primary">
                <i class="bi bi-play-circle"></i> Execute SQL
            </button>
            <button type="button" class="btn btn-outline-secondary ms-2" id="sql-use-base64">
                <i class="bi bi-shield-lock"></i> Use Base64 Submit
            </button>
        </form>
        <div class="card shadow-sm my-4">
            <div class="card-body">
                <h5 class="card-title h6">Allowed operations &amp; safeguards</h5>
                <ul class="small mb-0 ps-3">
                    <li>Table names must start with 'a_'. Fields to be added/removed must start with 'a_'.</li>
                    <li>You can modify records in tables starting with 'a_', but schema changes are only allowed for tables and fields starting with 'a_'.</li>    
                    <li>Only tables prefixed <code>a_</code> are accepted in SELECT/INSERT/UPDATE/DELETE statements.</li>
                    <li>Schema changes are limited to <code>a_</code> tables/columns; core system/entity tables remain read-only.</li>
                    <li><strong>DROP TABLE</strong> and table listing commands (<code>SHOW TABLES</code>, <code>information_schema</code>, etc.) are disabled.</li>
                    <li>Renaming or editing protected system tables is not permitted.</li>
                    <li>Standard DML/DDL verbs (SELECT, INSERT, UPDATE, DELETE, CREATE, ALTER) are available within these rules.</li>
                </ul>
            </div>
        </div>
        <?php if (!empty($this->runData['route']['sql_debug'])) { ?>
            <div class="card border-warning mb-4">
                <div class="card-header bg-warning-subtle">
                    <strong>SQL Console Debug (dev_debug_flag=Y)</strong>
                </div>
                <div class="card-body">
                    <pre class="small mb-0"><?php echo htmlspecialchars(json_encode($this->runData['route']['sql_debug'], JSON_PRETTY_PRINT)); ?></pre>
                </div>
            </div>
        <?php } ?>
    <?php } ?>
</div>

<script>
    // JavaScript for disabling form submissions if there are invalid fields
    (function() {
        'use strict';
        window.addEventListener('load', function() {
            var forms = document.getElementsByClassName('needs-validation');
            Array.prototype.filter.call(forms, function(form) {
                form.addEventListener('submit', function(event) {
                    if (form.checkValidity() === false) {
                        event.preventDefault();
                        event.stopPropagation();
                    }
                    form.classList.add('was-validated');
                }, false);
            });
        }, false);
    })();
</script>
<script>
(function () {
    var btn = document.getElementById('sql-use-base64');
    if (!btn) return;
    btn.addEventListener('click', function () {
        var txt = document.getElementById('sql_query');
        var b64 = document.getElementById('sql_query_b64');
        if (!txt || !b64) return;
        b64.value = btoa(unescape(encodeURIComponent(txt.value || '')));
        var form = btn.closest('form');
        if (form) form.submit();
    });
})();
</script>
<?php if (!empty($summary)) { ?>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const copyBtn = document.getElementById('sql-copy-query');
    if (copyBtn) {
        copyBtn.addEventListener('click', function() {
            const sql = this.getAttribute('data-query') || '';
            if (!sql) return;
            navigator.clipboard.writeText(sql).then(() => {
                this.classList.remove('btn-outline-secondary');
                this.classList.add('btn-success');
                this.innerHTML = '<i class="bi bi-check2"></i> Copied';
                setTimeout(() => {
                    this.classList.remove('btn-success');
                    this.classList.add('btn-outline-secondary');
                    this.innerHTML = '<i class="bi bi-clipboard"></i> Copy Query';
                }, 1200);
            }).catch(() => alert('Unable to copy query.'));
        });
    }

    const downloadBtn = document.getElementById('sql-download-csv');
    if (downloadBtn) {
        downloadBtn.addEventListener('click', function() {
            const table = document.querySelector('#sql-result-table table');
            if (!table) {
                alert('No table data found.');
                return;
            }
            const csv = tableToCsv(table);
            const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
            const url = URL.createObjectURL(blob);
            const link = document.createElement('a');
            link.href = url;
            link.download = 'query-results.csv';
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
            URL.revokeObjectURL(url);
        });
    }

    function tableToCsv(table) {
        const rows = Array.from(table.querySelectorAll('tr'));
        return rows.map(row => {
            return Array.from(row.querySelectorAll('th,td')).map(cell => {
                let text = cell.textContent.trim().replace(/\s+/g, ' ');
                text = text.replace(/"/g, '""');
                if (/[",\n]/.test(text)) {
                    text = `"${text}"`;
                }
                return text;
            }).join(',');
        }).join('\n');
    }

    // Pagination handling
    const table = document.getElementById('sql-result-data-table');
    const pager = document.getElementById('sql-result-pager');
    if (table && pager) {
        const rows = Array.from(table.querySelectorAll('tbody tr'));
        const pageSize = parseInt(pager.getAttribute('data-page-size'), 10) || 50;
        const totalPages = Math.max(1, Math.ceil(rows.length / pageSize));
        const infoBtn = document.getElementById('sql-page-info');
        const prevBtn = document.getElementById('sql-page-prev');
        const nextBtn = document.getElementById('sql-page-next');
        let currentPage = 1;

        function renderPage(page) {
            currentPage = Math.min(Math.max(page, 1), totalPages);
            rows.forEach((row, idx) => {
                const shouldShow = Math.floor(idx / pageSize) + 1 === currentPage;
                row.style.display = shouldShow ? '' : 'none';
            });
            if (infoBtn) {
                infoBtn.textContent = `Page ${currentPage} of ${totalPages}`;
            }
            if (prevBtn) prevBtn.disabled = currentPage === 1;
            if (nextBtn) nextBtn.disabled = currentPage === totalPages;
            if (totalPages === 1 && pager) {
                pager.classList.add('d-none');
            } else {
                pager.classList.remove('d-none');
            }
        }

        if (prevBtn) {
            prevBtn.addEventListener('click', () => renderPage(currentPage - 1));
        }
        if (nextBtn) {
            nextBtn.addEventListener('click', () => renderPage(currentPage + 1));
        }
        renderPage(1);
    }
});
</script>
<?php } ?>
