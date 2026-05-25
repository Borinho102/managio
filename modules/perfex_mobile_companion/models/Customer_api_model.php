<?php

defined('BASEPATH') or exit('No direct script access allowed');
class Customer_api_model extends App_Model
{
    public function __construct()
    {
        parent::__construct();
    }

    public function get_table($name, $id, $where = [])
    {
        switch ($name) {
            case 'projects':
                $this->load->model('Projects_api_model');

                return $this->Projects_api_model->get($id);
                break;
            case 'tasks':
                $this->load->model('Tasks_api_model');
                return $this->Tasks_api_model->get($id);
                break;
            case 'staffs':
                $this->load->model('Staff_api_model');

                return $this->Staff_api_model->get($id);
                break;
            case 'tickets':
                $this->load->model('Tickets_model');

                return $this->Tickets_model->get_ticket_by_id($id);
                break;
            case 'leads':
                $this->load->model('Leads_model');

                return $this->Leads_model->get($id);
                break;
            case 'clients':
                $this->load->model('Clients_api_model');
                return $this->Clients_api_model->get($id, [], [
                    'limit' => $this->input->get('limit'),
                    'offset' => $this->input->get('offset'),
                    'filters' => $this->input->get('filters')
                ]);
                break;
            case 'contracts':
                $this->load->model('Contracts_model');

                return $this->Contracts_model->get($id);
                break;
            case 'invoices':
                $this->load->model('Invoices_model');
                $data = $this->Invoices_model->get($id);
                if (!empty($data) && !empty($id)) {
                    $data->items = $this->get_api_custom_data($data->items, 'items', '', true);
                }

                return $data;
                break;
            case 'estimates':
                $this->load->model('Estimates_model');
                $data = $this->Estimates_model->get($id);
                if (!empty($data) && !empty($id)) {
                    $data->items = $this->get_api_custom_data($data->items, 'items', '', true);
                }

                return $data;
                break;
            case 'departments':
                $this->load->model('Departments_model');

                return $this->Departments_model->get($id);
                break;
            case 'payments':
                $this->load->model('Payments_model');

                return $this->Payments_model->get($id);
                break;
            case 'roles':
                $this->load->model('Roles_model');

                return $this->Roles_model->get($id);
                break;
            case 'proposals':
                $this->load->model('Proposals_model');
                
                $data = $this->Proposals_model->get($id);
                if (!empty($data) && !empty($id)) {
                    $data->items = $this->get_api_custom_data($data->items, 'items', '', true);
                }

                return $data;
                break;
            case 'knowledge':
                $this->load->model('Knowledge_base_model');

                return $this->Knowledge_base_model->get($id);
                break;
            case 'goals':
                $this->load->model('Goals_model');

                return $this->Goals_model->get($id);
                break;
            case 'currencies':
                $this->load->model('Currencies_model');

                return $this->Currencies_model->get($id);
                break;
            case 'annex':
                $this->load->model('Annex_model');

                return $this->Annex_model->get($id);
                break;
            case 'contacts':
                $this->load->model('Clients_model');

                return $this->clients_model->get_contact($id);
                break;
            case 'all_contacts':
                $this->load->model('Clients_model');

                return $this->clients_model->get_contacts($id);
                break;
            case 'invoices':
                $this->load->model('invoices_model');

                return $this->invoices_model->get($id);
                break;
            case 'invoice_items':
                $this->load->model('invoice_items_model');

                return $this->invoice_items_model->get($id);
                break;
            case 'milestones':
                return $this->get_milestones_api($id);
                break;
            case 'expenses':
                return $this->get_expenses_api($id);
                break;
            case 'creditnotes':
                $this->load->model('Credit_notes_model');
                $data = $this->Credit_notes_model->get($id);
                if (!empty($data) && !empty($id)) {
                    $data->items = $this->get_api_custom_data($data->items, "items", '', true);
                }
                return $data;
                break;
            case 'countries':
                $data = get_all_countries();
                return $data;
                break;
            case 'subscriptions':
                $this->load->model('Subscriptions_model');
                $data = $this->Subscriptions_model->get_by_id($id);
                return $data;
                break;
            default:
                return '';
                break;
        }
    }

    public function value($value)
    {
        if ($value) {
            return $value;
        }

        return '';
    }

    // public function search($type, $key)
    // {
    // }

