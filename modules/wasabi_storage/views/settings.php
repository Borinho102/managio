<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<div id="wrapper">
    <div class="content">
        <div class="row">
            <div class="col-md-8 col-md-offset-2">
                <h4 class="tw-mt-0 tw-font-semibold tw-text-lg tw-text-neutral-700">
                    <?php echo _l('wasabi_storage'); ?>
                </h4>
                <p class="text-muted"><?php echo _l('wasabi_storage_settings_intro'); ?></p>

                <?php if (!empty($last_error)) { ?>
                    <div class="alert alert-warning">
                        <strong><?php echo _l('wasabi_storage_last_error'); ?>:</strong>
                        <?php echo htmlspecialchars($last_error); ?>
                    </div>
                <?php } ?>

                <?php echo form_open(admin_url('wasabi_storage/save')); ?>
                <div class="panel_s">
                    <div class="panel-body">
                        <div class="checkbox checkbox-primary">
                            <input type="checkbox" name="wasabi_storage_enabled" id="wasabi_storage_enabled" value="1"
                                <?php echo get_option('wasabi_storage_enabled') == '1' ? 'checked' : ''; ?>>
                            <label for="wasabi_storage_enabled"><?php echo _l('wasabi_storage_enabled'); ?></label>
                        </div>
                        <hr />
                        <?php echo render_input('wasabi_access_key', 'wasabi_access_key', get_option('wasabi_access_key')); ?>
                        <div class="form-group">
                            <label for="wasabi_secret_key"><?php echo _l('wasabi_secret_key'); ?></label>
                            <input type="password" class="form-control" name="wasabi_secret_key" id="wasabi_secret_key"
                                   value="<?php echo get_option('wasabi_secret_key') !== '' ? '********' : ''; ?>"
                                   autocomplete="new-password"
                                   placeholder="<?php echo get_option('wasabi_secret_key') !== '' ? _l('wasabi_storage_secret_unchanged') : ''; ?>">
                        </div>
                        <?php echo render_input('wasabi_bucket', 'wasabi_bucket', get_option('wasabi_bucket')); ?>
                        <?php echo render_input('wasabi_region', 'wasabi_region', get_option('wasabi_region') ?: 'eu-central-1'); ?>
                        <?php echo render_input('wasabi_endpoint', 'wasabi_endpoint', get_option('wasabi_endpoint') ?: 'https://s3.eu-central-1.wasabisys.com'); ?>
                        <?php echo render_input('wasabi_path_prefix', 'wasabi_path_prefix', get_option('wasabi_path_prefix') ?: 'managio/'); ?>
                        <?php echo render_input('wasabi_signed_url_ttl', 'wasabi_signed_url_ttl', get_option('wasabi_signed_url_ttl') ?: '3600', 'number'); ?>
                        <div class="checkbox checkbox-primary">
                            <input type="checkbox" name="wasabi_backup_enabled" id="wasabi_backup_enabled" value="1"
                                <?php echo get_option('wasabi_backup_enabled') == '1' ? 'checked' : ''; ?>>
                            <label for="wasabi_backup_enabled"><?php echo _l('wasabi_backup_enabled'); ?></label>
                        </div>
                    </div>
                    <div class="panel-footer text-right">
                        <button type="submit" class="btn btn-primary"><?php echo _l('submit'); ?></button>
                    </div>
                </div>
                <?php echo form_close(); ?>

                <div class="panel_s">
                    <div class="panel-body">
                        <h5><?php echo _l('wasabi_storage_actions'); ?></h5>
                        <a href="<?php echo admin_url('wasabi_storage/test_connection'); ?>" class="btn btn-default mright5">
                            <?php echo _l('wasabi_storage_test_connection'); ?>
                        </a>
                        <a href="<?php echo admin_url('wasabi_storage/push_backups'); ?>" class="btn btn-default mright5">
                            <?php echo _l('wasabi_storage_push_backups'); ?>
                        </a>
                        <hr />
                        <?php echo form_open(admin_url('wasabi_storage/migrate')); ?>
                        <div class="checkbox checkbox-danger">
                            <input type="checkbox" name="purge_local" id="purge_local" value="1">
                            <label for="purge_local"><?php echo _l('wasabi_storage_purge_local'); ?></label>
                        </div>
                        <button type="submit" class="btn btn-info" onclick="return confirm('<?php echo _l('wasabi_storage_migrate_confirm'); ?>');">
                            <?php echo _l('wasabi_storage_migrate'); ?>
                        </button>
                        <?php echo form_close(); ?>

                        <?php
                        $status = json_decode($migrate_status ?: '', true);
                        if (is_array($status)) { ?>
                            <div class="alert alert-info mtop15">
                                <?php echo _l('wasabi_storage_migrate_status'); ?>:
                                <?php echo (int) ($status['uploaded'] ?? 0); ?> uploaded,
                                <?php echo (int) ($status['skipped'] ?? 0); ?> skipped,
                                <?php echo (int) ($status['failed'] ?? 0); ?> failed
                                (<?php echo htmlspecialchars($status['at'] ?? ''); ?>)
                            </div>
                        <?php } ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php init_tail(); ?>
