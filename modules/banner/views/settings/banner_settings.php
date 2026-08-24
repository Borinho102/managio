<div class="row">
	<div class="col-md-12">
		<?php echo render_input('settings[time_of_banner_presentation]', _l('set_time_of_banner_presentation'), get_option('time_of_banner_presentation'), 'number'); ?>
	</div>
	<div class="col-md-12">
		 <?php render_yes_no_option('enabled_banner_random_mode', 'enabled_banner_random_mode'); ?>
	</div>
</div>