<?php

defined('BASEPATH') or exit('No direct script access allowed');

require __DIR__ . '/../REST_Controller.php';

class Misc extends REST_Controller
{

    function __construct()
    {
        // Construct the parent class
        parent::__construct();
    }

    public function toggle_file_visibility_get($id)
    {
        $this->db->where('id', $id);
        $row = $this->db->get(db_prefix() . 'files')->row();
        
        if ($row->visible_to_customer == 1) {
            $v = 0;
        } else {
            $v = 1;
        }

        $this->db->where('id', $id);
        $this->db->update(db_prefix() . 'files', [
            'visible_to_customer' => $v,
        ]);

        $message = array(
            'status' => TRUE,
            'message' => 'file visibility changed.'
        );
        $this->response($message, REST_Controller::HTTP_OK);
    }

    public function upload_sales_file_post()
    {
        // form validation
        $this->form_validation->set_rules('rel_id', 'Rel id', 'trim|required');
        $this->form_validation->set_rules('type', 'Type', 'trim|required');
        if ($this->form_validation->run() == FALSE) {
            // form validation error
            $message = array(
                'status' => FALSE,
                'error' => $this->form_validation->error_array(),
                'message' => validation_errors()
            );
            $this->response($message, REST_Controller::HTTP_OK);
        } else {
            handle_sales_attachments($this->input->post('rel_id'), $this->input->post('type'));
        }
    }

    public function delete_sale_activity_delete($id)
    {
        $id = $this->security->xss_clean($id);
        if (empty($id) && !is_numeric($id)) {
            $message = array(
                'status' => FALSE,
                'message' => 'Invalid Activity ID'
            );
            $this->response($message, REST_Controller::HTTP_OK);
        } else {
            if (is_admin()) {
                $this->db->where('id', $id);
                $this->db->delete(db_prefix() . 'sales_activity');

                $message = array(
                    'status' => TRUE,
                    'message' => 'Activity Delete Successful.'
                );
                $this->response($message, REST_Controller::HTTP_OK);
            }

            // error
            $message = array(
                'status' => FALSE,
                'message' => 'Activity Delete Fail.'
            );
            $this->response($message, REST_Controller::HTTP_OK);
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
}
