<?php

defined('BASEPATH') or exit('No direct script access allowed');

function wasabi_storage_enabled()
{
    return get_option('wasabi_storage_enabled') == '1' && wasabi_storage_credentials_ready();
}

function wasabi_storage_credentials_ready()
{
    return trim((string) get_option('wasabi_access_key')) !== ''
        && trim((string) get_option('wasabi_secret_key')) !== ''
        && trim((string) get_option('wasabi_bucket')) !== ''
        && trim((string) get_option('wasabi_endpoint')) !== '';
}

function wasabi_storage_client()
{
    $CI = &get_instance();
    if (isset($CI->wasabi_client) && is_object($CI->wasabi_client) && get_class($CI->wasabi_client) === 'Wasabi_client') {
        return $CI->wasabi_client;
    }

    // Load directly — MX Loader can pass null config / fail class resolve on some PHP/Linux setups (HTTP 500).
    if (!class_exists('Wasabi_client', false)) {
        $path = module_dir_path(WASABI_STORAGE_MODULE_NAME, 'libraries/Wasabi_client.php');
        if (!is_file($path)) {
            throw new RuntimeException('Wasabi_client library not found at ' . $path);
        }
        require_once $path;
    }
    $CI->wasabi_client = new Wasabi_client([]);

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
        if ($file && !empty($file->external_link) && wasabi_storage_looks_like_object_key($file->external_link)) {
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
    // Dropzone/temp uploads: accept readable files even if is_uploaded_file() is false on some hosts.
    if ((!is_uploaded_file($tmpPath) && !is_file($tmpPath)) || !is_readable($tmpPath)) {
        wasabi_storage_activity_log('error', 'Invalid/unreadable upload temp file for key ' . $objectKey);

        return false;
    }
    try {
        $client = wasabi_storage_client();
        $ok = $client->put_object($objectKey, $tmpPath, $mime ?: 'application/octet-stream');
        if (!$ok) {
            $err = $client->get_last_error();
            update_option('wasabi_last_error', $err);
            wasabi_storage_activity_log('error', 'PUT failed for ' . $objectKey . ': ' . $err);
        } else {
            wasabi_storage_activity_log('info', 'PUT ok: ' . $objectKey);
        }

        return $ok;
    } catch (Throwable $e) {
        update_option('wasabi_last_error', $e->getMessage());
        wasabi_storage_activity_log('error', 'PUT exception for ' . $objectKey . ': ' . $e->getMessage());

        return false;
    }
}

/**
 * Append a line to the module activity log (and CI log).
 */
function wasabi_storage_activity_log($level, $message)
{
    $level = strtolower((string) $level);
    if (!in_array($level, ['debug', 'info', 'error', 'warning'], true)) {
        $level = 'info';
    }
    $line = '[' . date('Y-m-d H:i:s') . '] ' . strtoupper($level) . ': ' . trim((string) $message);

    $dir = wasabi_storage_logs_directory();
    if ($dir) {
        $file = $dir . 'wasabi-' . date('Y-m-d') . '.log';
        @file_put_contents($file, $line . PHP_EOL, FILE_APPEND | LOCK_EX);
    }

    if (function_exists('log_message')) {
        log_message($level === 'warning' ? 'error' : $level, 'Wasabi: ' . $message);
    }
}

function wasabi_storage_logs_directory()
{
    $dir = FCPATH . 'uploads/wasabi_storage/logs/';
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
        if (is_dir($dir) && !is_file($dir . 'index.html')) {
            @file_put_contents($dir . 'index.html', '');
        }
        if (is_dir($dir) && !is_file($dir . '.htaccess')) {
            @file_put_contents($dir . '.htaccess', "Order Deny,Allow\nDeny from all\n");
        }
    }

    return is_dir($dir) && is_writable($dir) ? $dir : null;
}

/**
 * Collect preview data for the Logs tab.
 *
 * @return array{last_error:string,activity:string,php_log:string,activity_file:?string,php_log_file:?string}
 */
