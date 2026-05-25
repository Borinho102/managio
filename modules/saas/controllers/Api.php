<?php defined('BASEPATH') or exit('No direct script access allowed');

class Api extends AdminController
{
    public function __construct()
    {
        parent::__construct();
        $this->load->model('saas_model');
        saas_access();
    }

    public function index()
    {
        $data['title'] = _l('api');
        $data['active'] = 1;
        $data['subview'] = $this->load->view('api/manage', $data, true);
        $this->load->view('_layout_main', $data);
    }

    public function generate_token($id = null)
    {
        $data['title'] = _l('generate') . ' ' . _l('token');
        if (!empty($id)) {
            $data['token'] = get_row('tbl_saas_api_token', ['id' => $id]);
        }
        $data['subview'] = $this->load->view('saas/api/create_generate_token', $data, FALSE);
        $this->load->view('saas/_layout_modal_lg', $data); //page load
    }

    public function tokenList()
    {
        if ($this->input->is_ajax_request()) {
            $this->load->model('datatables');
            $this->datatables->table = 'tbl_saas_api_token';
            $this->datatables->column_search = array('title', 'token');
            $this->datatables->column_order = array('title', 'token');
            $this->datatables->order = array('tbl_saas_api_token.id' => 'asc');
            $fetch_data = make_datatables();
            $data = array();

            foreach ($fetch_data as $_key => $v_creatives) {
                $action = null;
                $sub_array = array();
                $title = $v_creatives->title;
                $title .= '<div class="row-options">';
                $title .= '<a data-toggle="modal" data-placement="top" data-target="#myModal" href="' . saas_url('api/generate_token/' . $v_creatives->id) . '" data-toggle="tooltip" title="' . _l('edit') . '">' . _l('edit') . '</a>';
                $title .= ' | <a href="' . saas_url('api/delete_generate_token/' . $v_creatives->id) . '" class="text-danger _delete" data-toggle="tooltip" title="' . _l('delete') . '">' . _l('delete') . '</a>';
                $title .= '</div>';
                $sub_array[] = $title;
                $sub_array[] = $v_creatives->token;
                if ($v_creatives->status == 1) {
                    $sub_array[] = '<span class="label label-success">' . _l('active') . '</span>';
                } else {
                    $sub_array[] = '<span class="label label-danger">' . _l('deactive') . '</span>';
                }

                $data[] = $sub_array;
            }
            render_table($data);
        } else {
            redirect('admin/dashboard');
        }

    }

    public function delete_generate_token($id)
    {
        $this->db->where('id', $id);
        $this->db->delete('tbl_saas_api_token');
        set_alert('success', _l('deleted_successfully', _l('token')));
        redirect(saas_url('api'));
    }

    public function generated_token()
    {
        $token = bin2hex(random_bytes(16));
        echo json_encode(['token' => $token]);
    }

    public function save_token($id = null)
    {
        $data = $this->input->post();
        $data['status'] = $data['status'] ?? 0;

        if (!empty($id)) {
            $this->db->where('id', $id);
            $this->db->update('tbl_saas_api_token', $data);
            set_alert('success', _l('updated_successfully', _l('token')));
        } else {
            $this->db->insert('tbl_saas_api_token', $data);
            set_alert('success', _l('added_successfully', _l('token')));
        }
        redirect(saas_url('api'));
    }


}
