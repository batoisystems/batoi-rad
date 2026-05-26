<?php
// print '<pre>';print_r($this->runData['data']);print '</pre>';die('In Page Part');
$codeEditorDir = $this->runData['config']['dir']['admin'].'/assets/monaco';
$codeEditorLibUrl = $this->runData['config']['sys']['base_url'].'/rad-assets/monaco';
$postThroughAjaxUrl = $this->runData['config']['sys']['base_url'].'/rad-admin/theme/savetpl/'.$this->runData['data']['theme'].'/'.$this->runData['data']['templateFileName'];
$aiAssistanceUrl = $this->runData['config']['sys']['base_url'].'/rad-admin/theme/aiassist/'.$this->runData['data']['theme'].'/'.$this->runData['data']['templateFileName'];
// print $postThroughAjaxUrl.'<br>'.$aiAssistanceUrl.'<br>';
$tplName = $this->runData['data']['templateFileName'].'.tpl.php';
$tplPath = $this->runData['config']['dir']['theme'] . '/' . $this->runData['data']['templateFileName'] . '.tpl.php';
?>

<div class="alert alert-light border d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
    <div class="d-flex align-items-center gap-2">
        <span class="text-primary"><i class="bi bi-stars"></i></span>
        <div>
            <strong>AI Assist</strong>
            <div class="small text-muted">Place the cursor inside the editor and press <kbd>Shift</kbd> + <kbd>Space</kbd> for inline help.</div>
        </div>
    </div>
    <div class="d-flex align-items-center gap-2">
        <button type="button" class="btn btn-outline-primary btn-sm" id="theme-ai-btn">
            <i class="bi bi-stars me-1"></i>Ask AI
        </button>
        <small class="text-muted" id="theme-ai-status">Ready.</small>
    </div>
</div>

<div class="row">
    <div class="col-md-12">
        <!-- Load Tab Content -->
        <div class="card">
            <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
                <div><strong><i class='bi bi-file-earmark-text'></i> <?php echo $tplName; ?></strong></div>
                <div class="d-flex flex-wrap align-items-center gap-3">
                    <small class="text-muted" id="theme-save-status">All changes saved.</small>
                    <button type="button" class="btn btn-sm btn-warning" id="theme-version-btn">
                        <i class="bi bi-save"></i> Save & Version
                    </button>
                    <div id="toolbar" class="btn-group btn-group-sm" role="group" aria-label="Tooltip">
                        <button class="btn btn-outline-secondary" onclick="performUndo()" title="Undo"><i class="bi bi-arrow-counterclockwise"></i></button>
                        <button class="btn btn-outline-secondary" onclick="performRedo()" title="Redo"><i class="bi bi-arrow-clockwise"></i></button>
                        <button class="btn btn-outline-secondary" onclick="toggleComment()" title="Toggle Comment"><i class="bi bi-dash-square"></i></button>
                        <button class="btn btn-outline-secondary" onclick="formatCode()" title="Format Code"><i class="bi bi-code-square"></i></button>
                        <button class="btn btn-outline-secondary" onclick="goToLine()" title="Go to Line"><i class="bi bi-arrow-up-square"></i></button>
                        <button class="btn btn-outline-secondary" onclick="toggleLineWrap()" title="Toggle Line Wrap"><i class="bi bi-text-wrap"></i></button>
                        <button class="btn btn-outline-secondary" onclick="findAndReplace();" title="Find and Replace" data-bs-toggle="modal" data-bs-target="#findReplaceModal"><i class="bi bi-search"></i><i class="bi bi-arrow-counterclockwise"></i></button>
                    </div>
                </div>
            </div>
            <div class="card-body px-0 py-0">
                <div id="code_tpl" style="height: 600px; width: 100%;"></div>
            </div>
        </div>
    </div>
</div>


<!-- Bootstrap Modal -->
<div class="modal compact-modal fade" id="findReplaceModal" tabindex="-1" style="z-index: 10000;">
  <div class="modal-dialog compact-dialog">
    <div class="modal-content compact-content">
      <div class="modal-header compact-header">
        <h5 class="modal-title compact-title">Find and Replace</h5>
        <button type="button" class="btn-close compact-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body compact-body">
        <form id="findReplaceForm">
          <div class="mb-2 compact-group">
            <label for="findTerm" class="form-label compact-label">Find</label>
            <input type="text" class="form-control compact-control" id="findTerm">
          </div>
          <div class="mb-2 compact-group">
            <label for="replaceTerm" class="form-label compact-label">Replace</label>
            <input type="text" class="form-control compact-control" id="replaceTerm">
          </div>
        </form>
      </div>
      <div class="modal-footer compact-footer">
        <button type="button" class="btn btn-sm btn-secondary compact-btn" data-bs-dismiss="modal">Close</button>
        <button type="button" class="btn btn-sm btn-primary compact-btn" onclick="findAndReplace()">Find and Replace</button>
      </div>
    </div>
  </div>
</div>
