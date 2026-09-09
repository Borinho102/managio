<?php

defined('BASEPATH') or exit('No direct script access allowed');

function get_cash_flow_shortlink($cash_flow)
{
    $long_url = site_url("cash_flow/{$cash_flow->id}/{$cash_flow->hash}");
    if (!get_option('bitly_access_token')) {
        return $long_url;
    }

    if (!empty($cash_flow->short_link)) {
        return $cash_flow->short_link;
    }

    $short_link = app_generate_short_link([
        'long_url' => $long_url,
        'title' => 'Cash Flow#' . $cash_flow->id,
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
    if (!is_staff_logged_in()) {
        if (get_option('view_cash_flow_only_logged_in') == 1) {
            if ($cash_flow->client != get_client_user_id()) {
                show_404();
            }
        }
    }
}

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
    $staffId = is_null($staffId) ? get_staff_user_id() : $staffId;

    if (!staff_can('view','cash_flow' )) {
        $where_own = ['addedfrom' => $staffId];
    }

    return total_rows(db_prefix() . 'cf_expenses', array_merge(['trash' => 1], $where_own));
}

function get_category_id_by_name($categoryName)
{
    $CI = &get_instance();
    if (!class_exists('cash_flow/cash_flow_model')) {
        $CI->load->model('cash_flow/cash_flow_model');
    }
    $categoryName = strtolower($categoryName);
    $category_id = $CI->cash_flow_model->get_category_by_name($categoryName);
    return $category_id;
}

function handle_cf_expense_attachments($id)
{
    if (isset($_FILES['file']) && _perfex_upload_error($_FILES['file']['error'])) {
        header('HTTP/1.0 400 Bad error');
        echo _perfex_upload_error($_FILES['file']['error']);
        die;
    }
    $hookData = hooks()->apply_filters('before_handle_expense_attachment', [
        'expense_id' => $id,
        'index_name' => 'file',
        'handled_externally' => false,
        'handled_externally_successfully' => false,
        'files' => $_FILES
    ]);

    if ($hookData['handled_externally']) {
        return $hookData['handled_externally_successfully'];
    }
    $path = get_upload_path_by_type('expense') . $id . '/';

    $CI = &get_instance();  

    if (isset($_FILES['file']['name'])) {
      
        $tmpFilePath = $_FILES['file']['tmp_name'];
        if (!empty($tmpFilePath) && $tmpFilePath != '') {
            _maybe_create_upload_path($path);
            $filename = $_FILES['file']['name'];
            $newFilePath = $path . $filename;
            if (move_uploaded_file($tmpFilePath, $newFilePath)) {
                $attachment = [];
                $attachment[] = [
                    'file_name' => $filename,
                    'filetype' => $_FILES['file']['type'],
                ];
                $CI->misc_model->add_attachment_to_database($id, 'cashflow_expense', $attachment);
            }
        }
    }

}

function get_expenses_count_by_buisness_id($bid) {
    $CI = &get_instance();  
    $CI->db->from(db_prefix() . 'cf_expenses');
    $CI->db->where('buisness_id', $bid);
    return $CI->db->count_all_results();
}
function cf_force_download($filename = '', $data = '', $set_mime = FALSE)
	{
		if ($filename === '' OR $data === '')
		{
			return;
		}
		elseif ($data === NULL)
		{
			if ( ! @is_file($filename) OR ($filesize = @filesize($filename)) === FALSE)
			{
				return;
			}

			$filepath = $filename;
			$filename = explode('/', str_replace(DIRECTORY_SEPARATOR, '/', $filename));
			$filename = end($filename);
		}
		else
		{
			$filesize = strlen($data);
		}

		// Set the default MIME type to send
		$mime = 'application/octet-stream';

		$x = explode('.', $filename);
		$extension = end($x);

		if ($set_mime === TRUE)
		{
			if (count($x) === 1 OR $extension === '')
			{
				/* If we're going to detect the MIME type,
				 * we'll need a file extension.
				 */
				return;
			}

			// Load the mime types
			$mimes =& get_mimes();

			// Only change the default MIME if we can find one
			if (isset($mimes[$extension]))
			{
				$mime = is_array($mimes[$extension]) ? $mimes[$extension][0] : $mimes[$extension];
			}
		}

		/* It was reported that browsers on Android 2.1 (and possibly older as well)
		 * need to have the filename extension upper-cased in order to be able to
		 * download it.
		 *
		 * Reference: http://digiblog.de/2011/04/19/android-and-the-download-file-headers/
		 */
		if (count($x) !== 1 && isset($_SERVER['HTTP_USER_AGENT']) && preg_match('/Android\s(1|2\.[01])/', $_SERVER['HTTP_USER_AGENT']))
		{
			$x[count($x) - 1] = strtoupper($extension);
			$filename = implode('.', $x);
		}

		if ($data === NULL && ($fp = @fopen($filepath, 'rb')) === FALSE)
		{
			return;
		}

		// Clean output buffer
		if (ob_get_level() !== 0 && @ob_end_clean() === FALSE)
		{
			@ob_clean();
		}

		// Generate the server headers
		header('Content-Type: '.$mime);
		header('Content-Disposition: attachment; filename="'.$filename.'"');
		header('Expires: 0');
		header('Content-Transfer-Encoding: binary');
		header('Content-Length: '.$filesize);
		header('Cache-Control: private, no-transform, no-store, must-revalidate');

		// If we have raw data - just dump it
		if ($data !== NULL)
		{
			exit($data);
		}

		// Flush 1MB chunks of data
		while ( ! feof($fp) && ($data = fread($fp, 1048576)) !== FALSE)
		{
			echo $data;
		}

		fclose($fp);
		exit;
	}