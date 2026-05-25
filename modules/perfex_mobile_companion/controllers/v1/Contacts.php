<?php

defined('BASEPATH') or exit('No direct script access allowed');

require __DIR__ . '/../REST_Controller.php';

class Contacts extends REST_Controller
{
    function __construct()
    {
        // Construct the parent class
        parent::__construct();
        $this->load->model('authentication_model');
    }

    public function data_get($customer_id = '', $contact_id = '')
    {
        // If the id parameter doesn't exist return all the
        if (empty($contact_id) && !empty($customer_id)) {
            $data = $this->Api_model->get_table('all_contacts', $customer_id);
        }
        if (!empty($contact_id) && !empty($customer_id)) {
            $data = $this->Api_model->get_table('contacts', $contact_id);
        }
        if (empty($contact_id) && empty($customer_id)) {
            $this->response([
                'status' => FALSE,
                'message' => 'No data found'
            ], REST_Controller::HTTP_OK); // NOT_FOUND (404) being the HTTP response code
        }

        if ($data) {
            $data = $this->Api_model->get_api_custom_data($data, "contacts", $contact_id);

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
        $data = $this->Api_model->search('contacts', $key);

        if ($data) {
            foreach ($data as $key => $value) {
                $data[$key]['profile_url'] = contact_profile_image_url($value['id']);
                $data[$key]['permissions'] = $this->roles_model->get_contact_permissions($value['id']);
            }

            $data = $this->Api_model->get_api_custom_data($data, "contacts");

            $this->response($data, REST_Controller::HTTP_OK); // OK (200) being the HTTP response code
        } else {
            $this->response([
                'status' => FALSE,
                'message' => 'No data found'
            ], REST_Controller::HTTP_OK); // NOT_FOUND (404) being the HTTP response code
        }
    }

    public function change_contact_status_get($id, $status)
    {
        if (empty($id) && !is_numeric($id)) {
            $message = array(
                'status' => FALSE,
                'message' => 'Invalid Contact ID'
            );
            $this->response($message, REST_Controller::HTTP_OK);
        }

        if (has_permission('customers', '', 'edit') || is_customer_admin(get_user_id_by_contact_id($id))) {
            $success = $this->clients_model->change_contact_status($id, $status);
            if ($success) {
                $message = array(
                    'status' => TRUE,
                    'message' => 'Contact Status Changed',
                );
                $this->response($message, REST_Controller::HTTP_OK);
            }
        }

        // error
        $message = array(
            'status' => FALSE,
            'message' => 'Something Went Wrong While Changing Status'
        );
        $this->response($message, REST_Controller::HTTP_OK);
    }

    public function data_post()
    {
        $data = $this->input->post();
        $send_set_password_email = isset($data['send_set_password_email']) ? true : false;
        if ($send_set_password_email) {
            unset($data['password']);
        }

        $this->form_validation->set_rules('firstname', 'First Name', 'trim|required|max_length[255]');
        $this->form_validation->set_rules('lastname', 'Last Name', 'trim|required|max_length[255]');
        $this->form_validation->set_rules('email', 'Email', 'trim|required|max_length[255]|is_unique[' . db_prefix() . 'contacts.email]', array('is_unique' => 'This %s is already exists'));
        if (!$send_set_password_email) {
            $this->form_validation->set_rules('password', 'Password', 'trim|required|max_length[255]');
        }
        $this->form_validation->set_rules('customer_id', 'Customer Id', 'trim|required|numeric|callback_client_id_check');
        if ($this->form_validation->run() == FALSE) {
            $message = array(
                'status' => FALSE,
                'error' => $this->form_validation->error_array(),
                'message' => validation_errors()
            );
            $this->response($message, REST_Controller::HTTP_OK);
        } else {
            $customer_id = $data['customer_id'];
            unset($data['customer_id']);
            $id      = $this->clients_model->add_contact($data, $customer_id);
            if ($id > 0 && !empty($id)) {
                // success
                $message = array(
                    'status' => TRUE,
                    'message' => 'Contact added successfully.'
                );
                $this->response($message, REST_Controller::HTTP_OK);
            } else {
                // error
                $message = array(
                    'status' => FALSE,
                    'message' => 'Contact add fail.'
                );
                $this->response($message, REST_Controller::HTTP_OK);
            }
        }
    }

    public function profile_image_post($contact_id)
    {
        $success = handle_contact_profile_image_upload($contact_id);;
        if ($success) {
            // success
            $message = array(
                'status' => TRUE,
                'message' => 'Contact profile image added successfully.'
            );
            $this->response($message, REST_Controller::HTTP_OK);
        } else {
            // error
            $message = array(
                'status' => FALSE,
                'message' => 'Contact profile image add fail.'
            );
            $this->response($message, REST_Controller::HTTP_OK);
        }
    }

    public function profile_image_delete($contact_id = '')
    {
        $id = $this->security->xss_clean($contact_id);
        if (empty($id) && !is_numeric($id)) {
            $message = array(
                'status' => FALSE,
                'message' => 'Invalid Contact ID'
            );
            $this->response($message, REST_Controller::HTTP_OK);
        } else {
            $is_exist = $this->clients_model->get_contact($id);
            if (is_object($is_exist)) {
                $this->clients_model->delete_contact_profile_image($id);
                $message = array(
                    'status' => TRUE,
                    'message' => 'Profile Image Deleted Successfuly.'
                );
                $this->response($message, REST_Controller::HTTP_OK);
            } else {
                $message = array(
                    'status' => FALSE,
                    'message' => 'Invalid Contact ID'
                );
                $this->response($message, REST_Controller::HTTP_OK);
            }
        }
    }

    public function data_delete($customer_id = '')
    {
        $id = $this->security->xss_clean($customer_id);
        if (empty($id) && !is_numeric($id)) {
            $message = array(
                'status' => FALSE,
                'message' => 'Invalid Contact ID'
            );
            $this->response($message, REST_Controller::HTTP_OK);
        } else {
            $is_exist = $this->clients_model->get_contact($id);
            if (is_object($is_exist)) {
                $output = $this->clients_model->delete_contact($id);
                if ($output === TRUE) {
                    // success
                    $message = array(
                        'status' => TRUE,
                        'message' => 'Contact Deleted Successfuly.'
                    );
                    $this->response($message, REST_Controller::HTTP_OK);
                } else {
                    // error
                    $message = array(
                        'status' => FALSE,
                        'message' => 'Contact Delete Fail.'
                    );
                    $this->response($message, REST_Controller::HTTP_OK);
                }
            } else {
                $message = array(
                    'status' => FALSE,
                    'message' => 'Invalid Contact ID'
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
                'message' => 'Invalid Client ID'
            );
            $this->response($message, REST_Controller::HTTP_OK);
        } else {
            $this->load->model('authentication_model');
            $data = $this->input->post();
            $is_exist = $this->clients_model->get_contact($id);
            if (!is_object($is_exist)) {
                $message = array(
                    'status' => FALSE,
                    'message' => 'Contact ID Doesn\'t Not Exist.'
                );
                $this->response($message, REST_Controller::HTTP_OK);
            }
            $_current_email = $this->db->where('id', $id)->get(db_prefix() . 'contacts')->row();
            if ($_current_email->email == $this->input->post('email')) {
                $this->form_validation->set_rules('email', 'Email', 'trim|required|max_length[255]');
            } else {
                $this->form_validation->set_rules('email', 'Email', 'trim|required|max_length[255]|is_unique[' . db_prefix() . 'contacts.email]', array('is_unique' => 'This %s is already exists'));
            }
            if ($this->form_validation->run() == FALSE) {
                $message = array(
                    'status' => FALSE,
                    'error' => $this->form_validation->error_array(),
                    'message' => validation_errors()
                );
                $this->response($message, REST_Controller::HTTP_OK);
            }

            if (empty($data['send_set_password_email'])) {
                unset($data['send_set_password_email']);
            }

            $success = $this->clients_model->update_contact($data, $id);
            $updated = false;
            if (is_array($success)) {
                if (isset($success['set_password_email_sent'])) {
                    $message_str = _l('set_password_email_sent_to_client');
                } elseif (isset($success['set_password_email_sent_and_profile_updated'])) {
                    $updated = true;
                    $message_str = _l('set_password_email_sent_to_client_and_profile_updated');
                }
            } else {
                if ($success == true) {
                    $updated = true;
                    $message_str = "Contact Updated Successfully";
                }
            }

            if ($updated == true) {
                $message = array(
                    'status' => TRUE,
                    'message' => $message_str
                );
                $this->response($message, REST_Controller::HTTP_OK);
            } else {
                // error
                $message = array(
                    'status' => FALSE,
                    'message' => 'Client Update Fail.'
                );
                $this->response($message, REST_Controller::HTTP_OK);
            }
        }
    }

    public function client_id_check($customer_id)
    {
        $this->form_validation->set_message('client_id_check', 'The {field} is Invalid');
        if (empty($customer_id)) {
            return FALSE;
        }
        $query = $this->db->get_where(db_prefix() . 'clients', array('userid' => $customer_id));
        return $query->num_rows() > 0;
    }
}
