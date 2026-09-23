<?php

defined('BASEPATH') or exit('No direct script access allowed');

function wasabi_storage_enabled()
{
    return get_option('wasabi_storage_enabled') == '1' && wasabi_storage_credentials_ready();
}

function wasabi_storage_credentials_ready()
{
    return get_option('wasabi_access_key') !== ''
        && get_option('wasabi_secret_key') !== ''
        && get_option('wasabi_bucket') !== ''
        && get_option('wasabi_endpoint') !== '';
}

function wasabi_storage_client()
{
    $CI = &get_instance();
    if (!isset($CI->wasabi_client) || !($CI->wasabi_client instanceof Wasabi_client)) {
        $CI->load->library(WASABI_STORAGE_MODULE_NAME . '/wasabi_client');
    }

    return $CI->wasabi_client;
}

function wasabi_storage_prefix()
{
    $prefix = trim((string) get_option('wasabi_path_prefix'));
    if ($prefix === '') {
        return '';
    }

    return rtrim($prefix, '/') . '/';
}

function wasabi_storage_object_key($type, $relId, $filename)
{
    $type = preg_replace('/[^a-z0-9_\-]/i', '', (string) $type) ?: 'files';
    $relId = (int) $relId;
    $filename = ltrim(str_replace(['..', '\\'], '', (string) $filename), '/');

    return wasabi_storage_prefix() . $type . '/' . $relId . '/' . $filename;
}

function wasabi_storage_remember_mapping($relType, $relId, $fileName, $objectKey, $filetype = null)
{
    if (!function_exists('wasabi_storage_ensure_table')) {
        require_once module_dir_path(WASABI_STORAGE_MODULE_NAME, 'install.php');
    }
    wasabi_storage_ensure_table();

    $CI = &get_instance();
    $CI->db->where('rel_type', $relType);
    $CI->db->where('rel_id', (int) $relId);
    $CI->db->where('file_name', $fileName);
    $existing = $CI->db->get(db_prefix() . 'wasabi_files')->row();

    $data = [
        'rel_type'   => $relType,
        'rel_id'     => (int) $relId,
        'file_name'  => $fileName,
        'object_key' => $objectKey,
        'filetype'   => $filetype,
        'dateadded'  => date('Y-m-d H:i:s'),
    ];

    if ($existing) {
        $CI->db->where('id', $existing->id);
        $CI->db->update(db_prefix() . 'wasabi_files', $data);
    } else {
        $CI->db->insert(db_prefix() . 'wasabi_files', $data);
    }
}

function wasabi_storage_find_mapping($relType, $relId, $fileName)
{
    $CI = &get_instance();
    if (!$CI->db->table_exists(db_prefix() . 'wasabi_files')) {
        return null;
    }
    $CI->db->where('rel_type', $relType);
    $CI->db->where('rel_id', (int) $relId);
    $CI->db->where('file_name', $fileName);

    return $CI->db->get(db_prefix() . 'wasabi_files')->row();
}

function wasabi_storage_find_key_by_filename($fileName, $relType = null, $relId = null)
{
    $CI = &get_instance();
    if (!$CI->db->table_exists(db_prefix() . 'wasabi_files')) {
        return null;
    }
    if ($relType !== null) {
        $CI->db->where('rel_type', $relType);
    }
    if ($relId !== null) {
        $CI->db->where('rel_id', (int) $relId);
    }
    $CI->db->where('file_name', $fileName);
    $row = $CI->db->get(db_prefix() . 'wasabi_files')->row();

    return $row ? $row->object_key : null;
}

function wasabi_storage_delete_mapping_and_object($relType, $relId, $fileName)
{
    $row = wasabi_storage_find_mapping($relType, $relId, $fileName);
    $key = $row ? $row->object_key : null;
    if (!$key) {
        // also check files table
        $CI = &get_instance();
        $CI->db->where('rel_type', $relType);
        $CI->db->where('rel_id', (int) $relId);
        $CI->db->where('file_name', $fileName);
        $CI->db->where('external', WASABI_STORAGE_EXTERNAL);
        $file = $CI->db->get(db_prefix() . 'files')->row();
        if ($file && !empty($file->external_link)) {
            $key = $file->external_link;
        }
    }
    if ($key && wasabi_storage_credentials_ready()) {
        wasabi_storage_client()->delete_object($key);
    }
    if ($row) {
        $CI = &get_instance();
        $CI->db->where('id', $row->id);
        $CI->db->delete(db_prefix() . 'wasabi_files');
    }
}

