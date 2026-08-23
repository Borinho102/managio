<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<li class="icon dropdown tw-relative ltr:tw-mr-1.5 rtl:tw-ml-1.5" data-toggle="tooltip"
    title="<?php echo html_escape(_l('payin_wallet')); ?>" data-placement="bottom">
    <a href="#" class="dropdown-toggle !tw-px-0 tw-group" data-toggle="dropdown" aria-expanded="false">
        <span class="tw-inline-flex tw-items-center tw-justify-center tw-h-8 tw-px-2 -tw-mt-1.5">
            <i class="fa fa-wallet tw-text-neutral-500 group-hover:tw-text-neutral-800"></i>
            <span class="tw-ml-1 tw-text-sm tw-hidden md:tw-inline tw-text-neutral-700">
                <?php
                if (!empty($wallet['provisioned']) && empty($wallet['error'])) {
                    echo html_escape($wallet['formatted'] ?? _l('payin_wallet'));
                } else {
                    echo html_escape(_l('payin_wallet'));
                }
                ?>
            </span>
        </span>
    </a>
    <ul class="dropdown-menu animated fadeIn dropdown-menu-right tw-text-base" style="min-width:220px">
        <li class="dropdown-header"><?php echo html_escape(_l('payin_wallet')); ?></li>
        <?php if (!empty($wallet['provisioned'])) { ?>
            <?php if (!empty($wallet['formatted'])) { ?>
                <li class="disabled"><a href="#"><?php echo html_escape($wallet['formatted']); ?></a></li>
            <?php } ?>
            <li>
                <a href="<?php echo site_url('payin/open'); ?>" target="_blank">
                    <i class="fa fa-external-link tw-mr-1"></i> <?php echo html_escape(_l('payin_open_wallet')); ?>
                </a>
            </li>
            <li>
                <a href="<?php echo site_url('payin/refresh'); ?>">
                    <i class="fa fa-refresh tw-mr-1"></i> <?php echo html_escape(_l('payin_refresh_wallet')); ?>
                </a>
            </li>
        <?php } else { ?>
            <li>
                <a href="<?php echo site_url('payin/connect'); ?>">
                    <i class="fa fa-link tw-mr-1"></i> <?php echo html_escape(_l('payin_connect_wallet')); ?>
                </a>
            </li>
        <?php } ?>
    </ul>
</li>
