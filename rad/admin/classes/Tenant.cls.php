<?php
namespace RadAdmin;
use DateTime;
class Tenant{
    private $runData = [];
    private $errorHandler;
    public function __construct(array $runData) {
        $this->runData = $runData;
        $this->errorHandler = $runData['errorHandler'];
        // print '<pre>';print_r($this->runData['data']);print '</pre>';die('here');
    }

    public function view() {
        if(!$this->runData['route']['alert_from_request']) {
            $this->runData['route']['alert'] = 'info';
            $this->runData['route']['alert_message'] = 'Here, you can manage the SaaS Tenants.';
        }
        $this->runData['route']['h1'] = 'SaaS Tenants';
        $this->runData['route']['meta_title'] = 'SaaS Tenants';
        // Get the list of tenants
        $this->runData['data']['tenant'] = $this->runData['db']->select('s_tenant', ['livestatus' => '1'], true);
        return $this->runData;
    }

    public function add() {
        // alert
        if(!$this->runData['route']['alert_from_request']) {
            $this->runData['route']['alert'] = 'info';
            $this->runData['route']['alert_message'] = 'Here, you can add a new Tenant.';
        }
        // Check the post data
        if(isset($this->runData['request']->post['s_name'])) {
            // print_r($this->runData['request']->post);die('here');
            // print post s_users data
            $a_users = $this->runData['request']->post['s_users'];
            // print '<pre>';print_r($a_users);print '</pre>';die('here');
            // create a string in format role_id_1:user_id_1,user_id_2,...;role_id_2:user_id_3,...;... from the post array for s_users
            $s_users = '';
            foreach($a_users as $role_id => $user_ids) {
                $s_users .= $role_id . ':';
                $s_users .= implode(',', $user_ids);
                $s_users .= ';';
            }
            // print '<pre>';print_r($s_users);print '</pre>';die('here');
            // store the json value from the post data
            $s_definition = json_encode($this->runData['request']->post['s_definition']);
            // Insert the Tenant
            $insertData = [
                's_name' => $this->runData['request']->post['s_name'],
                's_description' => $this->runData['request']->post['s_description'],
                's_users' => $s_users,
                's_definition' => $s_definition,
            ];
            $this->runData['db']->insert('s_tenant', $insertData);
            // Set the alert
            $this->runData['route']['alert'] = 'success';
            $this->runData['route']['alert_message'] = 'Tenant added successfully';
            $this->runData['request']->setAlert($this->runData['route']['alert_message'], $this->runData['route']['alert']);
            // redirect to Tenant list
            $redirectUrl = '/rad-admin/tenant/view';
            header('Location: ' . $redirectUrl, true, 302);
            die();
        }
        $this->runData['route']['h1'] = 'Add Tenant';
        $this->runData['route']['meta_title'] = 'Add Tenant';
        $this->runData['data']['tenant'] = [];
        // backlink
        $this->runData['route']['backlink'] = '/rad-admin/tenant/view';
        return $this->runData;
    }

    public function edit() {
        // check the pathparts[3] is set
        if(!isset($this->runData['route']['pathparts'][3])) {
            $this->runData['route']['alert'] = 'danger';
            $this->runData['route']['alert_message'] = 'Role not found';
            $this->runData['request']->setAlert($this->runData['route']['alert_message'], $this->runData['route']['alert']);
            // redirect to role list
            $redirectUrl = '/rad-admin/role/view';
            header('Location: ' . $redirectUrl, true, 302);
            die();
        }
        // find the role from the UID in pathparts[2]
        // print '<pre>';print_r($this->runData['route']['pathparts']);print '</pre>';die('here');
        $roleRows = $this->runData['db']->select('s_role', ['uid' => $this->runData['route']['pathparts'][3]], true);
        // print '<pre>';print_r($roleRows);print '</pre>';die('here');
        if(!$roleRows) {
            $this->runData['route']['alert'] = 'danger';
            $this->runData['route']['alert_message'] = 'Role not found';
            $this->runData['request']->setAlert($this->runData['route']['alert_message'], $this->runData['route']['alert']);
            // redirect to role list
            $redirectUrl = '/rad-admin/role/view';
            header('Location: ' . $redirectUrl, true, 302);
            die();
        }
        $this->runData['data']['role'] = $roleRows[0];
        // print '<pre>';print_r($this->runData['data']['role']);print '</pre>';die('here');
        // get microservice from the s_default_route_id
        $routeRows = $this->runData['db']->select('s_msroute', ['id' => $this->runData['data']['role']['s_default_route_id']], true);
        $this->runData['data']['role']['ms_id'] = $routeRows[0]['s_ms_id'];
        // print '<pre>';print_r($this->runData['data']['role']);print '</pre>';die('here');
        // Check the post data
        if(isset($this->runData['request']->post['s_role_name'])) {
            // print '<pre>';print_r($this->runData['request']->post());print '</pre>';die('here');
            // Update the role
            $updateData = [
                's_role_name' => $this->runData['request']->post['s_role_name'],
                's_scope' => $this->runData['request']->post['s_scope'] ?? 'platform',
                's_default_route_id' => $this->runData['request']->post['s_default_route_id']
            ];
            $updateWhere = [
                'uid' => $this->runData['route']['pathparts'][3],
            ];
            $this->runData['db']->update('s_role', $updateData, $updateWhere);
            // Set the alert
            $this->runData['route']['alert'] = 'success';
            $this->runData['route']['alert_message'] = 'Role updated successfully';
            $this->runData['request']->setAlert($this->runData['route']['alert_message'], $this->runData['route']['alert']);
            // redirect to role list
            $redirectUrl = '/rad-admin/role/view';
            header('Location: ' . $redirectUrl, true, 302);
            die();
        }
        $this->runData['route']['h1'] = 'Edit Role: ' . $this->runData['data']['role']['s_role_name'];
        $this->runData['route']['meta_title'] = 'Edit Role: ' . $this->runData['data']['role']['s_role_name'];
        // backlink
        $this->runData['route']['backlink'] = '/rad-admin/role/view';
        return $this->runData;
    }

