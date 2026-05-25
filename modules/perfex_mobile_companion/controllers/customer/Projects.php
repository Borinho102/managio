<?php

defined('BASEPATH') or exit('No direct script access allowed');

require __DIR__ . '/../REST_Controller.php';

use app\services\projects\Gantt;

class Projects extends REST_Controller
{

    function __construct()
    {
        parent::__construct();
        $this->load->model('dashboard_model');
        $this->load->model('currencies_model');
        $this->load->model('invoices_model');
        $this->load->model('projects_model');
        $this->load->model('Customer_api_model');
    }

    public function data_get($id = '')
    {

        if (!has_contact_permission('projects')) {
            $this->response([
                'status' => FALSE,
                'message' => 'Access denied'
            ], REST_Controller::HTTP_OK); // NOT_FOUND (404) being the HTTP response code
        }

        if ($id) {

            $this->project_detail($id);
        } else {

            if ($this->input->get('search')) {
                $key = $this->input->get('search');
            }

            $data = $this->Customer_api_model->search_client('project', $key);

            if ($data) {

                $where = 'clientid=' . get_client_user_id();

                $this->load->model('projects_api_model');
                foreach ($data as $key => $value) {
                    $data[$key]['formatted_start_date']      = _d($value['start_date']);
                    $data[$key]['formatted_project_created'] = _d($value['project_created']);
                    $data[$key]['formatted_deadline']        = _d($value['deadline']);
                    $data[$key]['currency']                  = $this->projects_api_model->get_currency($value['id']);
                    $data[$key]['formatted_project_cost']    = app_format_money($value['project_cost'], $data[$key]['currency']);
                    $data[$key]['project_total_logged_time'] = seconds_to_time_format($this->projects_model->total_logged_time($value['id']));

                    $settings                                = $this->projects_api_model->get_project_settings($value['id']);

                    $settings_available_features = [];

                    $available_features_index = false;
                    foreach ($settings as $_key => $setting) {
                        if ($setting['name'] == 'available_features') {
                            $available_features       = unserialize($setting['value']);
                            if (is_array($available_features)) {
                                foreach ($available_features as $name => $avf) {
                                    $settings_available_features[] = $name;
                                }
                            }
                        }
                    }

                    $data[$key]['settings'] = new StdClass();
                    foreach ($settings as $setting) {
                        $data[$key]['settings']->{$setting['name']} = $setting['value'];
                    }
                    $data[$key]['settings']->available_features = $available_features;

                    $data[$key]['client_data'] = new StdClass();
                    $data[$key]['client_data'] = $this->clients_model->get(get_client_user_id());

                    $data[$key]['project_members'] = $this->projects_api_model->get_project_members($value['id']);
                    foreach ($data[$key]['project_members'] as $_key => $assignee) {
                        $data[$key]['project_members'][$_key]['profile_url'] = staff_profile_image_url($assignee['staffid']);
                    }


                    $percent           = $this->projects_model->calc_progress($value['id']);
                    $data[$key]['project_total_days']        = round((human_to_unix($data[$key]['deadline'] . ' 00:00') - human_to_unix($data[$key]['start_date'] . ' 00:00')) / 3600 / 24);
                    $data[$key]['project_days_left']         = $data[$key]['project_total_days'];
                    $data[$key]['project_time_left_percent'] = 100;
                    if ($data[$key]['deadline']) {
                        if (human_to_unix($data[$key]['start_date'] . ' 00:00') < time() && human_to_unix($data[$key]['deadline'] . ' 00:00') > time()) {
                            $data[$key]['project_days_left']         = round((human_to_unix($data[$key]['deadline'] . ' 00:00') - time()) / 3600 / 24);
                            $data[$key]['project_time_left_percent'] = $data[$key]['project_days_left'] / $data[$key]['project_total_days'] * 100;
                            $data[$key]['project_time_left_percent'] = round($data[$key]['project_time_left_percent'], 2);
                        }
                        if (human_to_unix($data[$key]['deadline'] . ' 00:00') < time()) {
                            $data[$key]['project_days_left']         = 0;
                            $data[$key]['project_time_left_percent'] = 0;
                        }
                    }

                    $__total_where_tasks = 'rel_type = "project" AND rel_id=' . $this->db->escape_str($value['id']);

                    if (!has_contact_permission('tasks')) {
                        $__total_where_tasks .= ' AND ' . db_prefix() . 'tasks.id IN (SELECT taskid FROM ' . db_prefix() . 'task_assigned)';

                        if (get_option('show_all_tasks_for_project_member') == 1) {
                            $__total_where_tasks .= ' AND (rel_type="project" AND rel_id IN (SELECT project_id FROM ' . db_prefix() . 'project_members ))';
                        }
                    }

                    $__total_where_tasks = hooks()->apply_filters('customer_total_project_tasks_where', $__total_where_tasks, $value['id']);


                    $where = ($__total_where_tasks == '' ? '' : $__total_where_tasks . ' AND ') . 'status != ' . Tasks_model::STATUS_COMPLETE;

                    $data[$key]['tasks_not_completed'] = total_rows(db_prefix() . 'tasks', $where);

                    $total_tasks                 = total_rows(db_prefix() . 'tasks', $__total_where_tasks);
                    $data[$key]['total_tasks']         = $total_tasks;

                    $where = ($__total_where_tasks == '' ? '' : $__total_where_tasks . ' AND ') . 'status = ' . Tasks_model::STATUS_COMPLETE . ' AND rel_type="project" AND rel_id="' . $value['id'] . '"';

                    $data[$key]['tasks_completed'] = total_rows(db_prefix() . 'tasks', $where);

                    $data[$key]['tasks_not_completed_progress'] = ($total_tasks > 0 ? number_format(($data[$key]['tasks_completed'] * 100) / $total_tasks, 2) : 0);
                    $data[$key]['tasks_not_completed_progress'] = round($data[$key]['tasks_not_completed_progress'], 2);

                    @$percent_circle        = $percent / 100;
                    $data[$key]['percent'] = $percent;
                    $data[$key]['percent_circle'] = $percent_circle;

                    $data[$key]['expenses']['total'] = mpc_app_format_money(sum_from_table(db_prefix() . 'expenses', array('where' => array('project_id' => $value['id']), 'field' => 'amount')), $data[$key]['currency']);
                    $data[$key]['expenses']['billable'] = mpc_app_format_money(sum_from_table(db_prefix() . 'expenses', array('where' => array('project_id' => $value['id'], 'billable' => 1), 'field' => 'amount')), $data[$key]['currency']);
                    $data[$key]['expenses']['billed'] = mpc_app_format_money(sum_from_table(db_prefix() . 'expenses', array('where' => array('project_id' => $value['id'], 'invoiceid !=' => 'NULL', 'billable' => 1), 'field' => 'amount')), $data[$key]['currency']);
                    $data[$key]['expenses']['unbilled'] = mpc_app_format_money(sum_from_table(db_prefix() . 'expenses', array('where' => array('project_id' => $value['id'], 'invoiceid IS NULL', 'billable' => 1), 'field' => 'amount')), $data[$key]['currency']);

                    if ($data[$key]['billing_type'] == 3 || $data[$key]['billing_type'] == 2) {
                        $_data = $this->projects_model->total_logged_time_by_billing_type($value['id']);
                        $data[$key]['hours']['logged'] = mpc_app_format_money($_data['total_money'], $data[$key]['currency']);

                        $_data = $this->projects_model->data_billable_time($value['id']);
                        $data[$key]['hours']['billable'] = mpc_app_format_money($_data['total_money'], $data[$key]['currency']);

                        $_data = $this->projects_model->data_billed_time($value['id']);
                        $data[$key]['hours']['billed'] = mpc_app_format_money($_data['total_money'], $data[$key]['currency']);

                        $_data = $this->projects_model->data_unbilled_time($value['id']);
                        $data[$key]['hours']['unbilled'] = mpc_app_format_money($_data['total_money'], $data[$key]['currency']);
                    }

                    $data[$key]['staff_notes'] = $this->projects_model->get_staff_notes($value['id']);


                    $data[$key]['project_customer_permission_warning'] = false;

                    if (
                        !has_contact_permission('projects', get_primary_contact_user_id($data[$key][get_client_user_id()]))
                        && total_rows(db_prefix() . 'contacts', ['userid' => $data[$key][get_client_user_id()]]) > 0
                    ) {

                        $data[$key]['project_customer_permission_warning'] = true;
                    }
                }


                $this->response($data, REST_Controller::HTTP_OK); // OK (200) being the HTTP response code
            } else {
                $this->response([
                    'status' => FALSE,
                    'message' => 'No data found'
                ], REST_Controller::HTTP_OK); // NOT_FOUND (404) being the HTTP response code
            }
        }
    }
    public function project_detail($id)
    {

        $id = $this->security->xss_clean($id);
        if (empty($id) && !is_numeric($id)) {
            $message = array(
                'status' => FALSE,
                'message' => 'Invalid Project ID'
            );
            $this->response($message, REST_Controller::HTTP_OK);
        } else {

            $data = $this->Customer_api_model->get_table('projects', $id);

            if ($data) {
                if ($id != '') {
                    $this->load->model('projects_api_model');
                    $data->formatted_start_date = _d($data->start_date);
                    $data->formatted_project_created = _d($data->project_created);
                    $data->formatted_deadline = _d($data->deadline);
                    $data->currency = $this->projects_api_model->get_currency($data->id);
                    $data->formatted_project_cost     = app_format_money($data->project_cost, $data->currency);
                    $data->project_members = $this->projects_api_model->get_project_members($id);

                    foreach ($data->project_members as $_key => $assignee) {
                        $data->project_members[$_key]['profile_url'] = staff_profile_image_url($assignee['staffid']);
                    }

                    $data->project_total_logged_time = seconds_to_time_format($this->projects_model->total_logged_time($id));

                    $percent           = $this->projects_model->calc_progress($id);
                    $data->project_total_days        = round((human_to_unix($data->deadline . ' 00:00') - human_to_unix($data->start_date . ' 00:00')) / 3600 / 24);
                    $data->project_days_left         = $data->project_total_days;
                    $data->project_time_left_percent = 100;
                    if ($data->deadline) {
                        if (human_to_unix($data->start_date . ' 00:00') < time() && human_to_unix($data->deadline . ' 00:00') > time()) {
                            $data->project_days_left         = round((human_to_unix($data->deadline . ' 00:00') - time()) / 3600 / 24);
                            $data->project_time_left_percent = $data->project_days_left / $data->project_total_days * 100;
                            $data->project_time_left_percent = round($data->project_time_left_percent, 2);
                        }
                        if (human_to_unix($data->deadline . ' 00:00') < time()) {
                            $data->project_days_left         = 0;
                            $data->project_time_left_percent = 0;
                        }
                    }

                    $__total_where_tasks = 'rel_type = "project" AND rel_id=' . $this->db->escape_str($id);
                    if (!has_contact_permission('tasks')) {
                        $__total_where_tasks .= ' AND ' . db_prefix() . 'tasks.id IN (SELECT taskid FROM ' . db_prefix() . 'task_assigned)';

                        if (get_option('show_all_tasks_for_project_member') == 1) {
                            $__total_where_tasks .= ' AND (rel_type="project" AND rel_id IN (SELECT project_id FROM ' . db_prefix() . 'project_members))';
                        }
                    }

                    $__total_where_tasks = hooks()->apply_filters('customer_total_project_tasks_where', $__total_where_tasks, $id);

                    $where = ($__total_where_tasks == '' ? '' : $__total_where_tasks . ' AND ') . 'status != ' . Tasks_model::STATUS_COMPLETE;

                    $data->tasks_not_completed = total_rows(db_prefix() . 'tasks', $where);
                    $total_tasks                 = total_rows(db_prefix() . 'tasks', $__total_where_tasks);
                    $data->total_tasks         = $total_tasks;

                    $where = ($__total_where_tasks == '' ? '' : $__total_where_tasks . ' AND ') . 'status = ' . Tasks_model::STATUS_COMPLETE . ' AND rel_type="project" AND rel_id="' . $id . '"';

                    $data->tasks_completed = total_rows(db_prefix() . 'tasks', $where);

                    $data->tasks_not_completed_progress = ($total_tasks > 0 ? number_format(($data->tasks_completed * 100) / $total_tasks, 2) : 0);
                    $data->tasks_not_completed_progress = round($data->tasks_not_completed_progress, 2);

                    @$percent_circle      = $percent / 100;
                    $data->percent        = $percent;
                    $data->percent_circle = $percent_circle;

                    $data->expenses['total'] = mpc_app_format_money(sum_from_table(db_prefix() . 'expenses', array('where' => array('project_id' => $data->id), 'field' => 'amount')), $data->currency);
                    $data->expenses['billable'] = mpc_app_format_money(sum_from_table(db_prefix() . 'expenses', array('where' => array('project_id' => $data->id, 'billable' => 1), 'field' => 'amount')), $data->currency);
                    $data->expenses['billed'] = mpc_app_format_money(sum_from_table(db_prefix() . 'expenses', array('where' => array('project_id' => $data->id, 'invoiceid !=' => 'NULL', 'billable' => 1), 'field' => 'amount')), $data->currency);
                    $data->expenses['unbilled'] = mpc_app_format_money(sum_from_table(db_prefix() . 'expenses', array('where' => array('project_id' => $data->id, 'invoiceid IS NULL', 'billable' => 1), 'field' => 'amount')), $data->currency);

                    if ($data->billing_type == 3 || $data->billing_type == 2) {
                        $_data = $this->projects_model->total_logged_time_by_billing_type($data->id);
                        $data->hours['logged'] = mpc_app_format_money($_data['total_money'], $data->currency);

                        $_data = $this->projects_model->data_billable_time($data->id);
                        $data->hours['billable'] = mpc_app_format_money($_data['total_money'], $data->currency);

                        $_data = $this->projects_model->data_billed_time($data->id);
                        $data->hours['billed'] = mpc_app_format_money($_data['total_money'], $data->currency);

                        $_data = $this->projects_model->data_unbilled_time($data->id);
                        $data->hours['unbilled'] = mpc_app_format_money($_data['total_money'], $data->currency);
                    }

                    $data->staff_notes = $this->projects_model->get_staff_notes($id);

                    $data->project_customer_permission_warning = false;

                    if (
                        !has_contact_permission('projects', get_primary_contact_user_id(get_client_user_id()))
                        && total_rows(db_prefix() . 'contacts', ['userid' => get_client_user_id()]) > 0
                    ) {

                        $data->project_customer_permission_warning = true;
                    }
                }

                $data = $this->Customer_api_model->get_api_custom_data($data, "projects", $id);

                $this->response($data, REST_Controller::HTTP_OK); // OK (200) being the HTTP response code
            } else {
                $this->response([
                    'status' => FALSE,
                    'message' => 'Project not exist'
                ], REST_Controller::HTTP_OK); // NOT_FOUND (404) being the HTTP response code
            }
        }
    }

