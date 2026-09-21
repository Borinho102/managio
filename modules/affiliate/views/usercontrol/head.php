<?php defined('BASEPATH') or exit('No direct script access allowed');
$locale = $locale ?? ($GLOBALS['locale'] ?? 'en');
$isRTL  = $isRTL ?? 'false';
$themeCss = '';
try {
    if (function_exists('compile_theme_css')) {
        $themeCss = compile_theme_css();
    }
} catch (Throwable $e) {
    $themeCss = '';
}
if (!is_string($themeCss) || trim($themeCss) === '') {
    $themePath = function_exists('theme_assets_path') ? theme_assets_path() : 'assets/themes/perfex';
    $fallback = [
        'assets/css/reset.css',
        'assets/plugins/bootstrap/css/bootstrap.min.css',
        'assets/plugins/font-awesome/css/fontawesome.min.css',
        'assets/plugins/font-awesome/css/solid.min.css',
        'assets/plugins/font-awesome/css/regular.min.css',
        'assets/plugins/font-awesome/css/brands.min.css',
        'assets/builds/tailwind.css',
        rtrim($themePath, '/') . '/css/style.css',
        'modules/affiliate/assets/css/affiliate_portal.css',
    ];
    foreach ($fallback as $href) {
        $themeCss .= '<link rel="stylesheet" type="text/css" href="' . base_url($href) . '">' . PHP_EOL;
    }
    $themeCss .= '<link rel="stylesheet" type="text/css" href="https://rsms.me/inter/inter.css">' . PHP_EOL;
}
?>
<!DOCTYPE html>
<html lang="<?php echo function_exists('new_html_entity_decode') ? new_html_entity_decode($locale) : htmlspecialchars((string) $locale); ?>">
<head>
	<meta charset="utf-8">
	<meta http-equiv="X-UA-Compatible" content="IE=edge">
	<meta name="viewport" content="user-scalable=no, width=device-width, initial-scale=1, maximum-scale=1">
	<title><?php if (isset($title)){ echo function_exists('new_html_entity_decode') ? new_html_entity_decode($title) : htmlspecialchars((string) $title); } ?></title>
	<?php if (function_exists('pwa_head')) { pwa_head(); } ?>
	<?php echo $themeCss; ?>
	<script src="<?php echo base_url('assets/plugins/jquery/jquery.min.js'); ?>"></script>
		<?php if (function_exists('app_affiliates_head')) { app_affiliates_head(); } ?>
</head>
<body class="customers<?php if(function_exists('is_mobile') && is_mobile()){echo ' mobile hide-sidebar';}?><?php if(isset($bodyclass)){echo ' ' . $bodyclass; } ?>" <?php if($isRTL == 'true'){ echo 'dir="rtl"';} ?>>
