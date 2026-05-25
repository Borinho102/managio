<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<a href="#" class="tw-flex tw-items-center tw-justify-center tw-absolute tw-right-10 tw-h-8 tw-w-8 tw-bg-neutral-200 tw-rounded-full hover:tw-bg-neutral-300 close_mobileapp tw-transition-all tw-z-10 tw-top-9" data-close="true">
    <i class="fa fa-remove fa-lg tw-text-neutral-800"></i>
</a>
<div class="row">
    <div class="col-md-6 col-md-offset-3">
        <div class="panel_s">
            <div class="panel-body">
                <div class="qr_code_container" style="text-align: center;">
                    <h3 style="font-weight: 800;margin-bottom: 0;">Download Perfex CRM App</h3>
                    <?php echo '<img src="data:image/png;base64,' . $QR . '" alt="QR Code" />'; ?>
                    <p style="font-weight: 600;margin-top: -20px;">Scan to download</p>
                </div>

                <div class="app-icons">
                    <a href="https://play.google.com/store/apps/details?id=com.myperfexcrm.app">
                        <img alt='Get it on Google Play' src="<?= site_url('modules/' . PERFEX_MOBILE_COMPANION . '/assets/playstore.png'); ?>" />
                    </a>
                    <a href="https://apps.apple.com/us/app/syndeopro-crm/id1625111197">
                        <img src="<?= site_url('modules/' . PERFEX_MOBILE_COMPANION . '/assets/appstore.svg'); ?>" style="padding: 10px;" />
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>