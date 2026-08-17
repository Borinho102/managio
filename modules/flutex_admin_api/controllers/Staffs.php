<?php

defined('BASEPATH') || exit('No direct script access allowed');

require_once __DIR__.'/RestController.php';

use FlutexAdminApi\RestController;

class Staffs extends RestController
{
    protected $staffInfo;

    public function __construct()
    {
        parent::__construct();
        register_language_files('flutex_admin_api');
        load_admin_language();
        
        $this->load->helper('flutex_admin_api');
        if (!isset(isAuthorized()['status'])) {
            $this->response(isAuthorized()['response'], isAuthorized()['response_code']);
        }

        $this->staffInfo = isAuthorized();

        if (staff_cant('view', 'staff', $this->staffInfo['data']->staff_id)) {
            $this->response(['message' => _l('not_permission_to_perform_this_action')], RestController::HTTP_FORBIDDEN);
        }
    }
    
    public function staffs_get()
    {
        try {
            
            if (!empty($this->get()) && !in_array('id', array_keys($this->get()))) {
                $this->response(['message' => _l('something_went_wrong')], RestController::HTTP_BAD_REQUEST);
            }
            
            $staffID = $this->get('id');
            
            $this->load->model('Staff_model');
            
            $data = $this->Staff_model->get($staffID);
            
            $staffData = [];
            
            if(!empty($data) && !empty($staffID)){
                $staffData = array(
                    'staffid' => $data->staffid,
                    'email' => $data->email,
                    'firstname' => $data->firstname,
                    'lastname' => $data->lastname,
                    'facebook' => $data->facebook,
                    'linkedin' => $data->linkedin,
                    'skype' => $data->skype,
                    'phonenumber' => $data->phonenumber,
                    'date_created' => $data->date_created ?? '',
                    'last_ip' => $data->last_ip ?? '',
                    'last_login' => $data->last_login ?? '',
                    'lastActivity' => $data->last_activity ?? '',
                    'last_password_change' => $data->last_password_change ?? '',
                    'active' => $data->active,
                    'admin' => $data->admin,
                    'role' => $data->role,
                    'default_language' => $data->default_language,
                    'direction' => $data->direction,
                    'media_path_slug' => $data->media_path_slug,
                    'is_not_staff' => $data->is_not_staff,
                    'hourly_rate' => $data->hourly_rate ?? '',
                    'email_signature' => $data->email_signature ?? '',
                    'full_name' => $data->full_name ?? '',
                    'profile_image' => base_url('uploads/staff_profile_images/' . $data->staffid . '/small_' . $data->profile_image),
                    'permissions' => $data->permissions
                );
                $this->response(['message' => _l('data_retrieved_successfully'), 'data' => $staffData], RestController::HTTP_OK);
            }
            
            foreach ($data as $staff) {
                $staffData[] = array(
                    'id' => $staff['staffid'],
                    'email' => $staff['email'],
                    'firstname' => $staff['firstname'],
                    'lastname' => $staff['lastname'],
                    'facebook' => $staff['facebook'],
                    'linkedin' => $staff['linkedin'],
                    'skype' => $staff['skype'],
                    'phonenumber' => $staff['phonenumber'],
                    'date_created' => $staff['date_created'] ?? '',
                    'last_ip' => $staff['last_ip'] ?? '',
                    'last_login' => $staff['last_login'] ?? '',
                    'lastActivity' => $staff['last_activity'] ?? '',
                    'last_password_change' => $staff['last_password_change'] ?? '',
                    'active' => $staff['active'],
                    'admin' => $staff['admin'],
                    'role' => $staff['role'],
                    'default_language' => $staff['default_language'],
                    'direction' => $staff['direction'],
                    'media_path_slug' => $staff['media_path_slug'],
                    'is_not_staff' => $staff['is_not_staff'],
                    'hourly_rate' => $staff['hourly_rate'] ?? '',
                    'email_signature' => $staff['email_signature'] ?? '',
                    'full_name' => $staff['full_name'],
                    'profile_image' => base_url('uploads/staff_profile_images/' . $staff['staffid'] . '/small_' . $staff['profile_image'])
                );
            }
            
            if (!empty($staffData)) {
                $this->response(['message' => _l('data_retrieved_successfully'), 'data' => $staffData], RestController::HTTP_OK);
            } else {
                $this->response(['message' => _l('data_not_found')], RestController::HTTP_NOT_FOUND);
            }
            
        } catch (\Throwable $th) {
            $this->response(['message' => _l('something_went_wrong')], RestController::HTTP_INTERNAL_ERROR);
        }
        
    }
    
