<?php

defined('BASEPATH') or exit('No direct script access allowed');

require __DIR__ . '/../REST_Controller.php';

class Tasks extends REST_Controller
{

    function __construct()
    {
        // Construct the parent class
        parent::__construct();
        $this->load->model('tasks_model');
        $this->load->model('invoice_items_model');
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

        $this->form_validation->set_rules('name', 'Task Name', 'trim|required|max_length[600]', array('is_unique' => 'This %s already exists please enter another Task Name'));
        $this->form_validation->set_rules('rel_id', 'Customer', 'trim|required|max_length[255]');

        if ($this->form_validation->run() == FALSE) {
            // form validation error
            $message = array(
                'status' => FALSE,
                'error' => $this->form_validation->error_array(),
                'message' => validation_errors()
            );
            $this->response($message, REST_Controller::HTTP_OK);
        } else {

            $insert_data = [
                'name' => $this->input->post('name', TRUE),
                'startdate' => ($this->input->post('startdate', TRUE) ?? date('Y-m-d H:i:s')),
                'priority' => 2,
                'rel_type' => 'customer',
                'rel_id' => $this->Api_model->value($this->input->post('rel_id', TRUE)),
            ];

            if (!empty($insert_data['startdate'])) {
                $insert_data['duedate'] = date('Y-m-d H:i:s', strtotime('+2 hours', strtotime($insert_data['startdate'])));
            }

            if (!empty($this->input->post('assignees', TRUE))) {
                $insert_data['assignees'] = $this->Api_model->value($this->input->post('assignees', TRUE));
            }

            // insert data
            $output = $this->tasks_model->add($insert_data);
            if ($output > 0 && !empty($output)) {
                // success
                $message = array(
                    'status' => TRUE,
                    'message' => 'Task add successful.',
                    'insert_id' => $output
                );
                $this->response($message, REST_Controller::HTTP_OK);
            } else {
                // error
                $message = array(
                    'status' => FALSE,
                    'message' => 'Task add failed.'
                );
                $this->response($message, REST_Controller::HTTP_OK);
            }
        }
    }
}
