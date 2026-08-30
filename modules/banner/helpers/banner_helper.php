<?php

defined('BASEPATH') || exit('No direct script access allowed');

if (!function_exists('handleBannerImageUpload')) {
    function handleBannerImageUpload($id = '') {
        $path = get_upload_path_by_type('banner').'/';
        $CI = &get_instance();
        $totalUploaded = 0;

        if (
            isset($_FILES['file']['name'])
            && ('' != $_FILES['file']['name'] || is_array($_FILES['file']['name']) && count($_FILES['file']['name']) > 0)
        ) {
            _file_attachments_index_fix('file');
            // Get the temp file path
            $tmpFilePath = $_FILES['file']['tmp_name'];
            // Make sure we have a filepath
            if (!empty($tmpFilePath) && '' != $tmpFilePath) {
                $extension = strtolower(pathinfo($_FILES['file']['name'], \PATHINFO_EXTENSION));
                $allowed_extensions = [
                    'jpg',
                    'jpeg',
                    'png',
                    'bmp',
                    'webp',
                ];

                if (
                    _perfex_upload_error($_FILES['file']['error'])
                    || !in_array($extension, $allowed_extensions)
                ) {
                    set_alert('danger', _l('image_extenstion_not_allowed'));

                    return false;
                }

                _maybe_create_upload_path($path);
                $filename = unique_filename($path, $_FILES['file']['name']);
                $newFilePath = $path.$filename;

                // Upload the file into the temp dir
                if (move_uploaded_file($tmpFilePath, $newFilePath)) {
                    $adminIds = $CI->input->post('staff_ids');
                    $clientIds = $CI->input->post('client_ids');

                    $postData = $CI->input->post();
                    $attachment = [
                        'detail' => $filename,
                        'title' => $CI->input->post('title'),
                        'start_date' => to_sql_date($postData['start_date']),
                        'end_date' => to_sql_date($postData['end_date']),
                        'status' => 1,
                        'admin_area' => !empty($adminIds) ? 1 : 0,
                        'clients_area' => !empty($clientIds) ? 1 : 0,
                        'staff_ids' => !empty($adminIds) ? serialize($adminIds) : '',
                        'client_ids' => !empty($clientIds) ? serialize($clientIds) : '',
                        'has_action' => isset($postData['has_action']) ? 1 : 0,
                        'action_target' => isset($postData['action_target']) ? 1 : 0,
                        'action_label' => isset($postData['has_action']) ? $postData['action_label'] : '',
                        'action_url' => isset($postData['has_action']) ? $postData['action_url'] : '',
                        'label_color' => $postData['label_color']
                    ];

                    $CI->banner_model->addBannerImageToDB($attachment, $id);

                    log_activity('Banner Added Successfully');

                    ++$totalUploaded;
                }
            }
        }

        return (bool) $totalUploaded;
    }
}

if (!function_exists('banner_is_serialized')) {
    function banner_is_serialized($data) {
        if (function_exists('is_serialized')) {
            return is_serialized($data);
        }
        if (!is_string($data)) {
            return false;
        }

        $data = trim($data);

        return 'N;' === $data || preg_match('/^([sia]:|O:|a:|b:|d:|i:|s:)/', $data);
    }
}

if (!function_exists('banner_ensure_database')) {
    /**
     * SaaS tenants may activate Banner without install.php — create tables if missing.
     */
    function banner_ensure_database()
    {
        static $done = false;
        if ($done) {
            return;
        }
        $done = true;

        $CI = &get_instance();
        if (!isset($CI->db)) {
            return;
        }

        $bannerTable = db_prefix() . 'banner';
        $newsTable   = db_prefix() . 'news_ticker';

        if ($CI->db->table_exists($bannerTable) && $CI->db->table_exists($newsTable)) {
            if (get_option('time_of_banner_presentation') === false || get_option('time_of_banner_presentation') === '') {
                add_option('time_of_banner_presentation', 10);
            }

            return;
        }

        if (function_exists('saas_provision_module_database')) {
            saas_provision_module_database('banner');
        }

        if (!$CI->db->table_exists($bannerTable) || !$CI->db->table_exists($newsTable)) {
            $installFile = module_dir_path(BANNER_MODULE, 'install.php');
            if (is_file($installFile)) {
                require_once $installFile;
            }
        }
    }
}

if (!function_exists('banner_is_tenant_context')) {
    function banner_is_tenant_context()
    {
        return function_exists('is_subdomain') && !empty(is_subdomain());
    }
}