function wasabi_storage_signed_url($objectKey)
{
    if (!$objectKey || !wasabi_storage_credentials_ready()) {
        return '';
    }

    return wasabi_storage_client()->signed_url($objectKey);
}

/**
 * Stable download URL for tblfiles rows stored on Wasabi (views use external_link as href).
 */
function wasabi_storage_files_table_download_url($relType, $attachmentKey, $id, $relId)
{
    switch ($relType) {
        case 'task':
            return site_url('download/file/taskattachment/' . $attachmentKey);
        case 'customer':
            return site_url('download/file/client/' . $attachmentKey);
        case 'contract':
            return site_url('download/file/contract/' . $attachmentKey);
        case 'lead':
            return site_url('download/file/lead_attachment/' . $id);
        case 'newsfeed':
            return site_url('download/file/newsfeed/' . $id);
        case 'expense':
            return site_url('download/file/expense/' . $relId);
        case 'estimate_request':
            return site_url('download/file/estimate_request_attachment/' . $id);
        case 'invoice':
        case 'estimate':
        case 'proposal':
        case 'credit_note':
            return site_url('download/file/sales_attachment/' . $attachmentKey);
        default:
            return $attachmentKey ? site_url('download/file/sales_attachment/' . $attachmentKey) : '';
    }
}

/**
 * True when external_link looks like an S3 object key (not an http URL).
 */
function wasabi_storage_looks_like_object_key($value)
{
    if (!is_string($value) || $value === '') {
        return false;
    }
    if (preg_match('#^(https?:)?//#i', $value) || strpos($value, 'download/file/') !== false) {
        return false;
    }

    return true;
}

function wasabi_storage_upload_tmp($tmpPath, $objectKey, $mime = 'application/octet-stream')
{
    if (!is_uploaded_file($tmpPath) && !is_file($tmpPath)) {
        return false;
    }
    $client = wasabi_storage_client();
    $ok = $client->put_object($objectKey, $tmpPath, $mime ?: 'application/octet-stream');
    if (!$ok) {
        update_option('wasabi_last_error', $client->get_last_error());
    }

    return $ok;
}

function wasabi_storage_unique_name($original)
{
    $original = basename(str_replace('\\', '/', (string) $original));
    $original = preg_replace('/[^a-zA-Z0-9._\- ]+/', '_', $original);
    if ($original === '' || $original === '.' || $original === '..') {
        $original = 'file.bin';
    }

    return time() . '_' . $original;
}

/**
 * Normalize $_FILES[$index] to multi-file arrays.
 */
function wasabi_storage_normalize_files_index($files, $index)
{
    if (!isset($files[$index])) {
        return null;
    }
    $f = $files[$index];
    if (!is_array($f['name'])) {
        return [
            'name'     => [$f['name']],
            'type'     => [$f['type']],
            'tmp_name' => [$f['tmp_name']],
            'error'    => [$f['error']],
            'size'     => [$f['size']],
        ];
    }

    return $f;
}

function wasabi_storage_collect_uploads($files, $index, $type, $relId)
{
    $normalized = wasabi_storage_normalize_files_index($files, $index);
    if (!$normalized) {
        return [];
    }

    $uploaded = [];
    for ($i = 0; $i < count($normalized['name']); $i++) {
        if (_perfex_upload_error($normalized['error'][$i]) || empty($normalized['tmp_name'][$i])) {
            continue;
        }
        if (function_exists('_upload_extension_allowed') && !_upload_extension_allowed($normalized['name'][$i])) {
            continue;
        }

        $filename = wasabi_storage_unique_name($normalized['name'][$i]);
        $mime = $normalized['type'][$i] ?: 'application/octet-stream';
        $key = wasabi_storage_object_key($type, $relId, $filename);
        if (!wasabi_storage_upload_tmp($normalized['tmp_name'][$i], $key, $mime)) {
            continue;
        }
        wasabi_storage_remember_mapping($type, $relId, $filename, $key, $mime);
        $uploaded[] = [
            'file_name'     => $filename,
            'filetype'      => $mime,
            'name'          => $filename,
            'link'          => $key,
            'mime'          => $mime,
            'external'      => WASABI_STORAGE_EXTERNAL,
            'external_link' => $key,
        ];
    }

    return $uploaded;
}

