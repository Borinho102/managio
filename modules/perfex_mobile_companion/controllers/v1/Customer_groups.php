<?php

defined('BASEPATH') or exit('No direct script access allowed');

require __DIR__ . '/../REST_Controller.php';
class Customer_groups extends REST_Controller
{

    function __construct()
    {
        // Construct the parent class
        parent::__construct();
    }


    public function data_get($id = '')
    {
        try {
            $this->load->model('clients_api_model');
            $groups = $this->clients_api_model->get_groups();

            $this->response($groups, REST_Controller::HTTP_OK); // OK (200) being the HTTP response code
        } catch (\Throwable $th) {
            $this->response([
                'status' => FALSE,
                'message' => 'No data found'
            ], REST_Controller::HTTP_NOT_FOUND); // NOT_FOUND (404) being the HTTP response code
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
            $this->response($message, REST_Controller::HTTP_NOT_FOUND);
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
                $this->response($message, REST_Controller::HTTP_NOT_FOUND);
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
            $this->response($message, REST_Controller::HTTP_NOT_FOUND);
        } else {
            // delete data
            $this->load->model('clients_model');
            $output = $this->clients_model->delete($id);
            if ($output === TRUE) {
                // success
                $message = array(
                    'status' => TRUE,
                    'message' => 'Customer Delete Successful.'
                );
                $this->response($message, REST_Controller::HTTP_OK);
            } else {
                // error
                $message = array(
                    'status' => FALSE,
                    'message' => 'Customer Delete Fail.'
                );
                $this->response($message, REST_Controller::HTTP_NOT_FOUND);
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
            $this->response($message, REST_Controller::HTTP_NOT_ACCEPTABLE);
        }
        $this->form_validation->set_data($_POST);

        if (empty($id) && !is_numeric($id)) {
            $message = array(
                'status' => FALSE,
                'message' => 'Invalid Customers ID'
            );
            $this->response($message, REST_Controller::HTTP_NOT_FOUND);
        } else {

            $update_data = $this->input->post();
            // update data
            $this->load->model('clients_model');
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
                $this->response($message, REST_Controller::HTTP_NOT_FOUND);
            }
        }
    }
}
