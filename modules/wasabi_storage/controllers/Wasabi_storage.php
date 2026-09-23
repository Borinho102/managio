<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Wasabi_storage extends AdminController
{
    public function __construct()
    {
        parent::__construct();
        if (!is_admin()) {
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
        $this->load->view('settings', $data);
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
            set_alert('success', _l('wasabi_storage_connection_ok'));
        } else {
            update_option('wasabi_last_error', $client->get_last_error());
            set_alert('danger', _l('wasabi_storage_connection_failed') . ': ' . $client->get_last_error());
        }
        redirect(admin_url('wasabi_storage'));
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
        set_alert('success', _l('wasabi_storage_migrate_done', $result['uploaded'] . '/' . $result['total']));
        redirect(admin_url('wasabi_storage'));
    }

    public function push_backups()
    {
        if (!wasabi_storage_credentials_ready() || !defined('BACKUPS_FOLDER')) {
            set_alert('warning', _l('wasabi_storage_configure_first'));
            redirect(admin_url('wasabi_storage'));
        }
        $count = 0;
        foreach (list_files(BACKUPS_FOLDER) as $file) {
            if ($file === 'index.html' || $file === '.htaccess') {
                continue;
            }
            if (wasabi_storage_push_backup_file($file)) {
                $count++;
            }
        }
        set_alert('success', _l('wasabi_storage_backups_pushed', $count));
        redirect(admin_url('wasabi_storage'));
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