    public function data_files_get($id)
    {
        $files = $this->projects_model->get_files($id);

        if ($files) {
            $this->response($files, REST_Controller::HTTP_OK);
        } else {
            $this->response([
                'status' => FALSE,
                'message' => 'No data found'
            ], REST_Controller::HTTP_OK); // NOT_FOUND (404) being the HTTP response code
        }
    }

    public function data_discussions_get()
    {
        $data = $this->_search_discussions($this->input->get('search'));

        if ($data) {
            foreach ($data as $key => $value) {
            }

            $this->response($data, REST_Controller::HTTP_OK); // OK (200) being the HTTP response code
        } else {
            $this->response([
                'status' => FALSE,
                'message' => 'No data found'
            ], REST_Controller::HTTP_OK); // NOT_FOUND (404) being the HTTP response code
        }
    }

    private function _search_discussions($q, $limit = 0, $api = false)
    {
        $result = [
            'result'         => [],
            'type'           => 'discussions',
            'search_heading' => _l('discussions'),
        ];

        if (has_contact_permission('projects') || true == $api) {
            $this->db->select('id, subject, description, last_activity, (SELECT COUNT(*) FROM ' . db_prefix() . 'projectdiscussioncomments WHERE discussion_id = ' . db_prefix() . 'projectdiscussions.id AND discussion_type="regular") as totalComments, show_to_customer');

            $this->db->group_start();
            $this->db->like('subject', $q);
            $this->db->group_end();

            if (!empty($this->input->get('filters'))) {
                $filters = json_decode($this->input->get('filters'), true);
                if (!empty($filters['project_id'])) {
                    $this->db->where('project_id', $filters['project_id']);
                }
            }

            if (is_numeric($this->input->get('limit')) && is_numeric($this->input->get('offset'))) {
                $this->db->limit($this->input->get('limit'));
                $this->db->offset($this->input->get('offset'));
            }

            $this->db->order_by('id', 'DESC');
            $result['result'] = $this->db->get(db_prefix() . 'projectdiscussions')->result_array();
        }

        return $result['result'];
    }

