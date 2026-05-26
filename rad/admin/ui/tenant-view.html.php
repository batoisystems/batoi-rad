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

    <!-- User table -->
    <table 
        class="table table-hover table-bordered table-sm mt-2" 
        id="dataTable"
        data-toggle="table"
        data-search="true"
        data-show-export="true"
        data-pagination="true"
        data-show-columns="true"
        data-filter-control="true"
    >
        <thead>
            <tr>
                <th data-sortable="true" data-field="id" data-filter-control="select">ID</th>
                <th data-sortable="true" data-field="uid" data-filter-control="select">UID</th>
                <th data-sortable="true" data-field="s_name" data-filter-control="select">Name</th>
                <th data-sortable="false" data-field="buttonaction">&nbsp;</th>
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