    public function _search_tickets_for_client($q, $limit = 0, $api = false)
    {

        $fields = get_custom_fields('tickets');
        $result = [
            'result'         => [],
            'type'           => 'tickets',
            'search_heading' => _l('support_tickets'),
        ];

        //     'project_id'                   => $project_id['project_id'],
        // ];

        // }

        $this->db->select('*,
                ' . db_prefix() . 'tickets.userid,
                ' . db_prefix() . 'tickets.name as from_name,
                ' . db_prefix() . 'tickets.email as ticket_email, 
                ' . db_prefix() . 'tickets.status,
                ' . db_prefix() . 'tickets_status.name as status_name,
                ' . db_prefix() . 'tickets.ticketid,
                ' . db_prefix() . 'tickets.admin, 
                ' . db_prefix() . 'tickets.date,
                ' . db_prefix() . 'departments.name as department_name, 
                ' . db_prefix() . 'tickets_priorities.name as priority_name, 
                ' . db_prefix() . 'services.name as service_name, 
               
                CONCAT(' . db_prefix() . 'contacts.firstname, \' \', ' . db_prefix() . 'contacts.lastname) as contact_full_name,
                ' . db_prefix() . 'contacts.firstname as user_firstname, 
                ' . db_prefix() . 'contacts.lastname as user_lastname,
                ' . db_prefix() . 'contacts.email,

                ' . db_prefix() . 'staff.firstname as staff_firstname, 
                ' . db_prefix() . 'staff.lastname as staff_lastname,

                statuscolor,
                service, 
                lastreply,
                message,
                subject,
                department,
                priority,
                adminread,
                clientread,
                date,
                (SELECT GROUP_CONCAT(name SEPARATOR ",") FROM ' . db_prefix() . 'taggables JOIN ' . db_prefix() . 'tags ON ' . db_prefix() . 'taggables.tag_id = ' . db_prefix() . 'tags.id WHERE rel_id = ' . db_prefix() . 'tickets.ticketid and rel_type="ticket" ORDER by tag_order ASC) as tags
            ');

        $this->db->join(db_prefix() . 'departments', db_prefix() . 'departments.departmentid = ' . db_prefix() . 'tickets.department', 'left');
        $this->db->join(db_prefix() . 'tickets_status', db_prefix() . 'tickets_status.ticketstatusid = ' . db_prefix() . 'tickets.status', 'left');
        $this->db->join(db_prefix() . 'services', db_prefix() . 'services.serviceid = ' . db_prefix() . 'tickets.service', 'left');
        $this->db->join(db_prefix() . 'clients', db_prefix() . 'clients.userid = ' . db_prefix() . 'tickets.userid', 'left');
        $this->db->join(db_prefix() . 'contacts', db_prefix() . 'contacts.id = ' . db_prefix() . 'tickets.contactid', 'left');
        $this->db->join(db_prefix() . 'staff', db_prefix() . 'staff.staffid = ' . db_prefix() . 'tickets.admin', 'left');
        $this->db->join(db_prefix() . 'tickets_priorities', db_prefix() . 'tickets_priorities.priorityid = ' . db_prefix() . 'tickets.priority', 'left');
        $this->db->where(db_prefix() . 'tickets.userid', get_client_user_id());
        $this->db->where(db_prefix() . 'tickets.contactid', get_contact_user_id());
        $this->db->from(db_prefix() . 'tickets');

        if (!_startsWith($q, '#')) {
            $where_string = '';
            foreach ($fields as $key => $value) {
                $this->db->join(db_prefix() . 'customfieldsvalues as ctable_' . $key . '', db_prefix() . 'tickets.ticketid = ctable_' . $key . '.relid and ctable_' . $key . '.fieldto="tickets" AND ctable_' . $key . '.fieldid=' . $value['id'], 'LEFT');
                $where_string .= ' OR ctable_' . $key . '.value LIKE "%' . $q . '%"';
            }
            $this->db->where('(
                    ticketid LIKE "' . $q . '%"
                    OR subject LIKE "%' . $q . '%"
                    OR message LIKE "%' . $q . '%"
                    OR tblcontacts.email LIKE "%' . $q . '%"
                    OR CONCAT(' . db_prefix() . 'contacts.firstname, \' \', ' . db_prefix() . 'contacts.lastname) LIKE "%' . $q . '%"
                    OR company LIKE "%' . $q . '%"
                    OR vat LIKE "%' . $q . '%"
                    OR tblcontacts.phonenumber LIKE "%' . $q . '%"
                    OR tblclients.phonenumber LIKE "%' . $q . '%"
                    OR city LIKE "%' . $q . '%"
                    OR state LIKE "%' . $q . '%"
                    OR address LIKE "%' . $q . '%"
                    OR tbldepartments.name LIKE "%' . $q . '%"
                    ' . $where_string . '
                    )');
        } else {
            $this->db->where('ticketid IN
                    (SELECT rel_id FROM tbltags_in WHERE tag_id IN
                    (SELECT id FROM tbltags WHERE name="' . strafter($q, '#') . '")
                    AND tbltags_in.rel_type=\'ticket\' GROUP BY rel_id HAVING COUNT(tag_id) = 1)
                    ');
        }

        if (!empty($this->input->get('filters'))) {
            $filters = json_decode($this->input->get('filters'), true);

            if (!empty($filters['status'])) {
                $this->db->where_in(db_prefix() . 'tickets.status', $filters['status']);
            }

            if (!empty($filters['assigned'])) {
                $this->db->where_in(db_prefix() . 'tickets.assigned', $filters['assigned']);
            }
            if (!empty($filters['project_id'])) {
                $this->db->where_in(db_prefix() . 'tickets.project_id', $filters['project_id']);
            }
        }

        if (!empty($this->input->get('sort'))) {
            $sorting = json_decode($this->input->get('sort'), true);
            $this->db->order_by($sorting['sort_by'], $sorting['order']);
        } else {
            $this->db->order_by('ticketid', 'DESC');
        }

        if (is_numeric($this->input->get('limit')) && is_numeric($this->input->get('offset'))) {
            $this->db->limit($this->input->get('limit'));
            $this->db->offset($this->input->get('offset'));
        }

        $result['result'] = $this->db->get()->result_array();
        // }

        return $result;

        // }
    }

    public function _search_leads($q, $limit = 0, $where = [], $api = false)
    {
        $fields = get_custom_fields('leads');
        $result = [
            'result'         => [],
            'type'           => 'leads',
            'search_heading' => _l('leads'),
        ];

        $has_permission_view = has_permission('leads', '', 'view');
        if (is_staff_member() || true == $api) {
            // Leads
            $this->db->select(db_prefix() . 'leads.*,
                ' . db_prefix() . 'leads_status.name as status_name,
                ' . db_prefix() . 'leads_sources.name as source_name,
                (SELECT GROUP_CONCAT(name SEPARATOR ",") FROM ' . db_prefix() . 'taggables JOIN ' . db_prefix() . 'tags ON ' . db_prefix() . 'taggables.tag_id = ' . db_prefix() . 'tags.id WHERE rel_id = ' . db_prefix() . 'leads.id and rel_type="lead" ORDER by tag_order ASC LIMIT 1) as tags,
                color,
                firstname as assigned_firstname
            ');
            $this->db->join(db_prefix() . 'leads_status', db_prefix() . 'leads_status.id = ' . db_prefix() . 'leads.status', 'left');
            $this->db->join(db_prefix() . 'leads_sources', db_prefix() . 'leads_sources.id = ' . db_prefix() . 'leads.source', 'left');
            $this->db->join(db_prefix() . 'staff', db_prefix() . 'staff.staffid = ' . db_prefix() . 'leads.assigned', 'left');
            $this->db->from(db_prefix() . 'leads');

            if (!_startsWith($q, '#')) {
                $where_string = '';
                foreach ($fields as $key => $value) {
                    $this->db->join(db_prefix() . 'customfieldsvalues as ctable_' . $key . '', db_prefix() . 'leads.id = ctable_' . $key . '.relid and ctable_' . $key . '.fieldto="leads" AND ctable_' . $key . '.fieldid=' . $value['id'], 'LEFT');
                    $where_string .= ' OR ctable_' . $key . '.value LIKE "%' . $q . '%"';
                }
                $this->db->where('(' . db_prefix() . 'leads.name LIKE "%' . $q . '%"
                    OR title LIKE "%' . $q . '%"
                    OR company LIKE "%' . $q . '%"
                    OR zip LIKE "%' . $q . '%"
                    OR city LIKE "%' . $q . '%"
                    OR state LIKE "%' . $q . '%"
                    OR address LIKE "%' . $q . '%"
                    OR ' . db_prefix() . 'leads.email LIKE "%' . $q . '%"
                    OR ' . db_prefix() . 'leads.phonenumber LIKE "%' . $q . '%"
                    ' . $where_string . '
                    )');
            } else {
                $this->db->where('id IN
                    (SELECT rel_id FROM tbltags_in WHERE tag_id IN
                    (SELECT id FROM tbltags WHERE name="' . strafter($q, '#') . '")
                    AND tbltags_in.rel_type=\'lead\' GROUP BY rel_id HAVING COUNT(tag_id) = 1)
                    ');
            }

            $this->db->where('client_id < 1');

            if (!empty($this->input->get('filters'))) {
                $filters = json_decode($this->input->get('filters'), true);

                if (!empty($filters['status'])) {
                    $this->db->where_in(db_prefix() . 'leads.status', $filters['status']);
                }

                if (!empty($filters['source'])) {
                    $this->db->where_in(db_prefix() . 'leads.source', $filters['source']);
                }

                if (has_permission('leads', '', 'view') && !empty($filters['assigned'])) {
                    $this->db->where_in(db_prefix() . 'leads.assigned', $filters['assigned']);
                }
            }

            if (!$has_permission_view) {
                $this->db->where('(assigned = ' . get_staff_user_id() . ' OR addedfrom = ' . get_staff_user_id() . ' OR is_public=1)');
            }

            if (!empty($this->input->get('sort'))) {
                $sorting = json_decode($this->input->get('sort'), true);
                $this->db->order_by($sorting['sort_by'], $sorting['order']);
            } else {
                $this->db->order_by('id', 'DESC');
            }

            if (is_numeric($this->input->get('limit')) && is_numeric($this->input->get('offset'))) {
                $this->db->limit($this->input->get('limit'));
                $this->db->offset($this->input->get('offset'));
            }

            $result['result'] = $this->db->get()->result_array();
        }

        return $result;
    }

    public function _search_invoices_for_client($q, $limit = 0, $where = [], $api = false)
    {
        $fields = get_custom_fields('invoice');
        $result = [
            'result'         => [],
            'type'           => 'invoices',
            'search_heading' => _l('invoices'),
        ];
        $has_permission_view_invoices     = has_contact_permission('invoices');
        $has_permission_view_invoices_own = has_contact_permission('invoices');

        if ($has_permission_view_invoices || $has_permission_view_invoices_own || true == $api) {
            if (is_numeric($q)) {
                $q = trim($q);
                $q = ltrim($q, '0');
            } elseif (startsWith($q, get_option('invoice_prefix'))) {
                $q = strafter($q, get_option('invoice_prefix'));
                $q = trim($q);
                $q = ltrim($q, '0');
            }

            $invoice_fields    = prefixed_table_fields_array(db_prefix() . 'invoices');
            $clients_fields    = prefixed_table_fields_array(db_prefix() . 'clients');
            // Invoices
            $this->db->select(
                implode(',', $invoice_fields) . ',
                ' . implode(',', $clients_fields) . ',
                ' . db_prefix() . 'invoices.id as invoiceid,
                ' . db_prefix() . 'currencies.name as currency_name,
                ' . db_prefix() . 'currencies.symbol,
                ' . db_prefix() . 'currencies.decimal_separator,
                ' . db_prefix() . 'currencies.thousand_separator,
                ' . db_prefix() . 'currencies.placement,
                ' . get_sql_select_client_company() . ',
                ' . db_prefix() . 'projects.name as project_name,
                (SELECT GROUP_CONCAT(name SEPARATOR ",") FROM ' . db_prefix() . 'taggables JOIN ' . db_prefix() . 'tags ON ' . db_prefix() . 'taggables.tag_id = ' . db_prefix() . 'tags.id WHERE rel_id = ' . db_prefix() . 'invoices.id and rel_type="invoice" ORDER by tag_order ASC) as tags
            '
            );

            $this->db->where_not_in(db_prefix() . 'invoices.status', [6]);
            $this->db->where(db_prefix() . 'invoices.clientid', get_client_user_id());
            $this->db->from(db_prefix() . 'invoices');
            $this->db->join(db_prefix() . 'clients', db_prefix() . 'clients.userid = ' . db_prefix() . 'invoices.clientid', 'left');
            $this->db->join(db_prefix() . 'projects', db_prefix() . 'projects.id = ' . db_prefix() . 'invoices.project_id', 'left');
            $this->db->join(db_prefix() . 'currencies', db_prefix() . 'currencies.id = ' . db_prefix() . 'invoices.currency', 'left');

            if ($q !== '') {
                if (!startsWith($q, '#')) {
                    $where_string = '';
                    foreach ($fields as $key => $value) {
                        $this->db->join(db_prefix() . 'customfieldsvalues as ctable_' . $key . '', db_prefix() . 'invoices.id = ctable_' . $key . '.relid and ctable_' . $key . '.fieldto="invoice" AND ctable_' . $key . '.fieldid=' . $value['id'], 'LEFT');
                        $where_string .= ' OR ctable_' . $key . '.value LIKE "%' . $q . '%"';
                    }
                    $this->db->where('(
                        ' . db_prefix() . 'invoices.number LIKE "' . $this->db->escape_like_str($q) . '"
                        OR
                        ' . db_prefix() . 'clients.company LIKE "%' . $this->db->escape_like_str($q) . '%" ESCAPE \'!\'
                        OR
                        ' . db_prefix() . 'invoices.clientnote LIKE "%' . $this->db->escape_like_str($q) . '%" ESCAPE \'!\'
                        OR
                        ' . db_prefix() . 'clients.vat LIKE "%' . $this->db->escape_like_str($q) . '%" ESCAPE \'!\'
                        OR
                        ' . db_prefix() . 'clients.phonenumber LIKE "%' . $this->db->escape_like_str($q) . '%" ESCAPE \'!\'
                        OR
                        ' . db_prefix() . 'clients.city LIKE "%' . $this->db->escape_like_str($q) . '%" ESCAPE \'!\'
                        OR
                        ' . db_prefix() . 'clients.state LIKE "%' . $this->db->escape_like_str($q) . '%" ESCAPE \'!\'
                        OR
                        ' . db_prefix() . 'clients.zip LIKE "%' . $this->db->escape_like_str($q) . '%" ESCAPE \'!\'
                        OR
                        ' . db_prefix() . 'clients.address LIKE "%' . $this->db->escape_like_str($q) . '%" ESCAPE \'!\'
                        OR
                        ' . db_prefix() . 'invoices.adminnote LIKE "%' . $this->db->escape_like_str($q) . '%" ESCAPE \'!\'
                        OR
                        company LIKE "%' . $this->db->escape_like_str($q) . '%" ESCAPE \'!\'
                        OR
                        ' . db_prefix() . 'invoices.billing_street LIKE "%' . $this->db->escape_like_str($q) . '%" ESCAPE \'!\'
                        OR
                        ' . db_prefix() . 'invoices.billing_city LIKE "%' . $this->db->escape_like_str($q) . '%" ESCAPE \'!\'
                        OR
                        ' . db_prefix() . 'invoices.billing_state LIKE "%' . $this->db->escape_like_str($q) . '%" ESCAPE \'!\'
                        OR
                        ' . db_prefix() . 'invoices.billing_zip LIKE "%' . $this->db->escape_like_str($q) . '%" ESCAPE \'!\'
                        OR
                        ' . db_prefix() . 'invoices.shipping_street LIKE "%' . $this->db->escape_like_str($q) . '%" ESCAPE \'!\'
                        OR
                        ' . db_prefix() . 'invoices.shipping_city LIKE "%' . $this->db->escape_like_str($q) . '%" ESCAPE \'!\'
                        OR
                        ' . db_prefix() . 'invoices.shipping_state LIKE "%' . $this->db->escape_like_str($q) . '%" ESCAPE \'!\'
                        OR
                        ' . db_prefix() . 'invoices.shipping_zip LIKE "%' . $this->db->escape_like_str($q) . '%" ESCAPE \'!\'
                        OR
                        ' . db_prefix() . 'clients.billing_street LIKE "%' . $this->db->escape_like_str($q) . '%" ESCAPE \'!\'
                        OR
                        ' . db_prefix() . 'clients.billing_city LIKE "%' . $this->db->escape_like_str($q) . '%" ESCAPE \'!\'
                        OR
                        ' . db_prefix() . 'clients.billing_state LIKE "%' . $this->db->escape_like_str($q) . '%" ESCAPE \'!\'
                        OR
                        ' . db_prefix() . 'clients.billing_zip LIKE "%' . $this->db->escape_like_str($q) . '%" ESCAPE \'!\'
                        OR
                        ' . db_prefix() . 'clients.shipping_street LIKE "%' . $this->db->escape_like_str($q) . '%" ESCAPE \'!\'
                        OR
                        ' . db_prefix() . 'clients.shipping_city LIKE "%' . $this->db->escape_like_str($q) . '%" ESCAPE \'!\'
                        OR
                        ' . db_prefix() . 'clients.shipping_state LIKE "%' . $this->db->escape_like_str($q) . '%" ESCAPE \'!\'
                        OR
                        ' . db_prefix() . 'clients.shipping_zip LIKE "%' . $this->db->escape_like_str($q) . '%" ESCAPE \'!\'
                        ' . $where_string . '
                    )');
                } else {
                    $this->db->where(db_prefix() . 'invoices.id IN
                        (SELECT rel_id FROM ' . db_prefix() . 'taggables WHERE tag_id IN
                        (SELECT id FROM ' . db_prefix() . 'tags WHERE name="' . $this->db->escape_str(strafter($q, '#')) . '")
                        AND ' . db_prefix() . 'taggables.rel_type=\'invoice\' GROUP BY rel_id HAVING COUNT(tag_id) = 1)
                    ');
                }
            }

            if (!empty($this->input->get('filters'))) {
                $filters = json_decode($this->input->get('filters'), true);

                if (!empty($filters['status'])) {
                    $this->db->where_in(db_prefix() . 'invoices.status', $filters['status']);
                }

                if (!empty($filters['sale_agent'])) {
                    $this->db->where_in(db_prefix() . 'invoices.sale_agent', $filters['sale_agent']);
                }

                if (!empty($filters['project_id'])) {
                    $this->db->where(db_prefix() . 'invoices.project_id', $filters['project_id']);
                }

                // }
            }

            $this->db->where($this->get_invoices_where_sql_for_client(get_client_user_id()));
            // }

            if (!empty($this->input->get('sort'))) {
                $sorting = json_decode($this->input->get('sort'), true);
                $this->db->order_by($sorting['sort_by'], $sorting['order']);
            } else {
                $this->db->order_by('number, YEAR(date)', 'desc');
            }

            if (is_numeric($this->input->get('limit')) && is_numeric($this->input->get('offset'))) {
                $this->db->limit($this->input->get('limit'));
                $this->db->offset($this->input->get('offset'));
            }

            $result['result'] = $this->db->get()->result_array();
        }

        return $result;
    }

    private function get_invoices_where_sql_for_client($clientid)
    {
        $CI = &get_instance();

        $whereUser = '';
        $whereUser = db_prefix() . 'invoices.clientid = ' . $CI->db->escape(get_client_user_id()) . ' 
        AND ' . db_prefix() . 'invoices.clientid IN (
            SELECT ' . db_prefix() . 'contact_permissions.userid 
            FROM ' . db_prefix() . 'contact_permissions 
            WHERE ' . db_prefix() . 'contact_permissions.userid = ' . $CI->db->escape(get_client_user_id()) . '
        )';

        // }

        return $whereUser;
    }

    public function _search_projects($q, $limit = 0, $where = false, $rel_type = null, $api = false)
    {
        $fields = get_custom_fields('projects');
        $result = [
            'result'         => [],
            'type'           => 'projects',
            'search_heading' => _l('projects'),
        ];

        $projects = has_permission('projects', '', 'view');
        // Projects
        $this->db->select(db_prefix() . 'projects.*, 
            CASE ' . db_prefix() . 'clients.company WHEN \' \' THEN (SELECT CONCAT(firstname, \' \', lastname) FROM tblcontacts WHERE userid = tblclients.userid and is_primary = 1) ELSE ' . db_prefix() . 'clients.company END as company,
            (SELECT GROUP_CONCAT(name SEPARATOR ",") FROM ' . db_prefix() . 'taggables JOIN ' . db_prefix() . 'tags ON ' . db_prefix() . 'taggables.tag_id = ' . db_prefix() . 'tags.id WHERE rel_id = ' . db_prefix() . 'projects.id and rel_type="project" ORDER by tag_order ASC) as tags,
            (SELECT GROUP_CONCAT(CONCAT(firstname, \' \', lastname) SEPARATOR ",") FROM ' . db_prefix() . 'project_members JOIN ' . db_prefix() . 'staff on ' . db_prefix() . 'staff.staffid = ' . db_prefix() . 'project_members.staff_id WHERE project_id=' . db_prefix() . 'projects.id ORDER BY staff_id) as members
        ');
        $this->db->from('tblprojects');
        if (isset($rel_type) && 'lead' == $rel_type) {
            $this->db->join('tblleads', 'tblleads.id = tblprojects.clientid');
        } else {
            $this->db->join('tblclients', 'tblclients.userid = tblprojects.clientid', 'LEFT');
            $this->db->join('tblleads', 'tblleads.id = tblprojects.clientid', 'LEFT');
        }

        if (!$projects && false == $api) {
            $this->db->where('tblprojects.id IN (SELECT project_id FROM tblprojectmembers WHERE staff_id=' . get_staff_user_id() . ')');
        }
        if (false != $where) {
            $this->db->where($where);
        }

        if (!_startsWith($q, '#')) {
            $where_string = '';
            foreach ($fields as $key => $value) {
                $this->db->join(db_prefix() . 'customfieldsvalues as ctable_' . $key . '', db_prefix() . 'projects.id = ctable_' . $key . '.relid and ctable_' . $key . '.fieldto="projects" AND ctable_' . $key . '.fieldid=' . $value['id'], 'LEFT');
                $where_string .= ' OR ctable_' . $key . '.value LIKE "%' . $q . '%"';
            }
            $this->db->where('(tblleads.company LIKE "%' . $q . '%"
                OR tblprojects.description LIKE "%' . $q . '%"
                OR tblprojects.name LIKE "%' . $q . '%"
                
                OR tblleads.phonenumber LIKE "%' . $q . '%"
                OR tblleads.city LIKE "%' . $q . '%"
                OR tblleads.zip LIKE "%' . $q . '%"
                OR tblleads.state LIKE "%' . $q . '%"
                OR tblleads.zip LIKE "%' . $q . '%"
                OR tblleads.address LIKE "%' . $q . '%"
                ' . $where_string . '
                )');
        } else {
            $this->db->where('id IN
                (SELECT rel_id FROM tbltags_in WHERE tag_id IN
                (SELECT id FROM tbltags WHERE name="' . strafter($q, '#') . '")
                AND tbltags_in.rel_type=\'project\' GROUP BY rel_id HAVING COUNT(tag_id) = 1)
                ');
        }

        if (!empty($this->input->get('filters'))) {
            $filters = json_decode($this->input->get('filters'), true);

            if (!empty($filters['clientid'])) {
                $this->db->where(db_prefix() . 'projects.clientid', $filters['clientid']);
            }

            if (!empty($filters['status'])) {
                $this->db->where_in(db_prefix() . 'projects.status', $filters['status']);
            }

            if (!empty($filters['staff_id'])) {
                $this->db->where(db_prefix() . 'projects.id IN (SELECT project_id FROM ' . db_prefix() . 'project_members WHERE staff_id=' . $filters['staff_id'] . ')');
            }
        }

        if (!has_permission('projects', '', 'view') || $this->input->post('my_projects')) {
            $this->db->where(db_prefix() . 'projects.id IN (SELECT project_id FROM ' . db_prefix() . 'project_members WHERE staff_id=' . get_staff_user_id() . ')');
        }

        if (!empty($this->input->get('sort'))) {
            $sorting = json_decode($this->input->get('sort'), true);
            $this->db->order_by($sorting['sort_by'], $sorting['order']);
        } else {
            $this->db->order_by(db_prefix() . 'projects.id', 'DESC');
        }

        if (is_numeric($this->input->get('limit')) && is_numeric($this->input->get('offset'))) {
            $this->db->limit($this->input->get('limit'));
            $this->db->offset($this->input->get('offset'));
        }

        $result['result'] = $this->db->get()->result_array();

        return $result;
    }

    public function _search_staff($q, $limit = 0, $api = false)
    {
        $result = [
            'result'         => [],
            'type'           => 'staff',
            'search_heading' => _l('staff_members'),
        ];

        if (has_permission('staff', '', 'view') || true == $api) {
            // Staff
            $fields = get_custom_fields('staff');

            $this->db->select('staff.*, CONCAT(firstname,\' \',lastname) as full_name, ' . db_prefix() . 'roles.name');
            $this->db->from(db_prefix() . 'staff');
            $this->db->join(db_prefix() . 'roles', db_prefix() . 'roles.roleid = ' . db_prefix() . 'staff.role', 'LEFT');

            $this->db->like('firstname', $q);
            $this->db->or_like('lastname', $q);
            $this->db->or_like("CONCAT(firstname, ' ', lastname)", $q, false);
            $this->db->or_like('facebook', $q);
            $this->db->or_like('linkedin', $q);
            $this->db->or_like('phonenumber', $q);
            $this->db->or_like('email', $q);
            $this->db->or_like('skype', $q);
            foreach ($fields as $key => $value) {
                $this->db->join(db_prefix() . 'customfieldsvalues as ctable_' . $key . '', db_prefix() . 'staff.staffid = ctable_' . $key . '.relid and ctable_' . $key . '.fieldto="staff" AND ctable_' . $key . '.fieldid=' . $value['id'], 'LEFT');
                $this->db->or_like('ctable_' . $key . '.value', $q);
            }

            if (!empty($this->input->get('sort'))) {
                $sorting = json_decode($this->input->get('sort'), true);
                $this->db->order_by($sorting['sort_by'], $sorting['order']);
            } else {
                $this->db->order_by('firstname', 'ASC');
            }

            if (is_numeric($this->input->get('limit')) && is_numeric($this->input->get('offset'))) {
                $this->db->limit($this->input->get('limit'));
                $this->db->offset($this->input->get('offset'));
            }

            $result['result'] = $this->db->get()->result_array();
        }

        return $result;
    }

    public function _search_tasks($q, $limit = 0, $api = false)
    {
        $result = [
            'result'         => [],
            'type'           => 'tasks',
            'search_heading' => _l('tasks'),
        ];

        if (has_permission('tasks', '', 'view') || true == $api) {
            // task
            $fields = get_custom_fields('tasks');
            $this->db->select(
                db_prefix() . 'tasks.*, 
                ' . get_sql_select_task_asignees_full_names() . ' as assignees,
                (SELECT GROUP_CONCAT(name SEPARATOR ",") FROM ' . db_prefix() . 'taggables JOIN ' . db_prefix() . 'tags ON ' . db_prefix() . 'taggables.tag_id = ' . db_prefix() . 'tags.id WHERE rel_id = ' . db_prefix() . 'tasks.id and rel_type="task" ORDER by tag_order ASC) as tags,
                ' . tasks_rel_name_select_query() . ' as rel_name
            '
            );
            $this->db->from(db_prefix() . 'tasks');
            $this->db->group_start();
            $this->db->like('name', $q);
            $this->db->or_like(db_prefix() . 'tasks.id', $q);
            foreach ($fields as $key => $value) {
                $this->db->join(db_prefix() . 'customfieldsvalues as ctable_' . $key . '', db_prefix() . 'tasks.id = ctable_' . $key . '.relid and ctable_' . $key . '.fieldto="tasks" AND ctable_' . $key . '.fieldid=' . $value['id'], 'LEFT');
                $this->db->or_like('ctable_' . $key . '.value', $q);
            }
            $this->db->group_end();
            if (!empty($this->input->get('filters'))) {
                $filters = json_decode($this->input->get('filters'), true);

                if (!empty($filters['assigned'])) {
                    $this->db->where(db_prefix() . 'tasks.id IN ( SELECT taskid from ' . db_prefix() . 'task_assigned where staffid IN (' . implode(', ', $filters['assigned']) . '))');
                }

                if (!empty($filters['status'])) {
                    $this->db->where_in(db_prefix() . 'tasks.status', $filters['status']);
                }

                if (!empty($filters['invoice_id'])) {
                    $this->db->where(db_prefix() . 'tasks.rel_id', $filters['invoice_id']);
                    $this->db->where(db_prefix() . 'tasks.rel_type', 'invoice');
                }

                if (!empty($filters['proposal_id'])) {
                    $this->db->where(db_prefix() . 'tasks.rel_id', $filters['proposal_id']);
                    $this->db->where(db_prefix() . 'tasks.rel_type', 'proposal');
                }

                if (!empty($filters['project_id'])) {
                    $this->db->where(db_prefix() . 'tasks.rel_id', $filters['project_id']);
                    $this->db->where(db_prefix() . 'tasks.rel_type', 'project');
                }

                if (!empty($filters['lead_id'])) {
                    $this->db->where(db_prefix() . 'tasks.rel_id', $filters['lead_id']);
                    $this->db->where(db_prefix() . 'tasks.rel_type', 'lead');
                }

                if (!empty($filters['rel_type']) && !empty($filters['rel_id'])) {
                    $this->db->where(db_prefix() . 'tasks.rel_type', $filters['rel_type']);
                    $this->db->where(db_prefix() . 'tasks.rel_id', $filters['rel_id']);
                }
            }

            if (!has_permission('tasks', '', 'view')) {
                $this->db->where(get_tasks_where_string(false), null, false);
            }

            $this->db->where('(CASE WHEN rel_type="project" AND rel_id IN (SELECT project_id FROM ' . db_prefix() . 'project_settings WHERE project_id=rel_id AND name="hide_tasks_on_main_tasks_table" AND value=1) THEN rel_type != "project" ELSE 1=1 END)', null, false);

            if (!empty($this->input->get('sort'))) {
                $sorting = json_decode($this->input->get('sort'), true);
                $this->db->order_by($sorting['sort_by'], $sorting['order']);
            } else {
                $this->db->order_by('id', 'DESC');
            }

            if (is_numeric($this->input->get('limit')) && is_numeric($this->input->get('offset'))) {
                $this->db->limit($this->input->get('limit'));
                $this->db->offset($this->input->get('offset'));
            }

            $result['result'] = $this->db->get()->result_array();
        }

        return $result;
    }

    public function get_user($id = '')
    {
        $this->db->select('*');
        if ('' != $id) {
            $this->db->where('id', $id);
        }

        return $this->db->get(db_prefix() . 'mobile_app_access_tokens')->result_array();
    }

    public function add_user($data)
    {
        $payload = [
            'user' => $data['user'],
            'name' => $data['name'],
        ];
        $this->load->library('Authorization_Token');
        // generate a token
        $data['token'] = $this->authorization_token->generateToken($payload);
        $today         = date('Y-m-d H:i:s');

        $data['expiration_date'] = to_sql_date($data['expiration_date'], true);
        $this->db->insert(db_prefix() . 'mobile_app_access_tokens', $data);
        $insert_id = $this->db->insert_id();
        if ($insert_id) {
            log_activity('New User Added [ID: ' . $insert_id . ', Name: ' . $data['name'] . ']');
        }

        return $insert_id;
    }

    public function update_user($data, $id)
    {
        $data['expiration_date'] = to_sql_date($data['expiration_date'], true);
        $this->db->where('id', $id);
        $this->db->update(db_prefix() . 'mobile_app_access_tokens', $data);
        if ($this->db->affected_rows() > 0) {
            log_activity('Ticket User Updated [ID: ' . $id . ' Name: ' . $data['name'] . ']');

            return true;
        }

        return false;
    }

    public function delete_user($id)
    {
        $this->db->where('id', $id);
        $this->db->delete(db_prefix() . 'mobile_app_access_tokens');
        if ($this->db->affected_rows() > 0) {
            log_activity('User Deleted [ID: ' . $id . ']');

            return true;
        }

        return false;
    }

    public function check_token($token)
    {
        $this->db->where('token', $token);
        $user = $this->db->get(db_prefix() . 'mobile_app_access_tokens')->row();
        if (isset($user)) {
            return true;
        }

        return false;
    }

    public function user_api_exists()
    {
        if ($this->input->is_ajax_request()) {
            if ($this->input->post()) {
                $lead_id          = $this->input->post('lead_id');
                $abbreviated_name = strtoupper($this->input->post('abbreviated_name'));
                if ('' != $lead_id) {
                    $this->db->where('id', $lead_id);
                    $_current_email = $this->db->get('tblleads')->row();
                    if ($_current_email->abbreviated_name == $abbreviated_name) {
                        echo json_encode(true);
                        die();
                    }
                }
                $result_lead   = true;
                $result_client = true;
                $client_id     = $this->input->post('client_id');
                $this->db->where('abbreviated_name', $abbreviated_name);
                if ('' != $client_id) {
                    $arr_id   = [];
                    $arr_id[] = $client_id;
                    $this->db->where_not_in('client_id', $arr_id);
                }

                $total_rows = $this->db->count_all_results('tblleads');

                if ($total_rows > 0) {
                    $result_lead = false;
                } else {
                    $result_lead = true;
                }
                $this->db->where('abbreviated_name', $abbreviated_name);
                if ('' != $client_id) {
                    $arr_id   = [];
                    $arr_id[] = $client_id;
                    $this->db->where_not_in('userid', $arr_id);
                }
                $total_rows = $this->db->count_all_results('tblclients');
                if ($total_rows > 0) {
                    $result_client = false;
                } else {
                    $result_client = true;
                }
                if ($result_lead && $result_client) {
                    echo json_encode(true);
                } else {
                    echo json_encode(false);
                }
                die();
            }
        }
    }

    // public function get_relation_data_mpc_api($type, $search = '')
    // {
    //     }

    //             $where_clients .= '(';
    //             $where_clients .= 'company LIKE "%' . $q . '%" OR CONCAT(firstname, " ", lastname) LIKE "%' . $q . '%" OR email LIKE "%' . $q . '%"';

    //                 $where_clients .= ' OR ctable_' . $key . '.value LIKE "%' . $q . '%"';
    //             }
    //             $where_clients .= ')';
    //         }
    //             'limit' => $this->input->get('limit'),
    //             'offset' => $this->input->get('offset'),
    //             'filters' => $this->input->get('filters'),
    //             'sort' => !empty($this->input->get('sort')) ?  json_decode($this->input->get('sort'), true) : ['sort_by' => 'company', 'order' => 'desc']
    //         ]);
    //     } elseif ('contacts' == $type) {
    //             $where_clients .= ' AND (';
    //             $where_clients .= ' company LIKE "%' . $this->db->escape_like_str($q) . '%" ESCAPE \'!\' OR CONCAT(firstname, " ", lastname) LIKE "%' . $this->db->escape_like_str($q) . '%" ESCAPE \'!\' OR email LIKE "%' . $this->db->escape_like_str($q) . '%" ESCAPE \'!\'';

    //                 $where_clients .= ' OR ctable_' . $key . '.value LIKE "%' . $q . '%"';
    //             }

    //             $where_clients .= ') AND ' . db_prefix() . 'clients.active = 1';
    //         }

    //             }
    //         }
    //         } else {
    //         }

    //         }

    //         // $this->db->order_by('id', 'DESC');

    //     } elseif ('template' == $type || 'templates' == $type) {

    //             }
    //         }

    //         }

    //     } elseif ('ticket' == $type) {
    //     } elseif ('lead' == $type || 'leads' == $type) {
    //             'junk' => 0,
    //         ], true);
    //     } elseif ('invoice' == $type || 'invoices' == $type) {
    //     } elseif ('invoice_items' == $type) {
    //             rate, 
    //             items.id, 
    //             description as name, 
    //             long_description as subtext, 
    //             unit, 
    //             t1.taxrate as taxrate_1, 
    //             t2.taxrate as taxrate_2,
    //             t1.name as taxname_1,
    //             t2.name as taxname_2,
    //             t1.id as tax_id_1,
    //             t2.id as tax_id_2,
    //             group_id,
    //             ' . db_prefix() . 'items_groups.name as group_name
    //         ');

    //         }

    //         } else {
    //         }

    //         }

    //         }
    //     } elseif ('project' == $type) {

    //             $where_projects .= '(clientid=' . $this->input->post('customer_id') . ' or clientid in (select id from tblleads where client_id=' . $this->input->post('customer_id') . ') )';
    //         }
    //             $where_projects .= ' and rel_type="' . $this->input->post('rel_type') . '" ';
    //         }

    //     } elseif ('staff' == $type) {
    //     } elseif ('tasks' == $type) {
    //     } elseif ('payments' == $type) {
    //     } elseif ('proposals' == $type || 'proposal' == $type) {
    //     } elseif ('estimates' == $type || 'estimate' == $type) {
    //     } elseif ('expenses' == $type || 'expense' == $type) {
    //     } elseif ('creditnotes' == $type) {
    //     } elseif ('milestones' == $type) {
    //             $where_milestones .= '(name LIKE "%' . $q . '%" OR id LIKE "%' . $q . '%")';
    //         }
    //     } elseif ('contracts' == $type) {
    //     }

    // }

    public function get_milestones_api($id = '', $where = [])
    {
        $this->db->select('*, (SELECT COUNT(id) FROM ' . db_prefix() . 'tasks WHERE milestone=' . db_prefix() . 'milestones.id) as total_tasks, (SELECT COUNT(id) FROM ' . db_prefix() . 'tasks WHERE rel_type="project" and milestone=' . db_prefix() . 'milestones.id AND status=5) as total_finished_tasks');
        if ('' != $id) {
            $this->db->where('id', $id);
        }
        if ((is_array($where) && count($where) > 0) || (is_string($where) && '' != $where)) {
            $this->db->where($where);
        }

        if (!empty($this->input->get('filters'))) {
            $filters = json_decode($this->input->get('filters'), true);

            if (!empty($filters['project_id'])) {
                $this->db->where(db_prefix() . 'milestones.project_id', $filters['project_id']);
            }
        }

        if (is_numeric($this->input->get('limit')) && is_numeric($this->input->get('offset'))) {
            $this->db->limit($this->input->get('limit'));
            $this->db->offset($this->input->get('offset'));
        }

        $this->db->order_by('milestone_order', 'ASC');
        $milestones = $this->db->get(db_prefix() . 'milestones')->result_array();

        return $milestones;
    }

    public function get_expenses_api($id, $where = [])
    {
        $this->db->select('*,' . db_prefix() . 'expenses.id as id,' . db_prefix() . 'expenses_categories.name as category_name,' . db_prefix() . 'payment_modes.name as payment_mode_name,' . db_prefix() . 'taxes.name as tax_name, ' . db_prefix() . 'taxes.taxrate as taxrate,' . db_prefix() . 'taxes_2.name as tax_name2, ' . db_prefix() . 'taxes_2.taxrate as taxrate2, ' . db_prefix() . 'expenses.id as expenseid,' . db_prefix() . 'expenses.addedfrom as addedfrom, recurring_from');
        $this->db->from(db_prefix() . 'expenses');
        $this->db->join(db_prefix() . 'clients', '' . db_prefix() . 'clients.userid = ' . db_prefix() . 'expenses.clientid', 'left');
        $this->db->join(db_prefix() . 'payment_modes', '' . db_prefix() . 'payment_modes.id = ' . db_prefix() . 'expenses.paymentmode', 'left');
        $this->db->join(db_prefix() . 'taxes', '' . db_prefix() . 'taxes.id = ' . db_prefix() . 'expenses.tax', 'left');
        $this->db->join('' . db_prefix() . 'taxes as ' . db_prefix() . 'taxes_2', '' . db_prefix() . 'taxes_2.id = ' . db_prefix() . 'expenses.tax2', 'left');
        $this->db->join(db_prefix() . 'expenses_categories', '' . db_prefix() . 'expenses_categories.id = ' . db_prefix() . 'expenses.category');
        $this->db->where($where);

        if (is_numeric($id)) {
            $this->db->where(db_prefix() . 'expenses.id', $id);
            $expense = $this->db->get()->row();
            if ($expense) {
                $expense->attachment            = '';
                $expense->filetype              = '';
                $expense->attachment_added_from = 0;

                $this->db->where('rel_id', $id);
                $this->db->where('rel_type', 'expense');
                $file = $this->db->get(db_prefix() . 'files')->row();

                if ($file) {
                    $expense->attachment            = $file->file_name;
                    $expense->filetype              = $file->filetype;
                    $expense->attachment_added_from = $file->staffid;
                }

                $this->load->model('projects_model');
                $expense->currency_data = get_currency($expense->currency);
                if (0 != $expense->project_id) {
                    $expense->project_data = $this->projects_model->get($expense->project_id);
                }

                if (null === $expense->payment_mode_name) {
                    // is online payment mode
                    $this->load->model('payment_modes_model');
                    $payment_gateways = $this->payment_modes_model->get_payment_gateways(true);
                    foreach ($payment_gateways as $gateway) {
                        if ($expense->paymentmode == $gateway['id']) {
                            $expense->payment_mode_name = $gateway['name'];
                        }
                    }
                }
            }

            return $expense;
        }
        $this->db->order_by('date', 'desc');

        return $this->db->get()->result_array();
    }

    public function get_api_custom_data($data, $custom_field_type, $id = '', $is_invoice_item = false)
    {
        $this->db->where('active', 1);
        $this->db->where('fieldto', $custom_field_type);

        $this->db->order_by('field_order', 'asc');
        $fields       = $this->db->get(db_prefix() . 'customfields')->result_array();
        $customfields = [];
        if ('' === $id) {
            foreach ($data as $data_key => $value) {
                $data[$data_key]['customfields'] = [];
                $value_id                        = $value['id'] ?? '';
                if ('customers' == $custom_field_type) {
                    $value_id = $value['userid'];
                }
                if ('tickets' == $custom_field_type) {
                    $value_id = $value['ticketid'];
                }
                if ('staff' == $custom_field_type) {
                    $value_id = $value['staffid'];
                }
                foreach ($fields as $key => $field) {
                    $customfields[$key]        = new StdClass();
                    $customfields[$key]->label = $field['name'];
                    if ('items' == $custom_field_type && !$is_invoice_item) {
                        $custom_field_type = 'items_pr';
                        $value_id          = $value['itemid'] ?? $value['id'];
                    }
                    $customfields[$key]->value = get_custom_field_value($value_id, $field['id'], $custom_field_type, false);
                }
                $data[$data_key]['customfields'] = $customfields;
            }
        }
        if ('' !== $id && is_numeric($id)) {
            $data->customfields = new StdClass();
            foreach ($fields as $key => $field) {
                $customfields[$key]        = new StdClass();
                $customfields[$key]->label = $field['name'];
                if ('items' == $custom_field_type && !$is_invoice_item) {
                    $custom_field_type = 'items_pr';
                }
                $customfields[$key]->value = get_custom_field_value($id, $field['id'], $custom_field_type, false);
            }
            $data->customfields = $customfields;
        }

        return $data;
    }

    public function _search_payment($q, $limit = 0, $api = false)
    {
        $result = [
            'result'         => [],
            'type'           => 'payments',
            'search_heading' => _l('payments'),
        ];

        if (has_permission('payments', '', 'view') || true == $api) {
            $this->db->select(db_prefix() . 'invoicepaymentrecords.*, 
                ' . db_prefix() . 'invoices.status as invoice_status, 
                ' . db_prefix() . 'invoices.date as invoice_date, 
                ' . db_prefix() . 'invoices.total as invoice_total, 
                ' . db_prefix() . 'payment_modes.name as payment_mode_name, 
                ' . db_prefix() . 'payment_modes.id as paymentmodeid, 
                ' . db_prefix() . 'currencies.name as currency_name, 
                ' . db_prefix() . 'invoicepaymentrecords.date as date,
                ' . get_sql_select_client_company());

            $this->db->from(db_prefix() . 'invoicepaymentrecords');
            $this->db->join(db_prefix() . 'payment_modes', db_prefix() . 'payment_modes.id=' . db_prefix() . 'invoicepaymentrecords.paymentmode', 'LEFT');
            $this->db->join(db_prefix() . 'invoices', db_prefix() . 'invoices.id=' . db_prefix() . 'invoicepaymentrecords.invoiceid', 'LEFT');
            $this->db->join(db_prefix() . 'clients', db_prefix() . 'clients.userid=' . db_prefix() . 'invoices.clientid', 'LEFT');
            $this->db->join(db_prefix() . 'currencies', db_prefix() . 'currencies.id=' . db_prefix() . 'invoices.currency', 'LEFT');

            $this->db->group_start();
            $this->db->like(db_prefix() . 'invoicepaymentrecords.id', $this->db->escape_like_str($q));
            $this->db->or_like(db_prefix() . 'invoicepaymentrecords.invoiceid', $this->db->escape_like_str($q));
            $this->db->or_like(db_prefix() . 'clients.company', $this->db->escape_like_str($q));
            $this->db->or_like(db_prefix() . 'invoicepaymentrecords.transactionid', $this->db->escape_like_str($q));
            $this->db->or_like(db_prefix() . 'payment_modes.name', $this->db->escape_like_str($q));
            $this->db->or_like(db_prefix() . 'invoicepaymentrecords.paymentmode', $this->db->escape_like_str($q));
            $this->db->or_like(db_prefix() . 'invoicepaymentrecords.amount', $this->db->escape_like_str($q));
            $this->db->group_end();

            if (!empty($this->input->get('filters'))) {
                $filters = json_decode($this->input->get('filters'), true);

                if (!empty($filters['invoice_id'])) {
                    $this->db->where(db_prefix() . 'invoicepaymentrecords.invoiceid', $filters['invoice_id']);
                }

                if (!empty($filters['clientid'])) {
                    $this->db->where(db_prefix() . 'invoices.clientid', $filters['clientid']);
                }
            }

            if (!has_permission('payments', '', 'view')) {
                $whereUser = '';
                $whereUser .= '(invoiceid IN (SELECT id FROM ' . db_prefix() . 'invoices WHERE (addedfrom=' . get_staff_user_id() . ' AND addedfrom IN (SELECT staff_id FROM ' . db_prefix() . 'staff_permissions WHERE feature = "invoices" AND capability="view_own")))';
                if (get_option('allow_staff_view_invoices_assigned') == 1) {
                    $whereUser .= ' OR invoiceid IN (SELECT id FROM ' . db_prefix() . 'invoices WHERE sale_agent=' . get_staff_user_id() . ')';
                }
                $whereUser .= ')';

                $this->db->where($whereUser);
            }

            if (!empty($this->input->get('sort'))) {
                $sorting = json_decode($this->input->get('sort'), true);
                $this->db->order_by($sorting['sort_by'], $sorting['order']);
            } else {
                $this->db->order_by('id', 'DESC');
            }

            if (is_numeric($this->input->get('limit')) && is_numeric($this->input->get('offset'))) {
                $this->db->limit($this->input->get('limit'));
                $this->db->offset($this->input->get('offset'));
            }

            $result['result'] = $this->db->get()->result_array();
        }

        return $result;
    }

    public function payment_get($id = '')
    {
        $this->db->select(db_prefix() . 'invoicepaymentrecords.*, ' . db_prefix() . 'invoices.status as invoice_status, ' . db_prefix() . 'invoices.date as invoice_date, ' . db_prefix() . 'invoices.total as invoice_total, ' . db_prefix() . 'payment_modes.name as payment_mode_name, ' . db_prefix() . 'currencies.name as currency_name, ' . get_sql_select_client_company());
        $this->db->join(db_prefix() . 'payment_modes', db_prefix() . 'payment_modes.id=' . db_prefix() . 'invoicepaymentrecords.paymentmode', 'LEFT');
        $this->db->join(db_prefix() . 'invoices', db_prefix() . 'invoices.id=' . db_prefix() . 'invoicepaymentrecords.invoiceid', 'LEFT');
        $this->db->join(db_prefix() . 'clients', db_prefix() . 'clients.userid=' . db_prefix() . 'invoices.clientid', 'LEFT');
        $this->db->join(db_prefix() . 'currencies', db_prefix() . 'currencies.id=' . db_prefix() . 'invoices.currency', 'LEFT');
        $this->db->order_by(db_prefix() . 'invoicepaymentrecords.id', 'asc');

        if (!empty($id)) {
            $this->db->where(db_prefix() . 'invoicepaymentrecords.id', $id);
            $payment = $this->db->get(db_prefix() . 'invoicepaymentrecords')->row();
        } else {
            $payment = $this->db->get(db_prefix() . 'invoicepaymentrecords')->result();
        }

        if (!$payment) {
            return false;
        }

        $this->load->model('payment_modes_model');
        $payment_gateways = $this->payment_modes_model->get_payment_gateways(true);

        if (!empty($id)) {
            if (null === $payment->id) {
                foreach ($payment_gateways as $gateway) {
                    if ($payment->paymentmode == $gateway['id']) {
                        $payment->name = $gateway['name'];
                    }
                }
            }
        }

        if (empty($id)) {
            foreach ($payment as $key => $pay) {
                if (null === $pay->id) {
                    foreach ($payment_gateways as $gateway) {
                        if ($pay->paymentmode == $gateway['id']) {
                            $payment[$key]->name = $gateway['name'];
                        }
                    }
                }
            }
        }

        return $payment;
    }

    public function _search_proposals_for_client($q, $limit = 0, $api = false)
    {
        $fields = get_custom_fields('proposal');
        $result = [
            'result'         => [],
            'type'           => 'proposals',
            'search_heading' => _l('proposals'),
        ];

        $has_permission_view_proposals     = has_contact_permission('proposals');
        $has_permission_view_proposals_own = has_contact_permission('proposals');

        if ($has_permission_view_proposals || $has_permission_view_proposals_own || true == $api) {
            if (is_numeric($q)) {
                $q = trim($q);
                $q = ltrim($q, '0');
            } elseif (startsWith($q, get_option('proposal_number_prefix'))) {
                $q = strafter($q, get_option('proposal_number_prefix'));
                $q = trim($q);
                $q = ltrim($q, '0');
            }

            $where_string = '';
            foreach ($fields as $key => $value) {
                $this->db->join(db_prefix() . 'customfieldsvalues as ctable_' . $key . '', db_prefix() . 'proposals.id = ctable_' . $key . '.relid and ctable_' . $key . '.fieldto="proposal" AND ctable_' . $key . '.fieldid=' . $value['id'], 'LEFT');
                $where_string .= ' OR ctable_' . $key . '.value LIKE "%' . $q . '%"';
            }

            // Proposals
            $this->db->select('*,
                ' . db_prefix() . 'proposals.id as id, 
                ' . db_prefix() . 'proposals.status as status, 
                ' . db_prefix() . 'proposals.id as proposal_id, 
                (SELECT GROUP_CONCAT(name SEPARATOR ",") FROM ' . db_prefix() . 'taggables JOIN ' . db_prefix() . 'tags ON ' . db_prefix() . 'taggables.tag_id = ' . db_prefix() . 'tags.id WHERE rel_id = ' . db_prefix() . 'proposals.id and rel_type="proposal" ORDER by tag_order ASC) as tags');
            $this->db->where(db_prefix() . 'proposals.rel_type', 'customer');
            $this->db->where_not_in(db_prefix() . 'proposals.status', [6]);
            $this->db->where(db_prefix() . 'proposals.rel_id', get_client_user_id());
            $this->db->from(db_prefix() . 'proposals');
            $this->db->join(db_prefix() . 'currencies', db_prefix() . 'currencies.id = ' . db_prefix() . 'proposals.currency', 'left');

            $this->db->where('(
                    ' . db_prefix() . 'proposals.id LIKE "' . $q . '%"
                    OR ' . db_prefix() . 'proposals.subject LIKE "%' . $this->db->escape_like_str($q) . '%" ESCAPE \'!\'
                    OR ' . db_prefix() . 'proposals.content LIKE "%' . $this->db->escape_like_str($q) . '%" ESCAPE \'!\'
                    OR ' . db_prefix() . 'proposals.proposal_to LIKE "%' . $this->db->escape_like_str($q) . '%" ESCAPE \'!\'
                    OR ' . db_prefix() . 'proposals.zip LIKE "%' . $this->db->escape_like_str($q) . '%" ESCAPE \'!\'
                    OR ' . db_prefix() . 'proposals.state LIKE "%' . $this->db->escape_like_str($q) . '%" ESCAPE \'!\'
                    OR ' . db_prefix() . 'proposals.city LIKE "%' . $this->db->escape_like_str($q) . '%" ESCAPE \'!\'
                    OR ' . db_prefix() . 'proposals.address LIKE "%' . $this->db->escape_like_str($q) . '%" ESCAPE \'!\'
                    OR ' . db_prefix() . 'proposals.email LIKE "%' . $this->db->escape_like_str($q) . '%" ESCAPE \'!\'
                    OR ' . db_prefix() . 'proposals.phone LIKE "%' . $this->db->escape_like_str($q) . '%" ESCAPE \'!\'
                    ' . $where_string . '
                    )');

            if (!empty($this->input->get('filters'))) {
                $filters = json_decode($this->input->get('filters'), true);

                if (!empty($filters['status'])) {
                    $this->db->where_in(db_prefix() . 'proposals.status', $filters['status']);
                }

                if (!empty($filters['sale_agent'])) {
                    $this->db->where_in(db_prefix() . 'proposals.assigned', $filters['sale_agent']);
                }

                // }

                if (!empty($filters['project_id'])) {
                    $this->db->join(db_prefix() . 'projects', db_prefix() . 'projects.id = ' . db_prefix() . 'proposals.project_id', 'left');
                    $this->db->where(db_prefix() . 'proposals.project_id', $this->db->escape_str($filters['project_id']));
                }
            }

            if (is_numeric($this->input->get('limit')) && is_numeric($this->input->get('offset'))) {
                $this->db->limit($this->input->get('limit'));
                $this->db->offset($this->input->get('offset'));
            }

            if (!empty($this->input->get('sort'))) {
                $sorting = json_decode($this->input->get('sort'), true);
                $this->db->order_by($sorting['sort_by'], $sorting['order']);
            } else {
                $this->db->order_by(db_prefix() . 'proposals.id', 'desc');
            }

            $result['result'] = $this->db->get()->result_array();
        }
       
        return $result;
    }

    public function _search_estimates_for_client($q, $limit = 0, $api = false)
    {
        $fields = get_custom_fields('estimate');
        $result = [
            'result'         => [],
            'type'           => 'estimates',
            'search_heading' => _l('estimates'),
        ];

        $has_permission_view_estimates     = has_contact_permission('estimates');
        $has_permission_view_estimates_own = has_contact_permission('estimates');

        if ($has_permission_view_estimates || $has_permission_view_estimates_own || $api = true) {
            if (is_numeric($q)) {
                $q = trim($q);
                $q = ltrim($q, '0');
            } elseif (startsWith($q, get_option('estimate_prefix'))) {
                $q = strafter($q, get_option('estimate_prefix'));
                $q = trim($q);
                $q = ltrim($q, '0');
            }

            $where_string = '';
            foreach ($fields as $key => $value) {
                $this->db->join(db_prefix() . 'customfieldsvalues as ctable_' . $key . '', db_prefix() . 'estimates.id = ctable_' . $key . '.relid and ctable_' . $key . '.fieldto="estimate" AND ctable_' . $key . '.fieldid=' . $value['id'], 'LEFT');
                $where_string .= ' OR ctable_' . $key . '.value LIKE "%' . $q . '%"';
            }

            // Estimates
            $estimates_fields  = prefixed_table_fields_array(db_prefix() . 'estimates');
            $clients_fields    = prefixed_table_fields_array(db_prefix() . 'clients');
            $currencies_fields = prefixed_table_fields_array(db_prefix() . 'currencies');

            $this->db->select(implode(',', $estimates_fields) . ',
                ' . implode(',', $clients_fields) . ',
                ' . implode(',', $currencies_fields) . ',
                ' . db_prefix() . 'estimates.id as id, 
                ' . db_prefix() . 'estimates.id as estimateid, 
                ' . db_prefix() . 'projects.name as project_name, 
                ' . get_sql_select_client_company() . ', 
                (SELECT GROUP_CONCAT(name SEPARATOR ",") FROM ' . db_prefix() . 'taggables JOIN ' . db_prefix() . 'tags ON ' . db_prefix() . 'taggables.tag_id = ' . db_prefix() . 'tags.id WHERE rel_id = ' . db_prefix() . 'estimates.id and rel_type="estimate" ORDER by tag_order ASC) as tags');
               
            $this->db->where_not_in(db_prefix() . 'estimates.status', [1]);
            $this->db->where(db_prefix() . 'estimates.clientid', get_client_user_id());

            $this->db->from(db_prefix() . 'estimates');
            $this->db->join(db_prefix() . 'clients', db_prefix() . 'clients.userid = ' . db_prefix() . 'estimates.clientid', 'left');
            $this->db->join(db_prefix() . 'currencies', db_prefix() . 'currencies.id = ' . db_prefix() . 'estimates.currency');
            $this->db->join(db_prefix() . 'contacts', db_prefix() . 'contacts.userid = ' . db_prefix() . 'clients.userid AND is_primary = 1', 'left');
            $this->db->join(db_prefix() . 'projects', db_prefix() . 'projects.id = ' . db_prefix() . 'estimates.project_id', 'left');

            $this->db->where('(
                ' . db_prefix() . 'estimates.number LIKE "' . $this->db->escape_like_str($q) . '"
                OR
                ' . db_prefix() . 'clients.company LIKE "%' . $this->db->escape_like_str($q) . '%" ESCAPE \'!\'
                OR
                ' . db_prefix() . 'estimates.clientnote LIKE "%' . $this->db->escape_like_str($q) . '%" ESCAPE \'!\'
                OR
                ' . db_prefix() . 'clients.vat LIKE "%' . $this->db->escape_like_str($q) . '%" ESCAPE \'!\'
                OR
                ' . db_prefix() . 'clients.phonenumber LIKE "%' . $this->db->escape_like_str($q) . '%" ESCAPE \'!\'
                OR
                ' . db_prefix() . 'clients.city LIKE "%' . $this->db->escape_like_str($q) . '%" ESCAPE \'!\'
                OR
                ' . db_prefix() . 'clients.state LIKE "%' . $this->db->escape_like_str($q) . '%" ESCAPE \'!\'
                OR
                ' . db_prefix() . 'clients.zip LIKE "%' . $this->db->escape_like_str($q) . '%" ESCAPE \'!\'
                OR
                address LIKE "%' . $this->db->escape_like_str($q) . '%" ESCAPE \'!\'
                OR
                ' . db_prefix() . 'estimates.adminnote LIKE "%' . $this->db->escape_like_str($q) . '%" ESCAPE \'!\'
                OR
                ' . db_prefix() . 'estimates.billing_street LIKE "%' . $this->db->escape_like_str($q) . '%" ESCAPE \'!\'
                OR
                ' . db_prefix() . 'estimates.billing_city LIKE "%' . $this->db->escape_like_str($q) . '%" ESCAPE \'!\'
                OR
                ' . db_prefix() . 'estimates.billing_state LIKE "%' . $this->db->escape_like_str($q) . '%" ESCAPE \'!\'
                OR
                ' . db_prefix() . 'estimates.billing_zip LIKE "%' . $this->db->escape_like_str($q) . '%" ESCAPE \'!\'
                OR
                ' . db_prefix() . 'estimates.shipping_street LIKE "%' . $this->db->escape_like_str($q) . '%" ESCAPE \'!\'
                OR
                ' . db_prefix() . 'estimates.shipping_city LIKE "%' . $this->db->escape_like_str($q) . '%" ESCAPE \'!\'
                OR
                ' . db_prefix() . 'estimates.shipping_state LIKE "%' . $this->db->escape_like_str($q) . '%" ESCAPE \'!\'
                OR
                ' . db_prefix() . 'estimates.shipping_zip LIKE "%' . $this->db->escape_like_str($q) . '%" ESCAPE \'!\'
                OR
                ' . db_prefix() . 'clients.billing_street LIKE "%' . $this->db->escape_like_str($q) . '%" ESCAPE \'!\'
                OR
                ' . db_prefix() . 'clients.billing_city LIKE "%' . $this->db->escape_like_str($q) . '%" ESCAPE \'!\'
                OR
                ' . db_prefix() . 'clients.billing_state LIKE "%' . $this->db->escape_like_str($q) . '%" ESCAPE \'!\'
                OR
                ' . db_prefix() . 'clients.billing_zip LIKE "%' . $this->db->escape_like_str($q) . '%" ESCAPE \'!\'
                OR
                ' . db_prefix() . 'clients.shipping_street LIKE "%' . $this->db->escape_like_str($q) . '%" ESCAPE \'!\'
                OR
                ' . db_prefix() . 'clients.shipping_city LIKE "%' . $this->db->escape_like_str($q) . '%" ESCAPE \'!\'
                OR
                ' . db_prefix() . 'clients.shipping_state LIKE "%' . $this->db->escape_like_str($q) . '%" ESCAPE \'!\'
                OR
                ' . db_prefix() . 'clients.shipping_zip LIKE "%' . $this->db->escape_like_str($q) . '%" ESCAPE \'!\'
                ' . $where_string . '
                )');

            if (!empty($this->input->get('filters'))) {
                $filters = json_decode($this->input->get('filters'), true);

                if (!empty($filters['status'])) {
                    $this->db->where_in(db_prefix() . 'estimates.status', $filters['status']);
                }

                if (!empty($filters['sale_agent'])) {
                    $this->db->where_in(db_prefix() . 'estimates.sale_agent', $filters['sale_agent']);
                }

                if (!empty($filters['project_id'])) {
                    $this->db->where(db_prefix() . 'estimates.project_id', $filters['project_id']);
                }

                // }
            }

            // }

            if (!empty($this->input->get('sort'))) {
                $sorting = json_decode($this->input->get('sort'), true);
                $this->db->order_by($sorting['sort_by'], $sorting['order']);
            } else {
                $this->db->order_by('number,YEAR(date)', 'desc');
            }

            if (is_numeric($this->input->get('limit')) && is_numeric($this->input->get('offset'))) {
                $this->db->limit($this->input->get('limit'));
                $this->db->offset($this->input->get('offset'));
            }

            $result['result'] = $this->db->get()->result_array();
        }

        return $result;
    }

    public function _search_expenses($q, $limit = 0, $api = false)
    {
        $fields = get_custom_fields('expenses');
        $result = [
            'result'         => [],
            'type'           => 'expenses',
            'search_heading' => _l('expenses'),
        ];

        $has_permission_expenses_view     = has_permission('expenses', '', 'view');
        $has_permission_expenses_view_own = has_permission('expenses', '', 'view_own');

        if ($has_permission_expenses_view || $has_permission_expenses_view_own || true == $api) {
            // Expenses

            $where_string = '';
            foreach ($fields as $key => $value) {
                $this->db->join(db_prefix() . 'customfieldsvalues as ctable_' . $key . '', db_prefix() . 'expenses.id = ctable_' . $key . '.relid and ctable_' . $key . '.fieldto="expenses" AND ctable_' . $key . '.fieldid=' . $value['id'], 'LEFT');
                $where_string .= ' OR ctable_' . $key . '.value LIKE "%' . $q . '%"';
            }

            $this->db->select('*,
                ' . db_prefix() . 'expenses.amount as amount,
                file_name,
                file_name as attachment,
                ' . db_prefix() . 'expenses_categories.name as category_name,
                ' . db_prefix() . 'payment_modes.name as payment_mode_name,
                ' . db_prefix() . 'taxes.name as tax_name, 
                ' . db_prefix() . 'taxes.taxrate as taxrate, 
                ' . db_prefix() . 'taxes_2.name as tax_name2,
                ' . db_prefix() . 'taxes_2.taxrate as taxrate2,
                ' . db_prefix() . 'expenses.id as expenseid,
                ' . db_prefix() . 'projects.name as project_name,
                ' . db_prefix() . 'currencies.name as currency_name');
            $this->db->from(db_prefix() . 'expenses');
            $this->db->join(db_prefix() . 'clients', db_prefix() . 'clients.userid = ' . db_prefix() . 'expenses.clientid', 'left');
            $this->db->join(db_prefix() . 'projects', db_prefix() . 'projects.id = ' . db_prefix() . 'expenses.project_id', 'left');
            $this->db->join(db_prefix() . 'payment_modes', db_prefix() . 'payment_modes.id = ' . db_prefix() . 'expenses.paymentmode', 'left');
            $this->db->join(db_prefix() . 'taxes', db_prefix() . 'taxes.id = ' . db_prefix() . 'expenses.tax', 'left');
            $this->db->join('' . db_prefix() . 'taxes as ' . db_prefix() . 'taxes_2', '' . db_prefix() . 'taxes_2.id = ' . db_prefix() . 'expenses.tax2', 'left');
            $this->db->join(db_prefix() . 'expenses_categories', db_prefix() . 'expenses_categories.id = ' . db_prefix() . 'expenses.category', 'left');
            $this->db->join(db_prefix() . 'files', '' . db_prefix() . 'files.rel_id = ' . db_prefix() . 'expenses.id AND rel_type="expense"', 'left');
            $this->db->join(db_prefix() . 'currencies', '' . db_prefix() . 'currencies.id = ' . db_prefix() . 'expenses.currency', 'left');

            $this->db->where('(company LIKE "%' . $this->db->escape_like_str($q) . '%" ESCAPE \'!\'
                OR paymentmode LIKE "%' . $this->db->escape_like_str($q) . '%" ESCAPE \'!\'
                OR ' . db_prefix() . 'payment_modes.name LIKE "%' . $this->db->escape_like_str($q) . '%" ESCAPE \'!\'
                OR vat LIKE "%' . $this->db->escape_like_str($q) . '%" ESCAPE \'!\'
                OR phonenumber LIKE "%' . $this->db->escape_like_str($q) . '%" ESCAPE \'!\'
                OR city LIKE "%' . $this->db->escape_like_str($q) . '%" ESCAPE \'!\'
                OR zip LIKE "%' . $this->db->escape_like_str($q) . '%" ESCAPE \'!\'
                OR address LIKE "%' . $this->db->escape_like_str($q) . '%" ESCAPE \'!\'
                OR state LIKE "%' . $this->db->escape_like_str($q) . '%" ESCAPE \'!\'
                OR ' . db_prefix() . 'expenses_categories.name LIKE "%' . $this->db->escape_like_str($q) . '%" ESCAPE \'!\'
                OR ' . db_prefix() . 'expenses.note LIKE "%' . $this->db->escape_like_str($q) . '%" ESCAPE \'!\'
                OR ' . db_prefix() . 'expenses.expense_name LIKE "%' . $this->db->escape_like_str($q) . '%" ESCAPE \'!\'
                ' . $where_string . '
                )');

            if (!empty($this->input->get('filters'))) {
                $filters = json_decode($this->input->get('filters'), true);

                if (!empty($filters['project_id'])) {
                    $this->db->where(db_prefix() . 'expenses.project_id', $filters['project_id']);
                }

                if (!empty($filters['expense_categories'])) {
                    $this->db->where_in(db_prefix() . 'expenses.category', $filters['expense_categories']);
                }

                if (!empty($filters['expense_months'])) {
                    $this->db->where_in('MONTH(' . db_prefix() . 'expenses.date)', $filters['expense_months']);
                }

                if (!empty($filters['paymentmode'])) {
                    $this->db->where_in(db_prefix() . 'expenses.paymentmode', $filters['paymentmode']);
                }

                if (isset($filters['expense']) && array_filter($filters['expense'])) {
                    $this->db->group_start();
                    if (in_array('invoiced', $filters['expense'])) {
                        $this->db->or_where('invoiceid IS NOT NULL');
                    }

                    if (in_array('billable', $filters['expense'])) {
                        $this->db->or_where('billable', 1);
                    }

                    if (in_array('non-billable', $filters['expense'])) {
                        $this->db->or_where('billable', 0);
                    }

                    if (in_array('unbilled', $filters['expense'])) {
                        $this->db->or_where('invoiceid IS NULL');
                    }

                    if (in_array('recurring', $filters['expense'])) {
                        $this->db->or_where('recurring', 1);
                    }
                    $this->db->group_end();
                }
            }

            if (!has_permission('expenses', '', 'view')) {
                $this->db->where(db_prefix() . 'expenses.addedfrom=' . get_staff_user_id());
            }

            if (!empty($this->input->get('sort'))) {
                $sorting = json_decode($this->input->get('sort'), true);
                $this->db->order_by($sorting['sort_by'], $sorting['order']);
            } else {
                $this->db->order_by('expenseid', 'DESC');
            }

            if (is_numeric($this->input->get('limit')) && is_numeric($this->input->get('offset'))) {
                $this->db->limit($this->input->get('limit'));
                $this->db->offset($this->input->get('offset'));
            }

            $result['result'] = $this->db->get()->result_array();
        }

        return $result;
    }

    public function _search_credit_notes($q, $limit = 0, $api = false)
    {
        $fields = get_custom_fields('credit_note');
        $result = [
            'result'         => [],
            'type'           => 'credit_note',
            'search_heading' => _l('credit_notes'),
        ];

        $has_permission_view_credit_notes     = has_permission('credit_notes', '', 'view');
        $has_permission_view_credit_notes_own = has_permission('credit_notes', '', 'view_own');

        if ($has_permission_view_credit_notes || $has_permission_view_credit_notes_own || true == $api) {
            if (is_numeric($q)) {
                $q = trim($q);
                $q = ltrim($q, '0');
            } elseif (startsWith($q, get_option('credit_note_prefix'))) {
                $q = strafter($q, get_option('credit_note_prefix'));
                $q = trim($q);
                $q = ltrim($q, '0');
            }

            $where_string = '';
            foreach ($fields as $key => $value) {
                $this->db->join(db_prefix() . 'customfieldsvalues as ctable_' . $key . '', db_prefix() . 'creditnotes.id = ctable_' . $key . '.relid and ctable_' . $key . '.fieldto="credit_note" AND ctable_' . $key . '.fieldid=' . $value['id'], 'LEFT');
                $where_string .= ' OR ctable_' . $key . '.value LIKE "%' . $q . '%"';
            }

            $credit_note_fields = prefixed_table_fields_array(db_prefix() . 'creditnotes');
            $clients_fields     = prefixed_table_fields_array(db_prefix() . 'clients');
            $currencies_fields = prefixed_table_fields_array(db_prefix() . 'currencies');

            // Invoices
            $this->db->select(implode(',', $credit_note_fields) . ',
                ' . implode(',', $clients_fields) . ',
                ' . implode(',', $currencies_fields) . ',
                ' . db_prefix() . 'creditnotes.id as id,
                ' . db_prefix() . 'creditnotes.id as credit_note_id,
                ' . db_prefix() . 'projects.name as project_name,
                ' . get_sql_select_client_company() . ',
                (SELECT ' . db_prefix() . 'creditnotes.total - (
                    (SELECT COALESCE(SUM(amount),0) FROM ' . db_prefix() . 'credits WHERE ' . db_prefix() . 'credits.credit_id=' . db_prefix() . 'creditnotes.id)
                    +
                    (SELECT COALESCE(SUM(amount),0) FROM ' . db_prefix() . 'creditnote_refunds WHERE ' . db_prefix() . 'creditnote_refunds.credit_note_id=' . db_prefix() . 'creditnotes.id)
                    )
                  ) as remaining_amount');

            $this->db->from(db_prefix() . 'creditnotes');
            $this->db->join(db_prefix() . 'clients', db_prefix() . 'clients.userid = ' . db_prefix() . 'creditnotes.clientid', 'left');
            $this->db->join(db_prefix() . 'currencies', db_prefix() . 'currencies.id = ' . db_prefix() . 'creditnotes.currency', 'left');
            $this->db->join(db_prefix() . 'contacts', db_prefix() . 'contacts.userid = ' . db_prefix() . 'clients.userid AND is_primary = 1', 'left');
            $this->db->join(db_prefix() . 'projects', db_prefix() . 'projects.id = ' . db_prefix() . 'creditnotes.project_id', 'left');

            $this->db->where('(
                ' . db_prefix() . 'creditnotes.number LIKE "' . $this->db->escape_like_str($q) . '"
                OR
                ' . db_prefix() . 'clients.company LIKE "%' . $this->db->escape_like_str($q) . '%" ESCAPE \'!\'
                OR
                ' . db_prefix() . 'creditnotes.clientnote LIKE "%' . $this->db->escape_like_str($q) . '%" ESCAPE \'!\'
                OR
                ' . db_prefix() . 'clients.vat LIKE "%' . $this->db->escape_like_str($q) . '%" ESCAPE \'!\'
                OR
                ' . db_prefix() . 'clients.phonenumber LIKE "%' . $this->db->escape_like_str($q) . '%" ESCAPE \'!\'
                OR
                ' . db_prefix() . 'clients.city LIKE "%' . $this->db->escape_like_str($q) . '%" ESCAPE \'!\'
                OR
                ' . db_prefix() . 'clients.state LIKE "%' . $this->db->escape_like_str($q) . '%" ESCAPE \'!\'
                OR
                ' . db_prefix() . 'clients.zip LIKE "%' . $this->db->escape_like_str($q) . '%" ESCAPE \'!\'
                OR
                ' . db_prefix() . 'clients.address LIKE "%' . $this->db->escape_like_str($q) . '%" ESCAPE \'!\'
                OR
                ' . db_prefix() . 'creditnotes.adminnote LIKE "%' . $this->db->escape_like_str($q) . '%" ESCAPE \'!\'
                OR
                CONCAT(firstname,\' \',lastname) LIKE "%' . $this->db->escape_like_str($q) . '%" ESCAPE \'!\'
                OR
                CONCAT(lastname,\' \',firstname) LIKE "%' . $this->db->escape_like_str($q) . '%" ESCAPE \'!\'
                OR
                ' . db_prefix() . 'creditnotes.billing_street LIKE "%' . $this->db->escape_like_str($q) . '%" ESCAPE \'!\'
                OR
                ' . db_prefix() . 'creditnotes.billing_city LIKE "%' . $this->db->escape_like_str($q) . '%" ESCAPE \'!\'
                OR
                ' . db_prefix() . 'creditnotes.billing_state LIKE "%' . $this->db->escape_like_str($q) . '%" ESCAPE \'!\'
                OR
                ' . db_prefix() . 'creditnotes.billing_zip LIKE "%' . $this->db->escape_like_str($q) . '%" ESCAPE \'!\'
                OR
                ' . db_prefix() . 'creditnotes.shipping_street LIKE "%' . $this->db->escape_like_str($q) . '%" ESCAPE \'!\'
                OR
                ' . db_prefix() . 'creditnotes.shipping_city LIKE "%' . $this->db->escape_like_str($q) . '%" ESCAPE \'!\'
                OR
                ' . db_prefix() . 'creditnotes.shipping_state LIKE "%' . $this->db->escape_like_str($q) . '%" ESCAPE \'!\'
                OR
                ' . db_prefix() . 'creditnotes.shipping_zip LIKE "%' . $this->db->escape_like_str($q) . '%" ESCAPE \'!\'
                OR
                ' . db_prefix() . 'clients.billing_street LIKE "%' . $this->db->escape_like_str($q) . '%" ESCAPE \'!\'
                OR
                ' . db_prefix() . 'clients.billing_city LIKE "%' . $this->db->escape_like_str($q) . '%" ESCAPE \'!\'
                OR
                ' . db_prefix() . 'clients.billing_state LIKE "%' . $this->db->escape_like_str($q) . '%" ESCAPE \'!\'
                OR
                ' . db_prefix() . 'clients.billing_zip LIKE "%' . $this->db->escape_like_str($q) . '%" ESCAPE \'!\'
                OR
                ' . db_prefix() . 'clients.shipping_street LIKE "%' . $this->db->escape_like_str($q) . '%" ESCAPE \'!\'
                OR
                ' . db_prefix() . 'clients.shipping_city LIKE "%' . $this->db->escape_like_str($q) . '%" ESCAPE \'!\'
                OR
                ' . db_prefix() . 'clients.shipping_state LIKE "%' . $this->db->escape_like_str($q) . '%" ESCAPE \'!\'
                OR
                ' . db_prefix() . 'clients.shipping_zip LIKE "%' . $this->db->escape_like_str($q) . '%" ESCAPE \'!\'
                ' . $where_string . '
                )');

            if (!empty($this->input->get('filters'))) {
                $filters = json_decode($this->input->get('filters'), true);

                if (!empty($filters['status'])) {
                    $this->db->where_in(db_prefix() . 'creditnotes.status', $filters['status']);
                }
                if (!empty($filters['project_id'])) {
                    $this->db->where(db_prefix() . 'creditnotes.project_id', $filters['project_id']);
                }
                if (!empty($filters['clientid'])) {
                    $this->db->where(db_prefix() . 'creditnotes.clientid', $filters['clientid']);
                }
                /*  if (!empty($filters['invoice_id'])) {
                    $this->db->where(db_prefix() . 'creditnotes.invoice_id', $filters['invoice_id']);
                } */
            }

            if (!has_permission('credit_notes', '', 'view')) {
                $this->db->where(db_prefix() . 'creditnotes.addedfrom=' . get_staff_user_id());
            }

            if (!empty($this->input->get('sort'))) {
                $sorting = json_decode($this->input->get('sort'), true);
                $this->db->order_by($sorting['sort_by'], $sorting['order']);
            } else {
                $this->db->order_by('number', 'desc');
            }

            if (is_numeric($this->input->get('limit')) && is_numeric($this->input->get('offset'))) {
                $this->db->limit($this->input->get('limit'));
                $this->db->offset($this->input->get('offset'));
            }

            $result['result'] = $this->db->get()->result_array();
        }

        return $result;
    }

    public function _search_contracts_for_client($q, $limit = 0, $api = false)
    {
        $fields = get_custom_fields('contracts');
        $result = [
            'result'         => [],
            'type'           => 'contract',
            'search_heading' => _l('contracts'),
        ];

        $has_permission_view_contracts     = has_contact_permission('contracts');
        $has_permission_view_contracts_own = has_contact_permission('contracts');

        if ($has_permission_view_contracts || $has_permission_view_contracts_own || true == $api) {

            if (is_numeric($q)) {
                $q = trim($q);
                $q = ltrim($q, '0');
            }

            $where_string = '';
            /* foreach ($fields as $key => $value) {
                $this->db->join(db_prefix() . 'customfieldsvalues as ctable_' . $key . '', db_prefix() . 'contracts.id = ctable_' . $key . '.relid and ctable_' . $key . '.fieldto="contracts" AND ctable_' . $key . '.fieldid=' . $value['id'], 'LEFT');
                $where_string .= ' OR ctable_' . $key . '.value LIKE "%' . $q . '%"';
            } */

            $this->db->select('*');
            $this->db->where(db_prefix() . 'contracts.client', get_client_user_id());
            $this->db->from(db_prefix() . 'contracts');
            $this->db->where('(
                ' . db_prefix() . 'contracts.id LIKE "' . $this->db->escape_like_str($q) . '"
                OR
                ' . db_prefix() . 'contracts.content LIKE "' . $this->db->escape_like_str($q) . '"
                OR
                ' . db_prefix() . 'contracts.description LIKE "%' . $this->db->escape_like_str($q) . '%" ESCAPE \'!\'
                OR
                ' . db_prefix() . 'contracts.subject LIKE "%' . $this->db->escape_like_str($q) . '%" ESCAPE \'!\'
                OR
                ' . db_prefix() . 'contracts.contract_value LIKE "%' . $this->db->escape_like_str($q) . '%" ESCAPE \'!\'
                ' . $where_string . '
                )');

            if (!empty($this->input->get('filters'))) {
                $filters = json_decode($this->input->get('filters'), true);

                if (!empty($filters['contract_type'])) {
                    $this->db->where_in(db_prefix() . 'contracts.contract_type', $filters['contract_type']);
                }
                if (!empty($filters['project_id'])) {
                    $this->db->where(db_prefix() . 'contracts.project_id', $filters['project_id']);
                }
                // }
            }

            // }

            if (!empty($this->input->get('sort'))) {
                $sorting = json_decode($this->input->get('sort'), true);
                $this->db->order_by($sorting['sort_by'], $sorting['order']);
            } else {
                $this->db->order_by('number,YEAR(date)', 'desc');
            }
          
            if (is_numeric($this->input->get('limit')) && is_numeric($this->input->get('offset'))) {
                $this->db->limit($this->input->get('limit'));
                $this->db->offset($this->input->get('offset'));
            }

            $result['result'] = $this->db->get()->result_array();
        }

        return $result;
    }

    private function total_refunds_by_credit_note($id)
    {
        return sum_from_table(db_prefix() . 'creditnote_refunds', [
            'field' => 'amount',
            'where' => ['credit_note_id' => $id],
        ]);
    }

    private function total_credits_used_by_credit_note($id)
    {
        return sum_from_table(db_prefix() . 'credits', [
            'field' => 'amount',
            'where' => ['credit_id' => $id],
        ]);
    }

    public function _generate_key()
    {
        do {
            $salt = base_convert(bin2hex($this->security->get_random_bytes(64)), 16, 36);

            // If an error occurred, then fall back to the previous method
            if ($salt === FALSE) {
                $salt = hash('sha256', time() . mt_rand());
            }

            $new_key = substr($salt, 0, config_item('rest_key_length'));
        } while ($this->_key_exists($new_key));

        return $new_key;
    }

    public function _key_exists($key)
    {
        return $this->db
            ->where(config_item('rest_key_column'), $key)
            ->count_all_results(config_item('rest_keys_table')) > 0;
    }

    public function _insert_key($key, $data)
    {
        $data[config_item('rest_key_column')] = $key;
        $data['date_created'] = function_exists('now') ? now() : time();

        return $this->db
            ->set($data)
            ->insert(config_item('rest_keys_table'));
    }

    public function search_client($type, $key)
    {
        return $this->get_relation_data_for_client_mpc_api($type, $key);
    }

    public function get_relation_data_for_client_mpc_api($type, $search = '')
    {
        $q  = '';
        if ('' != $search) {
            $q = $search;
            $q = trim(urldecode($q));
        }
        $data = [];
        if ('customer' == $type || 'customers' == $type) {
            $where_clients = '';

            if ($q) {
                $where_clients .= '(';
                $where_clients .= 'company LIKE "%' . $q . '%" OR CONCAT(firstname, " ", lastname) LIKE "%' . $q . '%" OR email LIKE "%' . $q . '%"';

                $fields = get_custom_fields('customers');
                foreach ($fields as $key => $value) {
                    $this->db->join(db_prefix() . 'customfieldsvalues as ctable_' . $key . '', db_prefix() . 'clients.userid = ctable_' . $key . '.relid and ctable_' . $key . '.fieldto="customers" AND ctable_' . $key . '.fieldid=' . $value['id'], 'LEFT');
                    $where_clients .= ' OR ctable_' . $key . '.value LIKE "%' . $q . '%"';
                }
                $where_clients .= ')';
            }
            $this->load->model('clients_api_model');
            $data = $this->clients_api_model->get('', $where_clients, [
                'limit' => $this->input->get('limit'),
                'offset' => $this->input->get('offset'),
                'filters' => $this->input->get('filters'),
                'sort' => !empty($this->input->get('sort')) ?  json_decode($this->input->get('sort'), true) : ['sort_by' => 'company', 'order' => 'desc']
            ]);
        } elseif ('contacts' == $type) {
            $where_clients = 'tblclients.active=1';
            if ($q) {
                $where_clients .= ' AND (';
                $where_clients .= ' company LIKE "%' . $this->db->escape_like_str($q) . '%" ESCAPE \'!\' OR CONCAT(firstname, " ", lastname) LIKE "%' . $this->db->escape_like_str($q) . '%" ESCAPE \'!\' OR email LIKE "%' . $this->db->escape_like_str($q) . '%" ESCAPE \'!\'';

                $fields = get_custom_fields('contacts');
                foreach ($fields as $key => $value) {
                    $this->db->join(db_prefix() . 'customfieldsvalues as ctable_' . $key . '', db_prefix() . 'contacts.id = ctable_' . $key . '.relid and ctable_' . $key . '.fieldto="contacts" AND ctable_' . $key . '.fieldid=' . $value['id'], 'LEFT');
                    $where_clients .= ' OR ctable_' . $key . '.value LIKE "%' . $q . '%"';
                }

                $where_clients .= ') AND ' . db_prefix() . 'clients.active = 1';
            }

            $this->db->select('contacts.id AS id,clients.*,contacts.*, CONCAT(' . db_prefix() . 'contacts.firstname, \' \', ' . db_prefix() . 'contacts.lastname) as full_name');
            $this->db->join(db_prefix() . 'clients', '' . db_prefix() . 'contacts.userid = ' . db_prefix() . 'clients.userid', 'left');

            $this->load->model('clients_model');

            if (!empty($this->input->get('filters'))) {
                $filters = json_decode($this->input->get('filters'), true);

                if (!empty($filters['userid'])) {
                    $this->db->where(db_prefix() . 'contacts.userid', $filters['userid']);
                }
            }
            if (!empty($this->input->get('sort'))) {
                $sorting = json_decode($this->input->get('sort'), true);
                $this->db->order_by($sorting['sort_by'], $sorting['order']);
            } else {
                $this->db->order_by('id', 'DESC');
            }

            if (is_numeric($this->input->get('limit')) && is_numeric($this->input->get('offset'))) {
                $this->db->limit($this->input->get('limit'));
                $this->db->offset($this->input->get('offset'));
            }

            $data = $this->clients_model->get_contacts('', $where_clients);
        } elseif ('template' == $type || 'templates' == $type) {

            $this->load->model('templates_model');

            if (!empty($this->input->get('filters'))) {
                $filters = json_decode($this->input->get('filters'), true);

                if (!empty($filters['rel_type'])) {
                    $this->db->where('type', $filters['rel_type']);
                }
            }

            if (is_numeric($this->input->get('limit')) && is_numeric($this->input->get('offset'))) {
                $this->db->limit($this->input->get('limit'));
                $this->db->offset($this->input->get('offset'));
            }

            $this->db->order_by('id', 'DESC');

            $data = $this->templates_model->get();
        } elseif ('ticket' == $type) {
            $search = $this->_search_tickets_for_client($q, 0, true);
            $data   = $search['result'];
        } elseif ('lead' == $type || 'leads' == $type) {
            $search = $this->_search_leads($q, 0, [
                'junk' => 0,
            ], true);
            $data = $search['result'];
        } elseif ('invoice' == $type || 'invoices' == $type) {
            $search = $this->_search_invoices_for_client($q, 0, [], true);
            $data   = $search['result'];
        } elseif ('invoice_items' == $type) {
            $this->load->model('invoice_items_model');
            $fields = get_custom_fields('items');
            $this->db->select('
                rate, 
                items.id, 
                description as name, 
                long_description as subtext, 
                unit, 
                t1.taxrate as taxrate_1, 
                t2.taxrate as taxrate_2,
                t1.name as taxname_1,
                t2.name as taxname_2,
                t1.id as tax_id_1,
                t2.id as tax_id_2,
                group_id,
                ' . db_prefix() . 'items_groups.name as group_name
            ');

            $this->db->like('description', $q);
            $this->db->or_like('long_description', $q);
            foreach ($fields as $key => $value) {
                $this->db->join(db_prefix() . 'customfieldsvalues as ctable_' . $key . '', db_prefix() . 'items.id = ctable_' . $key . '.relid and ctable_' . $key . '.fieldto="items_pr" AND ctable_' . $key . '.fieldid=' . $value['id'], 'LEFT');
                $this->db->or_like('ctable_' . $key . '.value', $q);
            }

            $this->db->join(db_prefix() . 'taxes t1', 't1.id = ' . db_prefix() . 'items.tax', 'LEFT');
            $this->db->join(db_prefix() . 'taxes t2', 't2.id = ' . db_prefix() . 'items.tax2', 'LEFT');
            $this->db->join(db_prefix() . 'items_groups', db_prefix() . 'items_groups.id = ' . db_prefix() . 'items.group_id', 'LEFT');

            if (!empty($this->input->get('sort'))) {
                $sorting = json_decode($this->input->get('sort'), true);
                $this->db->order_by($sorting['sort_by'], $sorting['order']);
            } else {
                $this->db->order_by('name', 'asc');
            }

            if (is_numeric($this->input->get('limit')) && is_numeric($this->input->get('offset'))) {
                $this->db->limit($this->input->get('limit'));
                $this->db->offset($this->input->get('offset'));
            }

            $items = $this->db->get(db_prefix() . 'items')->result_array();

            foreach ($items as $key => $item) {
                $items[$key]['subtext']       = $item['subtext'] !== null ? strip_tags(mb_substr($item['subtext'], 0, 200)) . '...' : '';
                $items[$key]['description']   = $item['name'];
                $items[$key]['name']          = '(' . app_format_number($item['rate']) . ') ' . $item['name'];
                $items[$key]['formated_rate'] = app_format_money($item['rate'], get_base_currency());

                $items[$key]['taxrate_1'] = $item['taxrate_1'] ?? 0;
                $items[$key]['formated_taxrate_1'] = app_format_number($items[$key]['taxrate_1']);

                $items[$key]['taxrate_2'] = $item['taxrate_2'] ?? 0;
                $items[$key]['formated_taxrate_2'] = app_format_number($items[$key]['taxrate_2']);
            }
            $data = $items;
        } elseif ('project' == $type) {

            $where_projects = '';
            if (get_client_user_id()) {
                $where_projects .= '(clientid=' .  get_client_user_id() . ' or clientid in (select id from tblleads where client_id=' . get_client_user_id() . ') )';
            }
            if ($this->input->post('rel_type')) {
                $where_projects .= ' and rel_type="' . $this->input->post('rel_type') . '" ';
            }
            $search = $this->_search_projects_for_client($q, 0, $where_projects, $this->input->post('rel_type'), true);

            $data   = $search['result'];
        } elseif ('staff' == $type) {
            $search = $this->_search_staff($q, 0, true);
            $data   = $search['result'];
        } elseif ('tasks' == $type) {
            $search = $this->_search_tasks_for_client($q, 0, true);
            $data   = $search['result'];
        } elseif ('payments' == $type) {
            $search = $this->_search_payment($q, 0, true);
            $data   = $search['result'];
        } elseif ('proposals' == $type || 'proposal' == $type) {
            $search = $this->_search_proposals_for_client($q, 0, true);
            $data   = $search['result'];
        } elseif ('estimates' == $type || 'estimate' == $type) {
            $search = $this->_search_estimates_for_client($q, 0, true);
            $data   = $search['result'];
        } elseif ('expenses' == $type || 'expense' == $type) {
            $search = $this->_search_expenses($q, 0, true);
            $data   = $search['result'];
        } elseif ('creditnotes' == $type) {
            $search = $this->_search_credit_notes($q, 0, true);
            $data   = $search['result'];
        } elseif ('milestones' == $type) {
            $where_milestones = '';
            if ($q) {
                $where_milestones .= '(name LIKE "%' . $q . '%" OR id LIKE "%' . $q . '%")';
            }
            $data = $this->get_milestones_api('', $where_milestones);
        } elseif ('contracts' == $type) {
            $search = $this->_search_contracts_for_client($q, 0, true);
            $data   = $search['result'];
        }

        return $data;
    }

    public function _search_projects_for_client($q, $limit = 0, $where = false, $rel_type = null, $api = false)
    {
        $fields = get_custom_fields('projects');
        $result = [
            'result'         => [],
            'type'           => 'projects',
            'search_heading' => _l('projects'),
        ];

        $projects = has_contact_permission('projects', get_contact_user_id());
        // Projects
        $this->db->select(db_prefix() . 'projects.*, 
            CASE ' . db_prefix() . 'clients.company WHEN \' \' THEN (SELECT CONCAT(firstname, \' \', lastname) FROM tblcontacts WHERE ' . db_prefix() . 'contacts.userid = tblclients.userid and is_primary = 1) ELSE ' . db_prefix() . 'clients.company END as company,
            (SELECT GROUP_CONCAT(name SEPARATOR ",") FROM ' . db_prefix() . 'taggables JOIN ' . db_prefix() . 'tags ON ' . db_prefix() . 'taggables.tag_id = ' . db_prefix() . 'tags.id WHERE rel_id = ' . db_prefix() . 'projects.id and rel_type="project" ORDER by tag_order ASC) as tags,
            (SELECT GROUP_CONCAT(CONCAT(firstname, \' \', lastname) SEPARATOR ",") FROM ' . db_prefix() . 'project_members JOIN ' . db_prefix() . 'staff on ' . db_prefix() . 'staff.staffid = ' . db_prefix() . 'project_members.staff_id WHERE project_id=' . db_prefix() . 'projects.id ORDER BY staff_id) as members
        ');
        $this->db->from('tblprojects');
        if (isset($rel_type) && 'lead' == $rel_type) {
            $this->db->join('tblleads', 'tblleads.id = tblprojects.clientid');
        } else {
            $this->db->join('tblclients', 'tblclients.userid = tblprojects.clientid', 'LEFT');
            $this->db->join('tblleads', 'tblleads.id = tblprojects.clientid', 'LEFT');
        }

        if (!$projects && false == $api) {
            $this->db->where('tblprojects.id IN (SELECT project_id FROM tblprojectmembers WHERE staff_id=' . get_staff_user_id() . ')');
        }
        if (false != $where) {
            $this->db->where($where);
        }

        if (!_startsWith($q, '#')) {
            $where_string = '';
            foreach ($fields as $key => $value) {
                $this->db->join(db_prefix() . 'customfieldsvalues as ctable_' . $key . '', db_prefix() . 'projects.id = ctable_' . $key . '.relid and ctable_' . $key . '.fieldto="projects" AND ctable_' . $key . '.fieldid=' . $value['id'], 'LEFT');
                $where_string .= ' OR ctable_' . $key . '.value LIKE "%' . $q . '%"';
            }
            $this->db->where('(tblleads.company LIKE "%' . $q . '%"
                OR tblprojects.description LIKE "%' . $q . '%"
                OR tblprojects.name LIKE "%' . $q . '%"
                
                OR tblleads.phonenumber LIKE "%' . $q . '%"
                OR tblleads.city LIKE "%' . $q . '%"
                OR tblleads.zip LIKE "%' . $q . '%"
                OR tblleads.state LIKE "%' . $q . '%"
                OR tblleads.zip LIKE "%' . $q . '%"
                OR tblleads.address LIKE "%' . $q . '%"
                ' . $where_string . '
                )');
        } else {
            $this->db->where('id IN
                (SELECT rel_id FROM tbltags_in WHERE tag_id IN
                (SELECT id FROM tbltags WHERE name="' . strafter($q, '#') . '")
                AND tbltags_in.rel_type=\'project\' GROUP BY rel_id HAVING COUNT(tag_id) = 1)
                ');
        }

        if (!empty($this->input->get('filters'))) {
            $filters = json_decode($this->input->get('filters'), true);

            if (!empty(get_client_user_id())) {
                $this->db->where(db_prefix() . 'projects.clientid',  get_client_user_id());
            }

            if (!empty($filters['status'])) {
                $this->db->where_in(db_prefix() . 'projects.status', $filters['status']);
            }

            if (!empty($filters['staff_id'])) {
                $this->db->where(db_prefix() . 'projects.id IN (SELECT project_id FROM ' . db_prefix() . 'project_members WHERE staff_id=' . $filters['staff_id'] . ')');
            }
        }

        // }

        if (!empty($this->input->get('sort'))) {
            $sorting = json_decode($this->input->get('sort'), true);
            $this->db->order_by($sorting['sort_by'], $sorting['order']);
        } else {
            $this->db->order_by(db_prefix() . 'projects.id', 'DESC');
        }

        if (is_numeric($this->input->get('limit')) && is_numeric($this->input->get('offset'))) {
            $this->db->limit($this->input->get('limit'));
            $this->db->offset($this->input->get('offset'));
        }

        $result['result'] = $this->db->get()->result_array();

        return $result;
    }

    public function _search_tasks_for_client($q, $limit = 0, $api = false)
    {
        $result = [
            'result'         => [],
            'type'           => 'tasks',
            'search_heading' => _l('tasks'),
        ];

        if (has_contact_permission('tasks') || true == $api) {
            // task
            $fields = get_custom_fields('tasks');
            $this->db->select(
                db_prefix() . 'tasks.*, 
                ' . get_sql_select_task_asignees_full_names() . ' as assignees,
                (SELECT GROUP_CONCAT(name SEPARATOR ",") FROM ' . db_prefix() . 'taggables JOIN ' . db_prefix() . 'tags ON ' . db_prefix() . 'taggables.tag_id = ' . db_prefix() . 'tags.id WHERE rel_id = ' . db_prefix() . 'tasks.id and rel_type="task" ORDER by tag_order ASC) as tags,
                ' . tasks_rel_name_select_query() . ' as rel_name
            '
            );
            $this->db->from(db_prefix() . 'tasks');
            $this->db->group_start();
            $this->db->like('name', $q);
            $this->db->or_like(db_prefix() . 'tasks.id', $q);
            foreach ($fields as $key => $value) {
                $this->db->join(db_prefix() . 'customfieldsvalues as ctable_' . $key . '', db_prefix() . 'tasks.id = ctable_' . $key . '.relid and ctable_' . $key . '.fieldto="tasks" AND ctable_' . $key . '.fieldid=' . $value['id'], 'LEFT');
                $this->db->or_like('ctable_' . $key . '.value', $q);
            }
            $this->db->group_end();
            if (!empty($this->input->get('filters'))) {
                $filters = json_decode($this->input->get('filters'), true);

                if (!empty($filters['assigned'])) {
                    $this->db->where(db_prefix() . 'tasks.id IN ( SELECT taskid from ' . db_prefix() . 'task_assigned where staffid IN (' . implode(', ', $filters['assigned']) . '))');
                }

                if (!empty($filters['status'])) {
                    $this->db->where_in(db_prefix() . 'tasks.status', $filters['status']);
                }

                if (!empty($filters['invoice_id'])) {
                    $this->db->where(db_prefix() . 'tasks.rel_id', $filters['invoice_id']);
                    $this->db->where(db_prefix() . 'tasks.rel_type', 'invoice');
                }

                if (!empty($filters['proposal_id'])) {
                    $this->db->where(db_prefix() . 'tasks.rel_id', $filters['proposal_id']);
                    $this->db->where(db_prefix() . 'tasks.rel_type', 'proposal');
                }

                if (!empty($filters['project_id'])) {
                    $this->db->where(db_prefix() . 'tasks.rel_id', $filters['project_id']);
                    $this->db->where(db_prefix() . 'tasks.rel_type', 'project');
                }

                if (!empty($filters['lead_id'])) {
                    $this->db->where(db_prefix() . 'tasks.rel_id', $filters['lead_id']);
                    $this->db->where(db_prefix() . 'tasks.rel_type', 'lead');
                }

                if (!empty($filters['rel_type']) && !empty($filters['rel_id'])) {
                    $this->db->where(db_prefix() . 'tasks.rel_type', $filters['rel_type']);
                    $this->db->where(db_prefix() . 'tasks.rel_id', $filters['rel_id']);
                }
            }

            if (!has_contact_permission('tasks', get_contact_user_id())) {
            }

            if (!empty($this->input->get('sort'))) {
                $sorting = json_decode($this->input->get('sort'), true);
                $this->db->order_by($sorting['sort_by'], $sorting['order']);
            } else {
                $this->db->order_by('id', 'DESC');
            }

            if (is_numeric($this->input->get('limit')) && is_numeric($this->input->get('offset'))) {
                $this->db->limit($this->input->get('limit'));
                $this->db->offset($this->input->get('offset'));
            }

            $result['result'] = $this->db->get()->result_array();
        }

        return $result;
    }
}
