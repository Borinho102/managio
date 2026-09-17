<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Email process diagnostics — suites used by Settings → Email → Diagnostics tab.
 */

if (! function_exists('managio_email_diagnostic_processes')) {
    /**
     * @return array<int, array{id:string,label:string,description:string,kind:string,slugs?:array<int,string>}>
     */
    function managio_email_diagnostic_processes()
    {
        $processes = [
            [
                'id'          => 'smtp_raw',
                'label'       => _l('email_diag_smtp_raw'),
                'description' => _l('email_diag_smtp_raw_desc'),
                'kind'        => 'smtp',
            ],
            [
                'id'          => 'simple_pipeline',
                'label'       => _l('email_diag_simple'),
                'description' => _l('email_diag_simple_desc'),
                'kind'        => 'simple',
            ],
            [
                'id'          => 'invoice',
                'label'       => _l('email_diag_invoice'),
                'description' => _l('email_diag_invoice_desc'),
                'kind'        => 'template',
                'slugs'       => [
                    'invoice-send-to-client',
                    'invoice-payment-recorded',
                    'invoice-overdue-notice',
                    'invoice-payment-recorded-to-staff',
                ],
            ],
            [
                'id'          => 'estimate',
                'label'       => _l('email_diag_estimate'),
                'description' => _l('email_diag_estimate_desc'),
                'kind'        => 'template',
                'slugs'       => [
                    'estimate-send-to-client',
                    'estimate-accepted-to-staff',
                    'estimate-declined-to-staff',
                    'estimate-expiry-reminder',
                ],
            ],
            [
                'id'          => 'proposal',
                'label'       => _l('email_diag_proposal'),
                'description' => _l('email_diag_proposal_desc'),
                'kind'        => 'template',
                'slugs'       => [
                    'proposal-send-to-customer',
                    'proposal-client-accepted',
                    'proposal-client-declined',
                    'proposal-comment-to-admin',
                ],
            ],
            [
                'id'          => 'contract',
                'label'       => _l('email_diag_contract'),
                'description' => _l('email_diag_contract_desc'),
                'kind'        => 'template',
                'slugs'       => [
                    'send-contract',
                    'contract-expiration',
                    'contract-signed-to-staff',
                    'contract-comment-to-admin',
                ],
            ],
            [
                'id'          => 'credit_note',
                'label'       => _l('email_diag_credit_note'),
                'description' => _l('email_diag_credit_note_desc'),
                'kind'        => 'template',
                'slugs'       => ['credit-note-send-to-client'],
            ],
            [
                'id'          => 'subscription',
                'label'       => _l('email_diag_subscription'),
                'description' => _l('email_diag_subscription_desc'),
                'kind'        => 'template',
                'slugs'       => [
                    'send-subscription',
                    'subscription-payment-succeeded',
                    'subscription-payment-failed',
                    'subscription-canceled',
                    'customer-subscribed-to-staff',
                ],
            ],
            [
                'id'          => 'ticket',
                'label'       => _l('email_diag_ticket'),
                'description' => _l('email_diag_ticket_desc'),
                'kind'        => 'template',
                'slugs'       => [
                    'new-ticket-opened-admin',
                    'new-ticket-created-staff',
                    'ticket-reply',
                    'ticket-reply-to-admin',
                    'ticket-assigned-to-admin',
                    'auto-close-ticket',
                ],
            ],
            [
                'id'          => 'project',
                'label'       => _l('email_diag_project'),
                'description' => _l('email_diag_project_desc'),
                'kind'        => 'template',
                'slugs'       => [
                    'assigned-to-project',
                    'staff-added-as-project-member',
                    'new-project-discussion-created-to-staff',
                    'new-project-discussion-created-to-customer',
                    'project-finished-to-customer',
                ],
            ],
            [
                'id'          => 'task',
                'label'       => _l('email_diag_task'),
                'description' => _l('email_diag_task_desc'),
                'kind'        => 'template',
                'slugs'       => [
                    'task-assigned',
                    'task-commented',
                    'task-deadline-notification',
                    'task-status-change-to-staff',
                    'task-status-change-to-contacts',
                ],
            ],
            [
                'id'          => 'client',
                'label'       => _l('email_diag_client'),
                'description' => _l('email_diag_client_desc'),
                'kind'        => 'template',
                'slugs'       => [
                    'new-client-created',
                    'client-statement',
                    'contact-forgot-password',
                    'contact-set-password',
                    'client-registration-confirmed',
                    'contact-verification-email',
                ],
            ],
            [
                'id'          => 'staff',
                'label'       => _l('email_diag_staff'),
                'description' => _l('email_diag_staff_desc'),
                'kind'        => 'template',
                'slugs'       => [
                    'new-staff-created',
                    'staff-forgot-password',
                    'staff-password-reseted',
                    'two-factor-authentication',
                    'reminder-email-staff',
                    'new-client-registered-to-admin',
                    'new-lead-assigned',
                ],
            ],
            [
                'id'          => 'gdpr',
                'label'       => _l('email_diag_gdpr'),
                'description' => _l('email_diag_gdpr_desc'),
                'kind'        => 'template',
                'slugs'       => [
                    'gdpr-removal-request',
                    'gdpr-removal-request-lead',
                ],
            ],
        ];

        return hooks()->apply_filters('managio_email_diagnostic_processes', $processes);
    }
}

