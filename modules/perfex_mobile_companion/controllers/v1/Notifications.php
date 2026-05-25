<?php defined('BASEPATH') or exit('No direct script access allowed');

require __DIR__ . '/../REST_Controller.php';

class Notifications extends REST_Controller
{
    function __construct()
    {
        parent::__construct();
        $this->load->model('Misc_model');
    }

    public function data_get($id = '')
    {
        // If the id parameter doesn't exist return all the
        $notifications = $this->_search_notifications($id);

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
                    $x               = 0;
                    foreach ($additional_data as $data) {
                        if (strpos($data, '<lang>') !== false) {
                            $lang = get_string_between($data, '<lang>', '</lang>');
                            $temp = _l($lang);
                            if (strpos($temp, 'project_status_') !== false) {
                                $status = get_project_status_by_id(strafter($temp, 'project_status_'));
                                $temp   = $status['name'];
                            }
                            $additional_data[$x] = $temp;
                        }
                        $x++;
                    }
                }
                $notifications[$_key]['formatted_description'] = _l($notification['description'], $additional_data);
                $notifications[$_key]['date']        = time_ago($notification['date']);
                $notifications[$_key]['full_date']   = $notification['date'];
            }

            $this->response($notifications, REST_Controller::HTTP_OK); // OK (200) being the HTTP response code
        } else {
            $this->response([
                'status' => FALSE,
                'message' => 'No data found'
            ], REST_Controller::HTTP_OK); // NOT_FOUND (404) being the HTTP response code
        }
    }

    public function data_mark_all_as_read_get()
    {
        $this->misc_model->mark_all_notifications_as_read_inline();
        $message = array(
            'status' => TRUE,
            'message' => 'Mark all as read successfuly.'
        );
        $this->response($message, REST_Controller::HTTP_OK);
    }

    public function data_mark_as_read_unread_get($id, $isread)
    {
        $this->db->where('id', $id);
        $this->db->update(db_prefix() . 'notifications', [
            'isread_inline' => $isread,
            'isread'        => $isread,
        ]);

        $message = array(
            'status' => TRUE
        );
        $this->response($message, REST_Controller::HTTP_OK);
    }

    public function data_post()
    {
        $this->form_validation->set_rules('token', 'Token', 'trim|required');
        $this->form_validation->set_rules('device_id', 'Device ID', 'trim|required');
        $this->form_validation->set_rules('user_id', 'User Id', 'trim|required');
        if ($this->form_validation->run() == FALSE) {
            // form validation error
            $message = array(
                'status' => FALSE,
                'error' => $this->form_validation->error_array(),
                'message' => validation_errors()
            );
            $this->response($message, REST_Controller::HTTP_OK);
        } else {
            $device = $this->db
                ->where('user_id', $this->input->post('user_id'))
                ->where('device_id', $this->input->post('device_id'))
                ->get(db_prefix() . 'push_notification_devices')->row();

            if (empty($device)) {
                $insert_data = [
                    'user_id' => $this->input->post('user_id', TRUE),
                    'token' => $this->input->post('token', TRUE),
                    'device_id' => $this->input->post('device_id', TRUE),
                    'additional_data' => $this->input->post('additional_data', TRUE),
                    'created_at' => date('Y-m-d H:i:s'),
                    'updated_at' => date('Y-m-d H:i:s')
                ];

                // insert data                    
                $this->db->insert(db_prefix() . 'push_notification_devices', $insert_data);
                $output = $this->db->insert_id();
                if ($output > 0 && !empty($output)) {
                    // success
                    $message = array(
                        'status' => TRUE,
                        'message' => 'Device added successfuly.',
                        'insert_id' => $output
                    );
                    $this->response($message, REST_Controller::HTTP_OK);
                } else {
                    // error
                    $message = array(
                        'status' => FALSE,
                        'message' => 'Device add failed.'
                    );
                    $this->response($message, REST_Controller::HTTP_OK);
                }
            } else {
                $insert_data = [
                    'user_id' => $this->input->post('user_id', TRUE),
                    'token' => $this->input->post('token', TRUE),
                    'additional_data' => $this->input->post('additional_data', TRUE),
                    'created_at' => date('Y-m-d H:i:s'),
                    'updated_at' => date('Y-m-d H:i:s')
                ];

                // update data                    
                $this->db
                    ->where('user_id', $this->input->post('user_id'))
                    ->where('device_id', $this->input->post('device_id'))
                    ->update(db_prefix() . 'push_notification_devices', [
                        'token' => $this->input->post('token', TRUE),
                        'additional_data' => $this->input->post('additional_data', TRUE),
                        'updated_at' => date('Y-m-d H:i:s')
                    ]);
                $message = array(
                    'status' => TRUE,
                    'message' => 'Device Updated Successfully.'
                );
                $this->response($message, REST_Controller::HTTP_OK);
            }
        }
    }

    private function _search_notifications($q)
    {
        $result = [
            'result'         => [],
            'type'           => 'notifications',
            'search_heading' => _l('notifications'),
        ];

        if (has_permission('notifications', '', 'view')) {
            $staff_id = get_staff_user_id();
            $this->db->where('touserid', $staff_id);

            if (is_numeric($this->input->get('limit')) && is_numeric($this->input->get('offset'))) {
                $this->db->limit($this->input->get('limit'));
                $this->db->offset($this->input->get('offset'));
            }


            $this->db->order_by('id', 'DESC');
            $result['result'] = $this->db->get(db_prefix() . 'notifications')->result_array();
        }

        return $result['result'];
    }
}
