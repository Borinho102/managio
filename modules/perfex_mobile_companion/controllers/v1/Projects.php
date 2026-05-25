<?php

defined('BASEPATH') or exit('No direct script access allowed');

require __DIR__ . '/../REST_Controller.php';

class Projects extends REST_Controller
{

    function __construct()
    {
        parent::__construct();
        $this->load->model('Api_model');
        $this->load->model('clients_model');
        $this->load->model('projects_model');
    }

    public function data_get($id = '')
    {
        // If the id parameter doesn't exist return all the
        $data = $this->Api_model->get_table('projects', $id);
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
                if (!has_permission('tasks', '', 'view')) {
                    $__total_where_tasks .= ' AND ' . db_prefix() . 'tasks.id IN (SELECT taskid FROM ' . db_prefix() . 'task_assigned WHERE staffid = ' . get_staff_user_id() . ')';

                    if (get_option('show_all_tasks_for_project_member') == 1) {
                        $__total_where_tasks .= ' AND (rel_type="project" AND rel_id IN (SELECT project_id FROM ' . db_prefix() . 'project_members WHERE staff_id=' . get_staff_user_id() . '))';
                    }
                }

                $__total_where_tasks = hooks()->apply_filters('admin_total_project_tasks_where', $__total_where_tasks, $id);

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
                    !has_contact_permission('projects', get_primary_contact_user_id($data->clientid))
                    && total_rows(db_prefix() . 'contacts', ['userid' => $data->clientid]) > 0
                ) {

                    $data->project_customer_permission_warning = true;
                }
            }

            $data = $this->Api_model->get_api_custom_data($data, "projects", $id);

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

        $data = $this->Api_model->search('project', $key);
        if ($data) {
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
                $data[$key]['client_data'] = $this->clients_model->get($value['clientid']);

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
                if (!has_permission('tasks', '', 'view')) {
                    $__total_where_tasks .= ' AND ' . db_prefix() . 'tasks.id IN (SELECT taskid FROM ' . db_prefix() . 'task_assigned WHERE staffid = ' . get_staff_user_id() . ')';

                    if (get_option('show_all_tasks_for_project_member') == 1) {
                        $__total_where_tasks .= ' AND (rel_type="project" AND rel_id IN (SELECT project_id FROM ' . db_prefix() . 'project_members WHERE staff_id=' . get_staff_user_id() . '))';
                    }
                }

                $__total_where_tasks = hooks()->apply_filters('admin_total_project_tasks_where', $__total_where_tasks, $value['id']);

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
                    !has_contact_permission('projects', get_primary_contact_user_id($data[$key]['clientid']))
                    && total_rows(db_prefix() . 'contacts', ['userid' => $data[$key]['clientid']]) > 0
                ) {

                    $data[$key]['project_customer_permission_warning'] = true;
                }
            }
            $data = $this->Api_model->get_api_custom_data($data, "projects");

            $this->response($data, REST_Controller::HTTP_OK); // OK (200) being the HTTP response code
        } else {
            $this->response([
                'status' => FALSE,
                'message' => 'No data found'
            ], REST_Controller::HTTP_OK); // NOT_FOUND (404) being the HTTP response code
        }
    }

    public function data_discussions_get($id = '')
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

    public function chart_data_get($id, $type = 'this_month')
    {
        $billing_type = get_project_billing_type($id);
        $chart        = [];
        $has_permission_create = has_permission('projects', '', 'create');
        // If don't have permission for projects create show only bileld time
        if (!$has_permission_create) {
            $timesheets_type = 'total_logged_time_only';
        } else {
            if ($billing_type == 2 || $billing_type == 3) {
                $timesheets_type = 'billable_unbilled';
            } else {
                $timesheets_type = 'total_logged_time_only';
            }
        }

        $chart             = [];
        $chart['xAxis']['data']   = [];
        $chart['series']   = [];

        $chart['series'][] = [
            'name'            => ($timesheets_type == 'billable_unbilled' ? str_replace(':', '', _l('project_overview_billable_hours')) : str_replace(':', '', _l('project_overview_logged_hours'))),
            'type'            => 'bar',
            'itemStyle'       => [
                'borderRadius' => 8
            ],
            'barWidth'        => 11,
            'barMinHeight'    => 3,
            'data'            => [],
            'color'           => [],
            'backgroundColor' => [],
            'borderColor'     => [],
            'borderWidth'     => 1,
        ];

        if ($timesheets_type == 'billable_unbilled') {
            $chart['series'][] = [
                'name'            => str_replace(':', '', _l('project_overview_unbilled_hours')),
                'type'            => 'bar',
                'itemStyle'       => [
                    'borderRadius' => 8
                ],
                'barWidth'        => 11,
                'barMinHeight'    => 3,
                'data'            => [],
                'color'           => [],
                'borderColor'     => [],
                'borderWidth'     => 1,
            ];
        }

        $temp_weekdays_data = [];
        $weeks              = [];
        $where_time         = '';

        if ($type == 'this_month') {
            $beginThisMonth = date('Y-m-01');
            $endThisMonth   = date('Y-m-t 23:59:59');

            $weeks_split_start = date('Y-m-d', strtotime($beginThisMonth));
            $weeks_split_end   = date('Y-m-d', strtotime($endThisMonth));

            $where_time = 'start_time BETWEEN ' . strtotime($beginThisMonth) . ' AND ' . strtotime($endThisMonth);
        } elseif ($type == 'last_month') {
            $beginLastMonth = date('Y-m-01', strtotime('-1 MONTH'));
            $endLastMonth   = date('Y-m-t 23:59:59', strtotime('-1 MONTH'));

            $weeks_split_start = date('Y-m-d', strtotime($beginLastMonth));
            $weeks_split_end   = date('Y-m-d', strtotime($endLastMonth));

            $where_time = 'start_time BETWEEN ' . strtotime($beginLastMonth) . ' AND ' . strtotime($endLastMonth);
        } elseif ($type == 'last_week') {
            $beginLastWeek = date('Y-m-d', strtotime('monday last week'));
            $endLastWeek   = date('Y-m-d 23:59:59', strtotime('sunday last week'));
            $where_time    = 'start_time BETWEEN ' . strtotime($beginLastWeek) . ' AND ' . strtotime($endLastWeek);
        } else {
            $beginThisWeek = date('Y-m-d', strtotime('monday this week'));
            $endThisWeek   = date('Y-m-d 23:59:59', strtotime('sunday this week'));
            $where_time    = 'start_time BETWEEN ' . strtotime($beginThisWeek) . ' AND ' . strtotime($endThisWeek);
        }

        if ($type == 'this_week' || $type == 'last_week') {
            $chart['xAxis']['data'] = mpc_get_short_weekdays();
            $weekDay = date('w', strtotime(date('Y-m-d H:i:s')));
            $i       = 0;
            foreach (get_weekdays_original() as $day) {
                if ($weekDay != '0') {
                    $chart['xAxis']['data'][$i] = date('d', strtotime($day . ' ' . str_replace('_', ' ', $type))) . ' - ' . $chart['xAxis']['data'][$i];
                } else {
                    if ($type == 'this_week') {
                        $strtotime = 'last ' . $day;
                        if ($day == 'Sunday') {
                            $strtotime = 'sunday this week';
                        }
                        $chart['xAxis']['data'][$i] = date('d', strtotime($strtotime)) . ' - ' . $chart['xAxis']['data'][$i];
                    } else {
                        $strtotime                   = $day . ' last week';
                        $chart['xAxis']['data'][$i] = date('d', strtotime($strtotime)) . ' - ' . $chart['xAxis']['data'][$i];
                    }
                }
                $i++;
            }
        } elseif ($type == 'this_month' || $type == 'last_month') {
            $weeks_split_start = new DateTime($weeks_split_start);
            $weeks_split_end   = new DateTime($weeks_split_end);
            $weeks             = get_weekdays_between_dates($weeks_split_start, $weeks_split_end);
            $total_weeks       = count($weeks);
            for ($i = 1; $i <= $total_weeks; $i++) {
                array_push($chart['xAxis']['data'], split_weeks_chart_label($weeks, $i));
            }
        }

        $loop_break = ($timesheets_type == 'billable_unbilled') ? 2 : 1;

        for ($i = 0; $i < $loop_break; $i++) {
            $temp_weekdays_data = [];
            // Store the weeks in new variable for each loop to prevent duplicating
            $tmp_weeks = $weeks;

            $color = '3, 169, 244';

            $where = 'task_id IN (SELECT id FROM ' . db_prefix() . 'tasks WHERE rel_type = "project" AND rel_id = "' . $this->db->escape_str($id) . '"';

            if ($timesheets_type != 'total_logged_time_only') {
                $where .= ' AND billable=1';
                if ($i == 1) {
                    $color = '252, 45, 66';
                    $where .= ' AND billed = 0';
                }
            }

            $where .= ')';
            $this->db->where($where_time);
            $this->db->where($where);
            if (!$has_permission_create) {
                $this->db->where('staff_id', get_staff_user_id());
            }
            $timesheets = $this->db->get(db_prefix() . 'taskstimers')->result_array();

            foreach ($timesheets as $t) {
                $total_logged_time = 0;
                if ($t['end_time'] == null) {
                    $total_logged_time = time() - $t['start_time'];
                } else {
                    $total_logged_time = $t['end_time'] - $t['start_time'];
                }

                if ($type == 'this_week' || $type == 'last_week') {
                    $weekday = date('N', $t['start_time']);
                    if (!isset($temp_weekdays_data[$weekday])) {
                        $temp_weekdays_data[$weekday] = 0;
                    }
                    $temp_weekdays_data[$weekday] += $total_logged_time;
                } else {
                    // months - this and last
                    $w = 1;
                    foreach ($tmp_weeks as $week) {
                        $start_time_date = strftime('%Y-%m-%d', $t['start_time']);
                        if (!isset($tmp_weeks[$w]['total'])) {
                            $tmp_weeks[$w]['total'] = 0;
                        }
                        if (in_array($start_time_date, $week)) {
                            $tmp_weeks[$w]['total'] += $total_logged_time;
                        }
                        $w++;
                    }
                }
            }

            if ($type == 'this_week' || $type == 'last_week') {
                ksort($temp_weekdays_data);
                for ($w = 1; $w <= 7; $w++) {
                    $total_logged_time = 0;
                    if (isset($temp_weekdays_data[$w])) {
                        $total_logged_time = $temp_weekdays_data[$w];
                    }
                    array_push($chart['series'][$i]['data'], sec2qty($total_logged_time));
                    array_push($chart['series'][$i]['color'], 'rgba(' . $color . ',0.8)');
                    array_push($chart['series'][$i]['borderColor'], 'rgba(' . $color . ',1)');
                }
            } else {
                // loop over $tmp_weeks because the unbilled is shown twice because we auto increment twice
                // months - this and last
                foreach ($tmp_weeks as $week) {
                    $total = 0;
                    if (isset($week['total'])) {
                        $total = $week['total'];
                    }
                    $total_logged_time = $total;
                    array_push($chart['series'][$i]['data'], sec2qty($total_logged_time));
                    array_push($chart['series'][$i]['color'], 'rgba(' . $color . ',0.8)');
                    array_push($chart['series'][$i]['borderColor'], 'rgba(' . $color . ',1)');
                }
            }
        }

        $this->response($chart, REST_Controller::HTTP_OK);
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

    public function data_files_delete($id)
    {
        $id = $this->security->xss_clean($id);
        if (empty($id) && !is_numeric($id)) {
            $message = array(
                'status' => FALSE,
                'message' => 'Invalid File ID'
            );
            $this->response($message, REST_Controller::HTTP_OK);
        } else {
            if ($this->projects_model->remove_file($id)) {
                $message = array(
                    'status' => TRUE,
                    'message' => 'File Delete Successful.'
                );
                $this->response($message, REST_Controller::HTTP_OK);
            } else {
                $message = array(
                    'status' => FALSE,
                    'message' => 'File Delete Fail.'
                );
                $this->response($message, REST_Controller::HTTP_OK);
            }
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
        $id = $this->projects_model->add_discussion($this->input->post());
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
    /* Add project members / ajax */
    public function data_add_member_post($project_id)
    {
        if (has_permission('projects', '', 'edit')) {
            $output = $this->projects_model->add_edit_members($this->input->post(), $project_id);
            if ($output) {
                // success
                $message = array(
                    'status' => TRUE,
                    'message' => 'Project members updates.',
                    'insert_id' => $output
                );
                $this->response($message, REST_Controller::HTTP_OK);
            }
        }

        $message = array(
            'status' => FALSE,
            'message' => 'Fail to update project member.'
        );
        $this->response($message, REST_Controller::HTTP_OK);
    }

    public function save_note_post($project_id)
    {

        $output = $this->projects_model->save_note($this->input->post(null, false), $project_id);
        if ($output) {
            // success
            $message = array(
                'status' => TRUE,
                'message' => _l('updated_successfully', _l('project_note')),
                'insert_id' => $output
            );
            $this->response($message, REST_Controller::HTTP_OK);
        }

        $message = array(
            'status' => FALSE,
            'message' => 'Fail to update personal note'
        );
        $this->response($message, REST_Controller::HTTP_OK);
    }

    public function data_post()
    {
        $this->form_validation->set_rules('name', 'Project Name', 'trim|required|max_length[600]', array('is_unique' => 'This %s already exists please enter another Project Name'));
        $this->form_validation->set_rules('billing_type', 'Billing Type', 'trim|required', array('is_unique' => 'This %s already exists please enter another Project Billing Type'));
        $this->form_validation->set_rules('start_date', 'Project Start Date', 'trim|required', array('is_unique' => 'This %s already exists please enter another Project Start Date'));
        $this->form_validation->set_rules('status', 'Project Status', 'trim|required', array('is_unique' => 'This %s already exists please enter another Project Status'));
        $related = $this->input->post('rel_type', TRUE);
        $this->form_validation->set_rules('clientid', ($related !== null) ? ucwords($related) : '', 'trim|required|max_length[11]', array('is_unique' => 'This %s already exists please enter another Project Name'));
        if ($this->form_validation->run() == FALSE) {
            // form validation error
            $message = array(
                'status' => FALSE,
                'error' => $this->form_validation->error_array(),
                'message' => validation_errors()
            );
            $this->response($message, REST_Controller::HTTP_OK);
        } else {
            $project_members = $this->Api_model->value($this->input->post('project_members', TRUE));
            $insert_data = $this->input->post();

            if ($project_members != '') {
                $insert_data['project_members'] = $project_members;
            }

            if (!empty($this->input->post('custom_fields', TRUE))) {
                $insert_data['custom_fields'] = $this->Api_model->value($this->input->post('custom_fields', TRUE));
            }
            // insert data                    
            $this->load->model('projects_model');
            $output = $this->projects_model->add($insert_data);
            if ($output > 0 && !empty($output)) {
                handle_project_file_uploads($output);
                // success
                $message = array(
                    'status' => TRUE,
                    'message' => 'Project add successful.',
                    'insert_id' => $output
                );
                $this->response($message, REST_Controller::HTTP_OK);
            } else {
                // error
                $message = array(
                    'status' => FALSE,
                    'message' => 'Project add failed.'
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
                'message' => 'Invalid Project ID'
            );
            $this->response($message, REST_Controller::HTTP_OK);
        } else {
            // delete data
            $this->load->model('projects_model');
            $output = $this->projects_model->delete($id);
            if ($output === TRUE) {
                // success
                $message = array(
                    'status' => TRUE,
                    'message' => 'Project Delete Successful.'
                );
                $this->response($message, REST_Controller::HTTP_OK);
            } else {
                // error
                $message = array(
                    'status' => FALSE,
                    'message' => 'Project Delete Fail.'
                );
                $this->response($message, REST_Controller::HTTP_OK);
            }
        }
    }

    public function data_discussions_delete($id = '')
    {
        $id = $this->security->xss_clean($id);
        if (empty($id) && !is_numeric($id)) {
            $message = array(
                'status' => FALSE,
                'message' => 'Invalid Discussion ID'
            );
            $this->response($message, REST_Controller::HTTP_OK);
        } else {
            // delete data
            $this->load->model('projects_model');
            $success = $this->projects_model->delete_discussion($id);
            if ($success) {
                // success
                $message = array(
                    'status' => TRUE,
                    'message' => _l('project_discussion_deleted')
                );
                $this->response($message, REST_Controller::HTTP_OK);
            } else {
                // error
                $message = array(
                    'status' => FALSE,
                    'message' => _l('project_discussion_failed_to_delete')
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
                'message' => 'Invalid Project ID'
            );
            $this->response($message, REST_Controller::HTTP_OK);
        } else {

            $update_data = $this->input->post();
            // update data
            $this->load->model('projects_api_model');
            $output = $this->projects_api_model->update($update_data, $id);
            if ($output == true && !empty($output)) {
                // success
                $message = array(
                    'status' => TRUE,
                    'message' => 'Project Update Successful.'
                );
                $this->response($message, REST_Controller::HTTP_OK);
            } else {
                // error
                $message = array(
                    'status' => FALSE,
                    'message' => 'Project Update Fail.'
                );
                $this->response($message, REST_Controller::HTTP_OK);
            }
        }
    }

    public function data_discussions_put($id = '')
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
                'message' => 'Invalid Project ID'
            );
            $this->response($message, REST_Controller::HTTP_OK);
        } else {

            $update_data = $this->input->post();
            $id   = $update_data['id'];
            unset($update_data['id']);

            // update data
            $output = $this->projects_model->edit_discussion($update_data, $id);
            if ($output == true && !empty($output)) {
                // success
                $message = array(
                    'status' => TRUE,
                    'message' => _l('updated_successfully', _l('project_discussion'))
                );
                $this->response($message, REST_Controller::HTTP_OK);
            } else {
                // error
                $message = array(
                    'status' => FALSE,
                    'message' => 'Project Discussion Update Fail.'
                );
                $this->response($message, REST_Controller::HTTP_OK);
            }
        }
    }

    public function mark_action_status_get($status, $id)
    {
        if (empty($id) && !is_numeric($id)) {
            $message = array(
                'status' => FALSE,
                'message' => 'Invalid Project ID'
            );
            $this->response($message, REST_Controller::HTTP_OK);
        }
        $data = ['project_id' => $id, 'status_id' => $status, 'mark_all_tasks_as_completed' => 0, 'notify_project_members_status_change' => 0, 'cancel_recurring_tasks' => 'NULL'];
        $success = $this->projects_model->mark_as($data);
        if ($success) {
            $message = array(
                'status' => TRUE,
                'message' => _l('project_status_changed_success'),
            );
            $this->response($message, REST_Controller::HTTP_OK);
        } else {
            // error
            $message = array(
                'status' => FALSE,
                'message' => _l('project_status_changed_fail')
            );
            $this->response($message, REST_Controller::HTTP_OK);
        }
    }

    public function change_activity_visibility_get($id, $status)
    {
        if (empty($id) && !is_numeric($id)) {
            $message = array(
                'status' => FALSE,
                'message' => 'Invalid Project ID'
            );
            $this->response($message, REST_Controller::HTTP_OK);
        }

        if (has_permission('projects', '', 'create')) {
            $this->projects_model->change_activity_visibility($id, $status);
            $message = array(
                'status' => TRUE,
                'message' => _l('project_activity_visibility_changed'),
            );
            $this->response($message, REST_Controller::HTTP_OK);
        } else {
            // error
            $message = array(
                'status' => FALSE,
                'message' => _l('project_activity_visibility_change_failed')
            );
            $this->response($message, REST_Controller::HTTP_OK);
        }
    }

    public function view_as_customer($id, $clientid)
    {
        if (is_admin()) {
            login_as_client($clientid);
            $message = array(
                'status' => TRUE,
                'message' => _l('fail')
            );
            $this->response($message, REST_Controller::HTTP_OK);
        }
    }

    private function _search_discussions($q, $limit = 0, $api = false)
    {
        $result = [
            'result'         => [],
            'type'           => 'discussions',
            'search_heading' => _l('discussions'),
        ];

        if (has_permission('projects', '', 'view') || true == $api) {
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

    public function copy_post($project_id)
    {
        if (has_permission('projects', '', 'create')) {
            $id = $this->projects_model->copy($project_id, $this->input->post());
            if ($id) {
                $message = array(
                    'status' => TRUE,
                    'message' => _l('project_copied_successfully'),
                    'insert_id' => $id
                );
                $this->response($message, REST_Controller::HTTP_OK);
            } else {
                $message = array(
                    'status' => FALSE,
                    'message' => _l('failed_to_copy_project')
                );
                $this->response($message, REST_Controller::HTTP_OK);
            }
        }
    }
}
