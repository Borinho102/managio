<?php

defined('BASEPATH') or exit('No direct script access allowed');

require __DIR__ . '/../REST_Controller.php';

class Customers extends REST_Controller
{

    function __construct()
    {
        // Construct the parent class
        parent::__construct();
        $this->load->model('clients_model');
    }

    public function data_get($id = '')
    {
        try {
            $data = $this->Api_model->get_table('clients', $id);

            if (!is_gdpr() && is_reference_in_table('clientid', db_prefix() . 'invoices', $id)) {
                $data->invoices_available = true;
            }

            if (!is_gdpr() && is_reference_in_table('clientid', db_prefix() . 'estimates', $id)) {
                $data->estimates_available = true;
            }

            if (!is_gdpr() && is_reference_in_table('clientid', db_prefix() . 'creditnotes', $id)) {
                $data->creditnotes_available = true;
            }

            if ($data) {

                $data->customer_groups = $this->clients_model->get_customer_groups($id);
                $data = $this->Api_model->get_api_custom_data($data, "customers", $id);
            }
            $this->response($data, REST_Controller::HTTP_OK); // OK (200) being the HTTP response code
        } catch (\Throwable $th) {
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
                'message' => 'Invalid Customers ID'
            );
            $this->response($message, REST_Controller::HTTP_OK);
        } else {

            $update_data = $this->input->post();
            // update data
            $this->load->model('clients_model');

            if (empty($update_data['update_all_other_transactions'])) {
                unset($update_data['update_all_other_transactions']);
            }

            if (empty($update_data['update_credit_notes'])) {
                unset($update_data['update_credit_notes']);
            }

            $output = $this->clients_model->update($update_data, $id);
            if ($output > 0 && !empty($output)) {
                // success
                $message = array(
                    'status' => TRUE,
                    'message' => 'Customers Update Successful.'
                );
                $this->response($message, REST_Controller::HTTP_OK);
            } else {
                // error
                $message = array(
                    'status' => FALSE,
                    'message' => 'Customers Update Fail.'
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
}