function wasabi_storage_collect_log_preview($maxLines = 200)
{
    $maxLines = max(50, min((int) $maxLines, 1000));
    $preview = [
        'last_error'    => (string) get_option('wasabi_last_error'),
        'activity'      => '',
        'php_log'       => '',
        'activity_file' => null,
        'php_log_file'  => null,
    ];

    $dir = wasabi_storage_logs_directory();
    if ($dir) {
        $today = $dir . 'wasabi-' . date('Y-m-d') . '.log';
        $yesterday = $dir . 'wasabi-' . date('Y-m-d', strtotime('-1 day')) . '.log';
        $files = [];
        if (is_file($today)) {
            $files[] = $today;
        }
        if (is_file($yesterday)) {
            $files[] = $yesterday;
        }
        if (!$files) {
            $all = glob($dir . 'wasabi-*.log') ?: [];
            rsort($all);
            if (!empty($all[0])) {
                $files[] = $all[0];
            }
        }
        $chunks = [];
        foreach (array_reverse($files) as $file) {
            $chunks[] = wasabi_storage_tail_file($file, $maxLines);
            $preview['activity_file'] = $file;
        }
        $preview['activity'] = trim(implode("\n", array_filter($chunks)));
    }

    $ciLogDir = defined('APPPATH') ? APPPATH . 'logs/' : (FCPATH . 'application/logs/');
    if (is_dir($ciLogDir)) {
        $ciToday = $ciLogDir . 'log-' . date('Y-m-d') . '.php';
        $ciFile = is_file($ciToday) ? $ciToday : null;
        if (!$ciFile) {
            $all = glob($ciLogDir . 'log-*.php') ?: [];
            rsort($all);
            $ciFile = $all[0] ?? null;
        }
        if ($ciFile) {
            $preview['php_log_file'] = $ciFile;
            $raw = wasabi_storage_tail_file($ciFile, $maxLines * 3);
            $filtered = [];
            foreach (preg_split('/\R/', $raw) as $line) {
                if ($line === '' || strpos($line, '<?php') === 0 || strpos($line, 'defined(') === 0) {
                    continue;
                }
                if (preg_match('/wasabi|Wasabi|S3|upload_file|task upload|Task upload/i', $line)) {
                    $filtered[] = $line;
                }
            }
            if (!$filtered) {
                // Fall back to last raw lines (minus PHP guard) so the tab is never empty when logs exist.
                foreach (array_slice(preg_split('/\R/', $raw), -$maxLines) as $line) {
                    if ($line === '' || strpos($line, '<?php') === 0 || strpos($line, 'defined(') === 0) {
                        continue;
                    }
                    $filtered[] = $line;
                }
            }
            $preview['php_log'] = implode("\n", array_slice($filtered, -$maxLines));
        }
    }

    if ($preview['activity'] === '') {
        $preview['activity'] = _l('wasabi_storage_logs_empty');
    }
    if ($preview['php_log'] === '') {
        $preview['php_log'] = _l('wasabi_storage_logs_php_empty');
    }

    return $preview;
}

function wasabi_storage_tail_file($path, $maxLines = 200)
{
    if (!is_file($path) || !is_readable($path)) {
        return '';
    }
    $size = filesize($path);
    if ($size === false || $size === 0) {
        return '';
    }
    $fh = fopen($path, 'rb');
    if (!$fh) {
        return '';
    }
    $buffer = '';
    $chunk = 8192;
    $pos = $size;
    $lines = 0;
    while ($pos > 0 && $lines <= $maxLines) {
        $read = ($pos >= $chunk) ? $chunk : $pos;
        $pos -= $read;
        fseek($fh, $pos);
        $buffer = fread($fh, $read) . $buffer;
        $lines = substr_count($buffer, "\n");
    }
    fclose($fh);
    $parts = preg_split('/\R/', $buffer);
    $parts = array_slice($parts, -$maxLines);

    return implode("\n", $parts);
}

function wasabi_storage_clear_activity_logs()
{
    $dir = wasabi_storage_logs_directory();
    if (!$dir) {
        return false;
    }
    foreach (glob($dir . 'wasabi-*.log') ?: [] as $file) {
        @unlink($file);
    }
    update_option('wasabi_last_error', '');

    return true;
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
    if (!isset($files[$index]) || !is_array($files[$index]) || !isset($files[$index]['name'])) {
        return null;
    }
    $f = $files[$index];
    if (!is_array($f['name'])) {
        return [
            'name'     => [$f['name'] ?? ''],
            'type'     => [$f['type'] ?? ''],
            'tmp_name' => [$f['tmp_name'] ?? ''],
            'error'    => [$f['error'] ?? UPLOAD_ERR_NO_FILE],
            'size'     => [$f['size'] ?? 0],
        ];
    }

    // Re-index like Perfex _file_attachments_index_fix (Dropzone gaps after remove).
    return [
        'name'     => array_values($f['name']),
        'type'     => array_values($f['type'] ?? []),
        'tmp_name' => array_values($f['tmp_name'] ?? []),
        'error'    => array_values($f['error'] ?? []),
        'size'     => array_values($f['size'] ?? []),
    ];
}

