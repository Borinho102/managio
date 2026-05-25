<?php

defined('BASEPATH') or exit('No direct script access allowed');

require __DIR__ . '/../REST_Controller.php';

class Staffs extends REST_Controller
{

    function __construct()
    {
        // Construct the parent class
        parent::__construct();
        $this->load->model('Api_model');
        $this->load->model('departments_model');
    }

    public function data_get($id = '')
    {
        // If the id parameter doesn't exist return all the
        $data = $this->Api_model->get_table('staffs', $id);

        if ($data) {

            if ($id != '') {
                $data->profile_image = staff_profile_image_url($data->staffid);
                $data->permissions = $this->staff_model->get_staff_permissions($id);
                $data->departments = $this->departments_model->get_staff_departments($id);
                $data->profile_images = [
                    'small' => staff_profile_image_url($id),
                    'thumb' => staff_profile_image_url($id, 'thumb'),
                ];

                $data->menu_items = [
                    'customers' => has_permission('customers', '', 'view') || (have_assigned_customers() || (!have_assigned_customers() && has_permission('customers', '', 'create'))),
                    'proposals' => (has_permission('proposals', '', 'view') || has_permission('proposals', '', 'view_own')) || (staff_has_assigned_proposals() && get_option('allow_staff_view_proposals_assigned') == 1),
                    'estimates' => (has_permission('estimates', '', 'view') || has_permission('estimates', '', 'view_own')) || (staff_has_assigned_estimates() && get_option('allow_staff_view_estimates_assigned') == 1),
                    'invoices' => (has_permission('invoices', '', 'view') || has_permission('invoices', '', 'view_own')) || (staff_has_assigned_invoices() && get_option('allow_staff_view_invoices_assigned') == 1),
                    'payments' => has_permission('payments', '', 'view') || has_permission('invoices', '', 'view_own') || (get_option('allow_staff_view_invoices_assigned') == 1 && staff_has_assigned_invoices()),
                    'credit_notes' => has_permission('credit_notes', '', 'view') || has_permission('credit_notes', '', 'view_own'),
                    'items' => has_permission('items', '', 'view'),
                    'subscriptions' => has_permission('subscriptions', '', 'view') || has_permission('subscriptions', '', 'view_own'),
                    'expenses' => has_permission('expenses', '', 'view') || has_permission('expenses', '', 'view_own'),
                    'contracts' => has_permission('contracts', '', 'view') || has_permission('contracts', '', 'view_own'),
                    'projects' => true,
                    'tasks' => true,
                    'tickets' => (!is_staff_member() && get_option('access_tickets_to_none_staff_members') == 1) || is_staff_member(),
                    'leads' => is_staff_member(),
                    'staff' => has_permission('staff', '', 'view')
                ];
            }
            $data = $this->Api_model->get_api_custom_data($data, "staff", $id);

            $this->response($data, REST_Controller::HTTP_OK); // OK (200) being the HTTP response code
        } else {
            $this->response([
                'status' => FALSE,
                'message' => 'No data found'
            ], REST_Controller::HTTP_OK); // NOT_FOUND (404) being the HTTP response code
        }
    }

    public function data_search_get($key = '')
    {
        if ($this->input->get('search')) {
            $key = $this->input->get('search');
        }

        $data = $this->Api_model->search('staff', $key);
        if ($data) {
            foreach ($data as $key => $value) {
                $data[$key]['permissions'] = $this->staff_model->get_staff_permissions($value['staffid']);
                $data[$key]['departments'] = $this->departments_model->get_staff_departments($value['staffid']);
                $data[$key]['profile_images'] = [
                    'small' => staff_profile_image_url($value['staffid']),
                    'thumb' => staff_profile_image_url($value['staffid'], 'thumb'),
                ];
            }

            $data = $this->Api_model->get_api_custom_data($data, "staff");

            $this->response($data, REST_Controller::HTTP_OK); // OK (200) being the HTTP response code
        } else {
            $this->response([
                'status' => FALSE,
                'message' => 'No data found'
            ], REST_Controller::HTTP_OK); // NOT_FOUND (404) being the HTTP response code
        }
    }

