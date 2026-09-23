<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Pwa extends App_Controller
{
    public function __construct()
    {
        parent::__construct();
    }

    public function manifest()
    {
        $name  = get_option('companyname') ?: 'Managio';
        $start = site_url('/');
        $startUrl = $start . (strpos($start, '?') !== false ? '&' : '?') . 'utm_source=pwa';
        $icon  = function ($file) {
            return site_url('assets/pwa/icons/' . $file);
        };
        $shot = function ($file) {
            return site_url('assets/pwa/screenshots/' . $file);
        };

        $icons = [];
        foreach (['72', '96', '128', '144', '152', '180', '192', '384', '512'] as $size) {
            $icons[] = [
                'src'     => $icon('icon-' . $size . '.png'),
                'sizes'   => $size . 'x' . $size,
                'type'    => 'image/png',
                'purpose' => 'any',
            ];
        }
        $icons[] = [
            'src'     => $icon('icon-maskable-512.png'),
            'sizes'   => '512x512',
            'type'    => 'image/png',
            'purpose' => 'maskable',
        ];

        $payload = [
            'id'              => $start,
            'name'            => $name,
            'short_name'      => 'Managio',
            'description'     => 'CRM, invoices, clients and operations',
            'lang'            => 'en',
            'dir'             => 'ltr',
            'start_url'       => $startUrl,
            'scope'           => site_url('/'),
            'display'         => 'standalone',
            'display_override'=> ['window-controls-overlay', 'tabbed', 'standalone', 'minimal-ui', 'browser'],
            'orientation'     => 'portrait-primary',
            'background_color'=> '#2563eb',
            'theme_color'     => '#2563eb',
            'categories'      => ['business', 'productivity'],
            'prefer_related_applications' => false,
            'related_applications' => [],
            'handle_links'    => 'preferred',
            'launch_handler'  => ['client_mode' => ['navigate-existing', 'auto']],
            'edge_side_panel' => ['preferred_width' => 420],
            'note_taking'     => ['new_note_url' => site_url('pwa/notes/new')],
            'tab_strip'       => [
                'home_tab' => [
                    'url'   => $start,
                    'icons' => [['src' => $icon('icon-192.png'), 'sizes' => '192x192']],
                ],
                'new_tab_button' => ['url' => site_url('admin')],
            ],
            'icons'           => $icons,
            'screenshots'     => [
                [
                    'src'         => $shot('narrow.png'),
                    'sizes'       => '720x1280',
                    'type'        => 'image/png',
                    'form_factor' => 'narrow',
                    'label'       => $name,
                ],
                [
                    'src'         => $shot('wide.png'),
                    'sizes'       => '1280x720',
                    'type'        => 'image/png',
                    'form_factor' => 'wide',
                    'label'       => $name,
                ],
            ],
            'shortcuts'       => [
                ['name' => 'Dashboard', 'short_name' => 'Home', 'url' => site_url('admin'), 'icons' => [['src' => $icon('icon-192.png'), 'sizes' => '192x192']]],
                ['name' => 'Clients', 'short_name' => 'Clients', 'url' => site_url('admin/clients'), 'icons' => [['src' => $icon('icon-192.png'), 'sizes' => '192x192']]],
                ['name' => 'Invoices', 'short_name' => 'Invoices', 'url' => site_url('admin/invoices'), 'icons' => [['src' => $icon('icon-192.png'), 'sizes' => '192x192']]],
                ['name' => 'Calendar', 'short_name' => 'Calendar', 'url' => site_url('admin/utilities/calendar'), 'icons' => [['src' => $icon('icon-192.png'), 'sizes' => '192x192']]],
            ],
            'share_target'    => [
                'action'  => site_url('pwa/share'),
                'method'  => 'POST',
                'enctype' => 'multipart/form-data',
                'params'  => [
                    'title' => 'title',
                    'text'  => 'text',
                    'url'   => 'url',
                    'files' => [['name' => 'media', 'accept' => ['image/*', 'application/pdf']]],
                ],
            ],
            'file_handlers'   => [[
                'action' => site_url('pwa/files'),
                'accept' => [
                    'application/pdf' => ['.pdf'],
                    'image/png'       => ['.png'],
                    'image/jpeg'      => ['.jpg', '.jpeg'],
                ],
            ]],
            'protocol_handlers' => [[
                'protocol' => 'web+managio',
                'url'      => site_url('pwa/protocol') . '?url=%s',
            ]],
            'widgets' => [[
                'name'           => $name,
                'description'    => 'Open Managio',
                'tag'            => 'managio',
                'template'       => 'managio-widget',
                'ms_ac_template' => site_url('pwa/widget.json'),
                'data'           => site_url('pwa/widget-data.json'),
                'auth'           => false,
                'update'         => 86400,
                'icons'          => [['src' => $icon('icon-192.png'), 'sizes' => '192x192']],
            ]],
        ];

        $this->output
            ->set_content_type('application/manifest+json', 'utf-8')
            ->set_header('Cache-Control: no-cache')
            ->set_output(json_encode($payload));
    }

    public function sw()
    {
        $path = FCPATH . 'assets/pwa/sw.js';
        if (!is_file($path)) {
            show_404();
        }

        $this->output
            ->set_content_type('application/javascript', 'utf-8')
            ->set_header('Service-Worker-Allowed: /')
            ->set_header('Cache-Control: no-cache')
            ->set_output(file_get_contents($path));
    }

    public function offline()
    {
        $path = FCPATH . 'assets/pwa/offline.html';
        if (!is_file($path)) {
            show_404();
        }

        $this->output
            ->set_content_type('text/html', 'utf-8')
            ->set_header('Cache-Control: no-cache')
            ->set_output(file_get_contents($path));
    }

    public function share()
    {
        $this->handoff(
            'Shared with Managio',
            'Content received',
            $this->input->post('title') ?: $this->input->post('text') ?: $this->input->post('url') ?: 'Open the dashboard to continue.',
            site_url('admin')
        );
    }

    public function files()
    {
        $this->handoff(
            'Open file | Managio',
            'File handler',
            $this->input->get('file') ?: 'Share a PDF or image into Managio.',
            site_url('admin')
        );
    }

    public function protocol()
    {
        $target = $this->input->get('url') ?: site_url('admin');
        $this->handoff('Managio link', 'Protocol handler', $target, site_url('admin'));
    }

    public function notes()
    {
        $this->handoff(
            'New note | Managio',
            'Note taking',
            'Create a note from the installed Managio app.',
            site_url('admin')
        );
    }

    public function widget()
    {
        $path = FCPATH . 'assets/pwa/widget-adaptive.json';
        if (!is_file($path)) {
            show_404();
        }

        $this->output
            ->set_content_type('application/json', 'utf-8')
            ->set_output(file_get_contents($path));
    }

    public function widget_data()
    {
        $this->output
            ->set_content_type('application/json', 'utf-8')
            ->set_output(json_encode([
                'name'    => get_option('companyname') ?: 'Managio',
                'message' => 'CRM, invoices and operations',
            ]));
    }

    public function browserconfig()
    {
        $path = FCPATH . 'assets/pwa/browserconfig.xml';
        if (!is_file($path)) {
            show_404();
        }

        $this->output
            ->set_content_type('application/xml', 'utf-8')
            ->set_output(file_get_contents($path));
    }

    protected function handoff($title, $heading, $detail, $continue)
    {
        $data = [
            'title'    => $title,
            'heading'  => $heading,
            'detail'   => $detail,
            'continue' => $continue,
        ];
        $this->load->view('pwa/handoff', $data);
    }
}