function wasabi_storage_has_incoming_files($files, $index)
{
    $normalized = wasabi_storage_normalize_files_index($files, $index);
    if (!$normalized) {
        return false;
    }
    for ($i = 0; $i < count($normalized['name']); $i++) {
        if (!_perfex_upload_error($normalized['error'][$i]) && !empty($normalized['tmp_name'][$i])) {
            return true;
        }
    }

    return false;
}

/**
 * When Wasabi is enabled, claim external handling with empty result so Perfex does not
 * silently fall back to local disk. Surfaces wasabi_last_error to the admin.
 */
function wasabi_storage_fail_external_upload(array $hookData, $message = null)
{
    $err = $message ?: get_option('wasabi_last_error') ?: 'Wasabi upload failed';
    update_option('wasabi_last_error', $err);
    wasabi_storage_activity_log('error', 'External upload hard-fail: ' . $err);
    if (function_exists('set_alert')) {
        $label = 'Wasabi upload failed';
        if (function_exists('_l')) {
            $translated = _l('wasabi_storage_upload_failed');
            if (is_string($translated) && $translated !== '' && $translated !== 'wasabi_storage_upload_failed') {
                $label = $translated;
            }
        }
        set_alert('danger', $label . ': ' . $err);
    }
    $hookData['handled_externally'] = true;
    $hookData['uploaded_files'] = [];
    $hookData['handled_externally_successfully'] = false;

    return $hookData;
}

/**
 * Store a single uploaded temp file on Wasabi and remember the mapping.
 *
 * @return string|false Object key on success, false on failure
 */
function wasabi_storage_store_uploaded_file($tmpPath, $relType, $relId, $fileName, $mime = 'application/octet-stream')
{
    if (!wasabi_storage_enabled()) {
        return false;
    }
    if (!is_uploaded_file($tmpPath) && !is_file($tmpPath)) {
        update_option('wasabi_last_error', 'Invalid upload temp file');

        return false;
    }
    $fileName = wasabi_storage_unique_name($fileName);
    $mime = $mime ?: 'application/octet-stream';
    $key = wasabi_storage_object_key($relType, $relId, $fileName);
    if (!wasabi_storage_upload_tmp($tmpPath, $key, $mime)) {
        return false;
    }
    wasabi_storage_remember_mapping($relType, (int) $relId, $fileName, $key, $mime);

    return $key;
}

/**
 * Drop-in replacement for move_uploaded_file when Wasabi is enabled.
 * Returns ['ok'=>bool,'file_name'=>string,'object_key'=>string|null,'local'=>bool]
 */
function wasabi_storage_move_or_store($tmpPath, $localFullPath, $relType, $relId, $fileName, $mime = 'application/octet-stream')
{
    if (wasabi_storage_enabled()) {
        $key = wasabi_storage_store_uploaded_file($tmpPath, $relType, $relId, $fileName, $mime);
        if ($key) {
            $parts = explode('/', $key);
            $storedName = end($parts);

            return [
                'ok'         => true,
                'file_name'  => $storedName,
                'object_key' => $key,
                'local'      => false,
                'external'   => WASABI_STORAGE_EXTERNAL,
            ];
        }

        return [
            'ok'         => false,
            'file_name'  => $fileName,
            'object_key' => null,
            'local'      => false,
            'external'   => null,
            'error'      => get_option('wasabi_last_error'),
        ];
    }

    $dir = dirname($localFullPath);
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }
    $ok = @move_uploaded_file($tmpPath, $localFullPath);

    return [
        'ok'         => (bool) $ok,
        'file_name'  => basename($localFullPath),
        'object_key' => null,
        'local'      => true,
        'external'   => null,
    ];
}

/**
 * Prefer Wasabi for modules that persist via misc_model->add_attachment_to_database.
 * Returns true on Wasabi success, false on Wasabi failure (hard-fail), null to use local disk.
 */
