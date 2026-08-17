<?php

defined('BASEPATH') or exit('No direct script access allowed');

// Exclude S3P callback and status check from CSRF (called by redirect/AJAX)
$config['csrf_exclude_uris'][] = 'index.php/s3p/complete/.*';
$config['csrf_exclude_uris'][] = 'index.php/s3p/check_status/.*';
