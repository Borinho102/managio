<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>

<div id="paypal-button-container"></div>
<script src="https://www.paypal.com/sdk/js?client-id=<?php echo ConfigItems('paypal_client_id'); ?>&vault=true&intent=subscription"></script>

<script>
    "use strict";
    paypal.Buttons({
        createSubscription: function (data, actions) {
            return actions.subscription.create({
                'plan_id': '<?php echo $priceId; ?>'
            });
        },
        onApprove: function (data, actions) {
            // post data to server
            return actions.subscription.get().then(function (details) {
                // post data to server using ajax
                $.ajax({
                    url: '<?php echo site_url('completePaypalPayment/' . $companies_id); ?>',
                    type: 'POST',
                    // dataType: 'json',
                    data: {
                        subscription_id: data.subscriptionID,
                        transaction_id: data.orderID,
                        currency: details.shipping_amount.currency_code,
                        post: '<?php echo json_encode($post); ?>',
                        paymentMethod: 'paypal'
                    },
                    success: function (response) {
                        if (response.result) {
                            // redirect to success page
                            window.location.href = '<?php echo site_url('paymentSuccess'); ?>';
                        } else {
                            window.location.href = '<?php echo site_url('paymentCancel'); ?>';
                        }
                    },
                    error: function (error) {
                        window.location.href = '<?php echo site_url('paymentCancel'); ?>';
                    }
                });
            });

        }
    }).render('#paypal-button-container');
</script>