<?php

defined('BASEPATH') or exit('No direct script access allowed');

$lang['wasabi_storage'] = 'Wasabi Storage';
$lang['wasabi_storage_settings_intro'] = 'Store CRM attachments (tasks, projects, tickets, sales documents, products) and optional database backups on Wasabi Hot Cloud Storage. Create a private bucket and API keys in the Wasabi console, then enable the module.';
$lang['wasabi_storage_enabled'] = 'Enable Wasabi for new uploads';
$lang['wasabi_access_key'] = 'Access key';
$lang['wasabi_secret_key'] = 'Secret key';
$lang['wasabi_bucket'] = 'Bucket name';
$lang['wasabi_region'] = 'Region';
$lang['wasabi_endpoint'] = 'S3 endpoint URL';
$lang['wasabi_path_prefix'] = 'Object key prefix';
$lang['wasabi_signed_url_ttl'] = 'Signed URL lifetime (seconds)';
$lang['wasabi_backup_enabled'] = 'Also upload database backups to Wasabi';
$lang['wasabi_storage_actions'] = 'Actions';
$lang['wasabi_storage_test_connection'] = 'Test connection';
$lang['wasabi_storage_push_backups'] = 'Push existing backups';
$lang['wasabi_storage_migrate'] = 'Migrate existing local uploads';
$lang['wasabi_storage_purge_local'] = 'Delete local files after successful upload (optional)';
$lang['wasabi_storage_migrate_confirm'] = 'Migrate all local uploads under uploads/ to Wasabi?';
$lang['wasabi_storage_migrate_status'] = 'Last migration';
$lang['wasabi_storage_migrate_done'] = 'Migration finished: %s';
$lang['wasabi_storage_connection_ok'] = 'Connected to Wasabi successfully';
$lang['wasabi_storage_connection_failed'] = 'Wasabi connection failed';
$lang['wasabi_storage_configure_first'] = 'Save Wasabi credentials first';
$lang['wasabi_storage_last_error'] = 'Last error';
$lang['wasabi_storage_secret_unchanged'] = 'Leave as-is to keep the current secret';
$lang['wasabi_storage_backups_pushed'] = '%s backup file(s) uploaded to Wasabi';
