<?php
defined('BASEPATH') or exit('No direct script access allowed'); ?>
<style>
    #payment-form {
        width: 100%;
        /* min-width: 500px; */
        align-self: center;
        box-shadow: 0px 0px 0px 0.5px rgba(50, 50, 93, 0.1),
        0px 2px 5px 0px rgba(50, 50, 93, 0.1), 0px 1px 1.5px 0px rgba(0, 0, 0, 0.07);
        border-radius: 7px;
        padding: 40px;
    }

    .hidden {
        display: none;
    }

    #payment-message {
        font-size: 16px;
        line-height: 20px;
        padding-top: 12px;
        text-align: center;
        color: #ff0000;
    }

    #payment-element {
        margin-bottom: 24px;
    }

    /* Buttons and links */
    button {
        background: #5469d4;
        font-family: Arial, sans-serif;
        color: #ffffff;
        border-radius: 4px;
        border: 0;
        padding: 12px 16px;
        font-size: 16px;
        font-weight: 600;
        cursor: pointer;
        display: block;
        transition: all 0.2s ease;
        box-shadow: 0px 4px 5.5px 0px rgba(0, 0, 0, 0.07);
        /*width: 100%;*/
    }

    button:hover {
        filter: contrast(115%);
    }

    button:disabled {
        opacity: 0.5;
        cursor: default;
    }

    /* spinner/processing state, errors */
    .spinner,
    .spinner:before,
    .spinner:after {
        border-radius: 50%;
    }

    .spinner {
        color: #ffffff;
        font-size: 22px;
        text-indent: -99999px;
        margin: 0px auto;
        position: relative;
        width: 20px;
        height: 20px;
        box-shadow: inset 0 0 0 2px;
        -webkit-transform: translateZ(0);
        -ms-transform: translateZ(0);
        transform: translateZ(0);
    }

    .spinner:before,
    .spinner:after {
        position: absolute;
        content: "";
    }

    .spinner:before {
        width: 10.4px;
        height: 20.4px;
        background: #5469d4;
        border-radius: 20.4px 0 0 20.4px;
        top: -0.2px;
        left: -0.2px;
        -webkit-transform-origin: 10.4px 10.2px;
        transform-origin: 10.4px 10.2px;
        -webkit-animation: loading 2s infinite ease 1.5s;
        animation: loading 2s infinite ease 1.5s;
    }

    .spinner:after {
        width: 10.4px;
        height: 10.2px;
        background: #5469d4;
        border-radius: 0 10.2px 10.2px 0;
        top: -0.1px;
        left: 10.2px;
        -webkit-transform-origin: 0px 10.2px;
        transform-origin: 0px 10.2px;
        -webkit-animation: loading 2s infinite ease;
        animation: loading 2s infinite ease;
    }

    @-webkit-keyframes loading {
        0% {
            -webkit-transform: rotate(0deg);
            transform: rotate(0deg);
        }

        100% {
            -webkit-transform: rotate(360deg);
            transform: rotate(360deg);
        }
    }

    @keyframes loading {
        0% {
            -webkit-transform: rotate(0deg);
            transform: rotate(0deg);
        }

        100% {
            -webkit-transform: rotate(360deg);
            transform: rotate(360deg);
        }
    }

    @media only screen and (max-width: 600px) {
        form {
            width: 80vw;
            min-width: initial;
        }
    }
</style>
<form id="payment-form" action="<?php echo site_url('stripePayment'); ?>"
      method="post">
    <input type="hidden" name="plan" id="plan" value="<?php echo $priceId; ?>">
    <input type="hidden" name="payment_method" class="payment-method">
    <input type="hidden" name="gateway" value="stripe">
    <div class="">
        <div id="link-authentication-element">
            <!--Stripe.js injects the Link Authentication Element-->
        </div>
        <div id="payment-element">
            <!--Stripe.js injects the Payment Element-->
        </div>

        <div class="tw-flex tw-justify-between
        tw-items-center">

            <button
                    id="submit"
                    type="submit" class="btn bg-indigo-500 hover:bg-indigo-600 text-white p-3 rounded-md mt-4">


                <div id="spinner"
                     class="tw-w-7 tw-h-7 tw-rounded-full tw-animate-spin hidden tw-border-2 tw-border-indigo-200 tw-border-t-transparent">
                </div>

                <span class=""
                      id="button-text"> <?php echo _l('pay') . ' ' . display_money($amount) . ' ' . _l('stripe'); ?>
                </span>

            </button>
            <a href="<?= site_url('updatePackage/' . $company_id) ?>"
               class="btn bg-indigo-500 hover:bg-indigo-600 text-white p-3 rounded-md mt-4"><?= _l('Change Plan') ?></a>
        </div>

        <div id="payment-message" class="hidden"></div>
    </div>
</form>

