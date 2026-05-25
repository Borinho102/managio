<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <link rel="stylesheet" type="text/css" id="roboto-css"
          href="<?php echo site_url('assets/plugins/roboto/roboto.css'); ?>">
    <style>

        body {
            font-family: Roboto, Geneva, sans-serif;
            font-size: 15px;
        }

        .bold, b, strong, h1, h2, h3, h4, h5, h6 {
            font-weight: 500;
        }

        .wrapper {
            margin: 0 auto;
            display: block;
            background: #f0f0f0;
            width: 700px;
            border: 1px solid #e4e4e4;
            padding: 20px;
            border-radius: 4px;
            margin-top: 50px;
            text-align: center;
        }

        .wrapper h1 {
            text-align: center;
            font-size: 27px;
            color: red;
            margin-top: 0px;
        }

        .wrapper .upgrade_now {
            text-transform: uppercase;
            background: #2f55d4;
            color: #fff;
            padding: 15px 25px;
            border-radius: 3px;
            text-decoration: none;
            text-align: center;
            border: 0px;
            outline: 0px;
            cursor: pointer;
            font-size: 15px;
        }

        .wrapper .upgrade_now:hover, .wrapper .upgrade_now:active {
            background: #2f55d4;
        }

        .wrapper .upgrade_now:disabled {
            cursor: not-allowed;
            pointer-events: none;
            box-shadow: none;
            opacity: .65;
        }

        .upgrade_now_wrapper {
            margin: 0 auto;
            width: 100%;
            text-align: left;
            margin-top: 35px;
        }

        .note {
            color: #636363;
        }
    </style>
</head>
<body>
<?php
$CI = &get_instance();
$module = $CI->app_modules->get(SaaS_MODULE);
?>
<div class="wrapper">
    <h1>SaaS Database upgrade is required!</h1>
    <p>You need to perform a database upgrade before proceeding. Your <b>files version
            is <?php echo wordwrap($module['headers']['version'], 1, '.', true); ?></b> and <b>database version
            is <?php echo wordwrap($module['installed_version'], 1, '.', true); ?>.</b></p>
    <p class="bold">Make sure that you have backup of your database before performing an upgrade.</p>
    <div class="upgrade_now_wrapper">
        <div style="text-align:center">
            <?php echo form_open($CI->config->site_url($CI->uri->uri_string()), array('id' => 'upgrade_db_form')); ?>
            <input type="hidden" name="upgrade_database" value="true">
            <button type="submit" id="submit_btn" onclick="upgradeDB(); return false;" class="upgrade_now">Upgrade now
            </button>
            <?php echo form_close(); ?>
        </div>
        </p>
    </div>
</div>
<script>
    function upgradeDB() {
        document.getElementById('submit_btn').disabled = true;
        document.getElementById('submit_btn').innerHTML = "Please wait...";
        document.getElementById("upgrade_db_form").submit();
    }
</script>
</body>
</html>