/* -------------------- Upload filters -------------------- */

function wasabi_storage_filter_task_attachments($hookData)
{
    if (!wasabi_storage_enabled()) {
        return $hookData;
    }
    $taskId = (int) ($hookData['task_id'] ?? 0);
    $index = $hookData['index_name'] ?? 'attachments';
    $files = $hookData['files'] ?? $_FILES;
    $uploaded = wasabi_storage_collect_uploads($files, $index, 'task', $taskId);
    $hookData['handled_externally'] = true;
    $hookData['uploaded_files'] = $uploaded;

    return $hookData;
}

function wasabi_storage_filter_ticket_attachments($hookData)
{
    if (!wasabi_storage_enabled()) {
        return $hookData;
    }
    $ticketId = (int) ($hookData['ticket_id'] ?? 0);
    $index = $hookData['index_name'] ?? 'attachments';
    $files = $hookData['files'] ?? $_FILES;
    $uploaded = wasabi_storage_collect_uploads($files, $index, 'ticket', $ticketId);
    // tickets table has no external column — keep local-style array, mapping table used on download
    $localStyle = [];
    foreach ($uploaded as $u) {
        $localStyle[] = [
            'file_name' => $u['file_name'],
            'filetype'  => $u['filetype'],
        ];
    }
    $hookData['handled_externally'] = true;
    $hookData['uploaded_files'] = $localStyle;

    return $hookData;
}

function wasabi_storage_filter_project_files($hookData)
{
    if (!wasabi_storage_enabled()) {
        return $hookData;
    }
    $projectId = (int) ($hookData['project_id'] ?? 0);
    $files = $hookData['files'] ?? $_FILES;
    $uploaded = wasabi_storage_collect_uploads($files, 'file', 'project', $projectId);
    $CI = &get_instance();
    $ok = false;
    foreach ($uploaded as $u) {
        if (is_client_logged_in()) {
            $contact_id = get_contact_user_id();
            $staffid = 0;
        } else {
            $staffid = get_staff_user_id();
            $contact_id = 0;
        }
        $data = [
            'project_id'         => $projectId,
            'file_name'          => $u['file_name'],
            'original_file_name' => $u['file_name'],
            'filetype'           => $u['filetype'],
            'dateadded'          => date('Y-m-d H:i:s'),
            'staffid'            => $staffid,
            'contact_id'         => $contact_id,
            'subject'            => $u['file_name'],
            'external'           => WASABI_STORAGE_EXTERNAL,
            'external_link'      => $u['link'],
            'visible_to_customer'=> is_client_logged_in() ? 1 : ($CI->input->post('visible_to_customer') == 'true' ? 1 : 0),
        ];
        $CI->db->insert(db_prefix() . 'project_files', $data);
        if ($CI->db->insert_id()) {
            $ok = true;
            $CI->load->model('projects_model');
            $CI->projects_model->new_project_file_notification($CI->db->insert_id(), $projectId);
        }
    }
    $hookData['handled_externally'] = true;
    $hookData['handled_externally_successfully'] = $ok;

    return $hookData;
}

function wasabi_storage_filter_sales_attachments($hookData)
{
    if (!wasabi_storage_enabled()) {
        return $hookData;
    }
    $relId = (int) ($hookData['rel_id'] ?? 0);
    $relType = $hookData['rel_type'] ?? 'invoice';
    $files = $hookData['files'] ?? $_FILES;
    $uploaded = wasabi_storage_collect_uploads($files, 'file', $relType, $relId);
    $CI = &get_instance();
    $payload = ['success' => false, 'rel_id' => $relId];
    if (!empty($uploaded[0])) {
        $u = $uploaded[0];
        $attachment = [[
            'name' => $u['name'],
            'link' => $u['link'],
            'mime' => $u['mime'],
        ]];
        $insert_id = $CI->misc_model->add_attachment_to_database($relId, $relType, $attachment, WASABI_STORAGE_EXTERNAL);
        $CI->db->where('id', $insert_id);
        $_attachment = $CI->db->get(db_prefix() . 'files')->row();
        $payload = [
            'success'       => true,
            'attachment_id' => $insert_id,
            'filetype'      => $u['mime'],
            'rel_id'        => $relId,
            'file_name'     => $u['name'],
            'key'           => $_attachment ? $_attachment->attachment_key : '',
        ];
        if ($relType == 'invoice') {
            $CI->load->model('invoices_model');
            $CI->invoices_model->log_invoice_activity($relId, 'invoice_activity_added_attachment');
        } elseif ($relType == 'estimate') {
            $CI->load->model('estimates_model');
            $CI->estimates_model->log_estimate_activity($relId, 'estimate_activity_added_attachment');
        }
    }
    $hookData['handled_externally'] = true;
    $hookData['handled_externally_successfully'] = json_encode($payload);

    return $hookData;
}

