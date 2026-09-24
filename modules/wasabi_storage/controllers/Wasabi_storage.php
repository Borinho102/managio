<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Wasabi_storage extends AdminController
{
    public function __construct()
    {
        parent::__construct();

        $method = $this->router->fetch_method();
        $staffPreview = in_array($method, ['preview', 'file'], true);

        if ($staffPreview) {
            if (!is_staff_logged_in()) {
                access_denied('Wasabi Storage');
            }
        } elseif (!is_admin()) {
            access_denied('Wasabi Storage');
        }

        if (!function_exists('wasabi_storage_ensure_table')) {
            require_once module_dir_path(WASABI_STORAGE_MODULE_NAME, 'install.php');
        }
        wasabi_storage_ensure_table();
    }

    public function index()
    {
        $data['title'] = _l('wasabi_storage');
        $data['migrate_status'] = get_option('wasabi_migrate_status');
        $data['last_error'] = get_option('wasabi_last_error');
        $data['active_tab'] = $this->input->get('tab') === 'logs' ? 'logs' : 'settings';
        $data['log_preview'] = wasabi_storage_collect_log_preview(250);
        $this->load->view('settings', $data);
    }

    public function clear_logs()
    {
        if (wasabi_storage_clear_activity_logs()) {
            set_alert('success', _l('wasabi_storage_logs_cleared'));
        } else {
            set_alert('warning', _l('wasabi_storage_logs_clear_failed'));
        }
        redirect(admin_url('wasabi_storage?tab=logs'));
    }

    public function save()
    {
        if ($this->input->post()) {
            $fields = [
                'wasabi_storage_enabled',
                'wasabi_access_key',
                'wasabi_bucket',
                'wasabi_region',
                'wasabi_endpoint',
                'wasabi_path_prefix',
                'wasabi_signed_url_ttl',
                'wasabi_backup_enabled',
            ];
            foreach ($fields as $field) {
                $value = $this->input->post($field);
                if ($field === 'wasabi_storage_enabled' || $field === 'wasabi_backup_enabled') {
                    $value = $value ? '1' : '0';
                }
                if ($field === 'wasabi_endpoint' && is_string($value)) {
                    $value = rtrim(trim($value), '/');
                }
                if ($field === 'wasabi_path_prefix' && is_string($value) && $value !== '') {
                    $value = rtrim(trim($value), '/') . '/';
                }
                update_option($field, $value === null ? '' : $value);
            }

            $secret = $this->input->post('wasabi_secret_key');
            if (is_string($secret) && $secret !== '' && $secret !== '********') {
                update_option('wasabi_secret_key', $secret);
            }

            wasabi_storage_activity_log('info', 'Settings saved (enabled=' . get_option('wasabi_storage_enabled') . ', backup=' . get_option('wasabi_backup_enabled') . ')');
            set_alert('success', _l('updated_successfully', _l('wasabi_storage')));
        }

        redirect(admin_url('wasabi_storage'));
    }

    public function test_connection()
    {
        $client = wasabi_storage_client();
        $ok = $client->test_connection();
        if ($ok) {
            update_option('wasabi_last_error', '');
            wasabi_storage_activity_log('info', 'Connection test OK (HEAD + PUT probe)');
            set_alert('success', _l('wasabi_storage_connection_ok'));
        } else {
            // Prefer the detailed message already written by Wasabi_client::test_connection().
            $err = get_option('wasabi_last_error') ?: $client->get_last_error();
            update_option('wasabi_last_error', $err);
            wasabi_storage_activity_log('error', 'Connection test failed: ' . $err);
            set_alert('danger', _l('wasabi_storage_connection_failed') . ': ' . $err);
        }
        redirect(admin_url('wasabi_storage?tab=logs'));
    }

    public function migrate()
    {
        if (!wasabi_storage_credentials_ready()) {
            set_alert('warning', _l('wasabi_storage_configure_first'));
            redirect(admin_url('wasabi_storage'));
        }

        $purge = (bool) $this->input->post('purge_local');
        $result = $this->run_migrate($purge);
        update_option('wasabi_migrate_status', json_encode($result));
        wasabi_storage_activity_log(
            ((int) ($result['failed'] ?? 0) > 0) ? 'warning' : 'info',
            'Migration finished: uploaded=' . ($result['uploaded'] ?? 0)
            . ' skipped=' . ($result['skipped'] ?? 0)
            . ' failed=' . ($result['failed'] ?? 0)
            . ' total=' . ($result['total'] ?? 0)
        );
        set_alert('success', _l('wasabi_storage_migrate_done', $result['uploaded'] . '/' . $result['total']));
        redirect(admin_url('wasabi_storage?tab=logs'));
    }

    public function push_backups()
    {
        if (!wasabi_storage_credentials_ready()) {
            set_alert('warning', _l('wasabi_storage_configure_first'));
            redirect(admin_url('wasabi_storage'));
        }
        if (!wasabi_storage_backups_folder_ready()) {
            set_alert('warning', _l('wasabi_storage_backups_folder_missing'));
            redirect(admin_url('wasabi_storage'));
        }

        $files = wasabi_storage_list_backup_files();
        if (count($files) === 0) {
            set_alert('warning', _l('wasabi_storage_backups_none'));
            redirect(admin_url('wasabi_storage'));
        }

        $count = 0;
        $failed = 0;
        foreach ($files as $file) {
            if (wasabi_storage_push_backup_file($file)) {
                $count++;
            } else {
                $failed++;
            }
        }

        if ($count > 0 && $failed === 0) {
            wasabi_storage_activity_log('info', 'Backups pushed: ' . $count);
            set_alert('success', _l('wasabi_storage_backups_pushed', $count));
        } elseif ($count > 0) {
            wasabi_storage_activity_log('warning', 'Backups partial: ' . $count . ' ok, ' . $failed . ' failed');
            set_alert('warning', _l('wasabi_storage_backups_pushed_partial', [$count, $failed]));
        } else {
            $err = get_option('wasabi_last_error');
            wasabi_storage_activity_log('error', 'Backups push failed: ' . $err);
            set_alert('danger', _l('wasabi_storage_backups_push_failed') . ($err ? ': ' . $err : ''));
        }
        redirect(admin_url('wasabi_storage?tab=logs'));
    }

    /**
     * Authenticated proxy for product images / attachments when signed URL embedding is awkward.
     */
    public function file()
    {
        $key = $this->input->get('key');
        if (!$key || !wasabi_storage_credentials_ready()) {
            show_404();
        }
        $url = wasabi_storage_signed_url($key);
        if (!$url) {
            show_404();
        }
        redirect($url);
    }

    /**
     * Same-origin image/file preview for staff (used as <img src> after AJAX uploads).
     * Avoids broken previews from long Wasabi signed URLs inside injected task HTML.
     */
    public function preview($fileId = 0)
    {
        $fileId = (int) $fileId;
        if ($fileId <= 0 || !wasabi_storage_credentials_ready()) {
            show_404();
        }

        $this->db->where('id', $fileId);
        $file = $this->db->get(db_prefix() . 'files')->row();
        if (!$file || empty($file->external) || $file->external !== WASABI_STORAGE_EXTERNAL) {
            show_404();
        }

        $objectKey = wasabi_storage_resolve_object_key_for_file($file);
        if (!$objectKey) {
            show_404();
        }

        $client = wasabi_storage_client();
        $body = $client->get_object($objectKey);
        if ($body === false || $body === null) {
            // Fallback: redirect to a fresh signed URL
            $url = wasabi_storage_signed_url($objectKey);
            if ($url) {
                redirect($url);
            }
            show_404();
        }

        $mime = !empty($file->filetype) ? $file->filetype : 'application/octet-stream';
        if (strpos($mime, 'image/') !== 0 && function_exists('wasabi_storage_guess_mime_from_name')) {
            $guessed = wasabi_storage_guess_mime_from_name($file->file_name);
            if ($guessed) {
                $mime = $guessed;
            }
        }

        $filename = basename((string) $file->file_name);
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        header('Content-Type: ' . $mime);
        header('Content-Length: ' . strlen($body));
        header('Content-Disposition: inline; filename="' . str_replace('"', '', $filename) . '"');
        header('Cache-Control: private, max-age=120');
        header('X-Content-Type-Options: nosniff');
        echo $body;
        exit;
    }

    private function run_migrate($purgeLocal = false)
    {
        $types = [
            'clients'            => 'customer',
            'tasks'              => 'task',
            'projects'           => 'project',
            'ticket_attachments' => 'ticket',
            'invoices'           => 'invoice',
            'estimates'          => 'estimate',
            'proposals'          => 'proposal',
            'credit_notes'       => 'credit_note',
            'expenses'           => 'expense',
            'contracts'          => 'contract',
            'leads'              => 'lead',
            'newsfeed'           => 'newsfeed',
            'products'           => 'products',
            'discussions'        => 'discussion',
        ];

        $uploaded = 0;
        $skipped = 0;
        $failed = 0;
        $total = 0;
        $client = wasabi_storage_client();

        foreach ($types as $folder => $relType) {
            $base = FCPATH . 'uploads/' . $folder;
            if (!is_dir($base)) {
                continue;
            }
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($base, FilesystemIterator::SKIP_DOTS)
            );
            foreach ($iterator as $fileInfo) {
                if (!$fileInfo->isFile()) {
                    continue;
                }
                $name = $fileInfo->getFilename();
                if ($name === 'index.html' || $name === '.htaccess' || strpos($name, '_thumb.') !== false) {
                    continue;
                }
                $total++;
                $full = $fileInfo->getPathname();
                $relPath = trim(str_replace($base, '', $fileInfo->getPath()), '/\\');
                $relId = 0;
                if ($relPath !== '' && is_numeric(explode('/', str_replace('\\', '/', $relPath))[0])) {
                    $relId = (int) explode('/', str_replace('\\', '/', $relPath))[0];
                }
                if (wasabi_storage_find_mapping($relType, $relId, $name)) {
                    $skipped++;
                    continue;
                }
                $key = wasabi_storage_object_key($relType, $relId, ($relPath ? str_replace('\\', '/', $relPath) . '/' : '') . $name);
                // Prefer flat key for standard pattern type/id/file
                if ($relId > 0) {
                    $key = wasabi_storage_object_key($relType, $relId, $name);
                }
                $mime = function_exists('mime_content_type') ? @mime_content_type($full) : 'application/octet-stream';
                if ($client->put_object($key, $full, $mime ?: 'application/octet-stream')) {
                    wasabi_storage_remember_mapping($relType, $relId, $name, $key, $mime);
                    $this->mark_db_external($relType, $relId, $name, $key, $mime);
                    $uploaded++;
                    if ($purgeLocal) {
                        @unlink($full);
                    }
                } else {
                    $failed++;
                    update_option('wasabi_last_error', $client->get_last_error());
                }
            }
        }

        return [
            'total'    => $total,
            'uploaded' => $uploaded,
            'skipped'  => $skipped,
            'failed'   => $failed,
            'at'       => date('Y-m-d H:i:s'),
        ];
    }

    private function mark_db_external($relType, $relId, $fileName, $objectKey, $mime)
    {
        if ($relType === 'project') {
            $this->db->where('project_id', $relId);
            $this->db->where('file_name', $fileName);
            $this->db->update(db_prefix() . 'project_files', [
                'external'      => WASABI_STORAGE_EXTERNAL,
                'external_link' => $objectKey,
            ]);

            return;
        }
        if ($relType === 'ticket' || $relType === 'products' || $relType === 'discussion') {
            return;
        }
        $this->db->where('rel_type', $relType);
        $this->db->where('rel_id', $relId);
        $this->db->where('file_name', $fileName);
        $row = $this->db->get(db_prefix() . 'files')->row();
        $update = [
            'external'      => WASABI_STORAGE_EXTERNAL,
            'external_link' => $objectKey,
            'filetype'      => $mime ?: null,
        ];
        if ($row) {
            $this->db->where('id', $row->id);
            $this->db->update(db_prefix() . 'files', $update);
            if (function_exists('wasabi_storage_files_table_download_url')) {
                $downloadUrl = wasabi_storage_files_table_download_url($relType, $row->attachment_key, $row->id, $relId);
                if ($downloadUrl !== '') {
                    $this->db->where('id', $row->id);
                    $this->db->update(db_prefix() . 'files', ['external_link' => $downloadUrl]);
                }
            }
        }
    }
}
