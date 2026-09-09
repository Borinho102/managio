<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Get cash flow short_url
 * @since  Version 2.7.3
 * @param  object $cash_flow
 * @return string Url
 */
function get_cash_flow_shortlink($cash_flow)
{
    $long_url = site_url("cash_flow/{$cash_flow->id}/{$cash_flow->hash}");
    if (!get_option('bitly_access_token')) {
        return $long_url;
    }

    // Check if cash_flow has short link, if yes return short link
    if (!empty($cash_flow->short_link)) {
        return $cash_flow->short_link;
    }

    // Create short link and return the newly created short link
    $short_link = app_generate_short_link([
        'long_url' => $long_url,
        'title'    => 'Cash Flow#' . $cash_flow->id,
    ]);
    
    if ($short_link) {
        $CI = &get_instance();
        $CI->db->where('id', $cash_flow->id);
        $CI->db->update(db_prefix() . 'cash_flow', [
            'short_link' => $short_link,
        ]);

        return $short_link;
    }

    return $long_url;
}

/**
 * Check the cash flow view restrictions
 *
 * @param  int $id
 * @param  string $hash
 *
 * @return void
 */
function check_cash_flow_restrictions($id, $hash)
{
    $CI = &get_instance();
    $CI->load->model('cash_flow_model');

    if (!$hash || !$id) {
        show_404();
    }

    if (!is_client_logged_in() && !is_staff_logged_in()) {
        if (get_option('view_cash_flow_only_logged_in') == 1) {
            redirect_after_login_to_current_url();
            redirect(site_url('authentication/login'));
        }
    }

    $cash_flow = $CI->cash_flow_model->get($id);

    if (!$cash_flow || ($cash_flow->hash != $hash)) {
        show_404();
    }

    // Do one more check
    if (!is_staff_logged_in()) {
        if (get_option('view_cash_flow_only_logged_in') == 1) {
            if ($cash_flow->client != get_client_user_id()) {
                show_404();
            }
        }
    }
}

/**
 * Function that will search possible contracts templates in applicaion/views/admin/contracts/templates
 * Will return any found files and user will be able to add new template
 *
 * @return array
 */
function get_cash_flow_templates()
{
    $cash_flow_templates = [];
    if (is_dir(VIEWPATH . 'admin/cash_flow/templates')) {
        foreach (list_files(VIEWPATH . 'admin/cash_flow/templates') as $template) {
            $cash_flow_templates[] = $template;
        }
    }

    return $cash_flow_templates;
}






function count_total_invoiced($staffId = null)
{
    $where_own = [];
    $staffId   = is_null($staffId) ? get_staff_user_id() : $staffId;

    if (!has_permission('cash_flow', '', 'view')) {
        $where_own = ['addedfrom' => $staffId];
    }

    return total_rows(db_prefix() . 'expenses', array_merge(['trash' => 1], $where_own));


    
}

// function get_total(){
//     $CI =& get_instance(); 
//     $data['total'] = $CI->cash_flow_model->get_expenses_total();
//     return $data['total'];
    
//  }