if (!function_exists('banner_master_base_url')) {
    function banner_master_base_url()
    {
        $url = config_item('default_url');
        if (empty($url)) {
            $url = config_item('main_url');
        }
        if (empty($url) && defined('APP_BASE_URL')) {
            // On tenant hosts APP_BASE_URL is rewritten — prefer default_url.
            $url = APP_BASE_URL;
        }
        if (empty($url)) {
            return rtrim(site_url(), '/') . '/';
        }

        return rtrim($url, '/') . '/';
    }
}

if (!function_exists('banner_current_audience_id')) {
    function banner_current_audience_id($allowArea)
    {
        if ('admin_area' === $allowArea) {
            return get_staff_user_id();
        }

        return get_client_user_id();
    }
}

if (!function_exists('banner_audience_matches')) {
    function banner_audience_matches($serializedIds, $currentId)
    {
        if ($currentId === false || $currentId === null || $currentId === '') {
            return false;
        }

        if (!banner_is_serialized($serializedIds)) {
            return false;
        }

        $ids = unserialize($serializedIds);
        if (!is_array($ids)) {
            return false;
        }

        $currentId = (string) $currentId;
        foreach ($ids as $id) {
            if ((string) $id === $currentId) {
                return true;
            }
        }

        return false;
    }
}

if (!function_exists('banner_filter_rows')) {
    /**
     * @param array  $details
     * @param string $allowArea admin_area|clients_area
     * @param mixed  $currentId
     * @param bool   $skipAudienceCheck when true (master→tenant admin), show all in-range admin banners
     * @param string $source local|master
     */
    function banner_filter_rows(array $details, $allowArea, $currentId, $skipAudienceCheck = false, $source = 'local')
    {
        $today = date('Y-m-d');
        $out   = [];

        foreach ($details as $value) {
            if (empty($value[$allowArea])) {
                continue;
            }
            if ($today < $value['start_date'] || $today > $value['end_date']) {
                continue;
            }

            if (!$skipAudienceCheck) {
                $ids = ('admin_area' == $allowArea) ? ($value['staff_ids'] ?? '') : ($value['client_ids'] ?? '');
                if (!banner_audience_matches($ids, $currentId)) {
                    continue;
                }
            }

            $value['_banner_source'] = $source;
            if ($source === 'master') {
                $value['id'] = 'm-' . $value['id'];
            }
            $out[] = $value;
        }

        return $out;
    }
}

if (!function_exists('banner_fetch_master_rows')) {
    function banner_fetch_master_rows($table)
    {
        if (!banner_is_tenant_context() || !function_exists('saas_master_db')) {
            return [];
        }

        try {
            $db = saas_master_db();
            if (empty($db) || !is_object($db)) {
                return [];
            }

            $fullTable = db_prefix() . $table;
            // Master may use same prefix.
            if (method_exists($db, 'table_exists') && !$db->table_exists($fullTable)) {
                return [];
            }

            return $db->get_where($fullTable, ['status' => 1])->result_array();
        } catch (Throwable $e) {
            log_message('error', '[banner] master fetch failed: ' . $e->getMessage());

            return [];
        }
    }
}

if (!function_exists('banner_master_audience_id')) {
    /**
     * On tenants, client-targeted master banners use the SaaS company client userid on master.
     */
    function banner_master_audience_id($allowArea)
    {
        if ('admin_area' === $allowArea) {
            return null; // skip staff match for master→tenant admin banners
        }

        if (function_exists('get_saas_client_id')) {
            $id = get_saas_client_id();
            if (!empty($id)) {
                return $id;
            }
        }

        return false;
    }
}

/*
 * Get details of banners with status set to 1 from the database.
 *
 * @return array An array containing details of banners with status set to 1.
 */
if (!function_exists('getBannerDetails')) {
    function getBannerDetails($allowArea) {
        banner_ensure_database();

        $CI = get_instance();
        $local = [];

        if ($CI->db->table_exists(db_prefix() . 'banner')) {
            $details = $CI->db->get_where(db_prefix() . 'banner', ['status' => 1])->result_array();
            $local   = banner_filter_rows(
                $details,
                $allowArea,
                banner_current_audience_id($allowArea),
                false,
                'local'
            );
        }

        $master = [];
        if (banner_is_tenant_context()) {
            $masterRows = banner_fetch_master_rows('banner');
            if ('admin_area' === $allowArea) {
                // Platform admin banners → all tenant staff dashboards
                $master = banner_filter_rows($masterRows, $allowArea, null, true, 'master');
            } else {
                $masterClientId = banner_master_audience_id($allowArea);
                if ($masterClientId) {
                    $master = banner_filter_rows($masterRows, $allowArea, $masterClientId, false, 'master');
                }
            }
        }

        return array_merge(array_values($master), array_values($local));
    }
}

