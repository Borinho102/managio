<?php defined('BASEPATH') or exit('No direct script access allowed');
$locale = $locale ?? ($GLOBALS['locale'] ?? 'en');
$isRTL  = $isRTL ?? 'false';
?>
<!DOCTYPE html>
<html lang="<?php echo function_exists('new_html_entity_decode') ? new_html_entity_decode($locale) : htmlspecialchars((string) $locale); ?>">
<head>
	<meta charset="utf-8">
	<meta http-equiv="X-UA-Compatible" content="IE=edge">
	<meta name="viewport" content="user-scalable=no, width=device-width, initial-scale=1, maximum-scale=1">
	<title><?php if (isset($title)){ echo function_exists('new_html_entity_decode') ? new_html_entity_decode($title) : htmlspecialchars((string) $title); } ?></title>
	<?php echo compile_theme_css(); ?>
	<script src="<?php echo base_url('assets/plugins/jquery/jquery.min.js'); ?>"></script>
		<?php if (function_exists('app_affiliates_head')) { app_affiliates_head(); } ?>
</head>
<body class="customers<?php if(function_exists('is_mobile') && is_mobile()){echo ' mobile hide-sidebar';}?><?php if(isset($bodyclass)){echo ' ' . $bodyclass; } ?>" <?php if($isRTL == 'true'){ echo 'dir="rtl"';} ?>>
