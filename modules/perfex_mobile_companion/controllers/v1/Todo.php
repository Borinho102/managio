<?php

defined('BASEPATH') or exit('No direct script access allowed');

require __DIR__ . '/../REST_Controller.php';

class Todo extends REST_Controller
{

    function __construct()
    {
        parent::__construct();
        $this->load->model('todo_api_model');
        $this->load->model('todo_model');
    }

    /* Change todo status */
    public function change_todo_status_get($id, $status)
    {
        $success = $this->todo_api_model->change_todo_status($id, $status);
        if ($success) {
            $message = array(
                'status' => TRUE,
                'message' => _l('todo_status_changed')
            );
            $this->response($message, REST_Controller::HTTP_OK);
        } else {
            $message = array(
                'status' => FALSE,
                'message' => 'Nothing Changed.'
            );
            $this->response($message, REST_Controller::HTTP_OK);
        }
    }

    public function data_post()
    {
        // form validation
        $this->form_validation->set_rules('description', 'Description', 'trim|required');
        if ($this->form_validation->run() == FALSE) {
            // form validation error
            $message = array(
                'status' => FALSE,
                'error' => $this->form_validation->error_array(),
                'message' => validation_errors()
            );
            $this->response($message, REST_Controller::HTTP_OK);
        } else {
            $output = $this->todo_model->add($this->input->post());
            if ($output > 0 && !empty($output)) {
                // success
                $message = array(
                    'status' => TRUE,
                    'message' =>  _l('added_successfully', _l('todo')),
                    'insert_id' => $output
                );
                $this->response($message, REST_Controller::HTTP_OK);
            } else {
                // error
                $message = array(
                    'status' => FALSE,
                    'message' => 'Todo item add fail.'
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
                'message' => 'Invalid Todo Item ID'
            );
            $this->response($message, REST_Controller::HTTP_OK);
        } else {
            // delete data
            $output = $this->todo_api_model->delete_todo_item($id);
            if ($output === TRUE) {
                // success
                $message = array(
                    'status' => TRUE,
                    'message' => 'Todo Item Delete Successful.'
                );
                $this->response($message, REST_Controller::HTTP_OK);
            } else {
                // error
                $message = array(
                    'status' => FALSE,
                    'message' => 'Todo Item Delete Fail.'
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
                'message' => 'Invalid Ticket ID'
            );
            $this->response($message, REST_Controller::HTTP_OK);
        } else {
            $success = $this->todo_model->update($id, $this->input->post());
            if ($success) {
                // success
                $message = array(
                    'status' => TRUE,
                    'message' =>  _l('updated_successfully', _l('todo'))
                );
                $this->response($message, REST_Controller::HTTP_OK);
            } else {
                // error
                $message = array(
                    'status' => FALSE,
                    'message' => 'Todo Update Fail.'
                );
                $this->response($message, REST_Controller::HTTP_OK);
            }
        }
    }
}
