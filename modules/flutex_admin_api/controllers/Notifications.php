<?php

defined('BASEPATH') || exit('No direct script access allowed');

require_once __DIR__.'/RestController.php';

use FlutexAdminApi\RestController;

class Notifications extends RestController
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
    
    public function notifications_get()
    {
        $staff_id = $this->staffInfo['data']->staff_id;
        $this->db->where('touserid', $staff_id);
        $this->db->limit('25');
        //$this->db->offset('0');
        $this->db->order_by('id', 'DESC');
        $notifications = $this->db->get(db_prefix() . 'notifications')->result_array();
        if ($notifications) {
            foreach ($notifications as $_key => $notification) {
                if (($notification['fromcompany'] == null && $notification['fromuserid'] != 0) || ($notification['fromcompany'] == null && $notification['fromclientid'] != 0)) {
                    if ($notification['fromuserid'] != 0) {
                        $notifications[$_key]['profile_image'] = staff_profile_image_url($notification['fromuserid']);
                    } else {
                        $notifications[$_key]['profile_image'] = contact_profile_image_url($notification['fromclientid']);
                    }
                } else {
                    $notifications[$_key]['profile_image'] = '';
                    $notifications[$_key]['full_name']     = '';
                }
                $additional_data = '';
                if (!empty($notification['additional_data'])) {
                    $additional_data = unserialize($notification['additional_data']);
                    $i               = 0;
                    foreach ($additional_data as $data) {
                        if (strpos($data, '<lang>') !== false) {
                            $lang = get_string_between($data, '<lang>', '</lang>');
                            $name = _l($lang);
                            if (strpos($name, 'project_status_') !== false) {
                                $status = get_project_status_by_id(strafter($name, 'project_status_'));
                                $name   = $status['name'];
                            }
                            $additional_data[$i] = $name;
                        }
                        $i++;
                    }
                }
                $notifications[$_key]['formatted_description'] = _l($notification['description'], $additional_data);
                $notifications[$_key]['date']        = time_ago($notification['date']);
                $notifications[$_key]['full_date']   = $notification['date'];
            }
            
            $this->response(['message' => _l('data_retrieved_successfully'), 'data' => $notifications], RestController::HTTP_OK);
        } else {
            $this->response(['message' => _l('data_not_found')], RestController::HTTP_NOT_FOUND);
        }
    }
}