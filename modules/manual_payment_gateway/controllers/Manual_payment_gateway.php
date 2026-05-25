<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Manual_payment_gateway extends AdminController
{
    public function __construct()
    {
        parent::__construct();
        $this->load->model('manual_payment_gateway_model');
    }

    public function index()
    {
        if (!staff_can('view',MANUAL_PAYMENT_GATEWAY_MODULE_NAME))
        {
            access_denied();
        }

        $data['title'] = _l('manual_payment_gateway');
        $data['table_data'] = $this->table();
        $this->load->view('admin/index', $data);
    }

    public function table()
    {
        $draw = intval($this->input->post('draw') ?? 1);

        $result = $this->manual_payment_gateway_model->get_all_gateways();

        $data = [];
        foreach ($result as $key => $aRow)
        {
            $toggleActive = '<div class="onoffswitch" data-toggle="tooltip" data-title="' . _l('customer_active_inactive_help') . '">
                <input type="checkbox"' . (staff_can('edit','manual_payment_gateway') ? '' : 'disabled') . ' data-switch-url="' . admin_url() . 'manual_payment_gateway/change_status" name="onoffswitch" class="onoffswitch-checkbox" id="' . $aRow->id . '" data-id="' . $aRow->id . '" ' . ($aRow->status == 1 ? 'checked' : '') . '>
                <label class="onoffswitch-label" for="' . $aRow->id . '"></label>
                </div>';

            $name = $aRow->name;

            $actionBtn = '';
            if (staff_can('edit', MANUAL_PAYMENT_GATEWAY_MODULE_NAME))
            {
                $actionBtn .= '<a href="' . admin_url('manual_payment_gateway/edit/' . $aRow->id) . '"'
                    . ' class="btn btn-default btn-sm mr-2" title="' . _l('edit') . '"><i class="fa fa-edit"></i></a>';
            }

            if (staff_can('delete', MANUAL_PAYMENT_GATEWAY_MODULE_NAME))
            {
                $actionBtn .= '<a href="' . admin_url('manual_payment_gateway/delete/' . $aRow->id) . '"'
                    . ' class="btn btn-default btn-sm mr-2 _delete" title="' . _l('delete') . '"><i class="fa fa-trash-alt"></i></a>';
            }

            $data[] = [
                'sr_no'        => $key + 1,
                'name'      => $name,
                'status'         => $toggleActive,
                'actions'      => $actionBtn,
            ];
        }

        return $data;
    }

    public function change_status($id, $status)
    {
        if (!staff_can('edit',MANUAL_PAYMENT_GATEWAY_MODULE_NAME))
        {
            access_denied();
        }
        if ($this->input->is_ajax_request()) {
            $this->manual_payment_gateway_model->change_status($id, $status);
        }
    }

    public function create()
    {
        if (!staff_can('create',MANUAL_PAYMENT_GATEWAY_MODULE_NAME))
        {
            access_denied();
        }
        if ($this->input->post())
        {
            $data = [];
            $data['name'] = $this->input->post('name');
            $data['description'] = html_purify($this->input->post('description', false));
            $labels = $this->input->post('label');

            $formFields = [];
            if(!empty($labels))
            {
                foreach ($labels as $key => $value)
                {
                    $slug = self::str_slug($labels[$key]);
                    $name = self::str_slug($labels[$key],'_');
                    $type = $this->input->post('type')[$key];
                    $require = $this->input->post('require')[$key];
                    $width = $this->input->post('width')[$key];

                    $formFields[$slug] =
                    [
                        'label' => $value,
                        'type' => $type,
                        'required' => $require,
                        'width' => $width,
                        'name' => $name
                    ];
                }
            }

            $data['form_fields'] = !empty($formFields) ? json_encode($formFields) : null;
            $data['status'] = 1;

            $this->manual_payment_gateway_model->insert_data($data);

            set_alert('success', _l('mpg_gateway_added_successfully'));
            redirect(admin_url('manual_payment_gateway'));
        }

        $data['title'] = _l('manual_payment_gateway');
        $this->app_css->add('mpg-css', module_dir_url('manual_payment_gateway', 'assets/css/style.css'));
        $this->load->view('admin/create',$data);
    }

    public function edit($id)
    {
        if (!staff_can('edit',MANUAL_PAYMENT_GATEWAY_MODULE_NAME))
        {
            access_denied();
        }

        if ($this->input->post())
        {
            $data = [];
            $data['name'] = $this->input->post('name');
            $data['description'] = html_purify($this->input->post('description', false));
            $labels = $this->input->post('label');

            $formFields = [];
            if(!empty($labels))
            {
                foreach ($labels as $key => $value)
                {
                    $slug = self::str_slug($labels[$key]);
                    $name = self::str_slug($labels[$key],'_');
                    $type = $this->input->post('type')[$key];
                    $require = $this->input->post('require')[$key];
                    $width = $this->input->post('width')[$key];

                    $formFields[$slug] =
                        [
                            'label' => $value,
                            'type' => $type,
                            'required' => $require,
                            'width' => $width,
                            'name' => $name
                        ];
                }
            }

            $data['form_fields'] = !empty($formFields) ? json_encode($formFields) : null;
            $data['status'] = 1;

            $this->manual_payment_gateway_model->update_data($id,$data);

            set_alert('success', _l('mpg_gateway_updated_successfully'));
            redirect(admin_url('manual_payment_gateway'));
        }

        $data['title'] = _l('manual_payment_gateway');
        $data['manual_payment_gateway'] = $this->manual_payment_gateway_model->get($id);
        $formFields = !empty($data['manual_payment_gateway']->form_fields) ? json_decode($data['manual_payment_gateway']->form_fields,true) : [];
        $data['form_fields'] = $formFields;
        $this->app_css->add('mpg-css', module_dir_url('manual_payment_gateway', 'assets/css/style.css'));
        $this->load->view('admin/edit',$data);
    }

    public static function str_slug($string,$separator='-')
    {
        return str_replace(' ', $separator, strtolower(trim($string)));
    }

    public function delete($id)
    {
        if (!staff_can('delete',MANUAL_PAYMENT_GATEWAY_MODULE_NAME))
        {
            access_denied();
        }

        $this->manual_payment_gateway_model->delete($id);
        set_alert('success', _l('deleted', _l('mpg_gateway_deleted')));
        redirect(admin_url('manual_payment_gateway'));
    }

    public function payment_requests($id = null)
    {
        $data = [];
        $data['title'] = _l('payment_requests');

        if($id)
        {
            $requestDetails = $this->manual_payment_gateway_model->get_request($id);
            $invoice = $this->invoices_model->get($requestDetails->invoice_id);

            if($this->input->post())
            {
                $status = $this->input->post('status');
                if(isset($status))
                {
                    if($status == 0)
                    {
                        $updateData = [
                            'status' => $status,
                            'message' => $this->input->post('message') ?? null,
                        ];
                        $this->manual_payment_gateway_model->update_request($updateData,$id);

                        set_alert('success', _l('mpg_payment_rejected_message'));
                        redirect(admin_url('manual_payment_gateway/payment_requests/' . $id));
                    }
                    else
                    {
                        $updateData = [
                            'status' => $status,
                            'message' => $this->input->post('message') ?? null,
                        ];
                        $this->manual_payment_gateway_model->update_request($updateData,$id);

                        $gateway = new App_gateway();

                        $data = [
                            'amount'        => $requestDetails->amount,
                            'invoiceid'     => $requestDetails->invoice_id,
                            'transactionid' => '',
                            'paymentmethod' => $requestDetails->gateway_name,
                        ];

                        $success = $gateway->addPayment($data);

                        set_alert('success', _l('mpg_payment_approved_message'));
                        redirect(admin_url('manual_payment_gateway/payment_requests/' . $id));
                    }
                }
                else
                {
                    set_alert('danger', _l('mpg_status_required'));
                    redirect(admin_url('manual_payment_gateway/payment_requests/' . $id));
                }
            }

            $formData =  json_decode($requestDetails->details,true);
            $formFields = !empty($requestDetails->details) && count($formData) > 0 ? $formData : [];

            $data['form_data'] = $formFields;
            $data['request_data'] = $requestDetails;

            $created_at = date('d F Y h:ia', strtotime($requestDetails->created_at));
            $data['created_at'] = strtoupper(preg_replace('/(\d)(am|pm)/i', '$1 $2', $created_at));

            $data['invoice_id'] = format_invoice_number($requestDetails->invoice_id);
            $data['invoice_amount'] = app_format_money($requestDetails->amount, $invoice->currency_name);

            $contact = $this->clients_model->get_contact($requestDetails->user_id);
            $client  = $contact ? $this->clients_model->get($contact->userid) : null;
            $data['customer_name'] = $contact->firstname . ' ' . $contact->lastname . ' (' . $client->company . ')';

            if($requestDetails->status == 1)
            {
                $data['status'] = '<span class="label label-success">'._l('mpg_payment_approved').'</span>';
            }
            elseif($requestDetails->status == 2)
            {
                $data['status'] = '<span class="label label-info">'._l('mpg_payment_pending').'</span>';
            }
            else
            {
                $data['status'] = '<span class="label label-danger">'._l('mpg_payment_rejected').'</span>';
            }

            $data['mpg_files'] = [];
            $this->load->view('admin/payment_request_details', $data);
        }
        else
        {
            if (!staff_can('view_requests', MANUAL_PAYMENT_GATEWAY_MODULE_NAME))
            {
                access_denied();
            }
            $data['table_data'] = $this->payment_requests_table();
            $this->load->view('admin/payment_requests', $data);
        }
    }

    public function payment_requests_table()
    {
        $draw = intval($this->input->post('draw') ?? 1);

        $result = $this->manual_payment_gateway_model->get_all_requests();

        $data = [];
        $i = 0;
        foreach ($result as $key => $aRow)
        {
            if($aRow->status == 2)
            {
                $i++;
                $this->load->model('invoices_model');
                $invoice = $this->invoices_model->get($aRow->invoice_id);

                $contact = $this->clients_model->get_contact($aRow->user_id);
                $client = $contact ? $this->clients_model->get($contact->userid) : null;
                $client_name = $contact->firstname . ' ' . $contact->lastname . ' (' . $client->company . ')';

                $gateway_name = $aRow->gateway_name;
                $invoice_id = format_invoice_number($aRow->invoice_id);
                $invoice_amount = app_format_money($aRow->amount, $invoice->currency_name);

                $created_at = date('d F Y h:ia', strtotime($aRow->created_at));
                $created_at = strtoupper(preg_replace('/(\d)(am|pm)/i', '$1 $2', $created_at));

                $actionBtn = '<a href="' . admin_url('manual_payment_gateway/payment_requests/' . $aRow->id) . '"'
                    . ' class="btn btn-default btn-sm">'
                    . _l('view') . '</a>';

                $data[] = [
                    'sr_no'        => $i,
                    'invoice_id'      => $invoice_id,
                    'client_name'         => $client_name,
                    'name'      => $gateway_name,
                    'invoice_amount'   => $invoice_amount,
                    'created_at'      => $created_at,
                    'action'      => $actionBtn,
                ];
            }
        }

        return $data;
    }

    public function payment_log_table()
    {
        $draw = intval($this->input->post('draw') ?? 1);

        $result = $this->manual_payment_gateway_model->get_all_requests();

        $data = [];
        $i = 0;
        foreach ($result as $key => $aRow)
        {
            if($aRow->status != 2)
            {
                $i++;
                $this->load->model('invoices_model');
                $invoice = $this->invoices_model->get($aRow->invoice_id);

                $contact = $this->clients_model->get_contact($aRow->user_id);
                $client = $contact ? $this->clients_model->get($contact->userid) : null;
                $client_name = $contact->firstname . ' ' . $contact->lastname . ' (' . $client->company . ')';

                $gateway_name = $aRow->gateway_name;
                $invoice_id = format_invoice_number($aRow->invoice_id);
                $invoice_amount = app_format_money($aRow->amount, $invoice->currency_name);

                if ($aRow->status == 1) {
                    $status = '<span class="label label-success">' . _l('mpg_payment_approved') . '</span>';
                } elseif ($aRow->status == 2) {
                    $status = '<span class="label label-info">' . _l('mpg_payment_pending') . '</span>';
                } else {
                    $status = '<span class="label label-danger">' . _l('mpg_payment_rejected') . '</span>';
                }

                $created_at = date('d F Y h:ia', strtotime($aRow->created_at));
                $created_at = strtoupper(preg_replace('/(\d)(am|pm)/i', '$1 $2', $created_at));

                $actionBtn = '<a href="' . admin_url('manual_payment_gateway/payment_requests/' . $aRow->id) . '"'
                    . ' class="btn btn-default btn-sm">'
                    . _l('view') . '</a>';

                $data[] = [
                    'sr_no'        => $i,
                    'invoice_id'      => $invoice_id,
                    'client_name'         => $client_name,
                    'name'      => $gateway_name,
                    'invoice_amount'   => $invoice_amount,
                    'status'   => $status,
                    'created_at'      => $created_at,
                    'action'      => $actionBtn,
                ];
            }
        }

        return $data;
    }

    public function payment_logs()
    {
        if (!staff_can('view_log', MANUAL_PAYMENT_GATEWAY_MODULE_NAME)) {
            access_denied();
        }

        $data = [];
        $data['title'] = _l('payment_requests');
        $data['table_data'] = $this->payment_log_table();
        $this->load->view('admin/payment_log', $data);
    }
}
