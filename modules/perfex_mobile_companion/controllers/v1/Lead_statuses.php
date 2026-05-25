<?php

defined('BASEPATH') or exit('No direct script access allowed');
require __DIR__ . '/../REST_Controller.php';

class Lead_statuses extends REST_Controller
{

    function __construct()
    {
        // Construct the parent class
        parent::__construct();
        $this->load->model('Api_model');
    }

    public function data_get()
    {
        $this->load->model('leads_model');
        // If the id parameter doesn't exist return all the
        $statuses = $this->leads_model->get_status();

        if ($statuses) {
            $this->response($statuses, REST_Controller::HTTP_OK); // OK (200) being the HTTP response code
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

        $data = $this->Api_model->search('lead', $key);
        if ($data) {
            $data = $this->Api_model->get_api_custom_data($data, "leads");

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
        $this->form_validation->set_rules('name', 'Lead Name', 'trim|required|max_length[600]', array('is_unique' => 'This %s already exists please enter another Lead Name'));
        $this->form_validation->set_rules('source', 'Source', 'trim|required', array('is_unique' => 'This %s already exists please enter another Lead source'));
        $this->form_validation->set_rules('status', 'Status', 'trim|required', array('is_unique' => 'This %s already exists please enter another Status'));
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
                'source' => $this->input->post('source', TRUE),
                'status' => $this->input->post('status', TRUE),

                'assigned' => $this->Api_model->value($this->input->post('assigned', TRUE)),
                'tags' => $this->Api_model->value($this->input->post('tags', TRUE)),
                'title' => $this->Api_model->value($this->input->post('title', TRUE)),
                'email' => $this->Api_model->value($this->input->post('email', TRUE)),
                'website' => $this->Api_model->value($this->input->post('website', TRUE)),
                'phonenumber' => $this->Api_model->value($this->input->post('phonenumber', TRUE)),
                'company' => $this->Api_model->value($this->input->post('company', TRUE)),
                'address' => $this->Api_model->value($this->input->post('address', TRUE)),
                'city' => $this->Api_model->value($this->input->post('city', TRUE)),
                'zip' => '',
                'state' => $this->Api_model->value($this->input->post('state', TRUE)),
                'default_language' => $this->Api_model->value($this->input->post('default_language', TRUE)),
                'description' => $this->Api_model->value($this->input->post('description', TRUE)),
                'custom_contact_date' => $this->Api_model->value($this->input->post('custom_contact_date', TRUE)),
                'is_public' => $this->Api_model->value($this->input->post('is_public', TRUE)),
                'contacted_today' => $this->Api_model->value($this->input->post('contacted_today', TRUE))
            ];
            if (!empty($this->input->post('custom_fields', TRUE))) {
                $insert_data['custom_fields'] = $this->Api_model->value($this->input->post('custom_fields', TRUE));
            }
            // insert data
            $this->load->model('leads_model');
            $output = $this->leads_model->add($insert_data);
            if ($output > 0 && !empty($output)) {
                // success
                $this->handle_lead_attachments_array($output);
                $message = array(
                    'status' => TRUE,
                    'message' => 'Lead add successful.'
                );
                $this->response($message, REST_Controller::HTTP_OK);
            } else {
                // error
                $message = array(
                    'status' => FALSE,
                    'message' => 'Lead add fail.'
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
                'message' => 'Invalid Lead ID'
            );
            $this->response($message, REST_Controller::HTTP_OK);
        } else {
            // delete data
            $this->load->model('leads_model');
            $output = $this->leads_model->delete($id);
            if ($output === TRUE) {
                // success
                $message = array(
                    'status' => TRUE,
                    'message' => 'Lead Delete Successful.'
                );
                $this->response($message, REST_Controller::HTTP_OK);
            } else {
                // error
                $message = array(
                    'status' => FALSE,
                    'message' => 'Lead Delete Fail.'
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
                'message' => 'Invalid Lead ID'
            );
            $this->response($message, REST_Controller::HTTP_OK);
        } else {

            $update_data = $this->input->post();
            // update data
            $this->load->model('leads_model');
            $output = $this->leads_model->update($update_data, $id);
            if ($output > 0 && !empty($output)) {
                // success
                $message = array(
                    'status' => TRUE,
                    'message' => 'Lead Update Successful.'
                );
                $this->response($message, REST_Controller::HTTP_OK);
            } else {
                // error
                $message = array(
                    'status' => FALSE,
                    'message' => 'Lead Update Fail.'
                );
                $this->response($message, REST_Controller::HTTP_OK);
            }
        }
    }

    function handle_lead_attachments_array($leadid, $index_name = 'file')
    {
        $path           = get_upload_path_by_type('lead') . $leadid . '/';
        $CI             = &get_instance();

        if (
            isset($_FILES[$index_name]['name'])
            && ($_FILES[$index_name]['name'] != '' || is_array($_FILES[$index_name]['name']) && count($_FILES[$index_name]['name']) > 0)
        ) {
            if (!is_array($_FILES[$index_name]['name'])) {
                $_FILES[$index_name]['name']     = [$_FILES[$index_name]['name']];
                $_FILES[$index_name]['type']     = [$_FILES[$index_name]['type']];
                $_FILES[$index_name]['tmp_name'] = [$_FILES[$index_name]['tmp_name']];
                $_FILES[$index_name]['error']    = [$_FILES[$index_name]['error']];
                $_FILES[$index_name]['size']     = [$_FILES[$index_name]['size']];
            }

            _file_attachments_index_fix($index_name);
            for ($i = 0; $i < count($_FILES[$index_name]['name']); $i++) {
                $tmpFilePath = $_FILES[$index_name]['tmp_name'][$i];

                // Make sure we have a filepath
                if (!empty($tmpFilePath) && $tmpFilePath != '') {
                    if (
                        _perfex_upload_error($_FILES[$index_name]['error'][$i])
                        || !_upload_extension_allowed($_FILES[$index_name]['name'][$i])
                    ) {
                        continue;
                    }

                    _maybe_create_upload_path($path);
                    $filename    = unique_filename($path, $_FILES[$index_name]['name'][$i]);
                    $newFilePath = $path . $filename;

                    // Upload the file into the temp dir
                    if (move_uploaded_file($tmpFilePath, $newFilePath)) {
                        $CI = &get_instance();
                        $CI->load->model('leads_model');
                        $data   = [];
                        $data[] = [
                            'file_name' => $filename,
                            'filetype'  => $_FILES[$index_name]['type'][$i],
                        ];
                        $CI->leads_model->add_attachment_to_database($leadid, $data, false);
                    }
                }
            }
        }
        return true;
    }
}
