<?php

hooks()->add_action('admin_init', function () {
    try {
        if (!get_instance()->app_modules->is_active('banner')) {
            return;
        }

        $staffId = get_staff_user_id();
        $canBanner = has_permission('banner', $staffId, 'view');
        $canNews = has_permission('news_ticker', $staffId, 'view');
        $canSettings = has_permission('banner_setting', $staffId, 'view');

        if ($canBanner || $canNews || $canSettings) {
            get_instance()->app_menu->add_sidebar_menu_item('banner', [
                'slug' => 'banner_management',
                'name' => _l('banner_management'),
                'position' => 25,
                'icon' => 'fa-regular fa-images menu-icon',
            ]);
        }

        if ($canBanner) {
            get_instance()->app_menu->add_sidebar_children_item('banner', [
                'slug' => 'banner_link',
                'name' => _l('banner'),
                'href' => admin_url('banner'),
                'position' => 1,
            ]);
        }

        if ($canNews) {
            get_instance()->app_menu->add_sidebar_children_item('banner', [
                'slug' => 'news_ticker',
                'name' => _l('news_ticker'),
                'href' => admin_url('banner/news_ticker'),
                'position' => 2,
            ]);
        }

        if ($canSettings) {
            get_instance()->app_menu->add_sidebar_children_item('banner', [
                'slug' => 'banner_settings',
                'name' => _l('settings'),
                'href' => admin_url('settings?group=banner'),
                'position' => 6,
            ]);

            $settings = [
                'name' => _l('banner'),
                'view' => 'banner/settings/banner_settings',
                'icon' => 'fa-regular fa-images menu-icon',
                'position' => 30,
            ];
            if (defined('BN_CTL_PERFEX_VERSION') && BN_CTL_PERFEX_VERSION && method_exists(get_instance()->app, 'add_settings_section_child')) {
                get_instance()->app->add_settings_section_child('other', 'banner', $settings);
            } elseif (isset(get_instance()->app_tabs) && method_exists(get_instance()->app_tabs, 'add_settings_tab')) {
                get_instance()->app_tabs->add_settings_tab('banner', $settings);
            }
        }
    } catch (Throwable $e) {
        log_message('error', 'Banner sidebar init failed: ' . $e->getMessage());
    }
});
