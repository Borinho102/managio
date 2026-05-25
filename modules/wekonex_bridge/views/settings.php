<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<div id="wrapper">
    <div class="content">
        <div class="row">
            <div class="col-md-10 col-md-offset-1">
                <div class="panel_s">
                    <div class="panel-body">
                        <h4 class="no-margin"><?php echo _l('wekonex_bridge_settings'); ?></h4>
                        <hr class="hr-panel-heading" />
                        <?php echo form_open(admin_url('wekonex_bridge/settings')); ?>
                        <input type="hidden" name="save_settings" value="1" />
                        <div class="checkbox checkbox-primary">
                            <input type="checkbox" name="wekonex_bridge_enabled" id="wekonex_bridge_enabled" value="1" <?php echo get_option('wekonex_bridge_enabled') == '1' ? 'checked' : ''; ?>>
                            <label for="wekonex_bridge_enabled"><?php echo _l('wekonex_bridge_enabled'); ?></label>
                        </div>
                        <?php echo render_input('wekonex_bridge_wekonex_url', 'wekonex_bridge_wekonex_url', get_option('wekonex_bridge_wekonex_url')); ?>
                        <?php echo render_input('wekonex_bridge_sso_secret', 'wekonex_bridge_sso_secret', get_option('wekonex_bridge_sso_secret'), 'password'); ?>
                        <p class="text-muted"><?php echo _l('wekonex_bridge_sso_secret_hint'); ?></p>
                        <?php echo render_input('wekonex_bridge_webhook_secret', 'wekonex_bridge_webhook_secret', get_option('wekonex_bridge_webhook_secret'), 'password'); ?>
                        <?php echo render_input('wekonex_bridge_log_days', 'wekonex_bridge_log_days', get_option('wekonex_bridge_log_days'), 'number'); ?>
                        <hr />
                        <p><strong><?php echo _l('wekonex_bridge_sso_consume_url'); ?>:</strong><br><code><?php echo html_escape($sso_consume_url); ?>?token=...</code></p>
                        <p><strong><?php echo _l('wekonex_bridge_webhook_ping'); ?>:</strong><br><code><?php echo html_escape($webhook_ping_url); ?></code></p>
                        <button type="submit" class="btn btn-primary"><?php echo _l('submit'); ?></button>
                        <?php echo form_close(); ?>
                    </div>
                </div>

                <div class="panel_s" id="api-setup">
                    <div class="panel-body">
                        <h4 class="no-margin"><?php echo _l('wekonex_bridge_api_setup_title'); ?></h4>
                        <p class="text-muted"><?php echo _l('wekonex_bridge_api_setup_desc'); ?></p>
                        <hr class="hr-panel-heading" />

                        <?php if (!empty($integration_staff)) { ?>
                        <div class="alert alert-info">
                            <?php echo _l('wekonex_bridge_staff_current'); ?>:
                            <strong><?php echo html_escape($integration_staff->firstname . ' ' . $integration_staff->lastname); ?></strong>
                            (<?php echo html_escape($integration_staff->email); ?> · ID <?php echo (int) $integration_staff->staffid; ?>)
                        </div>
                        <?php } ?>

                        <?php if (!empty($api_credentials['last_test'])) { ?>
                        <p>
                            <strong><?php echo _l('wekonex_bridge_api_last_test'); ?>:</strong>
                            <?php echo html_escape($api_credentials['last_test']); ?>
                            —
                            <span class="label label-<?php echo $api_credentials['last_test_status'] === 'ok' ? 'success' : 'danger'; ?>">
                                <?php echo html_escape($api_credentials['last_test_status']); ?>
                            </span>
                        </p>
                        <?php } ?>

                        <p><strong><?php echo _l('wekonex_bridge_login_api_url'); ?>:</strong><br><code><?php echo html_escape($login_api_url); ?></code></p>

                        <?php echo form_open(admin_url('wekonex_bridge/settings#api-setup')); ?>
                        <input type="hidden" name="create_integration_staff" value="1" />
                        <h5><?php echo _l('wekonex_bridge_step_03'); ?></h5>
                        <?php echo render_input('api_staff_email', 'wekonex_bridge_api_staff_email', $default_api_email, 'email'); ?>
                        <?php echo render_input('api_staff_password', 'wekonex_bridge_api_staff_password', '', 'password'); ?>
                        <p class="text-muted"><?php echo _l('wekonex_bridge_api_password_hint'); ?></p>
                        <button type="submit" class="btn btn-default"><?php echo _l('wekonex_bridge_create_staff'); ?></button>
                        <?php echo form_close(); ?>

                        <hr />

                        <?php echo form_open(admin_url('wekonex_bridge/settings#api-setup')); ?>
                        <input type="hidden" name="test_perfexgo_api" value="1" />
                        <h5><?php echo _l('wekonex_bridge_step_04'); ?></h5>
                        <?php echo render_input('api_staff_email', 'wekonex_bridge_api_staff_email', $default_api_email, 'email'); ?>
                        <?php echo render_input('api_staff_password', 'wekonex_bridge_api_staff_password', '', 'password'); ?>
                        <button type="submit" class="btn btn-info"><?php echo _l('wekonex_bridge_test_api'); ?></button>
                        <?php echo form_close(); ?>

                        <?php if (!empty($api_credentials['api_key'])) { ?>
                        <hr />
                        <h5><?php echo _l('wekonex_bridge_copy_to_wekonex'); ?></h5>
                        <pre class="bg-light" style="padding:12px;">MANAGIO_API_EMAIL=<?php echo html_escape($api_credentials['email']); ?>

MANAGIO_API_KEY=<?php echo html_escape($api_credentials['api_key']); ?>

MANAGIO_API_TOKEN=&lt;see admin — token hidden&gt;</pre>
                        <p class="text-muted"><?php echo _l('wekonex_bridge_token_hidden_hint'); ?></p>
                        <?php } ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php init_tail(); ?>
