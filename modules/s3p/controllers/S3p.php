<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * @property-read S3p_gateway $s3p_gateway
 * @property-read Invoices_model $invoices_model
 * @property-read CI_Session $session
 */
class S3p extends App_Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->load->library('s3p/S3p_gateway');
        $this->load->model('invoices_model');
    }

    /**
     * Payment form: collect customer phone number
     */
    public function payment(int $id, string $hash): void
    {
        check_invoice_restrictions($id, $hash);

        $quoteId = $this->session->userdata('s3p_quote_id');
        if (!$quoteId) {
            redirect(site_url('invoice/' . $id . '/' . $hash));
            return;
        }

        $invoice  = $this->invoices_model->get($id);
        load_client_language($invoice->clientid);

        $amount   = $this->session->userdata('s3p_amount');
        $currency = $this->session->userdata('s3p_currency');

        echo $this->_renderPaymentForm($invoice, $amount, $currency, $id, $hash);
    }

    /**
     * Process phone number form, initiate mobile money collection
     */
    public function initiate(int $id, string $hash): void
    {
        check_invoice_restrictions($id, $hash);

        $quoteId = $this->session->userdata('s3p_quote_id');
        if (!$quoteId || !$this->input->post()) {
            redirect(site_url('invoice/' . $id . '/' . $hash));
            return;
        }

        $invoice = $this->invoices_model->get($id);
        load_client_language($invoice->clientid);

        $phone    = trim($this->input->post('phone'));
        $name     = trim($this->input->post('customer_name'));
        $email    = trim($this->input->post('customer_email'));
        $address  = trim($this->input->post('customer_address')) ?: 'N/A';

        if (empty($phone)) {
            set_alert('danger', 'Phone number is required.');
            redirect(site_url('s3p/payment/' . $id . '/' . $hash));
            return;
        }

        $trid = $this->s3p_gateway->generateTrid($id);

        $result = $this->s3p_gateway->collect([
            'quoteId'              => $quoteId,
            'customerPhonenumber'  => $phone,
            'customerEmailaddress' => $email ?: 'noreply@example.com',
            'customerName'         => $name ?: 'Customer',
            'customerAddress'      => $address,
            'serviceNumber'        => $phone,
            'trid'                 => $trid,
        ]);

        if (!empty($result['error']) || (isset($result['status']) && $result['status'] === 'FAILED')) {
            $msg = $result['message'] ?? ($result['error'] ?? 'Payment initiation failed.');
            set_alert('danger', 'S3P: ' . $msg);
            redirect(site_url('s3p/payment/' . $id . '/' . $hash));
            return;
        }

        $this->session->set_userdata([
            's3p_trid'  => $trid,
            's3p_phone' => $phone,
        ]);

        redirect(site_url('s3p/waiting/' . $id . '/' . $hash));
    }

    /**
     * Waiting page: customer confirms USSD push on phone
     */
    public function waiting(int $id, string $hash): void
    {
        check_invoice_restrictions($id, $hash);

        $trid = $this->session->userdata('s3p_trid');
        if (!$trid) {
            redirect(site_url('invoice/' . $id . '/' . $hash));
            return;
        }

        $invoice  = $this->invoices_model->get($id);
        load_client_language($invoice->clientid);

        $phone    = $this->session->userdata('s3p_phone');
        $amount   = $this->session->userdata('s3p_amount');
        $currency = $this->session->userdata('s3p_currency');

        echo $this->_renderWaitingPage($invoice, $id, $hash, $phone, $amount, $currency);
    }

    /**
     * AJAX: poll transaction status
     */
    public function check_status(int $id, string $hash): void
    {
        check_invoice_restrictions($id, $hash);

        $trid = $this->session->userdata('s3p_trid');
        if (!$trid) {
            echo json_encode(['status' => 'ERROR', 'message' => 'No transaction reference']);
            return;
        }

        $result = $this->s3p_gateway->verifyTransaction($trid);

        $status  = $result['status'] ?? 'PENDING';
        $message = $result['message'] ?? '';

        echo json_encode([
            'status'  => $status,
            'message' => $message,
        ]);
    }

    /**
     * Complete payment after successful verification
     */
    public function complete(int $id, string $hash): void
    {
        check_invoice_restrictions($id, $hash);

        $trid   = $this->session->userdata('s3p_trid');
        $amount = $this->session->userdata('s3p_amount');

        if (!$trid || !$amount) {
            redirect(site_url('invoice/' . $id . '/' . $hash));
            return;
        }

        // Final verify before recording
        $result = $this->s3p_gateway->verifyTransaction($trid);
        $status = $result['status'] ?? '';

        if ($status !== 'SUCCESSFUL') {
            set_alert('danger', 'Payment could not be confirmed. Please contact support with reference: ' . $trid);
            redirect(site_url('invoice/' . $id . '/' . $hash));
            return;
        }

        // Prevent duplicate recording
        if (total_rows('invoicepaymentrecords', ['transactionid' => $trid]) === 0) {
            $success = $this->s3p_gateway->addPayment([
                'amount'        => $amount,
                'invoiceid'     => $id,
                'transactionid' => $trid,
                'paymentmethod' => 'S3P Smobilpay',
            ]);

            if ($success) {
                set_alert('success', 'Payment recorded successfully.');
            } else {
                set_alert('danger', 'Payment was received but could not be recorded. Reference: ' . $trid);
            }
        }

        // Clean up session
        $this->session->unset_userdata([
            's3p_quote_id', 's3p_amount', 's3p_invoice_id',
            's3p_currency', 's3p_trid', 's3p_phone',
        ]);

        redirect(site_url('invoice/' . $id . '/' . $hash));
    }

    private function _renderPaymentForm($invoice, float $amount, string $currency, int $id, string $hash): string
    {
        $invoiceNumber = format_invoice_number($id);
        $clientName    = $invoice->client->company ?: ($invoice->client->firstname . ' ' . $invoice->client->lastname);
        $clientEmail   = $invoice->client->email ?? '';

        ob_start();
        echo payment_gateway_head('Pay with S3P Smobilpay');
        ?>
        <body class="gateway-s3p">
        <div class="container">
            <div class="col-md-6 col-md-offset-3 mtop30">
                <div class="mbot30 text-center">
                    <?php echo payment_gateway_logo(); ?>
                </div>
                <div class="panel_s">
                    <div class="panel-heading">
                        <h4 class="panel-title">
                            Pay with Mobile Money —
                            Invoice <a href="<?php echo site_url('invoice/' . $id . '/' . $hash); ?>">
                                <?php echo e($invoiceNumber); ?>
                            </a>
                        </h4>
                    </div>
                    <div class="panel-body">
                        <p class="text-muted">Amount: <strong><?php echo e($currency . ' ' . number_format($amount, 0)); ?></strong></p>
                        <p class="text-muted small">A USSD push notification will be sent to your phone. Confirm the payment on your phone to complete.</p>

                        <form method="POST" action="<?php echo site_url('s3p/initiate/' . $id . '/' . $hash); ?>">
                            <input type="hidden" name="csrf_token_name" value="<?php echo $this->security->get_csrf_hash(); ?>">

                            <div class="form-group">
                                <label>Mobile Money Phone Number <span class="text-danger">*</span></label>
                                <input type="text" name="phone" class="form-control" placeholder="e.g. 237677000000" required>
                                <span class="text-muted small">Include country code (e.g. 237 for Cameroon)</span>
                            </div>

                            <div class="form-group">
                                <label>Full Name</label>
                                <input type="text" name="customer_name" class="form-control" value="<?php echo e($clientName); ?>">
                            </div>

                            <div class="form-group">
                                <label>Email</label>
                                <input type="email" name="customer_email" class="form-control" value="<?php echo e($clientEmail); ?>">
                            </div>

                            <div class="form-group">
                                <label>Address</label>
                                <input type="text" name="customer_address" class="form-control">
                            </div>

                            <div class="form-group">
                                <button type="submit" class="btn btn-primary btn-block">
                                    Initiate Mobile Money Payment
                                </button>
                                <a href="<?php echo site_url('invoice/' . $id . '/' . $hash); ?>" class="btn btn-default btn-block">
                                    Cancel
                                </a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
        <?php echo payment_gateway_scripts(); ?>
        </body>
        <?php
        echo payment_gateway_footer();
        $html = ob_get_contents();
        ob_end_clean();
        return $html;
    }

    private function _renderWaitingPage($invoice, int $id, string $hash, string $phone, float $amount, string $currency): string
    {
        $invoiceNumber = format_invoice_number($id);
        $checkUrl      = site_url('s3p/check_status/' . $id . '/' . $hash);
        $completeUrl   = site_url('s3p/complete/' . $id . '/' . $hash);
        $cancelUrl     = site_url('invoice/' . $id . '/' . $hash);

        ob_start();
        echo payment_gateway_head('Waiting for Payment Confirmation');
        ?>
        <body class="gateway-s3p-waiting">
        <div class="container">
            <div class="col-md-6 col-md-offset-3 mtop30">
                <div class="mbot30 text-center">
                    <?php echo payment_gateway_logo(); ?>
                </div>
                <div class="panel_s">
                    <div class="panel-heading">
                        <h4 class="panel-title">Awaiting Payment — Invoice <?php echo e($invoiceNumber); ?></h4>
                    </div>
                    <div class="panel-body text-center">
                        <div id="s3p-status-icon" style="font-size:48px; margin-bottom:16px;">⏳</div>
                        <h4 id="s3p-status-text">Waiting for confirmation...</h4>
                        <p class="text-muted">
                            A USSD push was sent to <strong><?php echo e($phone); ?></strong>.<br>
                            Amount: <strong><?php echo e($currency . ' ' . number_format($amount, 0)); ?></strong><br>
                            Please confirm the payment on your phone.
                        </p>
                        <div id="s3p-spinner" class="text-muted small" style="margin-top:10px;">Checking status...</div>
                        <div id="s3p-error" class="alert alert-danger" style="display:none; margin-top:15px;"></div>
                        <div style="margin-top:20px;">
                            <a href="<?php echo e($cancelUrl); ?>" class="btn btn-default">Cancel / Go Back</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <?php echo payment_gateway_scripts(); ?>
        <script>
        (function poll() {
            var attempts = 0;
            var maxAttempts = 60; // 3 minutes at 3s intervals

            function check() {
                $.getJSON('<?php echo $checkUrl; ?>', function(data) {
                    attempts++;

                    if (data.status === 'SUCCESSFUL') {
                        $('#s3p-status-icon').text('✅');
                        $('#s3p-status-text').text('Payment confirmed! Redirecting...');
                        $('#s3p-spinner').hide();
                        setTimeout(function() {
                            window.location.href = '<?php echo $completeUrl; ?>';
                        }, 1500);
                        return;
                    }

                    if (data.status === 'FAILED') {
                        $('#s3p-status-icon').text('❌');
                        $('#s3p-status-text').text('Payment failed');
                        $('#s3p-error').text(data.message || 'Payment was declined or cancelled.').show();
                        $('#s3p-spinner').hide();
                        return;
                    }

                    if (attempts >= maxAttempts) {
                        $('#s3p-spinner').text('Timed out. Please check your invoice or contact support.');
                        return;
                    }

                    $('#s3p-spinner').text('Checking status... (attempt ' + attempts + ')');
                    setTimeout(check, 3000);
                }).fail(function() {
                    if (attempts < maxAttempts) {
                        attempts++;
                        setTimeout(check, 3000);
                    }
                });
            }

            setTimeout(check, 3000);
        })();
        </script>
        </body>
        <?php
        echo payment_gateway_footer();
        $html = ob_get_contents();
        ob_end_clean();
        return $html;
    }
}