if (! function_exists('managio_email_diagnostic_dummy_merge_fields')) {
    /**
     * @return array<string, string>
     */
    function managio_email_diagnostic_dummy_merge_fields()
    {
        $CI = &get_instance();
        $merge = [];

        try {
            $CI->load->library('merge_fields/other_merge_fields');
            $merge = $CI->other_merge_fields->format();
            if (! is_array($merge)) {
                $merge = [];
            }
        } catch (Throwable $e) {
            $merge = [];
        }

        $company = get_option('companyname') ?: 'Managio';
        $dummy = [
            '{contact_firstname}'       => 'Test',
            '{contact_lastname}'        => 'User',
            '{contact_email}'           => 'test@example.com',
            '{client_company}'          => $company,
            '{client_id}'               => '1',
            '{invoice_number}'          => 'INV-TEST-001',
            '{invoice_link}'            => admin_url(),
            '{invoice_duedate}'         => date('Y-m-d', strtotime('+7 days')),
            '{invoice_total}'           => '10 000',
            '{payment_total}'           => '10 000',
            '{payment_date}'            => date('Y-m-d'),
            '{estimate_number}'         => 'EST-TEST-001',
            '{estimate_link}'           => admin_url(),
            '{estimate_expirydate}'     => date('Y-m-d', strtotime('+14 days')),
            '{proposal_number}'         => 'PROP-TEST-001',
            '{proposal_link}'           => admin_url(),
            '{proposal_subject}'        => 'Diagnostic proposal',
            '{contract_subject}'        => 'Diagnostic contract',
            '{contract_link}'           => admin_url(),
            '{contract_datestart}'      => date('Y-m-d'),
            '{credit_note_number}'      => 'CN-TEST-001',
            '{credit_note_link}'        => admin_url(),
            '{subscription_name}'       => 'Diagnostic subscription',
            '{subscription_link}'       => admin_url(),
            '{subscription_id}'         => '1',
            '{ticket_id}'               => '1',
            '{ticket_subject}'          => 'Diagnostic ticket',
            '{ticket_message}'          => 'This is an automated email diagnostics message.',
            '{ticket_department}'       => 'Support',
            '{ticket_status}'           => 'Open',
            '{ticket_priority}'         => 'Normal',
            '{ticket_url}'              => admin_url(),
            '{project_name}'            => 'Diagnostic project',
            '{project_link}'            => admin_url(),
            '{project_id}'              => '1',
            '{discussion_link}'         => admin_url(),
            '{discussion_subject}'      => 'Diagnostic discussion',
            '{discussion_description}'  => 'Automated diagnostics',
            '{task_name}'               => 'Diagnostic task',
            '{task_link}'               => admin_url(),
            '{task_priority}'           => 'Medium',
            '{task_startdate}'          => date('Y-m-d'),
            '{task_duedate}'            => date('Y-m-d', strtotime('+3 days')),
            '{staff_firstname}'         => 'Admin',
            '{staff_lastname}'          => 'Test',
            '{staff_email}'             => get_option('smtp_email') ?: 'admin@example.com',
            '{password_reset_url}'      => admin_url(),
            '{set_password_url}'        => admin_url(),
            '{email_signature}'         => get_option('email_signature') ?: $company,
            '{companyname}'             => $company,
            '{crm_url}'                 => site_url(),
            '{admin_url}'               => admin_url(),
            '{lead_name}'               => 'Diagnostic Lead',
            '{lead_email}'              => 'lead@example.com',
            '{lead_link}'               => admin_url(),
            '{event_title}'             => 'Diagnostic event',
            '{event_description}'       => 'Automated diagnostics',
            '{two_factor_auth_code}'    => '123456',
            '{statement_from}'          => date('Y-m-01'),
            '{statement_to}'            => date('Y-m-d'),
            '{statement_balance_due}'   => '0',
        ];

        return array_merge($dummy, $merge);
    }
}