    public function data_post()
    {
        // form validation
        $this->form_validation->set_rules('firstname', 'First Name', 'trim|required|max_length[600]', array('is_unique' => 'This %s already exists please enter another Staff First Name'));
        $this->form_validation->set_rules('email', 'Email', 'trim|required|valid_email', array('is_unique' => 'This %s already exists please enter another Staff Email'));
        $this->form_validation->set_rules('password', 'Password', 'trim|required', array('is_unique' => 'This %s already exists please enter another Staff password'));
        if ($this->form_validation->run() == FALSE) {
            // form validation error
            $message = array(
                'status' => FALSE,
                'error' => $this->form_validation->error_array(),
                'message' => validation_errors()
            );
            $this->response($message, REST_Controller::HTTP_OK);
        } else {
            $departments = $this->Api_model->value($this->input->post('departments', TRUE));
            $insert_data = [
                'firstname' => $this->input->post('firstname', TRUE),
                'email' => $this->input->post('email', TRUE),
                'password' => $this->input->post('password', TRUE),
                'lastname' => '',
                'hourly_rate' => $this->Api_model->value($this->input->post('hourly_rate', TRUE)),
                'phonenumber' => $this->Api_model->value($this->input->post('phonenumber', TRUE)),
                'facebook' => $this->Api_model->value($this->input->post('facebook', TRUE)),
                'linkedin' => $this->Api_model->value($this->input->post('linkedin', TRUE)),
                'skype' => $this->Api_model->value($this->input->post('skype', TRUE)),
                'default_language' => $this->Api_model->value($this->input->post('default_language', TRUE)),
                'email_signature' => $this->Api_model->value($this->input->post('email_signature', TRUE)),
                'direction' => $this->Api_model->value($this->input->post('direction', TRUE)),
                'send_welcome_email' => $this->Api_model->value($this->input->post('send_welcome_email', TRUE)),
                'role' => '1',
                'administrator' => $this->Api_model->value($this->input->post('administrator', TRUE)),
                'is_not_staff' => $this->Api_model->value($this->input->post('is_not_staff', TRUE)),
                'permissions' => array(
                    'bulk_pdf_exporter' => array('view'),
                    'contracts' => array('create', 'edit', 'delete'),
                    'credit_notes' => array('create', 'edit', 'delete'),
                    'customers' => array('view', 'create', 'edit', 'delete'),
                    'email_templates' => array('view', 'edit'),
                    'estimates' => array('create', 'edit', 'delete'),
                    'expenses' => array('create', 'edit', 'delete'),
                    'invoices' => array('create', 'edit', 'delete'),
                    'items' => array('view', 'create', 'edit', 'delete'),
                    'knowledge_base' => array('view', 'create', 'edit', 'delete'),
                    'payments' => array('view', 'create', 'edit', 'delete'),
                    'projects' => array('view', 'create', 'edit', 'delete'),
                    'proposals' => array('create', 'edit', 'delete'),
                    'contracts' => array('view'),
                    'roles' => array('view', 'create', 'edit', 'delete'),
                    'settings' => array('view', 'edit'),
                    'staff' => array('view', 'create', 'edit', 'delete'),
                    'subscriptions' => array('create', 'edit', 'delete'),
                    'tasks' => array('view', 'create', 'edit', 'delete'),
                    'checklist_templates' => array('create', 'delete'),
                    'leads' => array('view', 'delete'),
                    'goals' => array('view', 'create', 'edit', 'delete'),
                    'surveys' => array('view', 'create', 'edit', 'delete'),
                )
            ];
            if ($departments != '') {
                $insert_data['departments'] = $departments;
            }
            if (!empty($this->input->post('custom_fields', TRUE))) {
                $insert_data['custom_fields'] = $this->Api_model->value($this->input->post('custom_fields', TRUE));
            }

            // insert data
            if (empty($insert_data['administrator'])) {
                unset($insert_data['administrator']);
            }

            if (empty($insert_data['is_not_staff'])) {
                unset($insert_data['is_not_staff']);
            }

            $this->load->model('staff_model');
            $output = $this->staff_model->add($insert_data);
            if ($output > 0 && !empty($output)) {
                // success
                $message = array(
                    'status' => TRUE,
                    'message' => 'Staff add successful.',
                    'insert_id' => $output
                );
                $this->response($message, REST_Controller::HTTP_OK);
            } else {
                // error
                $message = array(
                    'status' => FALSE,
                    'message' => 'Staff add fail.'
                );
                $this->response($message, REST_Controller::HTTP_OK);
            }
        }
    }

