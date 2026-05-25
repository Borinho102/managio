<?php

defined('BASEPATH') or exit('No direct script access allowed');

require __DIR__ . '/../REST_Controller.php';
class Clients extends REST_Controller
{

    function __construct()
    {
        // Construct the parent class
        parent::__construct();
        $this->load->model('clients_model');
        $this->load->model('roles_model');

    }

    public function data_get($id = '')
    {
        $clientid =  get_client_user_id();

        $data = $this->clients_model->get($clientid);

        if ($data) {

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

        try {
            $data = $this->Api_model->search('customer', $key);

            if ($data) {
                foreach ($data as $key => $value) {
                    $data[$key]['customer_groups'] = $this->clients_model->get_customer_groups($value['userid']);

                    if (!is_gdpr() && is_reference_in_table('clientid', db_prefix() . 'invoices', $value['userid'])) {
                        $data[$key]['invoices_available'] = true;
                    }

                    if (!is_gdpr() && is_reference_in_table('clientid', db_prefix() . 'estimates', $value['userid'])) {
                        $data[$key]['estimates_available'] = true;
                    }

                    if (!is_gdpr() && is_reference_in_table('clientid', db_prefix() . 'creditnotes', $value['userid'])) {
                        $data[$key]['creditnotes_available'] = true;
                    }
                }

                $data = $this->Api_model->get_api_custom_data($data, "customers");
            }

            $this->response($data, REST_Controller::HTTP_OK); // OK (200) being the HTTP response code
        } catch (\Throwable $th) {
            $this->response([
                'status' => FALSE,
                'message' => 'No data found'
            ], REST_Controller::HTTP_OK); // NOT_FOUND (404) being the HTTP response code
        }
    }

    public function data_post()
    {
        // form validation
        $this->form_validation->set_rules('company', 'Company', 'trim|required|max_length[600]', array('is_unique' => 'This %s already exists please enter another Company'));
        if ($this->form_validation->run() == FALSE) {
            // form validation error
            $message = array(
                'status' => FALSE,
                'error' => $this->form_validation->error_array(),
                'message' => validation_errors()
            );
            $this->response($message, REST_Controller::HTTP_OK);
        } else {
            $groups_in = $this->Api_model->value($this->input->post('groups_in', TRUE));
            $insert_data = [
                'company' => $this->input->post('company', TRUE),

                'vat' => $this->Api_model->value($this->input->post('vat', TRUE)),
                'phonenumber' => $this->Api_model->value($this->input->post('phonenumber', TRUE)),
                'website' => $this->Api_model->value($this->input->post('website', TRUE)),
                'default_currency' => $this->Api_model->value($this->input->post('default_currency', TRUE)),
                'default_language' => $this->Api_model->value($this->input->post('default_language', TRUE)),
                'address' => $this->Api_model->value($this->input->post('address', TRUE)),
                'city' => $this->Api_model->value($this->input->post('city', TRUE)),
                'state' => $this->Api_model->value($this->input->post('state', TRUE)),
                'zip' => $this->Api_model->value($this->input->post('zip', TRUE)),
                'country' => $this->Api_model->value($this->input->post('country', TRUE)),
                'billing_street' => $this->Api_model->value($this->input->post('billing_street', TRUE)),
                'billing_city' => $this->Api_model->value($this->input->post('billing_city', TRUE)),
                'billing_state' => $this->Api_model->value($this->input->post('billing_state', TRUE)),
                'billing_zip' => $this->Api_model->value($this->input->post('billing_zip', TRUE)),
                'billing_country' => $this->Api_model->value($this->input->post('billing_country', TRUE)),
                'shipping_street' => $this->Api_model->value($this->input->post('shipping_street', TRUE)),
                'shipping_city' => $this->Api_model->value($this->input->post('shipping_city', TRUE)),
                'shipping_state' => $this->Api_model->value($this->input->post('shipping_state', TRUE)),
                'shipping_zip' => $this->Api_model->value($this->input->post('shipping_zip', TRUE)),
                'shipping_country' => $this->Api_model->value($this->input->post('shipping_country', TRUE))
            ];
            if (!empty($this->input->post('custom_fields', TRUE))) {
                $insert_data['custom_fields'] = $this->Api_model->value($this->input->post('custom_fields', TRUE));
            }
            if ($groups_in != '') {
                $insert_data['groups_in'] = $groups_in;
            }

            // insert data
            $this->load->model('clients_model');
            $output = $this->clients_model->add($insert_data);
            if ($output > 0 && !empty($output)) {
                // success
                $message = array(
                    'status' => TRUE,
                    'message' => 'Client add successful.',
                    'insert_id' => $output
                );
                $this->response($message, REST_Controller::HTTP_OK);
            } else {
                // error
                $message = array(
                    'status' => FALSE,
                    'message' => 'Client add fail.'
                );
                $this->response($message, REST_Controller::HTTP_OK);
            }
        }
    }

    public function data_delete($id = '')
    {
        $id = $this->security->xss_clean($id);
        if (empty($id) && !is_numeric($id)) {
            $message = array(
                'status' => FALSE,
                'message' => 'Invalid Customer ID'
            );
            $this->response($message, REST_Controller::HTTP_OK);
        } else {
            // delete data
            $this->load->model('clients_model');
            $response = $this->clients_model->delete($id);

            if (is_array($response) && isset($response['referenced'])) {
                // success
                $message = array(
                    'status' => false,
                    'message' => _l('customer_delete_transactions_warning', _l('invoices') . ', ' . _l('estimates') . ', ' . _l('credit_notes'))
                );
                $this->response($message, REST_Controller::HTTP_OK);
            } elseif ($response == true) {
                $message = array(
                    'status' => true,
                    'message' => _l('deleted', _l('client'))
                );
                $this->response($message, REST_Controller::HTTP_OK);
            } else {
                // error
                $message = array(
                    'status' => false,
                    'message' => _l('problem_deleting', _l('client_lowercase'))
                );
                $this->response($message, REST_Controller::HTTP_OK);
            }
        }
    }

    public function data_put($id = '')
    {

        $clientid = get_client_user_id();

        if (empty($clientid) && !is_numeric($clientid)) {
            $message = array(
                'status' => FALSE,
                'message' => ' Client ID Not Found'
            );
            $this->response($message, REST_Controller::HTTP_OK);
        } else {

            $_POST = json_decode($this->security->xss_clean(file_get_contents("php://input")), true);

            if (empty($_POST) || !isset($_POST)) {
                $message = array(
                    'status' => FALSE,
                    'message' => 'Data Not Acceptable OR Not Provided'
                );
                $this->response($message, REST_Controller::HTTP_OK);
            }

            $this->form_validation->set_data($_POST);

            $update_data = $this->input->post();

            $output = $this->clients_model->update($update_data, $clientid);

            if ($output > 0 && !empty($output)) {
                // success
                $message = array(
                    'status' => TRUE,
                    'message' => 'Company Update Successful.'
                );
                $this->response($message, REST_Controller::HTTP_OK);
            } else {
                // error
                $message = array(
                    'status' => FALSE,
                    'message' => 'Company Update Fail.'
                );
                $this->response($message, REST_Controller::HTTP_OK);
            }
        }
    }

    public function change_client_status_get($id, $status)
    {
        if (empty($id) && !is_numeric($id)) {
            $message = array(
                'status' => FALSE,
                'message' => 'Invalid Customer ID'
            );
            $this->response($message, REST_Controller::HTTP_OK);
        }

        $success = $this->clients_model->change_client_status($id, $status);
        if ($success) {
            $message = array(
                'status' => TRUE,
                'message' => 'Customer Status Changed',
            );
            $this->response($message, REST_Controller::HTTP_OK);
        } else {
            // error
            $message = array(
                'status' => FALSE,
                'message' => 'Something Went Wrong While Changing Status'
            );
            $this->response($message, REST_Controller::HTTP_OK);
        }
    }

    public function change_password_post()
    {
        $contact_id = get_contact_user_id();

        if (empty($contact_id)) {
            $message = array(
                'status' => FALSE,
                'message' => 'Contact ID Not Found'
            );
            $this->response($message, REST_Controller::HTTP_OK);
        }

        $_POST = json_decode($this->security->xss_clean(file_get_contents("php://input")), true);

        if (empty($_POST) || !isset($_POST)) {
            $message = array(
                'status' => FALSE,
                'message' => 'Data Not Acceptable OR Not Provided'
            );
            $this->response($message, REST_Controller::HTTP_OK);
        }

        $update_password = $this->input->post();

        $oldpassword    = $update_password['oldPassword'];
        $newpassword = $update_password['newPassword'];

        if (!empty($oldpassword) && !empty($newpassword)) {

            $success = $this->clients_model->change_contact_password(
                $contact_id,
                $oldpassword,
                $newpassword
            );

            if ($success) {
                $message = array(
                    'status' => TRUE,
                    'message' => 'Profile Password Updated Successfully!',

                );

                if (isset($success['old_password_not_match']) == 1) {
                    $message = array(
                        'status' => FALSE,
                        'message' => 'Old password does not match',

                    );
                }
            } else {
                $message = array(
                    'status' => FALSE,
                    'message' => 'Nothing updated!',

                );
            }
            $this->response($message, REST_Controller::HTTP_OK);
        }
        // error
        $message = array(
            'status' => FALSE,
            'message' => 'Nothing updated!'
        );
        $this->response($message, REST_Controller::HTTP_OK);
    }

    public function contact_put()
    {
        $contactid = get_contact_user_id();

        if (empty($contactid) && !is_numeric($contactid)) {
            $message = array(
                'status' => FALSE,
                'message' => ' Contact ID Not Found'
            );
            $this->response($message, REST_Controller::HTTP_OK);
        } else {

            $_POST = json_decode($this->security->xss_clean(file_get_contents("php://input")), true);

            if (empty($_POST) || !isset($_POST)) {
                $message = array(
                    'status' => FALSE,
                    'message' => 'Data Not Acceptable OR Not Provided'
                );
                $this->response($message, REST_Controller::HTTP_OK);
            }

            $this->form_validation->set_data($_POST);

            $update_data = $this->input->post();

            $output = $this->clients_model->update_contact($update_data, $contactid, true);

            if ($output > 0 && !empty($output)) {

                // success
                $message = array(
                    'status' => TRUE,
                    'message' => 'Client Update Successful.'
                );
                $this->response($message, REST_Controller::HTTP_OK);
            } else {
                // error
                $message = array(
                    'status' => FALSE,
                    'message' => 'Client Update Fail.'
                );
                $this->response($message, REST_Controller::HTTP_OK);
            }
        }
    }

    public function contact_get()
    {
        $contactid =  get_contact_user_id();

        $data = $this->clients_model->get_contact($contactid);

        $data->profile_image = contact_profile_image_url($contactid, 'thumb');

        $data->permissions = [];

        $data->permissions['invoice']     = has_contact_permission('invoices', $contactid) ? 1 : 0;

        $data->permissions['proposal']    = has_contact_permission('proposals', $contactid) ? 1 : 0;

        $data->permissions['estimate']    = has_contact_permission('estimates', $contactid) ? 1 : 0;

        $data->permissions['ticket']      = has_contact_permission('support', $contactid) ? 1 : 0;

        $data->permissions['contract']    = has_contact_permission('contracts', $contactid) ? 1 : 0;

        $data->permissions['project']     = has_contact_permission('projects', $contactid) ? 1 : 0;

        if ($data) {

            $this->response($data, REST_Controller::HTTP_OK); // OK (200) being the HTTP response code
        } else {
            $this->response([
                'status' => FALSE,
                'message' => 'No data found'
            ], REST_Controller::HTTP_OK); // NOT_FOUND (404) being the HTTP response code
        }
    }

    public function add_profile_image_post()
    {
        $contactid =  get_contact_user_id();

        $success = handle_contact_profile_image_upload($contactid);;
        if ($success) {
            // success
            $message = array(
                'status' => TRUE,
                'message' => 'Client profile image added successfully.'
            );
            $this->response($message, REST_Controller::HTTP_OK);
        } else {
            // error
            $message = array(
                'status' => FALSE,
                'message' => 'Client profile image add fail.'
            );
            $this->response($message, REST_Controller::HTTP_OK);
        }
    }

    public function remove_profile_image_delete()
    {
    
        $id = get_contact_user_id();
        if (empty($id) && !is_numeric($id)) {
            $message = array(
                'status' => FALSE,
                'message' => 'Invalid Contact ID'
            );
            $this->response($message, REST_Controller::HTTP_OK);
        } else {
            $is_exist = $this->clients_model->get_contact($id);
            if (is_object($is_exist)) {
                $this->clients_model->delete_contact_profile_image($id);
                $message = array(
                    'status' => TRUE,
                    'message' => 'Client Profile Image Deleted Successfuly.'
                );
                $this->response($message, REST_Controller::HTTP_OK);
            } else {
                $message = array(
                    'status' => FALSE,
                    'message' => 'Invalid Contact ID'
                );
                $this->response($message, REST_Controller::HTTP_OK);
            }
        }
    }

}
