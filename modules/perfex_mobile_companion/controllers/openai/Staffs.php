<?php

defined('BASEPATH') or exit('No direct script access allowed');

require __DIR__ . '/../REST_Controller.php';

class Staffs extends REST_Controller
{

    function __construct()
    {
        parent::__construct();
        $this->load->model('Api_model');
    }

    public function data_get($id = '')
    {
        if ($id != '') {
            $data = $this->Api_model->get_table('staffs', $id);
        } else {
            $search  = $this->input->get('search');
            $escaped_company_name = $this->db->escape_like_str($search);

            $sql = "SELECT staffid, CONCAT(firstname,' ',lastname) as full_name, email, admin FROM tblstaff WHERE MATCH(firstname, lastname) AGAINST ('$escaped_company_name' IN NATURAL LANGUAGE MODE)";

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

        $this->form_validation->set_rules('firstname', 'First Name', 'trim|required|max_length[600]', array('is_unique' => 'This %s already exists please enter another Staff First Name'));
        $this->form_validation->set_rules('email', 'Email', 'trim|required|valid_email', array('is_unique' => 'This %s already exists please enter another Staff Email'));
        $this->form_validation->set_rules('password', 'Password', 'trim|required', array('is_unique' => 'This %s already exists please enter another Staff password'));
        if ($this->form_validation->run() == FALSE) {
            $message = array(
                'status' => FALSE,
                'error' => $this->form_validation->error_array(),
                'message' => validation_errors()
            );
            $this->response($message, REST_Controller::HTTP_OK);
        } else {
            $insert_data = [
                'firstname' => $this->input->post('firstname', TRUE),
                'email' => $this->input->post('email', TRUE),
                'password' => $this->input->post('password', TRUE),
                'lastname' => $this->input->post('lastname', TRUE),
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
                'permissions' => [
                    'invoices' => ['view_own'],
                    'items' => ['view_own']
                ],
                'administrator' => $this->Api_model->value($this->input->post('admin', TRUE))
            ];

            if (empty($insert_data['administrator'])) {
                unset($insert_data['administrator']);
            }

            $this->load->model('staff_model');
            $output = $this->staff_model->add($insert_data);

            if ($output > 0 && !empty($output)) {
                // success
                if ($output == 'Email already exists') {
                    $message = array(
                        'status' => FALSE,
                        'message' => $output,
                    );
                    $this->response($message, REST_Controller::HTTP_OK);
                }
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
}