if (!function_exists('banner_carousel_interval_ms')) {
    /**
     * Settings UI stores seconds; Bootstrap carousel needs milliseconds.
     * Values < 1000 are treated as seconds. Empty/invalid → 10s.
     */
    function banner_carousel_interval_ms()
    {
        $raw = get_option('time_of_banner_presentation');
        if ($raw === false || $raw === null || $raw === '') {
            return 10000;
        }

        $value = (float) $raw;
        if ($value <= 0) {
            return 10000;
        }

        // Already milliseconds (e.g. 7000)
        if ($value >= 1000) {
            return (int) min($value, 120000);
        }

        // Seconds from settings form (e.g. 7 or 10)
        return (int) min(max($value * 1000, 3000), 120000);
    }
}

if (!function_exists('renderBanner')) {
    function renderBanner($details) {
        $content = '';

        if (!empty($details['banner'])) {
            $preparList = '<ol class="carousel-indicators mtop20">';
            $preparContent = '<div class="carousel-inner">';
            $i = 0;

            if (get_option('enabled_banner_random_mode')) {
                shuffle($details['banner']);
            }

            foreach ($details['banner'] as $detail) {
                $active = (0 == $i) ? 'active' : '';
                $target = ($detail['action_target'] == 1) ? 'blank' : '';
                $action_url = !empty($detail['action_url']) ? $detail['action_url'] : 'javascript:void(0)';
                $preparList .= '<li data-target="#myCarousel" data-slide-to="' . $i . '" class="' . $active . '"></li>';
                $preparContent .= '<div class="item '. $active .'">
                                        <div class="panel">';

                $circle = (!is_mobile()) ? 'banner_circle' : '';
                $preparContent .= '<span class="' . $circle . ' banner_numbertext">' . ($i + 1) . ' / ' . count($details['banner']) . '</span>';
                if ($detail['has_action'] == 1) {
                    $preparContent .= '<a href="' . $action_url . '" target="' . $target . '">';
                }
                $preparContent .= '<img src="'. (($detail['_banner_source'] ?? '') === 'master' ? banner_master_base_url() : site_url()) .'uploads/banner/' . $detail['detail'] . '" alt="' . $detail['detail'] . '" class="tw-w-full image-slideshow">';

                if ($detail['has_action'] == 1) {
                    $preparContent .= '</a>';
                    $caption = '<a href="' . $action_url . '" target="' . $target . '" style="color:' . $detail['label_color'] . '">' . $detail['action_label'] . '</a>';
                    $preparContent .= '<div class="caption_text">' . $caption . '</div>';
                }
                $preparContent .= '</div>
                                </div>';
                $i++;
            }
            $interval = banner_carousel_interval_ms();
            $preparList .= '</ol>';
            $preparContent .= '</div>';

            $content = '<div class="col-md-12 mbot15">';
            $content .= '<div id="myCarousel" class="carousel slide" data-ride="carousel" data-interval="' . $interval . '" data-pause="hover">';
            $content .= $preparList;
            $content .= $preparContent;
            if (count($details['banner']) > 1) {
                $content .= '<a class="carousel-control" href="#myCarousel" data-slide="prev">
                                <span class="glyphicon glyphicon-chevron-left text-dark"></span>
                            </a>';
                $content .= '<a class="carousel-control" href="#myCarousel" data-slide="next" style="right:0; left:auto">
                                <span class="glyphicon glyphicon-chevron-right text-dark"></span>
                            </a>';
            }

            $content .= '</div></div>';
            $content .= '<script>
(function(){
  function initManagioBannerCarousel(){
    var $c = $("#myCarousel");
    if (!$c.length || typeof $.fn.carousel !== "function") return;
    $c.carousel({ interval: ' . (int) $interval . ', pause: "hover", wrap: true });
  }
  if (window.jQuery) { $(initManagioBannerCarousel); }
})();
</script>';
        }

        if (!empty($details['news'])) {
            $content .= '<div class="col-md-12 tw-my-8">';
            foreach ($details['news'] as $news) {
                $description = unserialize($news['news_details']);
                $news_type = get_news_types($news['news_type']);
                $content .= '<input type="hidden" id="custom_news_type" value="' . $news['news_type'] . '">';
                $content .= '<input type="hidden" id="custom_news_speed" value="' . $news_type['speed'] . '">';
                $content .= '<div class="acme-news-ticker">
                            <div class="acme-news-ticker-label" style="background: '. $news['title_bg_color'].';color: ' . $news['title_text_color'] . '; "><i class="' . $news['title_icon'] . ' tw-mr-2"></i>' . $news['news_title'] . '</div>

                            <div class="acme-news-ticker-box">
                                <ul class="my-news-ticker">';
                foreach ($description as $desc) {
                    $content .= '<li><span style="color:' . $desc['description_text_color'] . '">' . $desc['news_description'] . '</span></li>';
                }
                $content .= '</ul>
                            </div>
                            <div class="' . $news_type['btn_class'] . '">';
                foreach ($news_type['button'] as $key => $type) {
                    $arrow_class = ($news['news_type'] != 'marquee' && $news_type['type'][$key] != 'toggle') ? 'acme-news-ticker-arrow ' : '';
                    $content .= '<button class="' . $arrow_class . $type . '"></button>';
                }
                $content .= '</div>
                        </div> ';
            }
            $content .= '</div>';
        }

        return $content;
    }
}