function wasabi_storage_filter_client_attachments($hookData)
{
    if (!wasabi_storage_enabled()) {
        return $hookData;
    }
    $customerId = (int) ($hookData['customer_id'] ?? 0);
    $files = $hookData['files'] ?? $_FILES;
    $uploaded = wasabi_storage_collect_uploads($files, 'file', 'customer', $customerId);
    $CI = &get_instance();
    $total = 0;
    foreach ($uploaded as $u) {
        $attachment = [[
            'name'       => $u['name'],
            'link'       => $u['link'],
            'mime'       => $u['mime'],
            'contact_id' => !empty($hookData['customer_upload']) ? get_contact_user_id() : null,
        ]];
        if ($CI->misc_model->add_attachment_to_database($customerId, 'customer', $attachment, WASABI_STORAGE_EXTERNAL)) {
            $total++;
        }
    }
    $hookData['handled_externally'] = true;
    $hookData['handled_externally_successfully'] = $total > 0;
    $hookData['total_uploaded'] = $total;

    return $hookData;
}

function wasabi_storage_filter_simple_rel($hookData, $idKey, $type, $index = null)
{
    if (!wasabi_storage_enabled()) {
        return $hookData;
    }
    $relId = (int) ($hookData[$idKey] ?? 0);
    $files = $hookData['files'] ?? $_FILES;
    $index = $index ?: ($hookData['index_name'] ?? 'file');
    $uploaded = wasabi_storage_collect_uploads($files, $index, $type, $relId);
    $CI = &get_instance();
    $ok = false;
    foreach ($uploaded as $u) {
        $attachment = [[
            'name' => $u['name'],
            'link' => $u['link'],
            'mime' => $u['mime'],
        ]];
        if ($CI->misc_model->add_attachment_to_database($relId, $type, $attachment, WASABI_STORAGE_EXTERNAL)) {
            $ok = true;
        }
    }
    $hookData['handled_externally'] = true;
    $hookData['handled_externally_successfully'] = $ok;

    return $hookData;
}

function wasabi_storage_filter_expense_attachment($hookData)
{
    return wasabi_storage_filter_simple_rel($hookData, 'expense_id', 'expense');
}

function wasabi_storage_filter_contract_attachment($hookData)
{
    return wasabi_storage_filter_simple_rel($hookData, 'contract_id', 'contract');
}

function wasabi_storage_filter_lead_attachment($hookData)
{
    return wasabi_storage_filter_simple_rel($hookData, 'lead_id', 'lead', $hookData['index_name'] ?? 'file');
}

function wasabi_storage_filter_newsfeed_attachments($hookData)
{
    if (!wasabi_storage_enabled()) {
        return $hookData;
    }
    $postId = (int) ($hookData['newsfeed_post_id'] ?? 0);
    $files = $hookData['files'] ?? $_FILES;
    $uploaded = wasabi_storage_collect_uploads($files, 'file', 'newsfeed', $postId);
    $CI = &get_instance();
    $ok = false;
    foreach ($uploaded as $u) {
        $attachment = [[
            'name' => $u['name'],
            'link' => $u['link'],
            'mime' => $u['mime'],
        ]];
        if ($CI->misc_model->add_attachment_to_database($postId, 'newsfeed', $attachment, WASABI_STORAGE_EXTERNAL)) {
            $ok = true;
        }
    }
    $hookData['handled_externally'] = true;
    $hookData['handled_externally_successfully'] = $ok;

    return $hookData;
}

function wasabi_storage_filter_estimate_request_attachment($hookData)
{
    return wasabi_storage_filter_simple_rel($hookData, 'estimate_request_id', 'estimate_request');
}