<script src="https://js.stripe.com/v3/"></script>
<script>
    (function () {
        const stripe = Stripe("<?php echo ConfigItems('stripe_publishable_key'); ?>");
        let elements;

        initialize();

        if ("<?php echo $client_secret; ?>".startsWith("set") == false) {
            // checkStatus();
        }

        document
            .querySelector("#payment-form")
            .addEventListener("submit", handleSubmit);

        let emailAddress = "<?php echo $company->email; ?>";

        async function initialize() {

            if ("<?php echo $client_secret; ?>".startsWith("set") == true) {
                var options = {
                    mode: 'setup',
                    curreny: "<?= $currency?>",
                    amount: "<?= $amount?>",
                };
            } else {
                var options = {
                    mode: 'subscription',
                    curreny: "<?= $currency?>",
                    amount: "<?= $amount?>",
                };
            }

            const clientSecret = "<?php echo $client_secret; ?>";

            // check if is dark mode or not
            let appearance;
            if (localStorage.getItem('color-theme') === 'dark') {
                appearance = {
                    theme: 'night',
                    variables: {
                        colorPrimary: '#6367f1',
                        colorBackground: '#182236',
                        colorText: 'rgb(148 163 184 / var(--tw-text-opacity))',
                        colorTextSecondary: '#f3f4f6',
                        colorTextPlaceholder: '#8e99a3',
                    }
                };
            } else {
                appearance = {
                    theme: 'stripe',
                    variables: {
                        colorPrimary: '#6367f1',
                        colorBackground: '#ffffff',
                        colorText: 'rgb(55, 65, 81)',
                        colorTextSecondary: 'rgb(71, 85, 105)',

                    }
                };
            }
            elements = stripe.elements({
                clientSecret: clientSecret,
                appearance: appearance,
            });

            const linkAuthenticationElement = elements.create("linkAuthentication");
            linkAuthenticationElement.mount("#link-authentication-element");

            const paymentElementOptions = {
                layout: {
                    type: 'tabs',
                    defaultCollapsed: true,
                    radios: true,
                    spacedAccordionItems: true
                },
                amount: '<?= $amount?>',
                currency: '<?= $currency?>',
            };

            const paymentElement = elements.create("payment", paymentElementOptions);
            paymentElement.mount("#payment-element");

            // show #button-text and hide #spinner
            setLoading(false);


        }

        async function handleSubmit(event) {
            event.preventDefault();
            setLoading(true);

            const secret = "<?php echo $client_secret; ?>";

            var error;
            let url = "<?php echo site_url('stripePayment'); ?>" + "?<?= $postURL ?>";

            if (secret.startsWith("set") == false) {
                error = await stripe.confirmPayment({
                    elements,
                    confirmParams: {
                        return_url: url,
                        receipt_email: emailAddress,
                    },
                });
            } else {
                var paymentForm = document.getElementById('payment-form');
                const receiptEmailField = paymentForm.querySelector('[name="receipt_email"]');
                if (receiptEmailField) {
                    paymentForm.removeChild(receiptEmailField);
                } else {
                    const receiptEmailField = document.getElementById('receipt_email');
                    if (receiptEmailField) {
                        paymentForm.removeChild(receiptEmailField);
                    }
                }

                error = await stripe.confirmSetup({
                    elements,
                    confirmParams: {
                        return_url: url,
                    },
                });
            }
            if (error?.error?.type === "card_error" || error?.error?.type === "validation_error") {
                showMessage(error.error.message);
            } else {
                showMessage(error.error.message);
            }
            setLoading(false);
        }

        function showMessage(message) {
            const messageElement = document.getElementById("payment-message");
            messageElement.textContent = message;
            messageElement.classList.remove("hidden");
        }


        async function checkStatus() {

            const clientSecret = "{{ $subscription['client_secret'] }}";

            if (!clientSecret) {
                return;
            }

            const {
                paymentIntent
            } = await stripe.retrievePaymentIntent(clientSecret);

            switch (paymentIntent.status) {
                case "succeeded":
                    showMessage("Payment succeeded!");
                    break;
                case "processing":
                    showMessage("Your payment is processing.");
                    break;
                case "requires_payment_method":
                    showMessage("Select a valid payment method to proceed.");
                    break;
                default:
                    showMessage("Something went wrong.");
                    break;
            }
        }

        // Show a spinner on payment submission
        function setLoading(isLoading) {
            if (isLoading) {
                // Disable the button and show a spinner
                document.querySelector("#submit").disabled = true;
                // document.querySelector("#spinner").classList.remove("hidden");
                // document.querySelector("#button-text").classList.add("hidden");
                // change the button text to processing
                document.querySelector("#button-text").textContent = "<?php echo _l('processing'); ?>";

            } else {
                document.querySelector("#submit").disabled = false;
                // document.querySelector("#spinner").classList.add("hidden");
                // document.querySelector("#button-text").classList.remove("hidden");
                // change the button text to pay
                document.querySelector("#button-text").textContent = "<?php echo _l('pay') . ' ' . display_money($amount) . ' ' . _l('stripe'); ?>";
            }
        }

    })();
</script>