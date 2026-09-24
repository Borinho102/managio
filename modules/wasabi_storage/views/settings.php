<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<?php
$active_tab = isset($active_tab) ? $active_tab : 'settings';
$log_preview = isset($log_preview) && is_array($log_preview) ? $log_preview : [
    'last_error' => '',
    'activity'   => '',
    'php_log'    => '',
];
?>
<div id="wrapper">
    <div class="content">
        <div class="row">
            <div class="col-md-10 col-md-offset-1">
                <h4 class="tw-mt-0 tw-font-semibold tw-text-lg tw-text-neutral-700">
                    <?php echo _l('wasabi_storage'); ?>
                </h4>
                        <p class="text-muted"><?php echo _l('wasabi_storage_settings_intro'); ?></p>
                        <div class="alert alert-info">
                            <?php echo _l('wasabi_storage_bucket_hint'); ?>
                        </div>

                <div class="horizontal-scrollable-tabs panel-full-width-tabs">
                    <div class="scroller arrow-left"><i class="fa fa-angle-left"></i></div>
                    <div class="scroller arrow-right"><i class="fa fa-angle-right"></i></div>
                    <div class="horizontal-tabs">
                        <ul class="nav nav-tabs nav-tabs-horizontal" role="tablist">
                            <li role="presentation" class="<?php echo $active_tab === 'settings' ? 'active' : ''; ?>">
                                <a href="#wasabi_settings" aria-controls="wasabi_settings" role="tab" data-toggle="tab">
                                    <?php echo _l('wasabi_storage_tab_settings'); ?>
                                </a>
                            </li>
                            <li role="presentation" class="<?php echo $active_tab === 'logs' ? 'active' : ''; ?>">
                                <a href="#wasabi_logs" aria-controls="wasabi_logs" role="tab" data-toggle="tab">
                                    <?php echo _l('wasabi_storage_tab_logs'); ?>
                                    <?php if (!empty($log_preview['last_error'])) { ?>
                                        <span class="badge tw-bg-danger-600 tw-text-white tw-ml-1">!</span>
                                    <?php } ?>
                                </a>
                            </li>
                        </ul>
                    </div>
                </div>

                <div class="tab-content mtop15">
                    <div role="tabpanel" class="tab-pane <?php echo $active_tab === 'settings' ? 'active' : ''; ?>" id="wasabi_settings">
                        <?php if (!empty($last_error)) { ?>
                            <div class="alert alert-warning">
                                <strong><?php echo _l('wasabi_storage_last_error'); ?>:</strong>
                                <?php echo htmlspecialchars($last_error); ?>
                                <a href="#wasabi_logs" data-toggle="tab" class="alert-link mleft10">
                                    <?php echo _l('wasabi_storage_view_logs'); ?>
                                </a>
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
                                <p class="text-muted tw-mb-0"><?php echo _l('wasabi_backup_enabled_help'); ?></p>
                                <?php if (!wasabi_storage_backups_folder_ready()) { ?>
                                    <div class="alert alert-warning mtop15 tw-mb-0">
                                        <?php echo _l('wasabi_storage_backups_folder_missing'); ?>
                                    </div>
                                <?php } ?>
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

                    <div role="tabpanel" class="tab-pane <?php echo $active_tab === 'logs' ? 'active' : ''; ?>" id="wasabi_logs">
                        <div class="panel_s">
                            <div class="panel-body">
                                <div class="tw-flex tw-items-center tw-justify-between tw-mb-4 tw-flex-wrap tw-gap-2">
                                    <div>
                                        <h5 class="tw-mt-0 tw-mb-1"><?php echo _l('wasabi_storage_logs_heading'); ?></h5>
                                        <p class="text-muted tw-mb-0"><?php echo _l('wasabi_storage_logs_intro'); ?></p>
                                    </div>
                                    <div>
                                        <a href="<?php echo admin_url('wasabi_storage?tab=logs'); ?>" class="btn btn-default mright5">
                                            <i class="fa fa-refresh"></i> <?php echo _l('wasabi_storage_logs_refresh'); ?>
                                        </a>
                                        <a href="<?php echo admin_url('wasabi_storage/clear_logs'); ?>" class="btn btn-danger"
                                           onclick="return confirm('<?php echo _l('wasabi_storage_logs_clear_confirm'); ?>');">
                                            <i class="fa fa-trash"></i> <?php echo _l('wasabi_storage_logs_clear'); ?>
                                        </a>
                                    </div>
                                </div>

                                <div class="form-group">
                                    <label><?php echo _l('wasabi_storage_last_error'); ?></label>
                                    <?php if (!empty($log_preview['last_error'])) { ?>
                                        <pre class="wasabi-log-pre wasabi-log-error"><?php echo htmlspecialchars($log_preview['last_error']); ?></pre>
                                    <?php } else { ?>
                                        <div class="alert alert-success tw-mb-0"><?php echo _l('wasabi_storage_logs_no_last_error'); ?></div>
                                    <?php } ?>
                                </div>

                                <div class="form-group">
                                    <label><?php echo _l('wasabi_storage_logs_activity'); ?></label>
                                    <?php if (!empty($log_preview['activity_file'])) { ?>
                                        <p class="text-muted tw-text-sm"><?php echo htmlspecialchars(basename($log_preview['activity_file'])); ?></p>
                                    <?php } ?>
                                    <pre class="wasabi-log-pre"><?php echo htmlspecialchars($log_preview['activity']); ?></pre>
                                </div>

                                <div class="form-group tw-mb-0">
                                    <label><?php echo _l('wasabi_storage_logs_php'); ?></label>
                                    <?php if (!empty($log_preview['php_log_file'])) { ?>
                                        <p class="text-muted tw-text-sm"><?php echo htmlspecialchars(basename($log_preview['php_log_file'])); ?></p>
                                    <?php } ?>
                                    <pre class="wasabi-log-pre"><?php echo htmlspecialchars($log_preview['php_log']); ?></pre>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<style>
.wasabi-log-pre {
    max-height: 360px;
    overflow: auto;
    background: #0f172a;
    color: #e2e8f0;
    border-radius: 6px;
    padding: 12px 14px;
    font-size: 12px;
    line-height: 1.45;
    white-space: pre-wrap;
    word-break: break-word;
    margin-bottom: 0;
}
.wasabi-log-error {
    background: #450a0a;
    color: #fecaca;
}
</style>
<?php init_tail(); ?>
<script>
(function() {
    var tab = <?php echo json_encode($active_tab); ?>;
    if (tab === 'logs') {
        $('a[href="#wasabi_logs"]').tab('show');
    }
    $('a[data-toggle="tab"]').on('shown.bs.tab', function(e) {
        var href = $(e.target).attr('href');
        if (href === '#wasabi_logs' || href === '#wasabi_settings') {
            var url = new URL(window.location.href);
            url.searchParams.set('tab', href === '#wasabi_logs' ? 'logs' : 'settings');
            window.history.replaceState({}, '', url.toString());
        }
    });
})();
</script>
