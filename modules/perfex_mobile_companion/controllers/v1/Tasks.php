<?php

defined('BASEPATH') or exit('No direct script access allowed');

require __DIR__ . '/../REST_Controller.php';

class Tasks extends REST_Controller
{

    function __construct()
    {
        // Construct the parent class
        parent::__construct();
        $this->load->model('tasks_model');
        $this->load->model('tasks_api_model');
    }

    public function data_get($id = '')
    {
        // If the id parameter doesn't exist return all the
        $data = $this->Api_model->get_table('tasks', $id);

        if ($data) {
            if ($id != '') {
                $data->is_timer_disabled = false;
                $data->is_timer_started = $this->tasks_model->is_timer_started($data->id);
                $data->current_user_is_assigned = $this->tasks_model->is_task_assignee(get_staff_user_id(), $id);
                $data->tags = implode(',', get_tags_in($data->id, 'task'));

                if (!$data->is_timer_started) {
                    $is_assigned = $data->current_user_is_assigned;
                    if (!$is_assigned || $data->status == Tasks_model::STATUS_COMPLETE) {
                        $data->is_timer_disabled = true;
                    }
                }

                $data->checklistTemplates = $this->tasks_model->get_checklist_templates();
                $data->formatted_startdate    = _d($data->startdate);
                $data->formatted_duedate    = _d($data->duedate);
                $data->formatted_dateadded    = _d($data->dateadded);
                $data->rel_name = task_rel_name($data->rel_name, $data->rel_id, $data->rel_type);
                $data->user_logged_time = seconds_to_time_format($this->tasks_model->calc_task_total_time($data->id, ' AND staff_id=' . get_staff_user_id()));
                $data->total_logged_time = seconds_to_time_format($this->tasks_model->calc_task_total_time($data->id));
                $rel_data = get_relation_data($data->rel_type, $data->rel_id);
                $data->relational_values = get_relation_values($rel_data, $data->rel_type);

                foreach ($data->checklist_items as $_key => $list) {
                    $data->checklist_items[$_key]['can_be_template']    = (total_rows(db_prefix() . 'tasks_checklist_templates', ['description' => $list['description']]) == 0);
                    $data->checklist_items[$_key]['addedfrom_fullname'] = get_staff_full_name($list['addedfrom']);
                    if ($list['finished'] == 1) {
                        $data->checklist_items[$_key]['finished_from_fullname'] = get_staff_full_name($list['finished_from']);
                    }

                    if (!empty($list['assigned'])) {
                        $data->checklist_items[$_key]['assigned_fullname'] = get_staff_full_name($list['assigned']);
                    }
                }

                foreach ($data->timesheets as $key => $timesheet) {
                    $data->timesheets[$key]['start_time'] = _dt($timesheet['start_time'], true);
                    if ($timesheet['end_time'] !== NULL) {
                        $data->timesheets[$key]['end_time'] = _dt($timesheet['end_time'], true);
                    }

                    if ($timesheet['time_spent'] == NULL) {
                        $data->timesheets[$key]['time_h'] = seconds_to_time_format(time() - $timesheet['start_time']);
                        $data->timesheets[$key]['time_decimal'] = sec2qty(time() - $timesheet['start_time']);
                    } else {
                        $data->timesheets[$key]['time_h'] = seconds_to_time_format($timesheet['time_spent']);
                        $data->timesheets[$key]['time_decimal'] = sec2qty($timesheet['time_spent']);
                    }
                }

                foreach ($data->assignees as $key => $assignee) {
                    $data->assignees[$key]['profile_url'] = staff_profile_image_url($assignee['assigneeid']);
                    $data->assignees[$key]['staff'] = get_staff($assignee['assigneeid']);
                }

                foreach ($data->followers as $key => $follower) {
                    $data->followers[$key]['profile_url'] = staff_profile_image_url($follower['followerid']);
                    $data->followers[$key]['staff'] = get_staff($follower['followerid']);
                }

                foreach ($data->attachments as $key => $attachment) {
                    $externalPreview = false;
                    $is_image = false;
                    $path = get_upload_path_by_type('task') . $data->id . '/' . $attachment['file_name'];
                    $href_url = site_url('download/file/taskattachment/' . $attachment['attachment_key']);
                    $isHtml5Video = is_html5_video($path);

                    if (empty($attachment['external'])) {
                        $is_image = is_image($path);
                        $img_url = site_url('download/preview_image?path=' . protected_file_url_by_path($path, true) . '&type=' . $attachment['filetype']);
                    } else if (
                        (!empty($attachment['thumbnail_link']) ||
                            !empty($attachment['external'])) &&
                        !empty($attachment['thumbnail_link'])
                    ) {
                        $is_image = true;
                        $img_url = optimize_dropbox_thumbnail($attachment['thumbnail_link']);
                        $externalPreview = $img_url;
                        $href_url = $attachment['external_link'];
                    } else if (!empty($attachment['external']) && empty($attachment['thumbnail_link'])) {
                        $href_url = $attachment['external_link'];
                    }

                    $data->attachments[$key]['time_ago'] = time_ago($attachment['dateadded']);
                    if ($attachment['staffid'] != 0) {
                        $data->attachments[$key]['addedby'] = get_staff_full_name($attachment['staffid']);
                    } else if ($attachment['contact_id'] != 0) {
                        $data->attachments[$key]['addedby'] = get_contact_full_name($attachment['contact_id']);
                    }

                    $data->attachments[$key]['is_image'] = $is_image;
                    if ($is_image) {
                        $data->attachments[$key]['src'] = $img_url;
                    } else if ($isHtml5Video) {
                        $data->attachments[$key]['src'] = site_url('download/preview_video?path=' . protected_file_url_by_path($path) . '&type=' . $attachment['filetype']);
                    } else {
                        $data->attachments[$key]['src'] = get_mime_class($attachment['filetype']);
                    }
                }

                if ($data->rel_type == 'project') {
                    $this->load->model('projects_model');
                    $data->project_members = $this->projects_model->get_project_members($data->rel_id);
                }

                if ($data->billable == 1 && $data->billed == 0 && ($data->rel_type != 'project' || ($data->rel_type == 'project' && $data->project_data->billing_type != 1)) && staff_can('create', 'invoices')) {
                    $data->billable_amount = $this->tasks_model->get_billable_amount($data->id);
                }
            }
            $data = $this->Api_model->get_api_custom_data($data, "tasks", $id);

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
        $data = $this->Api_model->search('tasks', $key);

        if ($data) {
            $checklistTemplates = $this->tasks_model->get_checklist_templates();

            foreach ($data as $key => $value) {
                $data[$key]['checklistTemplates']  = $checklistTemplates;
                $data[$key]['formatted_startdate'] = _d($value['startdate']);
                $data[$key]['formatted_duedate']   = _d($data[$key]['duedate']);
                $data[$key]['formatted_dateadded'] = _d($data[$key]['dateadded']);
                $data[$key]['rel_name']            = task_rel_name($value['rel_name'], $value['rel_id'], $value['rel_type']);
                $data[$key]['user_logged_time']    = seconds_to_time_format($this->tasks_model->calc_task_total_time($value['id'], ' AND staff_id=' . get_staff_user_id()));
                $data[$key]['total_logged_time']   = seconds_to_time_format($this->tasks_model->calc_task_total_time($value['id']));

                $rel_data                          = get_relation_data($value['rel_type'], $value['rel_id']);
                $data[$key]['relational_values']   = get_relation_values($rel_data, $value['rel_type']);
                $data[$key]['checklist_items']     = $this->tasks_model->get_checklist_items($value['id']);

                foreach ($data[$key]['checklist_items'] as $_key => $list) {
                    $data[$key]['checklist_items'][$_key]['can_be_template'] =  (total_rows(db_prefix() . 'tasks_checklist_templates', ['description' => $list['description']]) == 0);
                    $data[$key]['checklist_items'][$_key]['addedfrom_fullname'] = get_staff_full_name($list['addedfrom']);
                    if ($list['finished'] == 1) {
                        $data[$key]['checklist_items'][$_key]['finished_from_fullname'] = get_staff_full_name($list['finished_from']);
                    }

                    if (!empty($list['assigned'])) {
                        $data[$key]['checklist_items'][$_key]['assigned_fullname'] = get_staff_full_name($list['assigned']);
                    }
                }

                $data[$key]['is_timer_disabled'] = false;
                $data[$key]['is_timer_started'] = $this->tasks_model->is_timer_started($value['id']);
                $data[$key]['current_user_is_assigned'] = $this->tasks_model->is_task_assignee(get_staff_user_id(), $value['id']);

                if (!$data[$key]['is_timer_started']) {
                    $is_assigned = $data[$key]['current_user_is_assigned'];
                    if (!$is_assigned || $value['status'] == Tasks_model::STATUS_COMPLETE) {
                        $data[$key]['is_timer_disabled'] = true;
                    }
                }

                $data[$key]['timesheets']          = $this->tasks_model->get_timesheeets($value['id']);
                $data[$key]['assignees']           = $this->tasks_model->get_task_assignees($value['id']);
                $data[$key]['followers']           = $this->tasks_model->get_task_followers($value['id']);
                $data[$key]['attachments']         = $this->tasks_model->get_task_attachments($value['id']);

                foreach ($data[$key]['attachments'] as $___key => $attachment) {
                    $externalPreview = false;
                    $is_image = false;
                    $path = get_upload_path_by_type('task') . $value['id'] . '/' . $attachment['file_name'];
                    $href_url = site_url('download/file/taskattachment/' . $attachment['attachment_key']);
                    $isHtml5Video = is_html5_video($path);

                    if (empty($attachment['external'])) {
                        $is_image = is_image($path);
                        $img_url = site_url('download/preview_image?path=' . protected_file_url_by_path($path, true) . '&type=' . $attachment['filetype']);
                    } else if (
                        (!empty($attachment['thumbnail_link']) ||
                            !empty($attachment['external'])) &&
                        !empty($attachment['thumbnail_link'])
                    ) {
                        $is_image = true;
                        $img_url = optimize_dropbox_thumbnail($attachment['thumbnail_link']);
                        $externalPreview = $img_url;
                        $href_url = $attachment['external_link'];
                    } else if (!empty($attachment['external']) && empty($attachment['thumbnail_link'])) {
                        $href_url = $attachment['external_link'];
                    }

                    $data[$key]['attachments'][$___key]['time_ago'] = time_ago($attachment['dateadded']);
                    if ($attachment['staffid'] != 0) {
                        $data[$key]['attachments'][$___key]['addedby'] = get_staff_full_name($attachment['staffid']);
                    } else if ($attachment['contact_id'] != 0) {
                        $data[$key]['attachments'][$___key]['addedby'] = get_contact_full_name($attachment['contact_id']);
                    }

                    $data[$key]['attachments'][$___key]['is_image'] = $is_image;
                    if ($is_image) {
                        $data[$key]['attachments'][$___key]['src'] = $img_url;
                    } else if ($isHtml5Video) {
                        $data[$key]['attachments'][$___key]['src'] = site_url('download/preview_video?path=' . protected_file_url_by_path($path) . '&type=' . $attachment['filetype']);
                    } else {
                        $data[$key]['attachments'][$___key]['src'] = get_mime_class($attachment['filetype']);
                    }
                }

                foreach ($data[$key]['timesheets'] as $__key => $timesheet) {
                    $data[$key]['timesheets'][$__key]['start_time'] = _dt($timesheet['start_time'], true);
                    if ($timesheet['end_time'] !== NULL) {
                        $data[$key]['timesheets'][$__key]['end_time'] = _dt($timesheet['end_time'], true);
                    }

                    if ($timesheet['time_spent'] == NULL) {
                        $data[$key]['timesheets'][$__key]['time_h'] = seconds_to_time_format(time() - $timesheet['start_time']);
                        $data[$key]['timesheets'][$__key]['time_decimal'] = sec2qty(time() - $timesheet['start_time']);
                    } else {
                        $data[$key]['timesheets'][$__key]['time_h'] = seconds_to_time_format($timesheet['time_spent']);
                        $data[$key]['timesheets'][$__key]['time_decimal'] = sec2qty($timesheet['time_spent']);
                    }
                }

                $data[$key]['assignees_ids'] = [];
                foreach ($data[$key]['assignees'] as $_key => $assignee) {
                    $data[$key]['assignees'][$_key]['profile_url'] = staff_profile_image_url($assignee['assigneeid']);
                    $data[$key]['assignees'][$_key]['staff'] = get_staff($assignee['assigneeid']);
                    array_push($data[$key]['assignees_ids'], $assignee['assigneeid']);
                }

                $data[$key]['followers_ids'] = [];
                foreach ($data[$key]['followers'] as $_key => $follower) {
                    $data[$key]['followers'][$_key]['profile_url'] = staff_profile_image_url($follower['followerid']);
                    $data[$key]['followers'][$_key]['staff'] = get_staff($follower['followerid']);
                    array_push($data[$key]['followers_ids'], $follower['followerid']);
                }

                if ($value['rel_type'] == 'project') {
                    $this->load->model('projects_model');
                    $data[$key]['project_data'] = $this->projects_model->get($value['rel_id']);
                    $data[$key]['project_members'] = $this->projects_model->get_project_members($value['rel_id']);
                }

                if ($value['billable'] == 1 && $value['billed'] == 0 && ($value['rel_type'] != 'project' || ($value['rel_type'] == 'project' && $data[$key]['project_data']->billing_type != 1)) && staff_can('create', 'invoices')) {
                    $data[$key]['billable_amount'] = $this->tasks_model->get_billable_amount($value['id']);
                }
            }

            $data = $this->Api_model->get_api_custom_data($data, "tasks");

            $this->response($data, REST_Controller::HTTP_OK); // OK (200) being the HTTP response code
        } else {
            $this->response([
                'status' => FALSE,
                'message' => 'No data found'
            ], REST_Controller::HTTP_OK); // NOT_FOUND (404) being the HTTP response code
        }
    }

    public function timer_tracking_post()
    {
        $task_id   = $this->input->post('task_id');
        $adminStop = $this->input->get('admin_stop') && is_admin() ? true : false;

        if ($adminStop) {
            $this->session->set_flashdata('task_single_timesheets_open', true);
        }

        $message = array(
            'status'  => $this->tasks_api_model->timer_tracking(
                $task_id,
                $this->input->post('timer_id'),
                nl2br($this->input->post('note')),
                $adminStop
            )
        );
        $this->response($message, REST_Controller::HTTP_OK);
    }

    public function mark_as_get($status, $id)
    {
        if (
            $this->tasks_model->is_task_assignee(get_staff_user_id(), $id)
            || $this->tasks_model->is_task_creator(get_staff_user_id(), $id)
            || has_permission('tasks', '', 'edit')
        ) {
            $success = $this->tasks_model->mark_as($status, $id);

            $message = '';
            if ($success) {
                $message = _l('task_marked_as_success', format_task_status($status, true, true));
            }

            $message = array(
                'status'  => true,
                'message' => $message,
            );
            $this->response($message, REST_Controller::HTTP_OK);
        } else {
            $message = array(
                'status'  => false,
                'message' => 'Fail to change priority.',
            );
            $this->response($message, REST_Controller::HTTP_OK);
        }
    }

    public function change_priority_get($priority_id, $id)
    {
        if (has_permission('tasks', '', 'edit')) {
            $data = hooks()->apply_filters('before_update_task', ['priority' => $priority_id], $id);

            $this->db->where('id', $id);
            $this->db->update(db_prefix() . 'tasks', $data);

            $success = $this->db->affected_rows() > 0 ? true : false;

            hooks()->do_action('after_update_task', $id);

            // Don't do this query if the action is not performed via task single
            $message = array(
                'status'  => true,
                'message' => 'Task priority changed.'
            );
            $this->response($message, REST_Controller::HTTP_OK);
        } else {
            $message = array(
                'status'  => false,
                'message' => 'Fail to change priority.',
            );
            $this->response($message, REST_Controller::HTTP_OK);
        }
    }

    public function data_post()
    {
        $this->form_validation->set_rules('name', 'Task Name', 'trim|required|max_length[600]', array('is_unique' => 'This %s already exists please enter another Task Name'));
        $this->form_validation->set_rules('startdate', 'Task Start Date', 'trim|required', array('is_unique' => 'This %s already exists please enter another Task Start Date'));
        $this->form_validation->set_rules('is_public', 'Publicly available task', 'trim', array('is_unique' => 'Public state can be 1. Skip it completely to set it at non-public'));
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
                'startdate' => $this->input->post('startdate', TRUE),
                'hourly_rate' => $this->Api_model->value($this->input->post('hourly_rate', TRUE)),
                'milestone' => $this->Api_model->value($this->input->post('milestone', TRUE)),
                'duedate' => $this->Api_model->value($this->input->post('duedate', TRUE)),
                'priority' => $this->Api_model->value($this->input->post('priority', TRUE)),
                'repeat_every' => $this->Api_model->value($this->input->post('repeat_every', TRUE)),
                'repeat_every_custom' => $this->Api_model->value($this->input->post('repeat_every_custom', TRUE)),
                'repeat_type_custom' => $this->Api_model->value($this->input->post('repeat_type_custom', TRUE)),
                'cycles' => $this->Api_model->value($this->input->post('cycles', TRUE)),
                'rel_type' => $this->Api_model->value($this->input->post('rel_type', TRUE)),
                'rel_id' => $this->Api_model->value($this->input->post('rel_id', TRUE)),
                'tags' => $this->Api_model->value($this->input->post('tags', TRUE)),
                'description' => $this->Api_model->value($this->input->post('description', TRUE))
            ];

            if (!empty($this->input->post('custom_fields', TRUE))) {
                $insert_data['custom_fields'] = $this->Api_model->value($this->input->post('custom_fields', TRUE));
            }
            if (!empty($this->input->post('checklist_items', TRUE))) {
                $insert_data['checklist_items'] = $this->Api_model->value($this->input->post('checklist_items', TRUE));
            }
            if (!empty($this->input->post('assignees', TRUE))) {
                $insert_data['assignees'] = $this->Api_model->value($this->input->post('assignees', TRUE));
            }
            if (!empty($this->input->post('followers', TRUE))) {
                $insert_data['followers'] = $this->Api_model->value($this->input->post('followers', TRUE));
            }

            if (!empty($this->input->post('visible_to_client'))) {
                $insert_data['visible_to_client'] = $this->input->post('visible_to_client');
            }

            if (!empty($this->input->post('billable'))) {
                $insert_data['billable'] = $this->input->post('billable');
            }

            if (!empty($this->input->post('is_public'))) {
                $insert_data['is_public'] = $this->input->post('is_public');
            }

            // insert data
            $output = $this->tasks_model->add($insert_data);
            if ($output > 0 && !empty($output)) {
                // success
                $message = array(
                    'status' => TRUE,
                    'message' => 'Task add successful.',
                    'insert_id' => $output
                );
                $this->response($message, REST_Controller::HTTP_OK);
            } else {
                // error
                $message = array(
                    'status' => FALSE,
                    'message' => 'Task add failed.'
                );
                $this->response($message, REST_Controller::HTTP_OK);
            }
        }
    }

    public function files_post($taskid)
    {

        $files   = handle_task_attachments_array($taskid, 'file');
        $success = false;
        $message = 'Fail to upload attachment.';

        if ($files) {
            $i   = 0;
            $len = count($files);
            foreach ($files as $file) {
                $message = 'attachment uploaded successfully.';
                $success = $this->tasks_model->add_attachment_to_database($taskid, [$file], false, ($i == $len - 1 ? true : false));
                $i++;
            }
        }

        $message = array(
            'status' => $success,
            'message' => $message
        );
        $this->response($message, REST_Controller::HTTP_OK);
    }

    public function data_followers_post()
    {
        $task = $this->tasks_model->get($this->input->post('taskid'));

        if (
            staff_can('edit', 'tasks') ||
            ($task->current_user_is_creator && staff_can('create', 'tasks'))
        ) {
            $output = $this->tasks_model->add_task_followers($this->input->post());
            if ($output > 0 && !empty($output)) {
                // success
                $message = array(
                    'status' => TRUE,
                    'message' => 'Task Follower added successfuly.',
                    'insert_id' => $output
                );
                $this->response($message, REST_Controller::HTTP_OK);
            }
        }

        // error
        $message = array(
            'status' => FALSE,
            'message' => 'Task follower add failed.'
        );
        $this->response($message, REST_Controller::HTTP_OK);
    }

    /* Add task assignees / ajax */
    public function data_assignees_post()
    {
        $task = $this->tasks_model->get($this->input->post('taskid'));

        if (
            staff_can('edit', 'tasks') ||
            ($task->current_user_is_creator && staff_can('create', 'tasks'))
        ) {
            $output = $this->tasks_model->add_task_assignees($this->input->post());
            if ($output > 0 && !empty($output)) {
                // success
                $message = array(
                    'status' => TRUE,
                    'message' => 'Task Assignee added successfuly.',
                    'insert_id' => $output
                );
                $this->response($message, REST_Controller::HTTP_OK);
            }
        }

        $message = array(
            'status' => FALSE,
            'message' => 'Task Assignee add failed.'
        );
        $this->response($message, REST_Controller::HTTP_OK);
    }

    public function data_checklist_post()
    {
        $task = $this->tasks_model->get($this->input->post('taskid'));

        if (
            staff_can('edit', 'tasks') ||
            ($task->current_user_is_creator && staff_can('create', 'tasks'))
        ) {
            $output = $this->tasks_model->add_checklist_item($this->input->post());
            if ($output) {
                // success
                $message = array(
                    'status' => TRUE,
                    'message' => 'Task Checklist added successfuly.',
                    'insert_id' => $output
                );
                $this->response($message, REST_Controller::HTTP_OK);
            }
        }

        $message = array(
            'status' => FALSE,
            'message' => 'Task Checklist add failed.'
        );
        $this->response($message, REST_Controller::HTTP_OK);
    }

    public function data_checklist_item_template_post()
    {
        if (has_permission('checklist_templates', '', 'create')) {
            $output = $this->tasks_model->add_checklist_template($this->input->post('description'));
            if ($output) {
                // success
                $message = array(
                    'status' => TRUE,
                    'message' => 'Task Checklist Template added successfuly.',
                    'insert_id' => $output
                );
                $this->response($message, REST_Controller::HTTP_OK);
            }
        }

        $message = array(
            'status' => FALSE,
            'message' => 'Task Checklist Template add failed.'
        );
        $this->response($message, REST_Controller::HTTP_OK);
    }

    public function data_delete($id = '')
    {
        $id = $this->security->xss_clean($id);
        if (empty($id) && !is_numeric($id)) {
            $message = array(
                'status' => FALSE,
                'message' => 'Invalid Task ID'
            );
            $this->response($message, REST_Controller::HTTP_OK);
        } else {
            // delete data
            $output = $this->tasks_model->delete_task($id);
            if ($output === TRUE) {
                // success
                $message = array(
                    'status' => TRUE,
                    'message' => 'Task Delete Successful.'
                );
                $this->response($message, REST_Controller::HTTP_OK);
            } else {
                // error
                $message = array(
                    'status' => FALSE,
                    'message' => 'Task Delete Fail.'
                );
                $this->response($message, REST_Controller::HTTP_OK);
            }
        }
    }

    public function data_timesheet_delete($id = '')
    {
        $id = $this->security->xss_clean($id);
        if (empty($id) && !is_numeric($id)) {
            $message = array(
                'status' => FALSE,
                'message' => 'Invalid Timesheet ID'
            );
            $this->response($message, REST_Controller::HTTP_OK);
        } else {
            // delete data
            $output = $this->tasks_model->delete_timesheet($id);
            if ($output === TRUE) {
                // success
                $message = array(
                    'status' => TRUE,
                    'message' => 'Timesheet Delete Successful.'
                );
                $this->response($message, REST_Controller::HTTP_OK);
            } else {
                // error
                $message = array(
                    'status' => FALSE,
                    'message' => 'Timesheet Delete Fail.'
                );
                $this->response($message, REST_Controller::HTTP_OK);
            }
        }
    }

    public function data_assignees_delete($id = '', $task_id = '')
    {
        $id = $this->security->xss_clean($id);
        $task_id = $this->security->xss_clean($task_id);
        if (empty($id) && !is_numeric($id)) {
            $message = array(
                'status' => FALSE,
                'message' => 'Invalid Assignee ID'
            );
            $this->response($message, REST_Controller::HTTP_OK);
        } else if (empty($task_id) && !is_numeric($task_id)) {
            $message = array(
                'status' => FALSE,
                'message' => 'Invalid Task ID'
            );
            $this->response($message, REST_Controller::HTTP_OK);
        } else {
            // delete data
            $output = $this->tasks_model->remove_assignee($id, $task_id);
            if ($output === TRUE) {
                // success
                $message = array(
                    'status' => TRUE,
                    'message' => 'Assignee Delete Successful.'
                );
                $this->response($message, REST_Controller::HTTP_OK);
            } else {
                // error
                $message = array(
                    'status' => FALSE,
                    'message' => 'Assignee Delete Fail.'
                );
                $this->response($message, REST_Controller::HTTP_OK);
            }
        }
    }

    public function data_followers_delete($id = '', $task_id = '')
    {
        $id = $this->security->xss_clean($id);
        $task_id = $this->security->xss_clean($task_id);
        if (empty($id) && !is_numeric($id)) {
            $message = array(
                'status' => FALSE,
                'message' => 'Invalid Follower ID'
            );
            $this->response($message, REST_Controller::HTTP_OK);
        } else if (empty($task_id) && !is_numeric($task_id)) {
            $message = array(
                'status' => FALSE,
                'message' => 'Invalid Task ID'
            );
            $this->response($message, REST_Controller::HTTP_OK);
        } else {
            // delete data
            $output = $this->tasks_model->remove_follower($id, $task_id);
            if ($output === TRUE) {
                // success
                $message = array(
                    'status' => TRUE,
                    'message' => 'Follower Delete Successful.'
                );
                $this->response($message, REST_Controller::HTTP_OK);
            } else {
                // error
                $message = array(
                    'status' => FALSE,
                    'message' => 'Follower Delete Fail.'
                );
                $this->response($message, REST_Controller::HTTP_OK);
            }
        }
    }

    public function data_checklist_delete($id = '', $task_id = '')
    {
        $id = $this->security->xss_clean($id);
        $task_id = $this->security->xss_clean($task_id);
        if (empty($id) && !is_numeric($id)) {
            $message = array(
                'status' => FALSE,
                'message' => 'Invalid Checklist ID'
            );
            $this->response($message, REST_Controller::HTTP_OK);
        } else if (empty($task_id) && !is_numeric($task_id)) {
            $message = array(
                'status' => FALSE,
                'message' => 'Invalid Task ID'
            );
            $this->response($message, REST_Controller::HTTP_OK);
        } else {
            // delete data
            $output = $this->tasks_model->delete_checklist_item($id);
            if ($output === TRUE) {
                // success
                $message = array(
                    'status' => TRUE,
                    'message' => 'Checklist Delete Successful.'
                );
                $this->response($message, REST_Controller::HTTP_OK);
            } else {
                // error
                $message = array(
                    'status' => FALSE,
                    'message' => 'Checklist Delete Fail.'
                );
                $this->response($message, REST_Controller::HTTP_OK);
            }
        }
    }

    public function data_timesheet_post()
    {
        $output = $this->tasks_model->timesheet($this->input->post());
        if ($output) {
            $message = array(
                'status' => TRUE,
                'message' => _l('added_successfully', _l('project_timesheet')),
                'insert_id' => $output
            );
            $this->response($message, REST_Controller::HTTP_OK);
        } elseif (is_array($output) && isset($output['end_time_smaller'])) {
            $message = array(
                'status' => FALSE,
                'message' => _l('failed_to_add_project_timesheet_end_time_smaller')
            );
            $this->response($message, REST_Controller::HTTP_OK);
        } else {
            // error
            $message = array(
                'status' => FALSE,
                'message' => _l('project_timesheet_not_updated')
            );
            $this->response($message, REST_Controller::HTTP_OK);
        }
    }

    public function copy_post()
    {
        if (has_permission('tasks', '', 'create')) {
            $new_task_id = $this->tasks_model->copy($this->input->post());
            if ($new_task_id) {
                $message = array(
                    'status' => true,
                    'message' => _l('task_copied_successfully'),
                    'new_task_id' => $new_task_id
                );
                $this->response($message, REST_Controller::HTTP_OK);
            }
            $message = array(
                'status' => false,
                'message' => _l('failed_to_copy_task')
            );
            $this->response($message, REST_Controller::HTTP_OK);
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
                'message' => 'Invalid Task ID'
            );
            $this->response($message, REST_Controller::HTTP_OK);
        } else {

            $update_data = $this->input->post();

            if (empty($update_data['visible_to_client'])) {
                unset($update_data['visible_to_client']);
            }

            if (empty($update_data['billable'])) {
                unset($update_data['billable']);
            }

            if (empty($update_data['is_public'])) {
                unset($update_data['is_public']);
            }
            // update data
            $output = $this->tasks_model->update($update_data, $id);
            if ($output > 0 && !empty($output)) {
                // success
                $message = array(
                    'status' => TRUE,
                    'message' => 'Task Update Successful.'
                );
                $this->response($message, REST_Controller::HTTP_OK);
            } else {
                // error
                $message = array(
                    'status' => FALSE,
                    'message' => 'Task Update Fail.'
                );
                $this->response($message, REST_Controller::HTTP_OK);
            }
        }
    }

    public function data_timesheet_put($timer_id = '')
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
        $data = $this->input->post();

        $data['timer_id'] = $timer_id;
        $output = $this->tasks_model->timesheet($data);
        if ($output == true) {
            $message = array(
                'status' => TRUE,
                'message' => _l('updated_successfully', _l('project_timesheet')),
                'insert_id' => $output
            );
            $this->response($message, REST_Controller::HTTP_OK);
        } else {
            // error
            $message = array(
                'status' => FALSE,
                'message' => _l('project_timesheet_not_updated')
            );
            $this->response($message, REST_Controller::HTTP_OK);
        }
    }

    public function data_checklist_put($timer_id = '')
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
        $data = $this->input->post();

        $desc = $data['description'];
        $desc = trim($desc);
        $this->tasks_model->update_checklist_item($data['listid'], $desc);
        $message = array(
            'status' => TRUE,
            'message' => _l('updated_successfully', _l('task_checklist_item')),
            'can_be_template' => (total_rows(db_prefix() . 'tasks_checklist_templates', ['description' => $desc]) == 0)
        );
        $this->response($message, REST_Controller::HTTP_OK);
    }

    public function data_checkbox_action_get($listid, $value)
    {
        $this->db->where('id', $listid);
        $this->db->update(db_prefix() . 'task_checklist_items', [
            'finished' => $value,
        ]);

        if ($this->db->affected_rows() > 0) {
            if ($value == 1) {
                $finished_from = get_staff_user_id();

                $this->db->where('id', $listid);
                $this->db->update(db_prefix() . 'task_checklist_items', [
                    'finished_from' => $finished_from,
                ]);
                hooks()->do_action('task_checklist_item_finished', $listid);
            }
        }
    }

    public function data_comment_get($id = '')
    {
        $data = $this->tasks_model->get_task_comments($id);

        if ($data) {
            foreach ($data as $key => $value) {
                $data[$key]['profile_url'] = staff_profile_image_url($value['staffid']);
                $data[$key]['full_name'] = get_staff_full_name($value['staffid']);
                $data[$key]['time_ago'] = time_ago($value['dateadded']);
            }

            $this->response($data, REST_Controller::HTTP_OK); // OK (200) being the HTTP response code
        } else {
            $this->response([
                'status' => FALSE,
                'message' => 'No data found'
            ], REST_Controller::HTTP_OK); // NOT_FOUND (404) being the HTTP response code
        }
    }

    public function data_comment_post()
    {
        $data = $this->input->post();

        $this->form_validation->set_rules('content', 'Description', 'trim|required|max_length[255]');
        $this->form_validation->set_rules('taskid', 'Task ID', 'trim|required|greater_than[0]');

        if ($this->form_validation->run() == FALSE) {
            $message = array(
                'status' => FALSE,
                'error' => $this->form_validation->error_array(),
                'message' => validation_errors()
            );
            $this->response($message, REST_Controller::HTTP_OK);
        } else {
            $id = $this->tasks_model->add_task_comment($data);
            if ($id > 0 && !empty($id)) {
                // success
                $message = array(
                    'status' => TRUE,
                    'message' => 'Comment Added Successfully',
                    'insert_id' => $id
                );
                $this->response($message, REST_Controller::HTTP_OK);
            } else {
                // error
                $message = array(
                    'status' => FALSE,
                    'message' => 'Comment Add Fail'
                );
                $this->response($message, REST_Controller::HTTP_OK);
            }
        }
    }

    public function data_comment_delete($id = '')
    {
        $id = $this->security->xss_clean($id);
        if (empty($id) && !is_numeric($id)) {
            $message = array(
                'status' => FALSE,
                'message' => 'Invalid Comment ID'
            );
            $this->response($message, REST_Controller::HTTP_OK);
        } else {
            // delete data
            $this->load->model('tasks_model');
            $output = $this->tasks_model->remove_comment($id);
            if ($output === TRUE) {
                // success
                $message = array(
                    'status' => TRUE,
                    'message' => 'Comment Delete Successful.'
                );
                $this->response($message, REST_Controller::HTTP_OK);
            } else {
                // error
                $message = array(
                    'status' => FALSE,
                    'message' => 'Comment Delete Fail.'
                );
                $this->response($message, REST_Controller::HTTP_OK);
            }
        }
    }

    public function files_delete($id)
    {
        $id = $this->security->xss_clean($id);
        if (empty($id) && !is_numeric($id)) {
            $message = array(
                'status' => FALSE,
                'message' => 'Invalid Attachment ID'
            );
            $this->response($message, REST_Controller::HTTP_OK);
        } else {
            // delete data
            $this->load->model('tasks_model');
            $output = $this->tasks_model->remove_task_attachment($id);
            if ($output['success'] == TRUE) {
                // success
                $message = array(
                    'status' => TRUE,
                    'message' => 'Attachment Delete Successful.'
                );
                $this->response($message, REST_Controller::HTTP_OK);
            } else {
                // error
                $message = array(
                    'status' => FALSE,
                    'message' => 'Attachment Delete Fail.'
                );
                $this->response($message, REST_Controller::HTTP_OK);
            }
        }
    }

    public function data_comment_put($id = '')
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
                'message' => 'Invalid Comment ID'
            );
            $this->response($message, REST_Controller::HTTP_OK);
        } else {

            $this->form_validation->set_rules('content', 'Content', 'trim|required|max_length[255]');
            $this->form_validation->set_rules('taskid', 'Task ID', 'trim|required|greater_than[0]');

            if ($this->form_validation->run() == FALSE) {
                $message = array(
                    'status' => FALSE,
                    'error' => $this->form_validation->error_array(),
                    'message' => validation_errors()
                );
                $this->response($message, REST_Controller::HTTP_OK);
            } else {
                $data = $this->input->post();
                $data['id'] = $id;
                $this->load->model('tasks_model');
                $success = $this->tasks_model->edit_comment($data);
                if ($success == true) {
                    $message = array(
                        'status' => TRUE,
                        'message' => "Comment Updated Successfully",
                    );
                    $this->response($message, REST_Controller::HTTP_OK);
                } else {
                    // error
                    $message = array(
                        'status' => FALSE,
                        'message' => 'Comment Update Fail'
                    );
                    $this->response($message, REST_Controller::HTTP_OK);
                }
            }
        }
    }

    public function save_checklist_assigned_staff_post()
    {
        if ($this->input->post()) {
            $payload = $this->input->post();
            $item    = $this->tasks_model->get_checklist_item($payload['checklistId']);
            if (
                $item->addedfrom == get_staff_user_id()
                || is_admin() ||
                $this->tasks_model->is_task_creator(get_staff_user_id(), $payload['taskId'])
            ) {
                $this->tasks_model->update_checklist_assigned_staff($payload);
                $message = array(
                    'status' => TRUE,
                    'message' => "Task Checklist Staff Assigned Successfully",
                );
                $this->response($message, REST_Controller::HTTP_OK);
            }

            $message = array(
                'status' => FALSE,
                'message' => 'Task Checklist Assign Fail'
            );
            $this->response($message, REST_Controller::HTTP_OK);
        }
    }
    public function checklist_item_template_get()
    {

        $data  = $this->tasks_model->get_checklist_templates();
        if ($data) {
            $this->response($data, REST_Controller::HTTP_OK); // OK (200) being the HTTP response code
        } else {
            $message = array(
                'status' => FALSE,
                'message' => 'No Found'
            );
            $this->response($message, REST_Controller::HTTP_OK);
        }
    }
}
