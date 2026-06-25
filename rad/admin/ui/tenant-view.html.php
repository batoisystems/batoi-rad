<?php
    $numOfRecords = count($this->runData['data']['tenant']);
    // print '<pre>';print_r($this->runData['data']['tenant']);print '</pre>';die('here');
    // $numOfRecords = 0;
    if($numOfRecords > 0) {
?>
    <div class="d-flex justify-content-between align-items-center pb-1 border-bottom">
        <p class="pt-3">
            <?php echo 'Total Number of Tenants' .': '.$numOfRecords; ?>
        </p>
        <div class="btn-group">
            <a href="<?php print $this->runData['route']['rad_admin_url'] . '/tenant/add';?>" class="btn btn-outline-primary">
                <i class="bi bi-person-plus me-2"></i>Add Tenant</a>
        </div>
    </div>

    <div class="rad-table-tools input-group input-group-sm mt-3 mb-2 ms-auto">
        <span class="input-group-text"><i class="bi bi-search"></i></span>
        <input type="search" class="form-control" placeholder="Search tenants" data-uif-table-filter="#dataTable">
    </div>

    <!-- Tenant table -->
    <table 
        class="table table-hover table-bordered table-sm mt-2" 
        id="dataTable"
        data-uif="table"
    >
        <thead>
            <tr>
                <th data-uif-sort="asc">ID</th>
                <th data-uif-sort="asc">UID</th>
                <th data-uif-sort="asc">Name</th>
                <th>&nbsp;</th>
            </tr>
        </thead>
        <tbody>
            <?php
            foreach($this->runData['data']['tenant'] as $tenant) { ?>
                <tr>
                    <td><?php print $tenant['id'];?></td>
                    <td><?php print $tenant['uid'];?></td>
                    <td><?php print $tenant['s_name'];?></td>
                    <td>
                        <div class="btn-group">
                            <a href="<?php print $this->runData['route']['rad_admin_url'] . '/tenant/edit/'.$tenant['uid'];?>" class="btn btn-outline-primary"><i class="bi bi-pencil-square"></i></a>
                            <a href="<?php print $this->runData['route']['rad_admin_url'] . '/tenant/archive/'.$tenant['uid'];?>" class="btn btn-outline-danger"><i class="bi bi-archive"></i></a>
                        </div>
                    </td>
                </tr>
            <?php } ?>
        </tbody>
    </table>
<?php
    } else {
?>
    <div class="text-center mb-5">
        <i class="bi bi-exclamation-triangle-fill text-warning" style="font-size: 12rem;"></i>
        <p class="lead">No tenant available. Please add tenant using the <strong>Add Tenant</strong> function.</p>
        <a href="<?php print $this->runData['route']['rad_admin_url'].'/tenant/add';?>" class="btn btn-outline-primary">
            <i class="bi bi-person-plus me-2"></i>Add Tenant</a>
    </div>
<?php
    }
?>