    public function data_activity_get($id)
    {
        $this->load->model('projects_model');
        $data = $this->projects_model->get_activity($id);

        if ($data) {
            foreach ($data as $key => $value) {
                $data[$key]['time_ago'] = time_ago($value['dateadded']);
                if (is_numeric($value['staff_id']) && $value['staff_id'] != 0) {
                    $data[$key]['profile_url'] = staff_profile_image_url($value['staff_id']);
                }
            }

            $this->response($data, REST_Controller::HTTP_OK); // OK (200) being the HTTP response code
        } else {
            $this->response([
                'status' => FALSE,
                'message' => 'No data found'
            ], REST_Controller::HTTP_OK); // NOT_FOUND (404) being the HTTP response code
        }
    }

    public function data_files_post($id)
    {
        if (handle_project_file_uploads($id)) {
            $message = array(
                'status' => TRUE,
                'message' => 'File Uploaded Successfully.'
            );
            $this->response($message, REST_Controller::HTTP_OK);
        } else {
            $message = array(
                'status' => FALSE,
                'message' => 'Project File upload fail.'
            );
            $this->response($message, REST_Controller::HTTP_OK);
        }
    }

    public function data_discussions_post()
    {
        $_POST = json_decode($this->security->xss_clean(file_get_contents("php://input")), true);
        if (empty($_POST) || !isset($_POST)) {
            $message = array(
                'status' => FALSE,
                'message' => 'Data Not Acceptable OR Not Provided'
            );
            $this->response($message, REST_Controller::HTTP_OK);
        }

        $data = $this->input->post();
        $id = $this->projects_model->add_discussion($data);
        if ($id) {
            $message = array(
                'status' => TRUE,
                'insert_id' => $id,
                'message' => _l('added_successfully', _l('project_discussion'))
            );
            $this->response($message, REST_Controller::HTTP_OK);
        } else {
            $message = array(
                'status' => FALSE,
                'message' => 'Project Discussion add fail.'
            );
            $this->response($message, REST_Controller::HTTP_OK);
        }
    }

