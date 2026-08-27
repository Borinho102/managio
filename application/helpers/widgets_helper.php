<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Admin dashboard widgets
 * We are registering all widgets here
 * Also action hook is included to add new widgets if needed in my_functions_helper.php
 * @return array
 */
function get_dashboard_widgets()
{
    $widgets = [
        [
            'path'      => 'admin/dashboard/widgets/top_stats',
            'container' => 'top-12',
        ],
        [
            'path'      => 'admin/dashboard/widgets/finance_overview',
            'container' => 'left-8',
        ],
        [
            'path'      => 'admin/dashboard/widgets/user_data',
            'container' => 'left-8',
        ],
        [
            'path'      => 'admin/dashboard/widgets/upcoming_events',
            'container' => 'left-8',
        ],
        [
            'path'      => 'admin/dashboard/widgets/calendar',
            'container' => 'left-8',
        ],
        [
            'path'      => 'admin/dashboard/widgets/payments_chart',
            'container' => 'left-8',
        ],
        [
            'path'      => 'admin/dashboard/widgets/todos',
            'container' => 'right-4',
        ],
        [
            'path'      => 'admin/dashboard/widgets/leads_chart',
            'container' => 'right-4',
        ],
        [
            'path'      => 'admin/dashboard/widgets/projects_chart',
            'container' => 'right-4',
        ],
        [
            'path'      => 'admin/dashboard/widgets/tickets_chart',
            'container' => 'right-4',
        ],
        [
            'path'      => 'admin/dashboard/widgets/projects_activity',
            'container' => 'right-4',
        ],
        [
            'path'      => 'admin/dashboard/widgets/contracts_expiring',
            'container' => 'left-8',
        ],
        [
            'path'      => 'admin/dashboard/widgets/tickets_report',
            'container' => 'left-8',
        ],
    ];

    return hooks()->apply_filters('get_dashboard_widgets', $widgets);
}

/**
 * Render widgets based on container
 * The function will check if staff have re-organized the dashboard and apply any order which is needed.
 * @param  string $container
 * @return mixed
 */
function render_dashboard_widgets($container)
{
    $widgetsHtml = [];

    static $widgets     = null;
    static $widgetsData = null;

    $CI = &get_instance();

    if ($widgets === null) {
        $widgetsData = [];
        $widgets     = get_dashboard_widgets();
        if (!is_array($widgets)) {
            $widgets = [];
        }

        foreach ($widgets as $key => $widget) {
            if (empty($widget['path'])) {
                unset($widgets[$key]);
                continue;
            }

            $obLevel = ob_get_level();
            try {
                $raw = $CI->load->view($widget['path'], [], true);
            } catch (Throwable $e) {
                while (ob_get_level() > $obLevel) {
                    @ob_end_clean();
                }
                log_message('error', 'Dashboard widget failed [' . $widget['path'] . ']: ' . $e->getMessage());
                unset($widgets[$key]);
                continue;
            }

            if (!is_string($raw) || $raw === '') {
                unset($widgets[$key]);
                continue;
            }

            $htmlID = null;
            if (preg_match('/\sid\s*=\s*["\']([^"\']+)["\']/i', $raw, $m)) {
                $htmlID = $m[1];
            }
            if (empty($htmlID)) {
                $htmlID = 'widget-' . basename(str_replace('\\', '/', $widget['path']));
            }

            $settingID = (strpos($htmlID, 'widget-') === 0) ? substr($htmlID, strlen('widget-')) : $htmlID;

            $widgetsData[$htmlID] = [
                'widgetIndex'     => $key,
                'widgetPath'      => $widget['path'],
                'widgetContainer' => $widget['container'] ?? '',
                'html'            => $raw,
            ];

            $widgets[$key]['widgetID']   = $htmlID;
            $widgets[$key]['html']       = $raw;
            $widgets[$key]['settingID']  = $settingID;
        }
    }

    try {
        $staff_dashboard = get_staff_meta(get_staff_user_id(), 'dashboard_widgets_order');
        $staff_dashboard = !$staff_dashboard ? [] : @unserialize($staff_dashboard);
        if (!is_array($staff_dashboard)) {
            $staff_dashboard = [];
        }

        if (count($staff_dashboard) == 0) {
            foreach ($widgets as $widget) {
                if (!empty($widget['container']) && $widget['container'] == $container && isset($widget['html'], $widget['settingID'])) {
                    $widgetsHtml[$widget['settingID']] = $widget['html'];
                }
            }
        } else {
            if (isset($staff_dashboard[$container]) && is_array($staff_dashboard[$container])) {
                foreach ($staff_dashboard[$container] as $widget) {
                    if (isset($widgetsData[$widget])) {
                        $widgetsHtml[$widget] = $widgetsData[$widget]['html'];
                    }
                }
            }

            foreach ($widgetsData as $wID => $widget) {
                $applied = [];
                foreach ($staff_dashboard as $c => $w) {
                    if (is_array($w) && in_array($wID, $w)) {
                        $applied[] = $wID;
                    }
                }

                if ($widget['widgetContainer'] == $container && !in_array($wID, $applied)) {
                    $widgetsHtml[$wID] = $widget['html'];
                }
            }
        }

        $visibility = get_staff_meta(get_staff_user_id(), 'dashboard_widgets_visibility');
        $visibility = !$visibility ? [] : @unserialize($visibility);
        if (!is_array($visibility)) {
            $visibility = [];
        }

        foreach ($widgetsHtml as $widgetID => $widgetHTML) {
            $html = (string) $widgetHTML;
            $settingId = strpos((string) $widgetID, 'widget-') === 0
                ? substr((string) $widgetID, strlen('widget-'))
                : (string) $widgetID;

            foreach ($visibility as $option) {
                if (!is_array($option) || !isset($option['id'])) {
                    continue;
                }
                if ($option['id'] == $settingId && (string) ($option['visible'] ?? '1') === '0') {
                    if ($html !== '' && strpos($html, ' hide') === false) {
                        $html = preg_replace('/class=(["\'])([^"\']*)\1/', 'class=$1$2 hide$1', $html, 1) ?: $html;
                    }
                }
            }

            echo $html;
        }
    } catch (Throwable $e) {
        log_message('error', 'render_dashboard_widgets(' . $container . '): ' . $e->getMessage());
    }
}

/**
 * Create widget ID from the given widget file
 *
 * @param  string|null $id
 *
 * @return string
 */
function create_widget_id($id = null)
{
    $id = basename($id ? $id : debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2)[0]['file'], '.php');

    if (startsWith($id, 'my_')) {
        $id = strafter($id, 'my_');
    }

    return $id;
}
