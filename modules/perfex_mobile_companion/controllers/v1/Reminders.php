<?php defined('BASEPATH') or exit('No direct script access allowed');

require __DIR__ . '/../REST_Controller.php';

class Reminders extends REST_Controller
{
    function __construct()
    {
        parent::__construct();
    }

    public function data_search_get($id, $rel_type, $key = '')
    {
        $data = $this->__search_reminder($id, $rel_type, $key);
        if ($data) {

            foreach ($data as $key => $value) {
                $data[$key]['profile_url'] = staff_profile_image_url($value['staff']);
                $data[$key]['formatted_date'] = _d($value['date']);
            }

            $this->response($data, REST_Controller::HTTP_OK); // OK (200) being the HTTP response code
        } else {
            $this->response([
                'status' => FALSE,
                'message' => 'No data found'
            ], REST_Controller::HTTP_OK); // NOT_FOUND (404) being the HTTP response code
        }
    }

    private function __search_reminder($rel_id, $rel_type, $q)
    {
        $result = [
            'result'         => [],
            'type'           => 'reminders',
            'search_heading' => _l('reminders'),
        ];

        $this->db->select(db_prefix() . 'reminders.*, firstname, lastname');
        $this->db->join(db_prefix() . 'staff', db_prefix() . 'staff.staffid = ' . db_prefix() . 'reminders.staff');
        $this->db->from(db_prefix() . 'reminders');

        $this->db->group_start();
        $this->db->like('description', $q);
        $this->db->or_like(db_prefix() . 'reminders.id', $q);
        $this->db->group_end();

        $this->db->where('rel_id', $rel_id);
        $this->db->where('rel_type', $rel_type);

        if (is_numeric($this->input->get('limit')) && is_numeric($this->input->get('offset'))) {
            $this->db->limit($this->input->get('limit'));
            $this->db->offset($this->input->get('offset'));
        }

        $this->db->order_by('id', 'DESC');
        $result['result'] = $this->db->get()->result_array();

        return $result['result'];
    }

    public function data_post()
    {
        // form validation
        $this->form_validation->set_rules('description', 'Description', 'trim|required');
        $this->form_validation->set_rules('date', 'Date to be nitified', 'trim|required');
        $this->form_validation->set_rules('staff', 'Set reminder to', 'trim|required');

        if ($this->form_validation->run() == FALSE) {
            // form validation error
            $message = array(
                'status' => FALSE,
                'error' => $this->form_validation->error_array(),
                'message' => validation_errors()
            );
            $this->response($message, REST_Controller::HTTP_OK);
        } else {
            // insert data         
            $data = $this->input->post();
            $this->load->model('misc_model');
            $output = $this->misc_model->add_reminder($data, $data['rel_id']);
            if ($output > 0 && !empty($output)) {
                // success
                $message = array(
                    'status' => TRUE,
                    'message' => 'Reminder add successful.',
                    'insert_id' => $output
                );
                $this->response($message, REST_Controller::HTTP_OK);
            } else {
                // error
                $message = array(
                    'status' => FALSE,
                    'message' => 'Reminder add failed.'
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
                'message' => 'Invalid Reminder ID'
            );
            $this->response($message, REST_Controller::HTTP_OK);
        } else {
            // delete data
            $this->load->model('misc_model');
            $output = $this->misc_model->delete_reminder($id);
            if ($output === TRUE) {
                // success
                $message = array(
                    'status' => TRUE,
                    'message' => 'Reminder Delete Successful.'
                );
                $this->response($message, REST_Controller::HTTP_OK);
            } else {
                // error
                $message = array(
                    'status' => FALSE,
                    'message' => 'Reminder Delete Fail.'
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
                'message' => 'Invalid Reminder ID'
            );
            $this->response($message, REST_Controller::HTTP_OK);
        } else {

            $update_data = $this->input->post();
            // update data
            $this->load->model('misc_model');
            $output = $this->misc_model->edit_reminder($update_data, $id);
            if ($output == true && !empty($output)) {
                // success
                $message = array(
                    'status' => TRUE,
                    'message' => 'Reminder Update Successful.'
                );
                $this->response($message, REST_Controller::HTTP_OK);
            } else {
                // error
                $message = array(
                    'status' => FALSE,
                    'message' => 'Reminder Update Fail.'
                );
                $this->response($message, REST_Controller::HTTP_OK);
            }
        }
    }
}
