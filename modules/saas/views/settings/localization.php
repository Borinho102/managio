<?php defined('BASEPATH') or exit('No direct script access allowed');
$date_formats = get_available_date_formats();
?>
<div class="form-group">
    <label for="dateformat" class="control-label"><?php echo _l('settings_localization_date_format'); ?></label>
    <select name="settings[saas_dateformat]" id="dateformat" class="form-control selectpicker" data-none-selected-text="<?php echo _l('dropdown_non_selected_tex'); ?>">
        <?php foreach($date_formats as $key => $val){ ?>
            <option value="<?php echo $key; ?>" <?php if($key == get_option('saas_dateformat')){echo 'selected';} ?>><?php echo $val; ?></option>
        <?php } ?>
    </select>
</div>
<hr />
<div class="form-group">
    <label for="time_format" class="control-label"><?php echo _l('time_format'); ?></label>
    <select name="settings[saas_time_format]" id="time_format" class="form-control selectpicker" data-none-selected-text="<?php echo _l('dropdown_non_selected_tex'); ?>">
        <option value="24" <?php if('24' == get_option('saas_time_format')){echo 'selected';} ?>><?php echo _l('time_format_24'); ?></option>
        <option value="12" <?php if('12' == get_option('saas_time_format')){echo 'selected';} ?>><?php echo _l('time_format_12'); ?></option>
    </select>
</div>
<hr />
<div class="form-group">
    <label for="timezones" class="control-label"><?php echo _l('settings_localization_default_timezone'); ?></label>
    <select name="settings[saas_default_timezone]" id="timezones" class="form-control selectpicker" data-none-selected-text="<?php echo _l('dropdown_non_selected_tex'); ?>" data-live-search="true">
        <?php foreach(get_timezones_list() as $key => $timezones){ ?>
            <optgroup label="<?php echo $key; ?>">
                <?php foreach($timezones as $timezone){ ?>
                    <option value="<?php echo $timezone; ?>" <?php if(get_option('saas_default_timezone') == $timezone){echo 'selected';} ?>><?php echo $timezone; ?></option>
                <?php } ?>
            </optgroup>
        <?php } ?>
    </select>
</div>
<hr />
<div class="form-group">
    <label for="saas_active_language" class="control-label"><?php echo _l('settings_localization_default_language'); ?></label>
    <select name="settings[saas_active_language]" data-live-search="true" id="saas_active_language" class="form-control selectpicker" data-none-selected-text="<?php echo _l('dropdown_non_selected_tex'); ?>">
        <?php foreach($this->app->get_available_languages() as $availableLanguage){
            $subtext = hooks()->apply_filters('settings_language_subtext', '', $availableLanguage);
            ?>
            <option value="<?php echo $availableLanguage; ?>" data-subtext="<?php echo $subtext; ?>" <?php if($availableLanguage == get_option('saas_active_language')){echo ' selected'; } ?>><?php echo ucfirst($availableLanguage); ?></option>
        <?php } ?>
    </select>
</div>
<hr />
<div class="form-group">
    <label for="saas_default_currency" class="control-label"><?php echo _l('saas_default_currency'); ?></label>
    <select name="settings[saas_default_currency]" id="saas_default_currency" class="form-control selectpicker"
            data-none-selected-text="<?php echo _l('dropdown_non_selected_tex'); ?>" data-live-search="true">
        <?php
        $CI = &get_instance();
        $CI->load->model('currencies_model');
        $saasCurrencies = $CI->currencies_model->get();
        $saasDefaultCurrency = (string) get_option('saas_default_currency');
        $baseCurrency = function_exists('get_base_currency') ? get_base_currency() : null;
        $baseName = !empty($baseCurrency->name) ? $baseCurrency->name : 'USD';
        ?>
        <option value=""><?php echo _l('saas_package_currency_default', $baseName); ?></option>
        <?php foreach ($saasCurrencies as $currency) {
            $code = $currency['name'] ?? '';
            if ($code === '') {
                continue;
            }
            $label = $code;
            if (!empty($currency['symbol'])) {
                $label .= ' (' . $currency['symbol'] . ')';
            }
            ?>
            <option value="<?php echo html_escape($code); ?>" <?php if ($saasDefaultCurrency === $code) { echo 'selected'; } ?>><?php echo html_escape($label); ?></option>
        <?php } ?>
    </select>
    <p class="text-muted tw-mt-2"><?php echo _l('saas_default_currency_help'); ?></p>
</div>

<hr />
<div class="form-group">
    <label for="saas_exchange_rate_ttl" class="control-label"><?php echo _l('saas_exchange_rate_ttl_hours') ?: 'Exchange rate cache TTL (hours)'; ?></label>
    <?php $ttl_hours = get_option('saas_exchange_rate_ttl'); $ttl_hours = ($ttl_hours !== null && $ttl_hours !== '') ? intval($ttl_hours) : 12; ?>
    <input type="number" min="0" name="settings[saas_exchange_rate_ttl]" id="saas_exchange_rate_ttl" class="form-control" value="<?= $ttl_hours ?>" />
    <p class="text-muted"><?= _l('saas_exchange_rate_ttl_help') ?: 'Number of hours to cache exchange rates. Set 0 to disable TTL (helper will still try cached values on fetch failure). Default 12.' ?></p>
    <div class="mt-2">
        <form method="post" action="<?= site_url('saas/settings/clear_exchange_cache') ?>" style="display:inline">
            <button type="submit" class="btn btn-warning"><?php echo _l('saas_clear_exchange_cache') ?: 'Clear cached exchange rates'; ?></button>
        </form>
    </div>
</div>
