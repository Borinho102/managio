<?php

defined('BASEPATH') || exit('No direct script access allowed');

require_once __DIR__.'/RestController.php';
require_once __DIR__.'/../vendor/autoload.php';
use flutexAdminApi\RestController;

class Profile extends RestController
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
    }
     
    public function profile_get()
    {
        // Staff Information
        $staffID = $this->staffInfo['data']->staff_id;
        $staff = $this->db->where('staffid', $staffID)->get(db_prefix() . 'staff')->row();
        $staff_data = array(
            'id'=> $staff->staffid,
            'email' => $staff->email,
            'firstname' => $staff->firstname,
            'lastname' => $staff->lastname,
            'facebook' => $staff->facebook,
            'linkedin' => $staff->linkedin,
            'phonenumber' => $staff->phonenumber,
            'skype' => $staff->skype,
            'datecreated' => $staff->datecreated,
            'admin' => $staff->admin,
            'role' => $staff->role,
            'active' => $staff->active,
            'default_language' => $staff->default_language,
            'direction' => $staff->direction,
            'media_path_slug' => $staff->media_path_slug,
            'is_not_staff' => $staff->is_not_staff,
            'hourly_rate' => $staff->hourly_rate,
            'profile_image' => staff_profile_image_url($staff->staffid),
        );
        $this->response(['message' => _l('data_retrieved_successfully'),'data' => $staff_data], RestController::HTTP_OK);
    }
    
    public function profile_post()
    {
        $staffId = $this->staffInfo['data']->staff_id;
        if (staff_cant('edit', 'staff', $staffId)) {
            $this->response(['message' => _l('not_permission_to_perform_this_action')], RestController::HTTP_FORBIDDEN);
        }
        
        try {
            
            if (!empty($this->get()) && !in_array('id', array_keys($this->get()))) {
                $this->response(['message' => _l('something_went_wrong')], RestController::HTTP_BAD_REQUEST);
            }
            
            $this->load->model('staff_model');
            $staff = $this->staff_model->get($this->staffInfo['data']->staff_id);
            
            if (is_object($staff)) {
                handle_staff_profile_image_upload($staffId);
                $data = $this->input->post();
                $success = $this->staff_model->update_profile($data, $staffId);
                if ($success) {
                    $this->response(['message' => _l('profile_updated_successfully')], RestController::HTTP_OK);
                } else {
                    $this->response(['message' => _l('profile_update_failed')], RestController::HTTP_NOT_FOUND);
                }
            } else {
                $this->response(['message' => _l('invalid_staff_id')], RestController::HTTP_NOT_FOUND);
            }
            
        } catch (\Throwable $th) {
            $this->response(['message' => _l('something_went_wrong')], RestController::HTTP_INTERNAL_ERROR);
        }
    }
     
    public function firebase_token_post()
    {
        try {
            $this->form_validation->set_rules('fcm_token', 'FCM Token', 'required');
            
            if (!$this->form_validation->run()) {
                $this->response(['message' => strip_tags(validation_errors()),'error' => $this->form_validation->error_array()], RestController::HTTP_BAD_REQUEST);
            } else {
                $fcm_token = $this->input->post('fcm_token');
                $this->db->update(db_prefix() . 'staff', ['fcm_token' => $fcm_token], ['staffid' => $this->staffInfo['data']->staff_id]);
                $this->response(['message' => _l('fcm_token_set_successfully')], RestController::HTTP_OK);
            }
        } catch (\Throwable $th) {
            $this->response(['message' => _l('something_went_wrong')], RestController::HTTP_INTERNAL_ERROR);
        }
    }
     
    public function logout_get()
    {
        $this->db->update(db_prefix() . 'staff', ['flutex_api_key' => NULL], ['staffid' => $this->staffInfo['data']->staff_id]);
        $this->response(['message' => _l('logged_out_successfully')], RestController::HTTP_OK);
    }
}