<?php

defined('BASEPATH') or exit('No direct script access allowed');
/*
Module Name: S3P Smobilpay Gateway
Description: S3P Smobilpay mobile money payment gateway (Orange Money, MTN MOMO)
Version: 1.0.0 | by masumwen07@gmail.com
Requires at least: 2.3.0
Author Email: masumwen07@gmail.com
Author WhatsApp: +8801736477088
*/

const S3P_MODULE_NAME       = 's3p';
const S3P_MODULE_GATEWAY_ID = 'S3p_gateway';

register_payment_gateway(S3P_MODULE_GATEWAY_ID, S3P_MODULE_NAME);
register_activation_hook(S3P_MODULE_NAME, 's3pModuleActivation');

function s3pModuleActivation()
{
    add_option('s3p_module_initialized', 1);
}
