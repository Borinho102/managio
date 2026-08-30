<div class="row">
	<div class="col-md-12">
		<?php echo render_input('settings[time_of_banner_presentation]', _l('set_time_of_banner_presentation'), get_option('time_of_banner_presentation') ?: 10, 'number', ['min' => 3, 'max' => 120, 'step' => 1]); ?>
		<p class="text-muted tw-text-sm"><?php echo _l('set_time_of_banner_presentation'); ?> — min 3s, max 120s. Default: 10.</p>
	</div>
	<div class="col-md-12">
		 <?php render_yes_no_option('enabled_banner_random_mode', 'enabled_banner_random_mode'); ?>
	</div>
</div>