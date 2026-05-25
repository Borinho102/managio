<?php

defined('BASEPATH') or exit('No direct script access allowed');

require __DIR__ . '/../REST_Controller.php';

class Tickets extends REST_Controller
{

    function __construct()
    {
        parent::__construct();
        $this->load->model('tickets_model');
    }

    public function data_get($id = '')
    {
        $data = $this->Api_model->get_table('tickets', $id);

        if ($data) {
            if ($id != '') {
                $data->lastreply_time_ago = time_ago($data->lastreply ?? '');

                if ($data->assigned != 0) {
                    $data->assigned_name = get_staff_full_name($data->assigned);
                }

                $data->profile_image = null;
                if ($data->contactid != 0) {
                    $data->profile_image = contact_profile_image_url($data->contactid);
                }

                foreach ($data->attachments as $_index => $attachment) {

                    $path = get_upload_path_by_type('ticket') . $id . '/' . $attachment['file_name'];
                    $data->attachments[$_index]['is_image'] = is_image($path);

                    if ($data->attachments[$_index]['is_image']) {
                        $data->attachments[$_index]['src'] = site_url('download/preview_image?path=' . protected_file_url_by_path($path) . '&type=' . $attachment['filetype']);
                    }
                    $data->attachments[$_index]['mime_class'] = get_mime_class($attachment['filetype']);
                }

                $ticket_replies_order = get_option('ticket_replies_order');
                // backward compatibility for the action hook
                $ticket_replies_order = hooks()->apply_filters('ticket_replies_order', $ticket_replies_order);

                $data->ticket_replies = [
                    'order_by' => $ticket_replies_order,
                    'data' => $this->get_ticket_replies($id, $data)
                ];
            }

            $data = $this->Api_model->get_api_custom_data($data, "tickets", $id);

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

        $data = $this->Api_model->search('ticket', $key);

        if ($data) {

            $ticket_replies_order = get_option('ticket_replies_order');
            // backward compatibility for the action hook
            $ticket_replies_order = hooks()->apply_filters('ticket_replies_order', $ticket_replies_order);

            foreach ($data as $key => $value) {
                $data[$key]['lastreply_time_ago'] = time_ago($value['lastreply'] ?? '');

                if ($value['assigned'] != 0) {
                    $data[$key]['assigned_name'] = get_staff_full_name($value['assigned']);
                }

                $data[$key]['profile_image'] = null;
                if ($data[$key]['contactid'] != 0) {
                    $data[$key]['profile_image'] = contact_profile_image_url($value['contactid']);
                }

                $data[$key]['ticket_replies'] = [
                    'order_by' => $ticket_replies_order,
                    'data' => $this->get_ticket_replies($value['ticketid'], $value)
                ];
            }
            $data = $this->Api_model->get_api_custom_data($data, "tickets");

            $this->response($data, REST_Controller::HTTP_OK); // OK (200) being the HTTP response code
        } else {
            $this->response([
                'status' => FALSE,
                'message' => 'No data found'
            ], REST_Controller::HTTP_OK); // NOT_FOUND (404) being the HTTP response code
        }
    }

    public function data_departments_get()
    {

        $this->load->model('departments_model');
        $data = $this->departments_model->get();

        if ($data) {
            $this->response($data, REST_Controller::HTTP_OK); // OK (200) being the HTTP response code
        } else {
            $this->response([
                'status' => FALSE,
                'message' => 'No data found'
            ], REST_Controller::HTTP_OK); // NOT_FOUND (404) being the HTTP response code
        }
    }

    public function data_priorities_get()
    {

        $data = $this->tickets_model->get_priority();

        if ($data) {
            $this->response($data, REST_Controller::HTTP_OK); // OK (200) being the HTTP response code
        } else {
            $this->response([
                'status' => FALSE,
                'message' => 'No data found'
            ], REST_Controller::HTTP_OK); // NOT_FOUND (404) being the HTTP response code
        }
    }

    public function data_services_get()
    {

        $data = $this->tickets_model->get_service();

        if ($data) {
            $this->response($data, REST_Controller::HTTP_OK); // OK (200) being the HTTP response code
        } else {
            $this->response([
                'status' => FALSE,
                'message' => 'No data found'
            ], REST_Controller::HTTP_OK); // NOT_FOUND (404) being the HTTP response code
        }
    }

    public function data_statuses_get()
    {

        $data = $this->tickets_model->get_ticket_status();

        if ($data) {
            $this->response($data, REST_Controller::HTTP_OK); // OK (200) being the HTTP response code
        } else {
            $this->response([
                'status' => FALSE,
                'message' => 'No data found'
            ], REST_Controller::HTTP_OK); // NOT_FOUND (404) being the HTTP response code
        }
    }

    public function mark_action_status_get($status, $id)
    {

        if (empty($id) && !is_numeric($id)) {
            $message = array(
                'status' => FALSE,
                'message' => 'Invalid Ticket ID'
            );
            $this->response($message, REST_Controller::HTTP_OK);
        }

        $output = $this->tickets_model->change_ticket_status($id, $status);
        if ($output['alert'] == 'success') {
            $message = array(
                'status' => TRUE,
                'message' => $output['message'],
            );
            $this->response($message, REST_Controller::HTTP_OK);
        } else {
            // error
            $message = array(
                'status' => FALSE,
                'message' => $output['message']
            );
            $this->response($message, REST_Controller::HTTP_OK);
        }
    }

    public function data_post()
    {
        // form validation
        $this->form_validation->set_rules('subject', 'Ticket Name', 'trim|required', array('is_unique' => 'This %s already exists please enter another Ticket Name'));
        $this->form_validation->set_rules('department', 'Department', 'trim|required', array('is_unique' => 'This %s already exists please enter another Ticket Department'));
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
                'subject' => $this->input->post('subject', TRUE),
                'department' => $this->input->post('department', TRUE),
                'contactid' => $this->input->post('contactid', TRUE),
                'email' => $this->input->post('email', TRUE),
                'name' => $this->input->post('name', TRUE),
                'userid' => $this->input->post('userid', TRUE),

                'cc' => $this->Api_model->value($this->input->post('cc', TRUE)),
                'tags' => $this->Api_model->value($this->input->post('tags', TRUE)),
                'assigned' => $this->Api_model->value($this->input->post('assigned', TRUE)),
                'priority' => $this->Api_model->value($this->input->post('priority', TRUE)),
                'service' => $this->Api_model->value($this->input->post('service', TRUE)),
                'project_id' => $this->Api_model->value($this->input->post('project_id', TRUE)),
                'message' => $this->Api_model->value($this->input->post('message', TRUE))
            ];
            if (!empty($this->input->post('custom_fields', TRUE))) {
                $insert_data['custom_fields'] = $this->Api_model->value($this->input->post('custom_fields', TRUE));
            }

            // insert data
            $this->load->model('tickets_model');
            $output = $this->tickets_model->add($insert_data);
            if ($output > 0 && !empty($output)) {
                // success
                $message = array(
                    'status' => TRUE,
                    'message' => 'Ticket add successful.',
                    'insert_id' => $output
                );
                $this->response($message, REST_Controller::HTTP_OK);
            } else {
                // error
                $message = array(
                    'status' => FALSE,
                    'message' => 'Ticket add fail.'
                );
                $this->response($message, REST_Controller::HTTP_OK);
            }
        }
    }

    public function data_add_reply_post($id)
    {
        // form validation
        $this->form_validation->set_rules('message', 'Ticket Reply', 'trim|required', array('is_unique' => 'This %s already exists please enter another Ticket Reply'));
        if ($this->form_validation->run() == FALSE) {
            // form validation error
            $message = array(
                'status' => FALSE,
                'error' => $this->form_validation->error_array(),
                'message' => validation_errors()
            );
            $this->response($message, REST_Controller::HTTP_OK);
        } else {

            $returnToTicketList = false;
            $data               = $this->input->post();

            if (isset($data['ticket_add_response_and_back_to_list'])) {
                $returnToTicketList = true;
                unset($data['ticket_add_response_and_back_to_list']);
            }

            $data['message'] = html_purify($this->input->post('message', false));

            $this->load->model('tickets_model');
            $replyid         = $this->tickets_model->add_reply($data, $id, get_staff_user_id());
            if ($replyid > 0 && !empty($replyid)) {
                // success
                $message = array(
                    'status' => TRUE,
                    'message' => _l('replied_to_ticket_successfully', $id),
                    'insert_id' => $replyid
                );
                $this->response($message, REST_Controller::HTTP_OK);
            } else {
                // error
                $message = array(
                    'status' => FALSE,
                    'message' => 'Ticket reply add fail.'
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
                'message' => 'Invalid Ticket ID'
            );
            $this->response($message, REST_Controller::HTTP_OK);
        } else {
            // delete data
            $this->load->model('tickets_model');
            $output = $this->tickets_model->delete($id);
            if ($output === TRUE) {
                // success
                $message = array(
                    'status' => TRUE,
                    'message' => 'Ticket Delete Successful.'
                );
                $this->response($message, REST_Controller::HTTP_OK);
            } else {
                // error
                $message = array(
                    'status' => FALSE,
                    'message' => 'Ticket Delete Fail.'
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
                'message' => 'Invalid Ticket ID'
            );
            $this->response($message, REST_Controller::HTTP_OK);
        } else {

            $update_data = $this->input->post();
            // update data
            $this->load->model('tickets_model');
            $update_data['ticketid'] = $id;
            $output = $this->tickets_model->update_single_ticket_settings($update_data);
            if ($output > 0 && !empty($output)) {
                // success
                $message = array(
                    'status' => TRUE,
                    'message' => 'Ticket Update Successful.'
                );
                $this->response($message, REST_Controller::HTTP_OK);
            } else {
                // error
                $message = array(
                    'status' => FALSE,
                    'message' => 'Ticket Update Fail.'
                );
                $this->response($message, REST_Controller::HTTP_OK);
            }
        }
    }

    public function get_ticket_replies($id)
    {
        $ticket_replies_order = get_option('ticket_replies_order');
        // backward compatibility for the action hook
        $ticket_replies_order = hooks()->apply_filters('ticket_replies_order', $ticket_replies_order);

        $this->db->select(db_prefix() . 'ticket_replies.id,' . db_prefix() . 'ticket_replies.name as from_name,' . db_prefix() . 'ticket_replies.email as reply_email, ' . db_prefix() . 'ticket_replies.admin, ' . db_prefix() . 'ticket_replies.userid,' . db_prefix() . 'staff.firstname as staff_firstname, ' . db_prefix() . 'staff.lastname as staff_lastname,' . db_prefix() . 'contacts.firstname as user_firstname,' . db_prefix() . 'contacts.lastname as user_lastname,message,date,contactid');
        $this->db->from(db_prefix() . 'ticket_replies');
        $this->db->join(db_prefix() . 'clients', db_prefix() . 'clients.userid = ' . db_prefix() . 'ticket_replies.userid', 'left');
        $this->db->join(db_prefix() . 'staff', db_prefix() . 'staff.staffid = ' . db_prefix() . 'ticket_replies.admin', 'left');
        $this->db->join(db_prefix() . 'contacts', db_prefix() . 'contacts.id = ' . db_prefix() . 'ticket_replies.contactid', 'left');
        $this->db->where('ticketid', $id);
        $this->db->order_by('date', $ticket_replies_order);
        $replies = $this->db->get()->result_array();
        $i       = 0;
        foreach ($replies as $reply) {

            $replies[$i]['profile_image'] = null;
            if ($reply['admin'] !== null || $reply['admin'] != 0) {
                // staff reply
                $replies[$i]['submitter'] = $reply['staff_firstname'] . ' ' . $reply['staff_lastname'];
                $replies[$i]['profile_image'] = staff_profile_image_url($reply['admin']);
            } else {
                if ($reply['contactid'] != 0) {
                    $replies[$i]['submitter'] = $reply['user_firstname'] . ' ' . $reply['user_lastname'];
                    $replies[$i]['profile_image'] = contact_profile_image_url($reply['contactid']);
                } else {
                    $replies[$i]['submitter'] = $reply['from_name'];
                }
            }
            unset($replies[$i]['staff_firstname']);
            unset($replies[$i]['staff_lastname']);
            unset($replies[$i]['user_firstname']);
            unset($replies[$i]['user_lastname']);
            $replies[$i]['attachments'] = $this->tickets_model->get_ticket_attachments($id, $reply['id']);

            foreach ($replies[$i]['attachments'] as $_index => $attachment) {

                $path = get_upload_path_by_type('ticket') . $id . '/' . $attachment['file_name'];
                $replies[$i]['attachments'][$_index]['is_image'] = is_image($path);

                if ($replies[$i]['attachments'][$_index]['is_image']) {
                    $replies[$i]['attachments'][$_index]['src'] = site_url('download/preview_image?path=' . protected_file_url_by_path($path) . '&type=' . $attachment['filetype']);
                }
                $replies[$i]['attachments'][$_index]['mime_class'] = get_mime_class($attachment['filetype']);
            }
            $i++;
        }

        return $replies;
    }
}