    public function getroutes() {
        // check the post data
        if($this->runData['request']->post['s_ms_id']) {
            // print '<pre>';print_r($this->runData['request']->post());print '</pre>';die('here');
            // Get all the routes from the s_msroute table for s_ms_id = $this->runData['request']->post['s_ms_id']
            $routes = $this->runData['db']->select('s_msroute', ['s_ms_id' => $this->runData['request']->post['s_ms_id']], true);
            // create a dropdown list of routes with their ids as values and names as text. Also, mark the $this-runData['data']['role']['s_default_route_id'] as selected
            $routesDropdown = '';
            foreach($routes as $route) {
                $routesDropdown .= '<option value="' . $route['id'] . '" ' . ( ( isset($this->runData['data']['role']['s_default_route_id']) && ($this->runData['data']['role']['s_default_route_id'] == $route['id']) ) ? 'selected' : '') . '>' . $route['s_name'] . '</option>';
            }
            // print '<pre>';print_r($routesDropdown);print '</pre>';die('here');
            // return the routes dropdown
            print '<div class="form-group" id="route_form_group">
                <label for="s_default_route_id">Default Route <span class="text-danger">*</span></label>
                <select class="form-control" name="s_default_route_id" id="s_default_route_id" required>
                    ' . $routesDropdown . '
                </select>
                <div class="invalid-feedback">
                    Please choose a default route.
                </div>
                <small id="defaultRouteHelp" class="form-text text-muted">Select a default route for the system.</small>
            </div>';
            die();
        }
    }

    /*
     * Archive Role
     */
    public function archive() {
        $priv = new \Core\Sys\PrivilegeService($this->runData['config'] ?? [], $this->runData['entity'] ?? []);
        if (!$priv->can('idm_manage') || $priv->role() === 'developer') {
            throw new \Exception('Access denied.', 403);
        }
        // check the pathparts[3] is set
        if(!isset($this->runData['route']['pathparts'][3])) {
            $this->runData['route']['alert'] = 'danger';
            $this->runData['route']['alert_message'] = 'Role not found';
            $this->runData['request']->setAlert($this->runData['route']['alert_message'], $this->runData['route']['alert']);
            // redirect to role list
            $redirectUrl = '/rad-admin/role/view';
            header('Location: ' . $redirectUrl, true, 302);
            die();
        }
        // find the role from the UID in pathparts[3]
        // print '<pre>';print_r($this->runData['route']['pathparts']);print '</pre>';die('here');
        $roleRows = $this->runData['db']->select('s_role', ['uid' => $this->runData['route']['pathparts'][3]], true);
        // print '<pre>';print_r($roleRows);print '</pre>';die('here');
        if(!$roleRows) {
            $this->runData['route']['alert'] = 'danger';
            $this->runData['route']['alert_message'] = 'Role not found';
            $this->runData['request']->setAlert($this->runData['route']['alert_message'], $this->runData['route']['alert']);
            // redirect to role list
            $redirectUrl = '/rad-admin/role/view';
            header('Location: ' . $redirectUrl, true, 302);
            die();
        }
        // print '<pre>';print_r($roleRows);print '</pre>';die('here');
        // archive the role
        $updateData = [
            'livestatus' => '0'
        ];
        $updateWhere = [
            'uid' => $this->runData['route']['pathparts'][3],
        ];
        $this->runData['db']->update('s_role', $updateData, $updateWhere);
        // Set the alert
        $this->runData['route']['alert'] = 'success';
        $this->runData['route']['alert_message'] = 'Role <strong>'. $roleRows[0]['s_role_name'] .'</strong> archived successfully';
        $this->runData['request']->setAlert($this->runData['route']['alert_message'], $this->runData['route']['alert']);
        // redirect to role list
        $redirectUrl = '/rad-admin/role/view';
        header('Location: ' . $redirectUrl, true, 302);
        die();
        return $this->runData;
    }
}
