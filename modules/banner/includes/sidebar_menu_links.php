<?php

/*
 * Inject sidebar menu and links for banner module
 */
hooks()->add_action('admin_init', function () {
    if (!get_instance()->app_modules->is_active('banner')) {
        return;
    }

    if (has_permission('banner', get_staff_user_id(), 'view') || has_permission('news_ticker', get_staff_user_id(), 'view') || has_permission('banner_setting', get_staff_user_id(), 'view')) {
        get_instance()->app_menu->add_sidebar_menu_item('banner', [
            'slug' => 'banner_management',
            'name' => _l('banner_management'),
            'position' => 25,
            'icon' => 'fa-regular fa-images menu-icon',
        ]);
    }

    if (has_permission('banner', get_staff_user_id(), 'view')) {
        get_instance()->app_menu->add_sidebar_children_item('banner', [
            'slug' => 'banner_link',
            'name' => _l('banner'),
            'href' => admin_url('banner'),
            'position' => 1,
        ]);
    }

    if (has_permission('news_ticker', get_staff_user_id(), 'view')) {
        get_instance()->app_menu->add_sidebar_children_item('banner', [
            'slug' => 'news_ticker',
            'name' => _l('news_ticker'),
            'href' => admin_url('banner/news_ticker'),
            'position' => 2,
        ]);
    }

    if (has_permission('banner_setting', get_staff_user_id(), 'view')) {
        get_instance()->app_menu->add_sidebar_children_item('banner', [
            'slug' => 'banner_settings',
            'name' => _l('settings'),
            'href' => admin_url('settings?group=banner'),
            'position' => 6,
        ]);

        if (BN_CTL_PERFEX_VERSION) {
            get_instance()->app->add_settings_section_child('other', 'banner', [
                'name' => _l('banner'),
                'view' => 'banner/settings/banner_settings',
                'icon' => 'fa-regular fa-images menu-icon',
                'position' => 30,
            ]);
        } else {
            get_instance()->app_tabs->add_settings_tab('banner', [
                'name' => _l('banner'),
                'view' => 'banner/settings/banner_settings',
                'icon' => 'fa-regular fa-images menu-icon',
                'position' => 30,
            ]);
        }
    }
});

hooks()->add_action('module_deactivated', function ($module_name) {
    if (BANNER_MODULE == ($module_name['system_name'] ?? '')) {
        // Clear leftover license marker files if present.
        $url = basename(get_instance()->app_modules->get(BANNER_MODULE)['headers']['uri'] ?? 'banner') . '-' . trim(preg_replace(['#/admin.*#', '#https?://#', '/[^a-zA-Z0-9]+/'], ['', '', '-'], current_full_url()), '-');
        @write_file(TEMP_FOLDER . $url . '.lic', '');
    }
});