    public function data_delete($id)
    {
        $id = $this->security->xss_clean($id);
        if (empty($id) && !is_numeric($id)) {
            $message = array(
                'status' => FALSE,
                'message' => 'Invalid Staff ID'
            );
            $this->response($message, REST_Controller::HTTP_OK);
        } else {
            // delete data
            $this->load->model('staff_model');
            $output = $this->staff_model->delete($id, 0);
            if ($output === TRUE) {
                // success
                $message = array(
                    'status' => TRUE,
                    'message' => 'Staff Delete Successful.'
                );
                $this->response($message, REST_Controller::HTTP_OK);
            } else {
                // error
                $message = array(
                    'status' => FALSE,
                    'message' => 'Staff Delete Fail.'
                );
                $this->response($message, REST_Controller::HTTP_OK);
            }
        }
    }

    public function data_put($id)
    {
        $_POST = json_decode($this->security->xss_clean(file_get_contents("php://input")), true);
        if (empty($_POST) || !isset($_POST)) {
            $message = array(
                'status' => FALSE,
                'message' => 'Data Not Acceptable OR Not Provided'
            );
            $this->response($message, REST_Controller::HTTP_OK);
        }
        $this->form_validation->set_data($_POST);

        if (empty($id) && !is_numeric($id)) {
            $message = array(
                'status' => FALSE,
                'message' => 'Invalid Staff ID'
            );
            $this->response($message, REST_Controller::HTTP_OK);
        } else {

            $update_data = $this->input->post();
            // update data
            if (empty($update_data['administrator'])) {
                unset($update_data['administrator']);
            }

            if (empty($update_data['is_not_staff'])) {
                unset($update_data['is_not_staff']);
            }

            $this->load->model('staff_model');
            $output = $this->staff_model->update($update_data, $id);

            if ($output > 0 && !empty($output)) {
                // success
                $message = array(
                    'status' => TRUE,
                    'message' => 'Staff Update Successful.'
                );
                $this->response($message, REST_Controller::HTTP_OK);
            } else {
                // error
                $message = array(
                    'status' => FALSE,
                    'message' => 'Staff Update Fail.'
                );
                $this->response($message, REST_Controller::HTTP_OK);
            }
        }
    }

    public function change_status_get($id, $status)
    {
        if (empty($id) && !is_numeric($id)) {
            $message = array(
                'status' => FALSE,
                'message' => 'Invalid Staff ID'
            );
            $this->response($message, REST_Controller::HTTP_OK);
        }

        if (!has_permission('staff', '', 'edit')) {
            $message = array(
                'status' => FALSE,
                'message' => 'You don\'t have permission to change status'
            );
            $this->response($message, REST_Controller::HTTP_OK);
        }

        $this->load->model('staff_model');
        $this->staff_model->change_staff_status($id, $status);

        $message = array(
            'status' => TRUE,
            'message' => 'Staff Status Changed',
        );
        $this->response($message, REST_Controller::HTTP_OK);
    }

    public function profile_image_post($staff_id)
    {
        $success = handle_staff_profile_image_upload($staff_id);;
        if ($success) {
            // success
            $message = array(
                'status' => TRUE,
                'message' => 'Staff profile image added successfully.'
            );
            $this->response($message, REST_Controller::HTTP_OK);
        } else {
            // error
            $message = array(
                'status' => FALSE,
                'message' => 'Staff profile image add fail.'
            );
            $this->response($message, REST_Controller::HTTP_OK);
        }
    }

    public function profile_image_delete($staff_id = '')
    {
        $id = $this->security->xss_clean($staff_id);
        if (empty($id) && !is_numeric($id)) {
            $message = array(
                'status' => FALSE,
                'message' => 'Invalid Staff ID'
            );
            $this->response($message, REST_Controller::HTTP_OK);
        } else {
            $is_exist = $this->staff_model->get($id);
            if (is_object($is_exist)) {
                $this->staff_model->delete_staff_profile_image($id);
                $message = array(
                    'status' => TRUE,
                    'message' => 'Profile Image Deleted Successfuly.'
                );
                $this->response($message, REST_Controller::HTTP_OK);
            } else {
                $message = array(
                    'status' => FALSE,
                    'message' => 'Invalid Staff ID'
                );
                $this->response($message, REST_Controller::HTTP_OK);
            }
        }
    }
}