if (! function_exists('managio_run_email_diagnostic_process')) {
    /**
     * @param array  $process
     * @param string $toEmail
     * @param bool   $sendAllSlugs When true, send every slug in the process; otherwise first available active slug.
     *
     * @return array{id:string,label:string,ok:bool,details:array<int,array{slug?:string,status:string,message:string}>}
     */
    function managio_run_email_diagnostic_process(array $process, $toEmail, $sendAllSlugs = false)
    {
        $CI = &get_instance();
        $CI->load->model('emails_model');
        $CI->load->config('email');

        $result = [
            'id'      => $process['id'],
            'label'   => $process['label'],
            'ok'      => false,
            'details' => [],
        ];

        $kind = $process['kind'] ?? '';

        if ($kind === 'smtp') {
            try {
                $template           = new StdClass();
                $template->message  = get_option('email_header')
                    . '<p><strong>[Managio Email Diagnostics]</strong></p>'
                    . '<p>SMTP raw pipeline OK.</p>'
                    . get_option('email_footer');
                $template->fromname = get_option('companyname') != '' ? get_option('companyname') : 'Managio';
                $template->subject  = '[Diag] SMTP raw test';
                $template           = parse_email_template($template);

                $CI->email->initialize();
                $CI->email->set_newline(config_item('newline'));
                $CI->email->set_crlf(config_item('crlf'));
                $CI->email->from(get_option('smtp_email'), $template->fromname);
                $CI->email->to($toEmail);
                $bcc = get_option('bcc_emails');
                if ($bcc != '') {
                    $CI->email->bcc($bcc);
                }
                $CI->email->subject($template->subject);
                $CI->email->message($template->message);

                if ($CI->email->send(true)) {
                    $result['ok']        = true;
                    $result['details'][] = ['status' => 'success', 'message' => _l('email_diag_sent_ok')];
                } else {
                    $result['details'][] = [
                        'status'  => 'error',
                        'message' => strip_tags($CI->email->print_debugger()),
                    ];
                }
            } catch (Throwable $e) {
                $result['details'][] = ['status' => 'error', 'message' => $e->getMessage()];
            }

            return $result;
        }

        if ($kind === 'simple') {
            try {
                $ok = $CI->emails_model->send_simple_email(
                    $toEmail,
                    '[Diag] Simple email pipeline',
                    '<p><strong>[Managio Email Diagnostics]</strong></p>'
                    . '<p>send_simple_email() pipeline OK.</p>'
                );
                $result['ok']        = (bool) $ok;
                $result['details'][] = [
                    'status'  => $ok ? 'success' : 'error',
                    'message' => $ok ? _l('email_diag_sent_ok') : _l('email_diag_send_failed'),
                ];
            } catch (Throwable $e) {
                $result['details'][] = ['status' => 'error', 'message' => $e->getMessage()];
            }

            return $result;
        }

        if ($kind === 'template') {
            $slugs = $process['slugs'] ?? [];
            $merge = managio_email_diagnostic_dummy_merge_fields();
            $sent  = 0;
            $checked = 0;

            foreach ($slugs as $slug) {
                $checked++;
                $CI->db->where('slug', $slug);
                $CI->db->where('language', 'english');
                $tpl = $CI->db->get(db_prefix() . 'emailtemplates')->row();

                if (! $tpl) {
                    $result['details'][] = [
                        'slug'    => $slug,
                        'status'  => 'skip',
                        'message' => _l('email_diag_template_missing'),
                    ];
                    if (! $sendAllSlugs) {
                        continue;
                    }
                    continue;
                }

                if ((int) $tpl->active !== 1) {
                    $result['details'][] = [
                        'slug'    => $slug,
                        'status'  => 'skip',
                        'message' => _l('email_diag_template_disabled'),
                    ];
                    if (! $sendAllSlugs) {
                        continue;
                    }
                    continue;
                }

                try {
                    $ok = $CI->emails_model->send_email_template($slug, $toEmail, $merge);

                    if ($ok) {
                        $sent++;
                        $result['details'][] = [
                            'slug'    => $slug,
                            'status'  => 'success',
                            'message' => _l('email_diag_sent_ok'),
                        ];
                    } else {
                        $result['details'][] = [
                            'slug'    => $slug,
                            'status'  => 'error',
                            'message' => _l('email_diag_send_failed'),
                        ];
                    }
                } catch (Throwable $e) {
                    $result['details'][] = [
                        'slug'    => $slug,
                        'status'  => 'error',
                        'message' => $e->getMessage(),
                    ];
                }

                // Default mode: one successful live send per process is enough.
                if (! $sendAllSlugs && $sent > 0) {
                    break;
                }
            }

            $result['ok'] = $sent > 0;

            if ($checked === 0) {
                $result['details'][] = ['status' => 'error', 'message' => _l('email_diag_no_slugs')];
            } elseif ($sent === 0 && empty($result['details'])) {
                $result['details'][] = ['status' => 'error', 'message' => _l('email_diag_send_failed')];
            }

            return $result;
        }

        $result['details'][] = ['status' => 'error', 'message' => _l('email_diag_unknown_kind')];

        return $result;
    }
}
