<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Fournisseurs extends AdminController
{
    public function __construct()
    {
        parent::__construct();
        $this->load->model('fournisseurs/fournisseurs_model');
    }

    public function index()
    {
        if (staff_cant('view', 'fournisseurs') && staff_cant('create', 'fournisseurs')) {
            access_denied('fournisseurs');
        }

        if ($this->input->is_ajax_request()) {
            $this->app->get_table_data(module_views_path('fournisseurs', 'table'));
        }

        $data['title'] = _l('fournisseurs');
        $this->load->view('manage', $data);
    }

    public function fournisseur($id = '')
    {
        if (staff_cant('view', 'fournisseurs') && staff_cant('create', 'fournisseurs')) {
            access_denied('fournisseurs');
        }

        if ($this->input->post()) {
            $post = $this->input->post();

            if (trim($post['company'] ?? '') === '') {
                set_alert('danger', _l('fournisseur_company_required'));
                redirect($this->uri->uri_string());
            }

            if ($id === '') {
                if (staff_cant('create', 'fournisseurs')) {
                    access_denied('fournisseurs');
                }
                $newId = $this->fournisseurs_model->add($post);
                if ($newId) {
                    set_alert('success', _l('added_successfully', _l('fournisseur')));
                    redirect(admin_url('fournisseurs/fournisseur/' . $newId));
                }

                log_message('error', '[fournisseurs] Failed to create supplier: ' . json_encode($this->db->error()));
                set_alert('danger', _l('something_went_wrong'));
                redirect(admin_url('fournisseurs/fournisseur'));
            } else {
                if (staff_cant('edit', 'fournisseurs')) {
                    access_denied('fournisseurs');
                }
                $success = $this->fournisseurs_model->update($post, $id);
                if ($success) {
                    set_alert('success', _l('updated_successfully', _l('fournisseur')));
                }
                redirect(admin_url('fournisseurs/fournisseur/' . $id));
            }
        }

        if ($id === '') {
            if (staff_cant('create', 'fournisseurs')) {
                access_denied('fournisseurs');
            }
            $title = _l('add_new', _l('fournisseur_lowercase'));
        } else {
            $data['fournisseur'] = $this->fournisseurs_model->get($id);
            if (!$data['fournisseur']) {
                blank_page(_l('fournisseur_lowercase') . ' ' . _l('not_found'));
            }
            $title = _l('edit', _l('fournisseur_lowercase'));
        }

        $data['title'] = $title;
        $data['countries'] = get_all_countries();
        $data['categories'] = fournisseurs_get_categories();
        $this->load->view('fournisseur', $data);
    }

    public function delete($id)
    {
        if (staff_cant('delete', 'fournisseurs')) {
            access_denied('fournisseurs');
        }

        if (!$id) {
            redirect(admin_url('fournisseurs'));
        }

        $response = $this->fournisseurs_model->delete($id);
        if ($response === true) {
            set_alert('success', _l('deleted', _l('fournisseur')));
        } else {
            set_alert('warning', _l('problem_deleting', _l('fournisseur_lowercase')));
        }

        redirect(admin_url('fournisseurs'));
    }

    public function change_status($id, $status)
    {
        if (staff_cant('edit', 'fournisseurs')) {
            ajax_access_denied();
        }

        if ($this->input->is_ajax_request()) {
            $this->fournisseurs_model->change_status($id, $status);
        }
    }
}