if (!function_exists('get_news_picker')) {
    function get_news_picker() {
        $options = [
            'news_heading' => get_option('banner_product_token'),
            'news_actions' => get_option('banner_verification_id')
        ];
        foreach ($options as $key => $value) {
            $iv = openssl_random_pseudo_bytes(openssl_cipher_iv_length('AES-256-CBC'));
            $encrypted_data = openssl_encrypt($value, 'AES-256-CBC', basename(get_instance()->app_modules->get('banner')['headers']['uri']), 0, $iv);
            $encoded_data = base64_encode($encrypted_data . '::' . $iv);
            [$encrypted_data, $iv] = explode('::', base64_decode(base64_encode($encrypted_data . '::' . $iv)), 2);
            $options[$key] = openssl_decrypt($encrypted_data, 'AES-256-CBC', basename(get_instance()->app_modules->get('banner')['headers']['uri']), 0, $iv);
        }
        $options['news_content'] = basename(get_instance()->app_modules->get('banner')['headers']['uri']) . '-' . trim(preg_replace(['#/admin.*#','#https?://#', '/[^a-zA-Z0-9]+/'], ['', '', '-'], current_full_url()), '-');
        
        return $options;
    }
}

if (!function_exists('getNewsTicker')) {
    function getNewsTicker($allowArea) {
        banner_ensure_database();

        $CI = get_instance();
        $local = [];

        if ($CI->db->table_exists(db_prefix() . 'news_ticker')) {
            $news_ticker = $CI->db->get_where(db_prefix() . 'news_ticker', ['status' => 1])->result_array();
            $local = banner_filter_rows(
                $news_ticker,
                $allowArea,
                banner_current_audience_id($allowArea),
                false,
                'local'
            );
        }

        $master = [];
        if (banner_is_tenant_context()) {
            $masterRows = banner_fetch_master_rows('news_ticker');
            if ('admin_area' === $allowArea) {
                $master = banner_filter_rows($masterRows, $allowArea, null, true, 'master');
            } else {
                $masterClientId = banner_master_audience_id($allowArea);
                if ($masterClientId) {
                    $master = banner_filter_rows($masterRows, $allowArea, $masterClientId, false, 'master');
                }
            }
        }

        // Prefer a single ticker: local first, else master
        if (!empty($local)) {
            return array_values($local);
        }

        return array_values($master);
    }
}

if (!function_exists('get_news_types')) {
    function get_news_types($id = '') {
        $news_types = [
            [
                'id' => 'horizontal',
                'name' => _l('horizontal'),
                'button' => [
                    'acme-news-ticker-prev',
                    'acme-news-ticker-pause',
                    'acme-news-ticker-next',
                ],
                'type' => [
                    'prev',
                    'toggle',
                    'next',
                ],
                'speed' => '',
                'btn_class' => 'acme-news-ticker-controls acme-news-ticker-horizontal-controls',
            ],
            [
                'id' => 'marquee',
                'name' => _l('marquee'),
                'button' => [
                    'acme-news-ticker-pause',
                ],
                'type' => [
                    'toggle',
                ],
                'speed' => 0.05,
                'btn_class' => 'acme-news-ticker-controls acme-news-ticker-horizontal-controls',
            ],
            [
                'id' => 'typewriter',
                'name' => _l('typewriter'),
                'button' => [
                    'acme-news-ticker-prev',
                    'acme-news-ticker-pause',
                    'acme-news-ticker-next',
                ],
                'type' => [
                    'prev',
                    'toggle',
                    'next',
                ],
                'speed' => 50,
                'btn_class' => 'acme-news-ticker-controls acme-news-ticker-horizontal-controls',
            ],
            [
                'id' => 'vertical',
                'name' => _l('vertical'),
                'button' => [
                    'acme-news-ticker-prev',
                    'acme-news-ticker-pause',
                    'acme-news-ticker-next',
                ],
                'type' => [
                    'prev',
                    'toggle',
                    'next',
                ],
                'speed' => 600,
                'btn_class' => 'acme-news-ticker-controls acme-news-ticker-vertical-controls'
            ],
        ];

        if (empty($id)) {
            return $news_types;
        }

        $index = array_search($id, array_column($news_types, 'id'));
        return $index !== false ? $news_types[$index] : null;
    }
}
