<?php init_head(); ?>
<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<div id="wrapper">
	<div class="content">
		<?php
		$update_errors = [];
		$latest_version = isset($update['version']) ? $update['version'] : '';
		$database_upgrade_is_required = $this->app_modules->is_database_upgrade_required($module['system_name']);
		?>

		<?php if (!empty($support)): ?>

			<div class="col-md-12">
				<div class="alert alert-<?= $support['type'] ?>">
					<div class="tw-flex tw-items-center tw-justify-between">
						<span class="bold tw-text-xl"><?= _l('support') ?></span>
						<span><?= _l('support_ticket_content') ?></span>
					</div>
					<div class="tw-flex tw-items-center tw-justify-between">
						<p class="mtop10"><?= $support['message'] ?></p>

						<a href="<?= $support['support_url'] ?>" class="btn btn-default tw-w-48" target="_blank"><i class="fa-solid fa-up-right-from-square mright5"></i><?= _l('create_support_ticket') ?></a>

					</div>
				</div>
			</div>
		<?php endif ?>
		<?php if (!$database_upgrade_is_required): ?>
			<div class="col-md-6">
				<?= form_open($submit_url, ['id' => 'update_module_version_form']); ?>
				<div class="panel tw-p-6 tw-bg-black/5">
					<div class="panel no-margin">
						<div class="panel-heading tw-bg-white">
							<h4 class="no-margin text-info tw-font-medium"><?= _l('module_update') ?></h4>
						</div>
						<div class="panel-body">
							<div class="col-md-6 text-center">
								<div class="alert alert-<?= $latest_version > $module['installed_version'] ? 'danger' : 'info'; ?>">
									<h4 class="tw-font-bold !tw-text-base tw-mb-1"><?= _l('your_version'); ?></h4>
									<p class="tw-font-semibold tw-mb-0"><?= wordwrap($module['installed_version'], 1, '.', true); ?></p>
								</div>
							</div>
							<div class="col-md-6 text-center">
								<?php $alert = ($latest_version > $module['installed_version']) ? 'success' : ($latest_version == $module['installed_version'] ? 'info' : ''); ?>
								<div class="alert alert-<?= $alert ?>">
									<h4 class="tw-font-bold !tw-text-base tw-mb-1"><?= _l('latest_version'); ?></h4>
									<p class="tw-font-semibold tw-mb-0"><?= wordwrap($latest_version, 1, '.', true); ?></p>
									<?= form_hidden('latest_version', $latest_version); ?>
									<?= form_hidden('update_id', $update['update_id'] ?? '000'); ?>
									<?= form_hidden('has_sql', $update['has_sql'] ?? false); ?>
								</div>
							</div>
							<div class="clearfix"></div>
							<hr />
							<div class="col-md-12">
								<?= render_input('purchase_key', 'purchase_key', get_option('purchase_key'), '', ['autocomplete' => 'off']); ?>
							</div>
							<div class="col-md-12">
								<?= render_input('username', 'username', get_option('username'), '', ['autocomplete' => 'off']); ?>
							</div>
							<div class="col-md-12 text-center">
								<?php if ($module['installed_version'] != $latest_version && $latest_version > $module['installed_version']) { ?>
									<h3 class="bold text-center mbot20"><i class="fa-solid fa-bell fa-shake"></i>
										<?= _l('update_available'); ?></h3>
									<div class="update_app_wrapper" data-wait-text="<?= _l('wait_text'); ?>"
										data-original-text="<?= _l('update_now'); ?>">
										<?php if (count($update_errors) == 0) { ?>
											<button type="submit" class="btn btn-success" id="download_files"><?= _l('download_files') ?></button>
										<?php } ?>
									</div>
									<?php if ($module['installed_version'] != $latest_version && $latest_version > $module['installed_version']) { ?>
										<div class="col-md-12 mtop20">
											<div class="alert alert-warning">
												<?= _l('update_warning'); ?>
											</div>
										</div>
									<?php } ?>
									<div id="update_messages" class="mtop25 text-left"></div>
								<?php } else { ?>
									<h3 class="tw-font-medium text-success">
										<?= _l('using_latest_version'); ?>
									</h3>
								<?php } ?>
								<?php if (count($update_errors) > 0) { ?>
									<div class="tw-mt-5">
										<p class="text-danger"><?= _l('fix_errors'); ?></p>
										<?php foreach ($update_errors as $error) { ?>
											<div class="alert alert-danger">
												<?= e($error); ?>
											</div>
										<?php } ?>
									</div>
								<?php } ?>
							</div>

						</div>
					</div>
				</div>
				<?= form_close() ?>
			</div>
			<div class="col-md-6">
				<div class="panel tw-p-6 tw-bg-black/5">
					<div class="panel no-margin">
						<div class="panel-heading tw-bg-white">
							<h4 class="no-margin text-info tw-font-medium"><?= _l('changelog') ?></h4>
						</div>
						<div class="panel-body">
							<?php if (isset($update['changelog'])) { ?>
								<div class="tw-text-base">
									<?= $update['changelog']; ?>
								</div>
							<?php } ?>
						</div>
					</div>
				</div>
			</div>
		<?php endif ?>

		<?php if ($database_upgrade_is_required): ?>
			<div class="tw-mt-12 sm:tw-mt-0 col-md-6 col-md-offset-2 text-center">
				<div class="panel">
					<div class="panel-heading tw-bg-white">
						<h4 class="tw-mb-2 tw-mt-0 tw-text-lg tw-leading-6 tw-font-medium text-danger"><?= _l('database_upgrade_required') ?></h4>
					</div>
					<div class="panel-body">

						<p class="mt-4">
							<?= _l('update_content_1') ?>
							<?= _l('update_content_2') ?>
							<span class="text-success tw-font-bold"><?= wordwrap($module['headers']['version'], 1, '.', true); ?></span>
							<?= _l('update_content_3') ?>
							<span class="text-danger tw-font-bold"><?= wordwrap($module['installed_version'], 1, '.', true); ?></span>.
						</p>
						<p class="tw-font-bold tw-mt-3"><?= _l('update_content_4') ?></p>
						<div>
							<a href="<?= admin_url('modules/upgrade_database/' . $module['system_name']) ?>" class="btn btn-success btn-lg tw-mt-4"><?= _l('upgrade_now') ?></a>
						</div>
						<p class="text-muted tw-mt-5">
							<small><?= _l('update_content_5') ?></small>
						</p>
					</div>
				</div>
			</div>
	</div>
<?php endif ?>
</div>
</div>

<?php init_tail(); ?>

<script type="text/javascript">
	appValidateForm($('#update_module_version_form'), {
		purchase_key: 'required',
		username: 'required'
	}, update_module_version);


	function update_module_version(form) {
		$("#download_files").prop('disabled', true).prepend('<i class="fa fa-spinner fa-pulse"></i> ');
		$.post(form.action, $(form).serialize()).done(function(response) {
			var response = $.parseJSON(response);
			alert_float(response.type, response.message);
			if (response.type == 'success') {
				window.location.href = response.url;
			}
			$("#download_files").prop('disabled', false).find('i').remove();
		});
	}
</script>