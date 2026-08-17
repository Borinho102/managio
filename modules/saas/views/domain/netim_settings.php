<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php echo form_open(saas_url('netim_domains/settings'), ['id' => 'netim-settings-form']); ?>
<div class="panel_s">
    <div class="panel-heading">
        <h4><i class="fa fa-globe"></i> Netim Domain Registrar — Settings</h4>
    </div>
    <div class="panel-body">

        <div class="row">
            <div class="col-md-12">
                <div class="alert alert-info">
                    <strong>Setup:</strong> Create a Netim reseller account, fund your prepaid balance, then enter your API credentials below.
                    Use the <strong>Sandbox (OTE)</strong> environment for testing before going live.
                    <br>API docs: <a href="https://api.netim.com" target="_blank">api.netim.com</a>
                </div>
            </div>
        </div>

        <div class="row">
            <!-- API Credentials -->
            <div class="col-md-6">
                <h5 class="bold border-bottom pb-2 mb-3"><i class="fa fa-key"></i> API Credentials</h5>

                <div class="form-group">
                    <label>Netim API Login</label>
                    <input type="text" name="netim_api_login" class="form-control"
                           value="<?php echo get_option('netim_api_login'); ?>"
                           placeholder="Your Netim reseller login">
                </div>

                <div class="form-group">
                    <label>Netim API Secret</label>
                    <input type="password" name="netim_api_password" class="form-control"
                           value="<?php echo get_option('netim_api_password'); ?>"
                           placeholder="Your Netim API secret"
                           autocomplete="new-password">
                </div>

                <div class="form-group">
                    <label>Environment</label>
                    <select name="netim_sandbox_mode" class="form-control">
                        <option value="0" <?php echo get_option('netim_sandbox_mode') == '0' ? 'selected' : ''; ?>>
                            Production (Live)
                        </option>
                        <option value="1" <?php echo get_option('netim_sandbox_mode') == '1' ? 'selected' : ''; ?>>
                            Sandbox / OTE (Testing)
                        </option>
                    </select>
                </div>

                <button type="button" id="test-netim-btn" class="btn btn-default">
                    <i class="fa fa-plug"></i> Test Connection
                </button>
                <span id="test-result" class="ml-2"></span>
            </div>

            <!-- Nameservers -->
            <div class="col-md-6">
                <h5 class="bold border-bottom pb-2 mb-3"><i class="fa fa-server"></i> White-Label Nameservers</h5>
                <p class="text-muted" style="font-size:13px">
                    Set up white-label nameservers in your Netim panel (e.g. <code>ns1.yourdomain.com</code>)
                    so Netim branding is hidden from clients.
                    If left blank, Netim's default nameservers will be used.
                </p>

                <div class="form-group">
                    <label>Nameserver 1</label>
                    <input type="text" name="netim_nameserver_1" class="form-control"
                           value="<?php echo get_option('netim_nameserver_1'); ?>"
                           placeholder="ns1.yourdomain.com">
                </div>

                <div class="form-group">
                    <label>Nameserver 2</label>
                    <input type="text" name="netim_nameserver_2" class="form-control"
                           value="<?php echo get_option('netim_nameserver_2'); ?>"
                           placeholder="ns2.yourdomain.com">
                </div>
            </div>
        </div>

        <hr>

        <div class="row">
            <!-- DNS & Server -->
            <div class="col-md-6">
                <h5 class="bold border-bottom pb-2 mb-3"><i class="fa fa-sitemap"></i> DNS Auto-Configuration</h5>
                <p class="text-muted" style="font-size:13px">
                    After domain registration, automatically create A records pointing to your server.
                    This requires that nameservers above point to Netim's DNS (or your DNS cluster with Netim API access).
                </p>

                <div class="form-group">
                    <label>Server IP Address (for A record)</label>
                    <input type="text" name="netim_server_ip" class="form-control"
                           value="<?php echo get_option('netim_server_ip') ?: get_option('custom_domain_ip_address'); ?>"
                           placeholder="e.g. 192.0.2.1">
                    <p class="help-block">DNS A record: <code>@</code> and <code>www</code> → this IP</p>
                </div>

                <div class="form-group">
                    <label>Auto-configure DNS after registration</label>
                    <select name="netim_auto_dns" class="form-control">
                        <option value="1" <?php echo get_option('netim_auto_dns') == '1' ? 'selected' : ''; ?>>Yes — automatically create A records</option>
                        <option value="0" <?php echo get_option('netim_auto_dns') == '0' ? 'selected' : ''; ?>>No — manual DNS setup</option>
                    </select>
                </div>
            </div>

            <!-- Auto-register -->
            <div class="col-md-6">
                <h5 class="bold border-bottom pb-2 mb-3"><i class="fa fa-bolt"></i> Registration Flow</h5>
                <p class="text-muted" style="font-size:13px">
                    Choose whether domain registration happens instantly when a client submits a request
                    (requires sufficient Netim prepaid balance), or requires manual admin approval first.
                </p>

                <div class="form-group">
                    <label>Auto-register when client submits request</label>
                    <select name="netim_auto_register" class="form-control">
                        <option value="0" <?php echo get_option('netim_auto_register') == '0' ? 'selected' : ''; ?>>
                            No — admin reviews and registers manually
                        </option>
                        <option value="1" <?php echo get_option('netim_auto_register') == '1' ? 'selected' : ''; ?>>
                            Yes — register instantly on client submission
                        </option>
                    </select>
                </div>

                <div class="alert alert-warning" style="font-size:12px">
                    <strong>Note:</strong> Auto-register charges your Netim prepaid balance immediately.
                    Ensure sufficient funds before enabling.
                </div>
            </div>
        </div>

        <div class="btn-bottom-toolbar text-right">
            <button type="submit" class="btn btn-primary">
                <i class="fa fa-save"></i> Save Netim Settings
            </button>
        </div>

    </div><!-- /panel-body -->
</div>
<?php echo form_close(); ?>

<script>
$(document).ready(function () {
    $('#test-netim-btn').on('click', function () {
        var $btn    = $(this);
        var $result = $('#test-result');
        $btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Testing...');
        $result.html('');

        <?php $csrf_name = get_instance()->security->get_csrf_token_name(); ?>
        $.post('<?php echo saas_url('netim_domains/test_connection'); ?>', {
            '<?php echo $csrf_name; ?>': $('input[name="<?php echo $csrf_name; ?>"]').val()
        }, function (data) {
            if (data.success) {
                $result.html('<span class="text-success"><i class="fa fa-check"></i> ' + data.message + '</span>');
            } else {
                $result.html('<span class="text-danger"><i class="fa fa-times"></i> ' + data.message + '</span>');
            }
        }, 'json').always(function () {
            $btn.prop('disabled', false).html('<i class="fa fa-plug"></i> Test Connection');
        });
    });
});
</script>
