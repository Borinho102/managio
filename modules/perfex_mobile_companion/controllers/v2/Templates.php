<?php

defined('BASEPATH') or exit('No direct script access allowed');

require __DIR__ . '/../REST_Controller.php';

class Templates extends REST_Controller
{

    function __construct()
    {
        // Construct the parent class
        parent::__construct();
        $this->load->model('templates_model');
    }

    public function data_get($id = '')
    {
    }

    public function data_search_get($key = '')
    {
        if ($this->input->get('search')) {
            $key = $this->input->get('search');
        }

        $type = '';
        if (!empty($this->input->get('filters'))) {
            $filters = json_decode($this->input->get('filters'), true);

            if (!empty($filters['rel_type'])) {
                $type = $filters['rel_type'];
            }
        }
        if (is_numeric($this->input->get('limit')) && is_numeric($this->input->get('offset'))) {
            $this->db->limit($this->input->get('limit'));
            $this->db->offset($this->input->get('offset'));
        }

        $data = $this->templates_model->getByType($type);

        if ($data) {

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
        $this->form_validation->set_rules('name', 'Template Name', 'trim|required|max_length[600]');
        if ($this->form_validation->run() == FALSE) {
            // form validation error
            $message = array(
                'status' => FALSE,
                'error' => $this->form_validation->error_array(),
                'message' => validation_errors()
            );
            $this->response($message, REST_Controller::HTTP_OK);
        } else {
            $content = $this->input->post('content', false);
            $content = html_purify($content);

            $data['name']      = $this->input->post('name');
            $data['content']   = $content;
            $data['addedfrom'] = get_staff_user_id();
            $data['type']      = $this->input->post('rel_type');

            $this->load->model('templates_model');
            $output = $this->templates_model->create($data);
            if ($output > 0 && !empty($output)) {
                // success
                $message = array(
                    'status' => TRUE,
                    'message' => _l('template_added'),
                    'insert_id' => $output
                );
                $this->response($message, REST_Controller::HTTP_OK);
            } else {
                // error
                $message = array(
                    'status' => FALSE,
                    'message' => 'Template add fail.'
                );
                $this->response($message, REST_Controller::HTTP_OK);
            }
        }
    }

    public function data_put($id)
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
                'message' => 'Invalid Template ID'
            );
            $this->response($message, REST_Controller::HTTP_OK);
        } else {
            $this->authorize($id);

            $content = $this->input->post('content', false);
            $content = html_purify($content);

            $data['name']      = $this->input->post('name');
            $data['content']   = $content;
            $data['addedfrom'] = get_staff_user_id();
            $data['type']      = $this->input->post('rel_type');

            $this->load->model('templates_model');
            $output = $this->templates_model->update($id, $data);

            if ($output > 0 && !empty($output)) {
                // success
                $message = array(
                    'status' => TRUE,
                    'message' => _l('template_updated')
                );
                $this->response($message, REST_Controller::HTTP_OK);
            } else {
                // error
                $message = array(
                    'status' => FALSE,
                    'message' => 'Template Update Fail.'
                );
                $this->response($message, REST_Controller::HTTP_OK);
            }
        }
    }

    public function data_delete($id)
    {
        $id = $this->security->xss_clean($id);
        if (empty($id) && !is_numeric($id)) {
            $message = array(
                'status' => FALSE,
                'message' => 'Invalid Template ID'
            );
            $this->response($message, REST_Controller::HTTP_OK);
        } else {
            $this->authorize($id);
            // delete data
            $this->load->model('templates_model');
            $output = $this->templates_model->delete($id);
            if ($output === TRUE) {
                // success
                $message = array(
                    'status' => TRUE,
                    'message' => 'Template Delete Successful.'
                );
                $this->response($message, REST_Controller::HTTP_OK);
            } else {
                // error
                $message = array(
                    'status' => FALSE,
                    'message' => 'Template Delete Fail.'
                );
                $this->response($message, REST_Controller::HTTP_OK);
            }
        }
    }

    /**
     * Authorize the template for update/delete
     *
     * @param  int $id
     *
     * @return void
     */
    protected function authorize($id)
    {
        $template = $this->templates_model->find($id);

        if ($template->addedfrom != get_staff_user_id() && !is_admin()) {
            $message = array(
                'status' => FALSE,
                'message' => _l('access_denied')
            );
            $this->response($message, REST_Controller::HTTP_OK);
        }
    }
}
