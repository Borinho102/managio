<?php

defined('BASEPATH') or exit('No direct script access allowed');

require __DIR__ . '/../REST_Controller.php';

class Milestones extends REST_Controller
{

    function __construct()
    {
        // Construct the parent class
        parent::__construct();
    }

    public function data_get($id = '')
    {
        // If the id parameter doesn't exist return all the
        $data = $this->Api_model->get_table('milestones', $id);

        if ($data) {
            $data->formatted_due_date    = _d($data->due_date);
            $data->duedate_passed = false;
            if (date('Y-m-d') > $data->due_date && total_rows(db_prefix() . 'tasks', [
                'milestone' => $data->id,
                'status !=' => 5,
                'rel_id' => $data->project_id,
                'rel_type' => 'project',
            ]) > 0) {
                $data->duedate_passed = true;
            }
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

        // If the id parameter doesn't exist return all the
        $data = $this->Api_model->search('milestones', $key);

        if ($data) {

            foreach ($data as $key => $value) {
                $data[$key]['formatted_due_date']     = _d($value['due_date']);
                $data[$key]['duedate_passed'] = false;
                if (date('Y-m-d') > $value['due_date'] && total_rows(db_prefix() . 'tasks', [
                    'milestone' => $value['id'],
                    'status !=' => 5,
                    'rel_id' => $value['project_id'],
                    'rel_type' => 'project',
                ]) > 0) {
                    $data[$key]['duedate_passed'] = true;
                }
            }
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
        $this->form_validation->set_rules('name', 'Milestone Name', 'trim|required|max_length[600]', array('is_unique' => 'This %s already exists please enter another Milestone Name'));
        $this->form_validation->set_rules('project_id', 'Project id', 'trim|required', array('is_unique' => 'This %s already exists please enter another Project id'));
        $this->form_validation->set_rules('due_date', 'Milestone Due Date', 'trim|required', array('is_unique' => 'This %s already exists please enter another Milestone Due Date'));
        if ($this->form_validation->run() == FALSE) {
            // form validation error
            $message = array(
                'status' => FALSE,
                'error' => $this->form_validation->error_array(),
                'message' => validation_errors()
            );
            $this->response($message, REST_Controller::HTTP_OK);
        } else {
            $this->load->model('projects_model');

            $data = $this->input->post();
            $output = $this->projects_model->add_milestone($data);
            if ($output > 0 && !empty($output)) {
                // success
                $message = array(
                    'status' => TRUE,
                    'message' => 'Milestone add successful.'
                );
                $this->response($message, REST_Controller::HTTP_OK);
            } else {
                // error
                $message = array(
                    'status' => FALSE,
                    'message' => 'Milestone add fail.'
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
                'message' => 'Invalid Milestone ID'
            );
            $this->response($message, REST_Controller::HTTP_OK);
        } else {
            // delete data
            $this->load->model('projects_model');
            $output = $this->projects_model->delete_milestone($id);
            if ($output === TRUE) {
                // success
                $message = array(
                    'status' => TRUE,
                    'message' => 'Milestone Delete Successful.'
                );
                $this->response($message, REST_Controller::HTTP_OK);
            } else {
                // error
                $message = array(
                    'status' => FALSE,
                    'message' => 'Milestone Delete Fail.'
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
            $this->response($message, REST_Controller::HTTP_NOT_ACCEPTABLE);
        }
        $this->form_validation->set_data($_POST);

        if (empty($id) && !is_numeric($id)) {
            $message = array(
                'status' => FALSE,
                'message' => 'Invalid Milestone ID'
            );
            $this->response($message, REST_Controller::HTTP_OK);
        } else {

            $update_data = $this->input->post();
            // update data
            $this->load->model('projects_model');
            $output = $this->projects_model->update_milestone($update_data, $id);
            if ($output > 0 && !empty($output)) {
                // success
                $message = array(
                    'status' => TRUE,
                    'message' => 'Milestone Update Successful.'
                );
                $this->response($message, REST_Controller::HTTP_OK);
            } else {
                // error
                $message = array(
                    'status' => FALSE,
                    'message' => 'Milestone Update Fail.'
                );
                $this->response($message, REST_Controller::HTTP_OK);
            }
        }
    }
}
