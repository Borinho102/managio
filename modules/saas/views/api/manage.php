<?php
$authtoken = 'ab2f73d6338d85cba60093b872c67c9f';
?>
<div class="row">
    <div class="col-sm-3">
        <h4 class="tw-font-semibold tw-mt-0 tw-text-neutral-800">
            Api Management
        </h4>
        <ul class="nav navbar-pills navbar-pills-flat nav-tabs nav-stacked">
            <li class="active">
                <a href="#get_packages" data-toggle="tab">
                    <i class="fa fa-cubes menu-icon"></i>
                    <?= _l('get') . ' ' . _l('packages') ?>
                </a>
            </li>
            <li class="">
                <a href="#get_coupons" data-toggle="tab">
                    <i class="fa fa-gift menu-icon"></i>
                    <?= _l('get') . ' ' . _l('coupons') ?>
                </a>
            </li>
            <li class="">
                <a href="#get_modules" data-toggle="tab">
                    <i class="fa fa-solid fa-receipt menu-icon"></i>
                    <?= _l('get') . ' ' . _l('modules') ?>
                </a>
            </li>
            <li class="">
                <a href="#check_slug" data-toggle="tab">
                    <i class="fa fa-cog  menu-icon"></i>
                    <?= _l('check') . ' ' . _l('slug') . '/' . _l('domain') ?>
                </a>
            </li>
            <li class="">
                <a href="#company_registration" data-toggle="tab">
                    <i class="fa fa-cog  menu-icon"></i>
                    <?= _l('company_tenant') . ' ' . _l('registration') ?>
                </a>
            </li>
            <li class="">
                <a href="#generate_token" data-toggle="tab">
                    <i class="fa fa-code  menu-icon"></i>
                    <?= _l('generate') . ' ' . _l('token') ?>
                </a>
            </li>
        </ul>
    </div>
    <div class="col-sm-9">
        <div id="get_packages" class="tab-pane active">
            <?php $this->load->view('api/get_package', ['authtoken' => $authtoken]); ?>
        </div>
        <div id="get_coupons" class="tab-pane">
            <?php $this->load->view('api/get_coupons', ['authtoken' => $authtoken]); ?>
        </div>
        <div id="get_modules" class="tab-pane">
            <?php $this->load->view('api/get_modules', ['authtoken' => $authtoken]); ?>
        </div>
        <div id="check_slug" class="tab-pane">
            <?php $this->load->view('api/check_slug', ['authtoken' => $authtoken]); ?>
        </div>
        <div id="company_registration" class="tab-pane">
            <?php $this->load->view('api/company_registration', ['authtoken' => $authtoken]); ?>
        </div>
        <div id="generate_token" class="tab-pane">
            <?php $this->load->view('api/generate_token', ['authtoken' => $authtoken]); ?>
        </div>
    </div>
</div>
<script>
    $(function () {
        // hide all tabs
        $('.tab-pane').hide();
        // check if there is a hash in the url

        // if no hash value then show the first tab
        $('.tab-pane:first').show();
        $('.nav-tabs a:first').parent().addClass('active');


        // activate the first tab-pane in the nav-tabs

        $('a[data-toggle="tab"]').on('shown.bs.tab', function (e) {
            var target = $(e.target).attr("href");
            // show the tab clicked
            $(target).show(); // activated tab
            // hide the other tabs
            $('a[data-toggle="tab"]').not(this).each(function () {
                $($(this).attr("href")).hide();
            });

        });
    });
</script>