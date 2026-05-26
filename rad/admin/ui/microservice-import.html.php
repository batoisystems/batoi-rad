<div class="card">
    <div class="card-body">
        <h1 class="h4 mb-3">Import Microservicelet</h1>
        <p class="text-muted">Upload a Microservicelet package ZIP exported from another RAD installation.</p>
        <form action="<?php echo htmlspecialchars($this->runData['route']['url']); ?>" method="post" enctype="multipart/form-data">
            <div class="mb-3">
                <label class="form-label">Package file (ZIP)</label>
                <input type="file" name="package" class="form-control" accept=".zip" required>
            </div>
            <div class="mb-3">
                <label class="form-label">On name collision</label>
                <select name="collision_strategy" class="form-select">
                    <option value="abort" selected>Abort import</option>
                    <option value="rename">Auto-rename microservicelet</option>
                </select>
                <div class="form-text">If the microservicelet name exists, choose whether to abort or auto-rename.</div>
            </div>
            <button type="submit" class="btn btn-primary"><i class="bi bi-box-arrow-in-down"></i> Import</button>
        </form>
    </div>
</div>