    public function search_get()
    {
        try {
            
            if (!empty($this->get()) && !in_array('search', array_keys($this->get()))) {
                $this->response(['message' => _l('something_went_wrong')], RestController::HTTP_BAD_REQUEST);
            }
            
            $keySearch = $this->get('search');
            
            $where = '';

            if ($keySearch) {
                $keySearch = trim(urldecode($keySearch));
                $where .= '(CONCAT(firstname, " ", lastname) LIKE "%' . $keySearch . '%" OR email LIKE "%' . $keySearch . '%")';
            }
            
            $this->load->model('Staff_model');
            
            $data = $this->Staff_model->get('', $where);
            $staffData = [];
            
            foreach ($data as $staff) {
                $staffData[] = array(
                    'id' => $staff['staffid'],
                    'email' => $staff['email'],
                    'firstname' => $staff['firstname'],
                    'lastname' => $staff['lastname'],
                    'facebook' => $staff['facebook'],
                    'linkedin' => $staff['linkedin'],
                    'skype' => $staff['skype'],
                    'phonenumber' => $staff['phonenumber'],
                    'date_created' => $staff['date_created'] ?? '',
                    'last_ip' => $staff['last_ip'] ?? '',
                    'last_login' => $staff['last_login'] ?? '',
                    'lastActivity' => $staff['last_activity'] ?? '',
                    'last_password_change' => $staff['last_password_change'] ?? '',
                    'active' => $staff['active'],
                    'admin' => $staff['admin'],
                    'role' => $staff['role'],
                    'default_language' => $staff['default_language'],
                    'direction' => $staff['direction'],
                    'media_path_slug' => $staff['media_path_slug'],
                    'is_not_staff' => $staff['is_not_staff'],
                    'hourly_rate' => $staff['hourly_rate'] ?? '',
                    'email_signature' => $staff['email_signature'] ?? '',
                    'full_name' => $staff['full_name'],
                    'profile_image' => base_url('uploads/staff_profile_images/' . $staff['staffid'] . '/small_' . $staff['profile_image'])
                );
            }
            
            if (!empty($staffData)) {
                $this->response(['message' => _l('data_retrieved_successfully'), 'data' => $staffData], RestController::HTTP_OK);
            } else {
                $this->response(['message' => _l('data_not_found')], RestController::HTTP_NOT_FOUND);
            }
            
        } catch (\Throwable $th) {
            $this->response(['message' => _l('something_went_wrong')], RestController::HTTP_INTERNAL_ERROR);
        }
    }
    
    public function staffs_post()
    {
        if (staff_cant('create', 'staff', $this->staffInfo['data']->staff_id)) {
            $this->response(['message' => _l('not_permission_to_perform_this_action')], RestController::HTTP_FORBIDDEN);
        }
        
        try {
            $this->form_validation->set_rules('firstname', 'First Name', 'required|max_length[600]');
            $this->form_validation->set_rules('lastname', 'Last Name', 'required|max_length[600]');
            $this->form_validation->set_rules('email', 'Email', 'trim|required|valid_email');
            $this->form_validation->set_rules('password', 'Password', 'required');
            if (!$this->form_validation->run()) {
                $this->response(['message' => strip_tags(validation_errors()),'error' => $this->form_validation->error_array()], RestController::HTTP_BAD_REQUEST);
            } else {
                $data = $this->input->post();
                $this->load->model('staff_model');
                $success = $this->staff_model->add($data);
                if ($success) {
                    handle_staff_profile_image_upload($success);
                    $this->response(['message' => _l('staff_added_successfully')], RestController::HTTP_OK);
                } else {
                    $this->response(['message' => _l('staff_add_failed')], RestController::HTTP_NOT_FOUND);
                }
            }
            
        } catch (\Throwable $th) {
            $this->response(['message' => _l('something_went_wrong').$th], RestController::HTTP_INTERNAL_ERROR);
        }
    }
    
    public function staffs_put()
    {
        if (staff_cant('edit', 'staff', $this->staffInfo['data']->staff_id)) {
            $this->response(['message' => _l('not_permission_to_perform_this_action')], RestController::HTTP_FORBIDDEN);
        }
        
        try {
            
            if (!empty($this->get()) && !in_array('id', array_keys($this->get()))) {
                $this->response(['message' => _l('something_went_wrong')], RestController::HTTP_BAD_REQUEST);
            }
            
            $staffID = $this->get('id');
            $this->load->model('staffs_model');
            $staff = $this->staffs_model->get($staffID);
            
            if (is_object($staff)) {
                $data = array();
                parse_str(file_get_contents('php://input'), $data);
                $success = $this->clients_model->update($data, $staffID);
                if ($success) {
                    $this->response(['message' => _l('staff_updated_successfully')], RestController::HTTP_OK);
                } else {
                    $this->response(['message' => _l('staff_update_failed')], RestController::HTTP_NOT_FOUND);
                }
            } else {
                $this->response(['message' => _l('invalid_staff_id')], RestController::HTTP_NOT_FOUND);
            }
            
        } catch (\Throwable $th) {
            $this->response(['message' => _l('something_went_wrong')], RestController::HTTP_INTERNAL_ERROR);
        }
    }
    
    public function staffs_delete()
    {
        $staffID = $this->get('id');
        $transferDataTo = $this->get('transfer_data_to');
        
        if (staff_cant('delete', 'staff', $this->staffInfo['data']->staff_id)) {
            $this->response(['message' => _l('not_permission_to_perform_this_action')], RestController::HTTP_FORBIDDEN);
        }
        
        $this->load->model('staff_model');
        $staff = $this->staff_model->get($staffID);
        if (is_object($staff)) {
            $success = $this->staff_model->delete($staffID,$transferDataTo);
            if ($success) {
                $this->response(['message' => _l('staff_deleted_successfully')], RestController::HTTP_OK);
            } else {
                $this->response(['message' => _l('staff_delete_failed')], RestController::HTTP_NOT_FOUND);
            }
        } else {
            $this->response(['message' => _l('invalid_staff_id')], RestController::HTTP_NOT_FOUND);
        }
    }
}