<?php

defined('BASEPATH') or exit('No direct script access allowed');

require __DIR__ . '/../REST_Controller.php';

class Customers extends REST_Controller
{

    function __construct()
    {
        parent::__construct();
        $this->load->model('clients_model');
    }

    public function data_get($id = '')
    {

        if ($id != '') {
            $data = $this->Api_model->get_table('clients', $id);
        } else {
            $search  = $this->input->get('search');
            $escaped_company_name = $this->db->escape_like_str($search);

            $sql = "SELECT userid, company, phonenumber, website, active, longitude, latitude FROM tblclients WHERE MATCH (company) AGAINST ('$escaped_company_name' IN NATURAL LANGUAGE MODE)";

            $query = $this->db->query($sql);
            $data = $query->result_array();
        }

        $this->response($data, REST_Controller::HTTP_OK); // OK (200) being the HTTP response code
    }

    public function data_post()
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
        $this->form_validation->set_rules('company', 'Customer Name', 'trim|required|max_length[600]', array('is_unique' => 'This %s already exists please enter another Company'));
        $this->form_validation->set_rules('email', 'Email', 'required');
        $this->form_validation->set_rules('password', 'Password', 'required');

        if ($this->form_validation->run() == FALSE) {
            // form validation error
            $message = array(
                'status' => FALSE,
                'error' => $this->form_validation->error_array(),
                'message' => validation_errors()
            );
            $this->response($message, REST_Controller::HTTP_OK);
        } else {
            $full_name = explode(' ', $this->input->post('company', TRUE));
            $insert_data = [
                'company' => $this->input->post('company', TRUE),
                'email' => $this->input->post('email', TRUE),
                'password' => $this->input->post('password', TRUE),
                'is_primary' => 1,
                'firstname' => $full_name[0] ?? '',
                'lastname' => $full_name[1] ?? '',
            ];

            $address = $this->input->post('address', TRUE);

            if (!empty($address)) {
                $apiKey = get_option('google_api_key');
                if (!empty($apiKey)) {
                    include_once(APPPATH . 'third_party/JD_Geocoder_Request.php');

                    $georequest = new JD_Geocoder_Request($apiKey);
                    $georequest->forwardSearch(implode(', ', $address));
                    $georequest = (object) $georequest;

                    if ($georequest->response->status == 'OK') {
                        $insert_data['latitude'] = $georequest->lat;
                        $insert_data['longitude'] = $georequest->lng;
                    }
                }

                if (isset($insert_data['country'])) {
                    $insert_data['country'] = get_country_id($insert_data['country']);
                }
            }

            // insert data
            $this->load->model('clients_model');
            $output = $this->clients_model->add($insert_data, true);
            if ($output > 0 && !empty($output)) {
                // success
                $message = array(
                    'status' => TRUE,
                    'message' => 'Client Created Successfully',
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
}