function wasabi_storage_try_attach_to_database($relId, $relType, $tmpPath, $originalName, $mime = 'application/octet-stream')
{
    if (!function_exists('wasabi_storage_enabled') || !wasabi_storage_enabled()) {
        return null;
    }
    $key = wasabi_storage_store_uploaded_file($tmpPath, $relType, $relId, $originalName, $mime);
    if (!$key) {
        if (function_exists('set_alert')) {
            set_alert('danger', _l('wasabi_storage_upload_failed') . ': ' . get_option('wasabi_last_error'));
        }

        return false;
    }
    $parts = explode('/', $key);
    $filename = end($parts);
    $CI = &get_instance();
    $insertId = $CI->misc_model->add_attachment_to_database($relId, $relType, [[
        'name' => $filename,
        'link' => $key,
        'mime' => $mime ?: 'application/octet-stream',
    ]], WASABI_STORAGE_EXTERNAL);

    return $insertId ? true : false;
}

function wasabi_storage_collect_uploads($files, $index, $type, $relId)
{
    $normalized = wasabi_storage_normalize_files_index($files, $index);
    if (!$normalized) {
        return [];
    }

    $uploaded = [];
    $hadIncoming = false;
    $hadFailure = false;
    for ($i = 0; $i < count($normalized['name']); $i++) {
        if (_perfex_upload_error($normalized['error'][$i]) || empty($normalized['tmp_name'][$i])) {
            continue;
        }
        $hadIncoming = true;
        if (function_exists('_upload_extension_allowed') && !_upload_extension_allowed($normalized['name'][$i])) {
            $hadFailure = true;
            update_option('wasabi_last_error', 'File type not allowed: ' . $normalized['name'][$i]);
            continue;
        }

        $filename = wasabi_storage_unique_name($normalized['name'][$i]);
        $mime = $normalized['type'][$i] ?: 'application/octet-stream';
        $key = wasabi_storage_object_key($type, $relId, $filename);
        if (!wasabi_storage_upload_tmp($normalized['tmp_name'][$i], $key, $mime)) {
            $hadFailure = true;
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

    // Any failure while Wasabi is enabled must surface as empty so callers hard-fail
    // (never claim success when some PUTs failed, and never soft-skip into local disk).
    if ($hadIncoming && ($hadFailure || count($uploaded) === 0)) {
        return [];
    }

    return $uploaded;
}

/* -------------------- Upload filters -------------------- */

function wasabi_storage_filter_task_attachments($hookData)
{
    if (!wasabi_storage_enabled()) {
        return $hookData;
    }
    try {
        $taskId = (int) ($hookData['task_id'] ?? 0);
        $index = $hookData['index_name'] ?? 'attachments';
        $files = $hookData['files'] ?? $_FILES;
        $uploaded = wasabi_storage_collect_uploads($files, $index, 'task', $taskId);
        if (count($uploaded) === 0) {
            // Hard-fail: do not let Perfex save locally when Wasabi is enabled and files were posted.
            if (wasabi_storage_has_incoming_files($files, $index)) {
                return wasabi_storage_fail_external_upload($hookData);
            }

            return $hookData;
        }
        $hookData['handled_externally'] = true;
        $hookData['uploaded_files'] = $uploaded;
    } catch (Throwable $e) {
        return wasabi_storage_fail_external_upload($hookData, $e->getMessage());
    }

    return $hookData;
}

function wasabi_storage_filter_ticket_attachments($hookData)
{
    if (!wasabi_storage_enabled()) {
        return $hookData;
    }
    try {
        $ticketId = (int) ($hookData['ticket_id'] ?? 0);
        $index = $hookData['index_name'] ?? 'attachments';
        $files = $hookData['files'] ?? $_FILES;
        $uploaded = wasabi_storage_collect_uploads($files, $index, 'ticket', $ticketId);
        if (count($uploaded) === 0) {
            if (wasabi_storage_has_incoming_files($files, $index)) {
                return wasabi_storage_fail_external_upload($hookData);
            }

            return $hookData;
        }
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
    } catch (Throwable $e) {
        return wasabi_storage_fail_external_upload($hookData, $e->getMessage());
    }

    return $hookData;
}

function wasabi_storage_filter_project_files($hookData)
{
    if (!wasabi_storage_enabled()) {
        return $hookData;
    }
    try {
        $projectId = (int) ($hookData['project_id'] ?? 0);
        $files = $hookData['files'] ?? $_FILES;
        $uploaded = wasabi_storage_collect_uploads($files, 'file', 'project', $projectId);
        if (count($uploaded) === 0) {
            if (wasabi_storage_has_incoming_files($files, 'file')) {
                return wasabi_storage_fail_external_upload($hookData);
            }

            return $hookData;
        }
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
            $insertId = $CI->db->insert_id();
            if ($insertId) {
                $ok = true;
                $CI->load->model('projects_model');
                $CI->projects_model->new_project_file_notification($insertId, $projectId);
            }
        }
        $hookData['handled_externally'] = true;
        $hookData['handled_externally_successfully'] = $ok;
    } catch (Throwable $e) {
        return wasabi_storage_fail_external_upload($hookData, $e->getMessage());
    }

    return $hookData;
}

function wasabi_storage_filter_sales_attachments($hookData)
{
    if (!wasabi_storage_enabled()) {
        return $hookData;
    }
    try {
        $relId = (int) ($hookData['rel_id'] ?? 0);
        $relType = $hookData['rel_type'] ?? 'invoice';
        $files = $hookData['files'] ?? $_FILES;
        $uploaded = wasabi_storage_collect_uploads($files, 'file', $relType, $relId);
        if (count($uploaded) === 0) {
            if (wasabi_storage_has_incoming_files($files, 'file')) {
                $hookData = wasabi_storage_fail_external_upload($hookData);
                $hookData['handled_externally_successfully'] = json_encode(['success' => false, 'rel_id' => $relId, 'error' => get_option('wasabi_last_error')]);

                return $hookData;
            }

            return $hookData;
        }
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
    } catch (Throwable $e) {
        $hookData = wasabi_storage_fail_external_upload($hookData, $e->getMessage());
        $hookData['handled_externally_successfully'] = json_encode(['success' => false, 'error' => $e->getMessage()]);
    }

    return $hookData;
}

function wasabi_storage_filter_client_attachments($hookData)
{
    if (!wasabi_storage_enabled()) {
        return $hookData;
    }
    try {
        $customerId = (int) ($hookData['customer_id'] ?? 0);
        $files = $hookData['files'] ?? $_FILES;
        $uploaded = wasabi_storage_collect_uploads($files, 'file', 'customer', $customerId);
        if (count($uploaded) === 0) {
            if (wasabi_storage_has_incoming_files($files, 'file')) {
                return wasabi_storage_fail_external_upload($hookData);
            }

            return $hookData;
        }
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
    } catch (Throwable $e) {
        return wasabi_storage_fail_external_upload($hookData, $e->getMessage());
    }

    return $hookData;
}

function wasabi_storage_filter_simple_rel($hookData, $idKey, $type, $index = null)
{
    if (!wasabi_storage_enabled()) {
        return $hookData;
    }
    try {
        $relId = (int) ($hookData[$idKey] ?? 0);
        $files = $hookData['files'] ?? $_FILES;
        $index = $index ?: ($hookData['index_name'] ?? 'file');
        $uploaded = wasabi_storage_collect_uploads($files, $index, $type, $relId);
        if (count($uploaded) === 0) {
            if (wasabi_storage_has_incoming_files($files, $index)) {
                return wasabi_storage_fail_external_upload($hookData);
            }

            return $hookData;
        }
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
    } catch (Throwable $e) {
        return wasabi_storage_fail_external_upload($hookData, $e->getMessage());
    }

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
    try {
        $postId = (int) ($hookData['newsfeed_post_id'] ?? 0);
        $files = $hookData['files'] ?? $_FILES;
        $uploaded = wasabi_storage_collect_uploads($files, 'file', 'newsfeed', $postId);
        if (count($uploaded) === 0) {
            if (wasabi_storage_has_incoming_files($files, 'file')) {
                return wasabi_storage_fail_external_upload($hookData);
            }

            return $hookData;
        }
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
    } catch (Throwable $e) {
        return wasabi_storage_fail_external_upload($hookData, $e->getMessage());
    }

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
    try {
        $discussionId = (int) ($hookData['discussion_id'] ?? 0);
        $files = $hookData['files'] ?? $_FILES;
        $uploaded = wasabi_storage_collect_uploads($files, 'file', 'discussion', $discussionId);
        if (count($uploaded) === 0) {
            if (wasabi_storage_has_incoming_files($files, 'file')) {
                return wasabi_storage_fail_external_upload($hookData);
            }

            return $hookData;
        }
        $hookData['handled_externally'] = true;
        $hookData['handled_externally_successfully'] = true;
        if (!empty($uploaded[0]) && isset($hookData['insert_data']) && is_array($hookData['insert_data'])) {
            $hookData['insert_data']['file_name'] = $uploaded[0]['file_name'];
            $hookData['insert_data']['file_mime_type'] = $uploaded[0]['filetype'];
        }
    } catch (Throwable $e) {
        return wasabi_storage_fail_external_upload($hookData, $e->getMessage());
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

    // Module attachments (purchase/warehouse/accounting) stored with external=wasabi
    if (!$objectKey && $attachmentid !== '') {
        $att = null;
        if (is_numeric($attachmentid)) {
            $att = $loadFileById($attachmentid);
        } else {
            $att = $loadFileByKey($attachmentid);
        }
        if ($att && !empty($att->external) && $att->external === WASABI_STORAGE_EXTERNAL) {
            $objectKey = wasabi_storage_find_key_by_filename($att->file_name, $att->rel_type, $att->rel_id);
            if (!$objectKey && !empty($att->external_link) && wasabi_storage_looks_like_object_key($att->external_link)) {
                $objectKey = $att->external_link;
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

/**
 * Resolve the local DB backups directory.
 * Does not depend on the backup module being loaded (BACKUPS_FOLDER may be undefined).
 */
function wasabi_storage_backups_folder()
{
    if (defined('BACKUPS_FOLDER') && BACKUPS_FOLDER !== '') {
        return rtrim(BACKUPS_FOLDER, '/\\') . DIRECTORY_SEPARATOR;
    }

    return rtrim(FCPATH, '/\\') . DIRECTORY_SEPARATOR . 'backups' . DIRECTORY_SEPARATOR;
}

function wasabi_storage_backups_folder_ready()
{
    $folder = wasabi_storage_backups_folder();

    return is_dir($folder) && is_readable($folder);
}

function wasabi_storage_list_backup_files()
{
    if (!wasabi_storage_backups_folder_ready()) {
        return [];
    }

    $files = list_files(wasabi_storage_backups_folder());
    if (!is_array($files)) {
        return [];
    }

    return array_values(array_filter($files, static function ($file) {
        return $file !== 'index.html' && $file !== '.htaccess';
    }));
}

function wasabi_storage_remember_backup_snapshot()
{
    if (!wasabi_storage_backups_folder_ready()) {
        return;
    }
    $list = wasabi_storage_list_backup_files();
    update_option('wasabi_known_backups', json_encode($list));
}

function wasabi_storage_maybe_push_new_backups()
{
    if (get_option('wasabi_backup_enabled') != '1' || !wasabi_storage_credentials_ready()) {
        return;
    }
    if (!wasabi_storage_backups_folder_ready()) {
        return;
    }
    $known = json_decode(get_option('wasabi_known_backups') ?: '[]', true);
    if (!is_array($known)) {
        $known = [];
    }
    $folder = wasabi_storage_backups_folder();
    $current = wasabi_storage_list_backup_files();
    $newFiles = array_diff($current, $known);
    foreach ($newFiles as $file) {
        $path = $folder . $file;
        if (!is_file($path)) {
            continue;
        }
        $key = wasabi_storage_prefix() . 'backups/' . $file;
        if (wasabi_storage_client()->put_object($key, $path, 'application/zip')) {
            wasabi_storage_remember_mapping('backup', 0, $file, $key, 'application/zip');
        }
    }
    update_option('wasabi_known_backups', json_encode($current));
}

function wasabi_storage_push_backup_file($filename)
{
    if (!wasabi_storage_credentials_ready() || !wasabi_storage_backups_folder_ready()) {
        return false;
    }
    $path = wasabi_storage_backups_folder() . $filename;
    if (!is_file($path)) {
        return false;
    }
    $key = wasabi_storage_prefix() . 'backups/' . $filename;
    $ok = wasabi_storage_client()->put_object($key, $path, 'application/zip');
    if ($ok) {
        wasabi_storage_remember_mapping('backup', 0, $filename, $key, 'application/zip');
    } else {
        update_option('wasabi_last_error', wasabi_storage_client()->get_last_error());
    }

    return $ok;
}