    public function data_discussions_comment_post()
    {
        $_POST = json_decode($this->security->xss_clean(file_get_contents("php://input")), true);
        if (empty($_POST) || !isset($_POST)) {
            $message = array(
                'status' => FALSE,
                'message' => 'Data Not Acceptable OR Not Provided'
            );
            $this->response($message, REST_Controller::HTTP_OK);
        }

        $data = $this->input->post();

        $id = $this->projects_model->add_discussion_comment($data, $data['discussion_id'], 'regular');
        if ($id) {
            $message = array(
                'status' => TRUE,
                'insert_id' => $id,
                'message' => _l('added_successfully', 'Project discussion comment')
            );
            $this->response($message, REST_Controller::HTTP_OK);
        } else {
            $message = array(
                'status' => FALSE,
                'message' => 'Project Discussion add fail.'
            );
            $this->response($message, REST_Controller::HTTP_OK);
        }
    }

    public function update_discussions_comment_post($id)
    {
        $_POST = json_decode($this->security->xss_clean(file_get_contents("php://input")), true);
        if (empty($_POST) || !isset($_POST)) {
            $message = array(
                'status' => FALSE,
                'message' => 'Data Not Acceptable OR Not Provided'
            );
            $this->response($message, REST_Controller::HTTP_OK);
        }

        $data = $this->input->post();
        $data['id'] = $id;

        $id = $this->projects_model->update_discussion_comment($data);
        if ($id) {
            $message = array(
                'status' => TRUE,
                'insert_id' => $id,
                'message' => _l('updated_successfully', 'Project discussion comment')
            );
            $this->response($message, REST_Controller::HTTP_OK);
        } else {
            $message = array(
                'status' => FALSE,
                'message' => 'Project Discussion add fail.'
            );
            $this->response($message, REST_Controller::HTTP_OK);
        }
    }