function wasabi_storage_filter_discussion_attachment($hookData)
{
    if (!wasabi_storage_enabled()) {
        return $hookData;
    }
    $discussionId = (int) ($hookData['discussion_id'] ?? 0);
    $files = $hookData['files'] ?? $_FILES;
    $uploaded = wasabi_storage_collect_uploads($files, 'file', 'discussion', $discussionId);
    $hookData['handled_externally'] = count($uploaded) > 0;
    $hookData['handled_externally_successfully'] = count($uploaded) > 0;
    if (!empty($uploaded[0]) && isset($hookData['insert_data']) && is_array($hookData['insert_data'])) {
        $hookData['insert_data']['file_name'] = $uploaded[0]['file_name'];
        $hookData['insert_data']['file_mime_type'] = $uploaded[0]['filetype'];
    }

    return $hookData;
}

function wasabi_storage_filter_passthrough_company($hookData)
{
    // Company logo/favicon/signature and profile images are often referenced by local path in settings.
    // Keep local disk for these branding assets so theme/URL helpers keep working.
    return $hookData;
}

function wasabi_storage_filter_company_logo($hookData)
{
    return wasabi_storage_filter_passthrough_company($hookData);
}

function wasabi_storage_filter_company_signature($hookData)
{
    return wasabi_storage_filter_passthrough_company($hookData);
}

function wasabi_storage_filter_favicon($hookData)
{
    return wasabi_storage_filter_passthrough_company($hookData);
}

function wasabi_storage_filter_staff_profile($hookData)
{
    return wasabi_storage_filter_passthrough_company($hookData);
}

function wasabi_storage_filter_contact_profile($hookData)
{
    return wasabi_storage_filter_passthrough_company($hookData);
}

/* -------------------- Download / URL -------------------- */

function wasabi_storage_download_file_path($path, $data)
{
    if (!wasabi_storage_credentials_ready()) {
        return $path;
    }

    $folder = $data['folder'] ?? '';
    $attachmentid = $data['attachmentid'] ?? '';
    $CI = &get_instance();
    $objectKey = null;
    $fileName = null;
    $relType = null;
    $relId = null;

    $loadFileByKey = function ($key) use ($CI) {
        $CI->db->where('attachment_key', $key);

        return $CI->db->get(db_prefix() . 'files')->row();
    };
    $loadFileById = function ($id) use ($CI) {
        $CI->db->where('id', $id);

        return $CI->db->get(db_prefix() . 'files')->row();
    };

    if ($folder === 'ticket') {
        $CI->db->where('id', $attachmentid);
        $att = $CI->db->get(db_prefix() . 'ticket_attachments')->row();
        if ($att) {
            $fileName = $att->file_name;
            $relType = 'ticket';
            $relId = $att->ticketid;
            $objectKey = wasabi_storage_find_key_by_filename($fileName, 'ticket', $relId);
        }
    } elseif (in_array($folder, ['taskattachment', 'sales_attachment', 'contract', 'client', 'newsfeed', 'lead_attachment', 'l_attachment_key', 'estimate_request_attachment', 'expense'], true)) {
        if (in_array($folder, ['taskattachment', 'sales_attachment', 'contract', 'client', 'l_attachment_key'], true)) {
            $att = $loadFileByKey($attachmentid);
        } elseif ($folder === 'expense') {
            $CI->db->where('rel_id', $attachmentid);
            $CI->db->where('rel_type', 'expense');
            $att = $CI->db->get(db_prefix() . 'files')->row();
        } else {
            $att = $loadFileById($attachmentid);
        }
        if ($att) {
            $fileName = $att->file_name;
            $relType = $att->rel_type;
            $relId = $att->rel_id;
            if (!empty($att->external) && $att->external === WASABI_STORAGE_EXTERNAL) {
                $objectKey = wasabi_storage_find_key_by_filename($fileName, $relType, $relId);
                if (!$objectKey && !empty($att->external_link) && wasabi_storage_looks_like_object_key($att->external_link)) {
                    $objectKey = $att->external_link;
                }
            } else {
                $objectKey = wasabi_storage_find_key_by_filename($fileName, $relType, $relId);
            }
        }
    }

    if (!$objectKey && is_string($path) && $path !== '') {
        $base = basename($path);
        $objectKey = wasabi_storage_find_key_by_filename($base);
    }

    if ($objectKey) {
        $url = wasabi_storage_signed_url($objectKey);
        if ($url) {
            redirect($url);
            exit;
        }
    }

    return $path;
}

