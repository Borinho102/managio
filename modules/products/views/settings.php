<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<link rel="stylesheet" href="<?php echo module_dir_url('products', 'assets/css/products_settings.css'); ?>?v=<?php echo $this->app_scripts->core_version(); ?>">

<div class="products-settings-wrap col-md-12">
	<!-- General -->
	<div class="settings-section-card">
		<div class="settings-section-header">
			<div class="section-icon icon-general"><i class="fa fa-cog"></i></div>
			<h4><?php echo _l('settings_general'); ?></h4>
		</div>
		<div class="settings-section-body">
			<div class="settings-row">
				<div class="settings-field">
					<?php render_yes_no_option('product_menu_disabled', 'product_menu_disabled', 'product_menu_disabled_tooltip'); ?>
				</div>
				<div class="settings-field">
					<?php render_yes_no_option('nlu_product_menu_disabled', 'nlu_product_menu_disabled', 'nlu_product_menu_disabled_tooltip'); ?>
				</div>
			</div>
			<div class="settings-row">
				<div class="settings-field">
					<?php render_yes_no_option('nlu_hiddenprices_disabled', 'nlu_hiddenprices_disabled', 'nlu_hiddenprices_disabled_tooltip'); ?>
				</div>
				<div class="settings-field">
					<?php render_yes_no_option('b2bmode_disabled', 'b2bmode_disabled', 'b2bmode_disabled_tooltip'); ?>
				</div>
			</div>
			<div class="settings-row">
				<div class="settings-field">
					<?php render_yes_no_option('coupons_disabled', 'coupons_disabled', 'coupons_disabled_tooltip'); ?>
				</div>
				<div class="settings-field">
					<?php render_yes_no_option('product_guest_checkout_enabled', 'product_guest_checkout_enabled', 'product_guest_checkout_enabled_tooltip'); ?>
					<p class="text-warning no-margin"><small><i class="fa fa-flask"></i> <?php echo _l('product_guest_checkout_beta_note'); ?></small></p>
				</div>
			</div>
		</div>
	</div>

	<!-- Features -->
	<div class="settings-section-card">
		<div class="settings-section-header">
			<div class="section-icon icon-features"><i class="fa fa-puzzle-piece"></i></div>
			<h4><?php echo _l('settings_features'); ?></h4>
		</div>
		<div class="settings-section-body">
			<div class="settings-row">
				<div class="settings-field">
					<?php render_yes_no_option('product_wishlist_enabled', 'product_wishlist_enabled', 'product_wishlist_enabled_tooltip'); ?>
				</div>
				<div class="settings-field">
					<?php render_yes_no_option('product_detail_pages_enabled', 'product_detail_pages_enabled', 'product_detail_pages_enabled_tooltip'); ?>
				</div>
			</div>
			<div class="settings-row">
				<div class="settings-field">
					<?php render_yes_no_option('product_seo_meta_enabled', 'product_seo_meta_enabled', 'product_seo_meta_enabled_tooltip'); ?>
				</div>
				<div class="settings-field">
					<?php render_yes_no_option('product_analytics_enabled', 'product_analytics_enabled', 'product_analytics_enabled_tooltip'); ?>
				</div>
			</div>
			<div class="settings-row">
				<div class="settings-field">
					<?php render_yes_no_option('product_abandoned_cart_tracking_enabled', 'product_abandoned_cart_tracking_enabled', 'product_abandoned_cart_tracking_enabled_tooltip'); ?>
				</div>
				<div class="settings-field">
					<?php render_yes_no_option('product_digital_downloads_enabled', 'product_digital_downloads_enabled', 'product_digital_downloads_enabled_tooltip'); ?>
				</div>
			</div>
		</div>
	</div>

	<!-- Shipping & Tax -->
	<div class="settings-section-card">
		<div class="settings-section-header">
			<div class="section-icon icon-shipping"><i class="fa fa-truck"></i></div>
			<h4><?php echo _l('settings_shipping_tax'); ?></h4>
		</div>
		<div class="settings-section-body">
			<div class="settings-row">
				<div class="settings-field">
					<label class="control-label"><?php echo _l('low_qty'); ?>
						<i class="fa fa-question-circle settings-tooltip-icon" data-toggle="tooltip" data-placement="top" data-html="true" title="<?php echo htmlspecialchars(_l('product_low_quantity_tooltip')); ?>"></i>
					</label>
					<?php echo render_input('settings[product_low_quantity]', '', get_option('product_low_quantity'), 'number', ['required'=>true,'min'=>0]); ?>
				</div>
				<div class="settings-field">
					<label class="control-label"><?php echo _l('flat_shipping'); ?>
						<i class="fa fa-question-circle settings-tooltip-icon" data-toggle="tooltip" data-placement="top" data-html="true" title="<?php echo htmlspecialchars(_l('flat_shipping_tooltip')); ?>"></i>
					</label>
					<?php echo render_input('settings[product_flat_rate_shipping]', '', get_option('product_flat_rate_shipping'), 'number', ['required'=>true,'min'=>0]); ?>
				</div>
			</div>
			<div class="settings-row">
				<div class="settings-field full-width" app-field-wrapper="settings[product_tax_for_shipping_cost]">
					<label class="control-label"><?php echo _l('tax_for_shipping_cost'); ?>
						<i class="fa fa-question-circle settings-tooltip-icon" data-toggle="tooltip" data-placement="top" title="<?php echo htmlspecialchars(_l('tax_for_shipping_cost_tooltip')); ?>"></i>
					</label>
					<?php
					$selected_taxes = '';
					if (!empty(get_option('product_tax_for_shipping_cost'))) {
						$selected_taxes = unserialize(get_option('product_tax_for_shipping_cost'));
					}
					echo $this->misc_model->get_taxes_dropdown_template('settings[product_tax_for_shipping_cost][]', $selected_taxes);
					?>
				</div>
			</div>
		</div>
	</div>

	<!-- Multi-currency -->
	<div class="settings-section-card">
		<div class="settings-section-header">
			<div class="section-icon icon-shipping"><i class="fa fa-money"></i></div>
			<h4><?php echo _l('settings_multicurrency'); ?></h4>
		</div>
		<div class="settings-section-body">
			<div class="settings-row">
				<div class="settings-field">
					<?php render_yes_no_option('product_multicurrency_enabled', 'product_multicurrency_enabled', 'product_multicurrency_enabled_tooltip'); ?>
				</div>
				<div class="settings-field">
					<?php render_yes_no_option('product_currency_switcher_enabled', 'product_currency_switcher_enabled', 'product_currency_switcher_enabled_tooltip'); ?>
				</div>
			</div>
			<div class="settings-row">
				<div class="settings-field full-width" app-field-wrapper="settings[product_currencies_enabled]">
					<label class="control-label"><?php echo _l('product_currencies_enabled'); ?>
						<i class="fa fa-question-circle settings-tooltip-icon" data-toggle="tooltip" data-placement="top" data-html="true" title="<?php echo htmlspecialchars(_l('product_currencies_enabled_tooltip')); ?>"></i>
					</label>
					<?php
					$this->load->model('products/product_prices_model');
					// products_all_currencies() normalises to objects: the Perfex
					// currencies model returns arrays from get() but an object from
					// get_base_currency().
					$all_currencies      = products_all_currencies();
					$settings_base       = products_base_currency();
					$selected_currencies = array_filter(array_map('intval', explode(',', (string) get_option('product_currencies_enabled'))));
					$priced_currency_ids = $this->product_prices_model->get_priced_currency_ids();
					$currency_options    = [];
					foreach ($all_currencies as $currency_id => $settings_currency) {
						// The base currency is always sellable, so listing it here
						// would only invite someone to "disable" it.
						if (!empty($settings_base) && $currency_id == (int) $settings_base->id) {
							continue;
						}
						$currency_options[] = [
							'id'   => $currency_id,
							'name' => $settings_currency->name . (in_array($currency_id, $priced_currency_ids, true) ? '' : ' - ' . _l('product_currency_no_prices_yet')),
						];
					}
					// data-container="body" lifts the dropdown out of the settings
					// card, which is overflow:hidden and would otherwise clip it.
					echo render_select('settings[product_currencies_enabled][]', $currency_options, ['id', 'name'], '', $selected_currencies, ['multiple' => true, 'data-container' => 'body', 'data-actions-box' => 'true', 'data-none-selected-text' => _l('dropdown_non_selected_tex')], [], '', '', false);
					?>
					<?php if (empty($currency_options)) { ?>
						<p class="text-danger"><small><?php echo _l('product_currencies_none_available'); ?></small></p>
					<?php } ?>
				</div>
			</div>
			<div class="settings-row">
				<div class="settings-field full-width" app-field-wrapper="settings[product_currencies_no_tax]">
					<label class="control-label"><?php echo _l('product_currencies_no_tax'); ?>
						<i class="fa fa-question-circle settings-tooltip-icon" data-toggle="tooltip" data-placement="top" data-html="true" title="<?php echo htmlspecialchars(_l('product_currencies_no_tax_tooltip')); ?>"></i>
					</label>
					<?php
					// Tax free selling currencies. The base currency is offered
					// here too: a merchant may well bill their home market
					// tax free and charge tax abroad.
					$no_tax_selected = array_filter(array_map('intval', explode(',', (string) get_option('product_currencies_no_tax'))));
					$no_tax_options  = [];
					foreach ($all_currencies as $currency_id => $tax_currency) {
						$no_tax_options[] = ['id' => $currency_id, 'name' => $tax_currency->name];
					}
					echo render_select('settings[product_currencies_no_tax][]', $no_tax_options, ['id', 'name'], '', $no_tax_selected, ['multiple' => true, 'data-container' => 'body', 'data-actions-box' => 'true', 'data-none-selected-text' => _l('dropdown_non_selected_tex')], [], '', '', false);
					?>
					<p class="text-muted"><small><?php echo _l('product_currencies_no_tax_help'); ?></small></p>
					<p class="text-muted"><small><?php echo _l('product_currencies_enabled_help', !empty($settings_base) ? $settings_base->name : ''); ?></small></p>
				</div>
			</div>
		</div>
	</div>

	<!-- Remarketing -->
	<div class="settings-section-card">
		<div class="settings-section-header">
			<div class="section-icon icon-remarketing"><i class="fa fa-bullseye"></i></div>
			<h4><?php echo _l('settings_remarketing'); ?></h4>
		</div>
		<div class="settings-section-body">
			<div class="settings-subheading"><?php echo _l('product_remarketing_facebook_enabled'); ?></div>
			<div class="settings-row">
				<div class="settings-field">
					<?php render_yes_no_option('product_remarketing_facebook_enabled', 'product_remarketing_facebook_enabled', 'product_remarketing_facebook_tooltip'); ?>
				</div>
				<div class="settings-field">
					<label class="control-label"><?php echo _l('product_remarketing_facebook_pixel_id'); ?></label>
					<?php echo render_input('settings[product_remarketing_facebook_pixel_id]', '', get_option('product_remarketing_facebook_pixel_id'), 'text', ['placeholder' => 'e.g. 123456789012345']); ?>
				</div>
			</div>

			<div class="settings-divider"></div>
			<div class="settings-subheading"><?php echo _l('product_remarketing_google_enabled'); ?></div>
			<div class="settings-row">
				<div class="settings-field">
					<?php render_yes_no_option('product_remarketing_google_enabled', 'product_remarketing_google_enabled', 'product_remarketing_google_tooltip'); ?>
				</div>
				<div class="settings-field">
					<label class="control-label"><?php echo _l('product_remarketing_google_id'); ?></label>
					<?php echo render_input('settings[product_remarketing_google_id]', '', get_option('product_remarketing_google_id'), 'text', ['placeholder' => 'AW-123456789']); ?>
				</div>
				<div class="settings-field">
					<label class="control-label"><?php echo _l('product_remarketing_google_label'); ?> <span class="text-muted">(<?php echo _l('optional'); ?>)</span></label>
					<?php echo render_input('settings[product_remarketing_google_label]', '', get_option('product_remarketing_google_label'), 'text'); ?>
				</div>
			</div>

			<div class="settings-divider"></div>
			<div class="settings-subheading"><?php echo _l('product_remarketing_custom_enabled'); ?></div>
			<div class="settings-row">
				<div class="settings-field">
					<?php render_yes_no_option('product_remarketing_custom_enabled', 'product_remarketing_custom_enabled', 'product_remarketing_custom_tooltip'); ?>
				</div>
			</div>
			<div class="settings-row">
				<div class="settings-field full-width">
					<label class="control-label"><?php echo _l('product_remarketing_custom_script'); ?></label>
					<?php echo render_textarea('settings[product_remarketing_custom_script]', '', get_option('product_remarketing_custom_script'), ['rows' => 6, 'placeholder' => 'Paste your pixel or tracking code here, e.g. <script>...</script>']); ?>
				</div>
			</div>
		</div>
	</div>

	<!-- Abandoned Cart Email -->
	<div class="settings-section-card">
		<div class="settings-section-header">
			<div class="section-icon icon-abandoned"><i class="fa fa-envelope"></i></div>
			<h4><?php echo _l('settings_abandoned_cart_email'); ?></h4>
		</div>
		<div class="settings-section-body">
			<div class="settings-row">
				<div class="settings-field">
					<?php render_yes_no_option('product_abandoned_cart_email_enabled', 'product_abandoned_cart_email_enabled', 'product_abandoned_cart_email_tooltip'); ?>
				</div>
				<div class="settings-field">
					<label class="control-label"><?php echo _l('product_abandoned_cart_email_delay_hours'); ?></label>
					<?php echo render_input('settings[product_abandoned_cart_email_delay_hours]', '', get_option('product_abandoned_cart_email_delay_hours'), 'number', ['min' => 1]); ?>
				</div>
			</div>
		</div>
	</div>

	<!-- Exit Popups -->
	<div class="settings-section-card">
		<div class="settings-section-header">
			<div class="section-icon icon-remarketing"><i class="fa fa-window-restore"></i></div>
			<h4><?php echo _l('settings_exit_popups'); ?></h4>
		</div>
		<div class="settings-section-body">
			<div class="settings-row">
				<div class="settings-field">
					<?php render_yes_no_option('product_exit_popups_enabled', 'product_exit_popups_enabled', 'product_exit_popups_enabled_tooltip'); ?>
				</div>
				<div class="settings-field">
					<label class="control-label"><?php echo _l('product_exit_popup_dismiss_days'); ?></label>
					<?php echo render_input('settings[product_exit_popup_dismiss_days]', '', get_option('product_exit_popup_dismiss_days'), 'number', ['min' => 0]); ?>
				</div>
			</div>
			<p class="text-muted"><?php echo _l('exit_popups'); ?>: <a href="<?php echo admin_url('products/exit_popups'); ?>"><?php echo _l('exit_popups'); ?></a></p>
			<p class="text-muted mtop5"><small><i class="fa fa-info-circle"></i> <?php $test_url = site_url('products/client?products_exit_popup_test=1'); $link = '<a href="' . htmlspecialchars($test_url, ENT_QUOTES, 'UTF-8') . '" target="_blank">' . htmlspecialchars($test_url, ENT_QUOTES, 'UTF-8') . '</a>'; echo str_replace('{link}', $link, _l('exit_popup_where_works')); ?></small></p>
		</div>
	</div>

	<!-- WhatsApp / SMS Notifications -->
	<div class="settings-section-card">
		<div class="settings-section-header">
			<div class="section-icon icon-remarketing"><i class="fa fa-whatsapp"></i></div>
			<h4><?php echo _l('settings_product_notifications'); ?></h4>
		</div>
		<div class="settings-section-body">
			<div class="settings-row">
				<div class="settings-field">
					<?php render_yes_no_option('product_notifications_enabled', 'product_notifications_enabled', 'product_notifications_enabled_tooltip'); ?>
				</div>
			</div>
			<div class="settings-row">
				<div class="settings-field full-width">
					<label class="control-label"><?php echo _l('product_sms_gateway_url'); ?></label>
					<?php echo render_input('settings[product_sms_gateway_url]', '', get_option('product_sms_gateway_url'), 'text', ['placeholder' => 'https://...']); ?>
					<p class="text-muted"><?php echo _l('product_sms_gateway_url_tooltip'); ?></p>
				</div>
			</div>
			<div class="settings-row">
				<div class="settings-field full-width">
					<label class="control-label"><?php echo _l('product_whatsapp_gateway_url'); ?></label>
					<?php echo render_input('settings[product_whatsapp_gateway_url]', '', get_option('product_whatsapp_gateway_url'), 'text', ['placeholder' => 'https://...']); ?>
					<p class="text-muted"><?php echo _l('product_whatsapp_gateway_url_tooltip'); ?></p>
				</div>
			</div>
			<p class="text-muted"><?php echo _l('product_notifications'); ?>: <a href="<?php echo admin_url('products/product_notifications'); ?>"><?php echo _l('product_notifications'); ?></a></p>
		</div>
	</div>

	<!-- Marketing Features -->
	<div class="settings-section-card">
		<div class="settings-section-header">
			<div class="section-icon icon-features"><i class="fa fa-bullhorn"></i></div>
			<h4><?php echo _l('settings_marketing'); ?></h4>
		</div>
		<div class="settings-section-body">
			<div class="settings-row">
				<div class="settings-field">
					<?php render_yes_no_option('product_reviews_enabled', 'product_reviews_enabled', 'product_reviews_enabled_tooltip'); ?>
				</div>
				<div class="settings-field">
					<?php render_yes_no_option('product_referral_enabled', 'product_referral_enabled', 'product_referral_enabled_tooltip'); ?>
				</div>
			</div>
			<div class="settings-row">
				<div class="settings-field">
					<?php render_yes_no_option('product_back_in_stock_enabled', 'product_back_in_stock_enabled', 'product_back_in_stock_enabled_tooltip'); ?>
				</div>
				<div class="settings-field">
					<?php render_yes_no_option('product_price_drop_enabled', 'product_price_drop_enabled', 'product_price_drop_enabled_tooltip'); ?>
				</div>
			</div>
			<div class="settings-row">
				<div class="settings-field">
					<?php render_yes_no_option('product_recommendations_enabled', 'product_recommendations_enabled', 'product_recommendations_enabled_tooltip'); ?>
				</div>
				<div class="settings-field">
					<?php render_yes_no_option('product_newsletter_enabled', 'product_newsletter_enabled', 'product_newsletter_enabled_tooltip'); ?>
				</div>
			</div>
			<div class="settings-row">
				<div class="settings-field">
					<?php render_yes_no_option('product_urgency_enabled', 'product_urgency_enabled', 'product_urgency_enabled_tooltip'); ?>
				</div>
				<div class="settings-field">
					<?php render_yes_no_option('product_gift_cards_enabled', 'product_gift_cards_enabled', 'product_gift_cards_enabled_tooltip'); ?>
				</div>
			</div>
			<div class="settings-row">
				<div class="settings-field">
					<label class="control-label"><?php echo _l('product_gift_card_min_amount'); ?>
						<i class="fa fa-question-circle settings-tooltip-icon" data-toggle="tooltip" data-placement="top" title="<?php echo htmlspecialchars(_l('product_gift_card_min_amount_tooltip')); ?>"></i>
					</label>
					<?php echo render_input('settings[product_gift_card_min_amount]', '', get_option('product_gift_card_min_amount'), 'number', ['min' => 0, 'step' => '0.01']); ?>
				</div>
			</div>
			<div class="settings-row">
				<div class="settings-field">
					<label class="control-label"><?php echo _l('product_referral_commission_percent'); ?>
						<i class="fa fa-question-circle settings-tooltip-icon" data-toggle="tooltip" data-placement="top" title="<?php echo htmlspecialchars(_l('product_referral_commission_percent_tooltip')); ?>"></i>
					</label>
					<?php echo render_input('settings[product_referral_commission_percent]', '', get_option('product_referral_commission_percent'), 'number', ['min' => 0, 'step' => '0.01']); ?>
				</div>
				<div class="settings-field">
					<label class="control-label"><?php echo _l('product_referral_commission_fixed'); ?>
						<i class="fa fa-question-circle settings-tooltip-icon" data-toggle="tooltip" data-placement="top" title="<?php echo htmlspecialchars(_l('product_referral_commission_fixed_tooltip')); ?>"></i>
					</label>
					<?php echo render_input('settings[product_referral_commission_fixed]', '', get_option('product_referral_commission_fixed'), 'number', ['min' => 0, 'step' => '0.01']); ?>
				</div>
			</div>
			<div class="settings-row">
				<div class="settings-field">
					<?php render_yes_no_option('product_upsell_enabled', 'product_upsell_enabled', 'product_upsell_enabled_tooltip'); ?>
				</div>
				<div class="settings-field">
					<?php render_yes_no_option('product_social_proof_enabled', 'product_social_proof_enabled', 'product_social_proof_enabled_tooltip'); ?>
				</div>
				<div class="settings-field">
					<label class="control-label"><?php echo _l('product_social_proof_recent_hours'); ?></label>
					<?php echo render_input('settings[product_social_proof_recent_hours]', '', get_option('product_social_proof_recent_hours'), 'number', ['min' => 1, 'max' => 168]); ?>
					<p class="text-muted"><small><?php echo _l('product_social_proof_recent_hours_tooltip'); ?></small></p>
				</div>
				<div class="settings-field">
					<?php render_yes_no_option('product_social_proof_recent_anonymous', 'product_social_proof_recent_anonymous', 'product_social_proof_recent_anonymous_tooltip'); ?>
				</div>
			</div>
			<div class="settings-row">
				<div class="settings-field">
					<?php render_yes_no_option('product_ab_testing_enabled', 'product_ab_testing_enabled', 'product_ab_testing_enabled_tooltip'); ?>
				</div>
				<div class="settings-field">
					<?php render_yes_no_option('product_segmentation_enabled', 'product_segmentation_enabled', 'product_segmentation_enabled_tooltip'); ?>
				</div>
			</div>
		</div>
	</div>

	<!-- Heatmap -->
	<div class="settings-section-card">
		<div class="settings-section-header">
			<div class="section-icon icon-general"><i class="fa fa-map"></i></div>
			<h4><?php echo _l('settings_heatmap'); ?></h4>
		</div>
		<div class="settings-section-body">
			<div class="settings-row">
				<div class="settings-field">
					<?php render_yes_no_option('product_heatmap_script_enabled', 'product_heatmap_script_enabled', 'product_heatmap_script_enabled_tooltip'); ?>
				</div>
			</div>
			<div class="settings-row">
				<div class="settings-field full-width">
					<label class="control-label"><?php echo _l('product_heatmap_script'); ?></label>
					<?php echo render_textarea('settings[product_heatmap_script]', '', get_option('product_heatmap_script'), ['rows' => 6, 'placeholder' => 'Paste Hotjar, Crazy Egg, Microsoft Clarity, or similar <script>...</script>']); ?>
				</div>
			</div>
		</div>
	</div>

	<!-- Cron Jobs (required for scheduled tasks) -->
	<div class="settings-section-card settings-cron-infobox">
		<div class="settings-section-header">
			<div class="section-icon icon-cron"><i class="fa fa-clock-o"></i></div>
			<h4><?php echo _l('product_cron_jobs'); ?></h4>
		</div>
		<div class="settings-section-body">
			<div class="alert alert-info">
				<i class="fa fa-info-circle"></i>
				<?php echo _l('product_cron_infobox_desc'); ?>
			</div>
			<div class="cron-list">
				<div class="cron-item">
					<div class="cron-item-header">
						<span class="cron-item-name"><?php echo _l('product_cron_abandoned_cart'); ?></span>
						<span class="cron-item-freq"><?php echo _l('product_cron_recommended'); ?>: <?php echo _l('product_cron_daily'); ?></span>
					</div>
					<div class="cron-item-desc"><?php echo _l('product_cron_abandoned_cart_desc'); ?></div>
					<code class="cron-item-url"><?php echo htmlspecialchars(site_url('products/cron/abandoned_cart_emails')); ?></code>
				</div>
				<div class="cron-item">
					<div class="cron-item-header">
						<span class="cron-item-name"><?php echo _l('product_cron_back_in_stock'); ?></span>
						<span class="cron-item-freq"><?php echo _l('product_cron_recommended'); ?>: <?php echo _l('product_cron_daily'); ?></span>
					</div>
					<div class="cron-item-desc"><?php echo _l('product_cron_back_in_stock_desc'); ?></div>
					<code class="cron-item-url"><?php echo htmlspecialchars(site_url('products/cron/back_in_stock_emails')); ?></code>
				</div>
				<div class="cron-item">
					<div class="cron-item-header">
						<span class="cron-item-name"><?php echo _l('product_cron_price_drop'); ?></span>
						<span class="cron-item-freq"><?php echo _l('product_cron_recommended'); ?>: <?php echo _l('product_cron_daily'); ?></span>
					</div>
					<div class="cron-item-desc"><?php echo _l('product_cron_price_drop_desc'); ?></div>
					<code class="cron-item-url"><?php echo htmlspecialchars(site_url('products/cron/price_drop_emails')); ?></code>
				</div>
			</div>
			<p class="text-muted mtop15"><i class="fa fa-terminal"></i> <?php echo _l('product_cron_example'); ?></p>
		</div>
	</div>
</div>
<?php if (get_option('product_debug_timing_enabled') == '1') {
	$req_start = $_SERVER['REQUEST_TIME_FLOAT'] ?? microtime(true);
	$elapsed = round(microtime(true) - $req_start, 3);
?>
<div class="products-debug-timing alert alert-info mtop15" style="margin:15px 0;padding:10px 15px;font-size:12px;">
	<i class="fa fa-clock-o"></i> Settings: full request loaded in <strong><?php echo htmlspecialchars($elapsed); ?>s</strong>
</div>
<?php } ?>