    public function delete_discussion_comment_delete($id)
    {

        $id = $this->projects_model->delete_discussion_comment($id);
        if ($id) {
            $message = array(
                'status' => TRUE,
                'insert_id' => $id,
                'message' => _l('deleted_successfully', 'Project discussion comment')
            );
            $this->response($message, REST_Controller::HTTP_OK);
        } else {
            $message = array(
                'status' => FALSE,
                'message' => 'Project discussion comment delete fail.'
            );
            $this->response($message, REST_Controller::HTTP_OK);
        }
    }

    public function data_discussions_comments_get($id)
    {

        $data = $this->projects_model->get_discussion_comments($id, "regular");
        if ($data) {

            $this->response($data, REST_Controller::HTTP_OK);
        } else {
            $message = array(
                'status' => FALSE,
                'message' => 'Discussion Comment not exist.'
            );
            $this->response($message, REST_Controller::HTTP_OK);
        }
    }

    public function data_discussions_comment_get($id)
    {

        $data = $this->projects_model->get_discussion_comment($id);
        if ($data) {

            $this->response($data, REST_Controller::HTTP_OK);
        } else {
            $message = array(
                'status' => FALSE,
                'message' => 'Discussion Comment not exist.'
            );
            $this->response($message, REST_Controller::HTTP_OK);
        }
    }
}