function wasabi_storage_project_file_url($url, $file = null)
{
    // Some Perfex versions pass only $file as first arg via filter signature project_file_url($file,$preview)
    // Our hook is registered as filter on return value — Perfex core doesn't filter project_file_url by default.
    // We override via helper replacement in module init if needed. Keep safe no-op here.
    return $url;
}

function wasabi_storage_url_for_project_file($file)
{
    if (is_array($file)) {
        $file = (object) $file;
    }
    if (!empty($file->external) && $file->external === WASABI_STORAGE_EXTERNAL && !empty($file->external_link)) {
        return wasabi_storage_signed_url($file->external_link);
    }
    if (!empty($file->file_name) && !empty($file->project_id)) {
        $key = wasabi_storage_find_key_by_filename($file->file_name, 'project', $file->project_id);
        if ($key) {
            return wasabi_storage_signed_url($key);
        }
    }

    return null;
}

/* -------------------- Products -------------------- */

function wasabi_storage_handle_product_image($productId)
{
    if (!wasabi_storage_enabled() || empty($_FILES['product']['name'])) {
        return false;
    }
    $tmp = $_FILES['product']['tmp_name'];
    if (empty($tmp)) {
        return false;
    }
    $ext = strtolower(pathinfo($_FILES['product']['name'], PATHINFO_EXTENSION));
    $filename = 'product_' . (int) $productId . '.' . $ext;
    $mime = $_FILES['product']['type'] ?: 'application/octet-stream';
    $key = wasabi_storage_object_key('products', $productId, $filename);
    if (!wasabi_storage_upload_tmp($tmp, $key, $mime)) {
        return false;
    }
    wasabi_storage_remember_mapping('products', $productId, $filename, $key, $mime);
    $CI = &get_instance();
    $CI->load->model('products/products_model');
    $CI->products_model->edit_product(['product_image' => $filename], $productId);

    return true;
}

function wasabi_storage_product_image_url($productImage)
{
    if (empty($productImage)) {
        return '';
    }
    // product_image may be "wasabi:key" or plain filename
    if (strpos($productImage, 'wasabi:') === 0) {
        return wasabi_storage_signed_url(substr($productImage, 7));
    }
    $CI = &get_instance();
    // try mapping without knowing product id
    $key = wasabi_storage_find_key_by_filename($productImage, 'products');
    if ($key) {
        return wasabi_storage_signed_url($key);
    }

    return '';
}

/* -------------------- Backup -------------------- */

function wasabi_storage_remember_backup_snapshot()
{
    if (!defined('BACKUPS_FOLDER') || !is_dir(BACKUPS_FOLDER)) {
        return;
    }
    $list = list_files(BACKUPS_FOLDER);
    update_option('wasabi_known_backups', json_encode(array_values($list)));
}

function wasabi_storage_maybe_push_new_backups()
{
    if (get_option('wasabi_backup_enabled') != '1' || !wasabi_storage_credentials_ready()) {
        return;
    }
    if (!defined('BACKUPS_FOLDER') || !is_dir(BACKUPS_FOLDER)) {
        return;
    }
    $known = json_decode(get_option('wasabi_known_backups') ?: '[]', true);
    if (!is_array($known)) {
        $known = [];
    }
    $current = list_files(BACKUPS_FOLDER);
    $newFiles = array_diff($current, $known);
    foreach ($newFiles as $file) {
        if ($file === 'index.html' || $file === '.htaccess') {
            continue;
        }
        $path = BACKUPS_FOLDER . $file;
        if (!is_file($path)) {
            continue;
        }
        $key = wasabi_storage_prefix() . 'backups/' . $file;
        wasabi_storage_client()->put_object($key, $path, 'application/zip');
        wasabi_storage_remember_mapping('backup', 0, $file, $key, 'application/zip');
    }
    update_option('wasabi_known_backups', json_encode(array_values($current)));
}

function wasabi_storage_push_backup_file($filename)
{
    if (!wasabi_storage_credentials_ready() || !defined('BACKUPS_FOLDER')) {
        return false;
    }
    $path = BACKUPS_FOLDER . $filename;
    if (!is_file($path)) {
        return false;
    }
    $key = wasabi_storage_prefix() . 'backups/' . $filename;
    $ok = wasabi_storage_client()->put_object($key, $path, 'application/zip');
    if ($ok) {
        wasabi_storage_remember_mapping('backup', 0, $filename, $key, 'application/zip');
    }

    return $ok;
}
