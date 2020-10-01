<?php

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;

defined('BASEPATH') or exit('No direct script access allowed');

class Two_checkout extends App_Controller
{
    public function payment($invoice_id, $invoice_hash)
    {
        check_invoice_restrictions($invoice_id, $invoice_hash);

        $this->load->model('invoices_model');
        $invoice = $this->invoices_model->get($invoice_id);
        load_client_language($invoice->clientid);

        $data['invoice']  = $invoice;
        $data['total']    = $this->session->userdata('two_checkout_total');
        $data['description'] = $this->two_checkout_gateway->description($invoice_id);
        $data['merchant_code'] = $this->two_checkout_gateway->merchant_code();
        $data['testMode'] = $this->two_checkout_gateway->getSetting('test_mode_enabled') == '1';
        echo $this->get_html($data);
    }

    /**
     * Get payment gateway html view
     *
     * @param  array  $data
     *
     * @return string
     */
    protected function get_html($data = [])
    {
        ob_start(); ?>
        <?php echo payment_gateway_head() ?>
        <script type="text/javascript" src="https://2pay-js.2checkout.com/v1/2pay.js"></script>
        <style>
            .mright25 {
                margin-right: 25px !important;
            }
        </style>

        <body class="gateway-two-checkout">
            <div class="container">
                <div class="col-md-8 col-md-offset-2 mtop30">
                    <div class="mbot30 text-center">
                        <?php echo payment_gateway_logo(); ?>
                    </div>
                    <div class="row">
                        <div class="panel_s">
                            <div class="panel-body">
                                <h4 class="no-margin">
                                    <?php echo _l('payment_for_invoice'); ?> <a href="<?php echo site_url('invoice/' . $data['invoice']->id . '/' . $data['invoice']->hash); ?>"><?php echo format_invoice_number($data['invoice']->id); ?></a>
                                </h4>
                                <hr />
                                <h4 class="mbot20">
                                    <?php echo _l('payment_total', app_format_money($data['total'], $data['invoice']->currency_name)); ?>
                                </h4>
                                <!-- <a href="#" class="btn btn-success" id="buy-button">Pay now!</a> -->
                                <form type="post" id="payment-form">
                                    <?php echo render_input('name', 'name', '', 'text', [], [], 'mright25'); ?>
                                    <div id="card-element">
                                        <!-- A TCO IFRAME will be inserted here. -->
                                    </div>
                                    <button class="btn btn-primary" type="submit">_<?php echo _l('two_checkout_proceed_to_pay') ?></button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <?php echo payment_gateway_scripts(); ?>
            <script type="text/javascript">
                window.addEventListener('load', function() {

                    //make name required
                    $('#name').prop('required', true);

                    // Initialize the 2Pay.js client.
                    let jsPaymentClient = new TwoPayClient('<?php echo $data["merchant_code"]; ?>');

                    // Create the component that will hold the card fields.
                    let component = jsPaymentClient.components.create('card');
                    component.mount('#card-element');

                    document.getElementById('payment-form').addEventListener('submit', (event) => {
                        event.preventDefault();
                        const billingDetails = {
                            name: document.querySelector('#name').value
                        };

                        // Call the generate method using the component as the first parameter
                        // and the billing details as the second one
                        jsPaymentClient.tokens.generate(component, billingDetails).then((response) => {
                            location.replace("<?php echo site_url('gateways/two_checkout/complete/' . $data['invoice']->id . '/' . $data['invoice']->hash . '/'); ?>" + response.token);
                        }).catch((error) => {
                            console.error(error);
                        });
                    });
                });
            </script>
            <?php echo payment_gateway_footer(); ?>
    <?php
        $contents = ob_get_contents();
        ob_end_clean();

        return $contents;
    }

    public function complete($invoice_id, $invoice_hash, $token)
    {
        check_invoice_restrictions($invoice_id, $invoice_hash);
        $this->load->model('invoices_model');
        $invoice = $this->invoices_model->get($invoice_id);
        load_client_language($invoice->clientid);

        $data = $this->prepare_data($invoice, $token);

        $result = $this->call2co($data);
        if (!is_object($result)) {
            set_alert('warning', _l('invalid_transaction'));
            // var_dump($data);
            // var_dump($result);
            exit;
        } else {

            if ($result->status == 'COMPLETE') {
                $success = $this->two_checkout_gateway->addPayment(
                    [
                        'amount'        => $result->NetPrice,
                        'invoiceid'     => $invoice_id,
                        'transactionid' => $result->RefNo,
                        'paymentmethod' => 'CARD',
                    ]
                );
                if ($success) {
                    set_alert('success', _l('online_payment_recorded_success'));
                } else {
                    set_alert('danger', _l('online_payment_recorded_success_fail_database'));
                }
            } else {
                set_alert('warning', 'Thank You. Your transaction status is ' . $result->status);
            }
        }

        $this->session->unset_userdata('two_checkout_total');
        redirect(site_url('invoice/' . $invoice_id . '/' . $invoice_hash));
    }

    public function call2co($data)
    {
        $merchantCode = $this->two_checkout_gateway->merchant_code();
        $key = $this->two_checkout_gateway->secret_key();
        $datetime = gmdate('Y-m-d H:i:s');
        $string = strlen($merchantCode) . $merchantCode . strlen($datetime) . $datetime;
        $hash = hash_hmac('md5', $string, $key);

        $options = [
            'headers' => [
                'Content-Type'     => 'application/json',
                'Accept'     => 'application/json',
                'X-Avangate-Authentication' => 'code="' . $merchantCode . '" date="' . $datetime . '" hash="' . $hash . '"',
            ],
            'json' => json_encode($data)
        ];

        $url =  'https://api.2checkout.com/rest/6.0/orders/';
        $client = new Client();
        try {
            $response = $client->request('POST', $url, $options);
            return json_decode($response->getBody());
        } catch (RequestException $e) {
            // TO DO: FIND A WORK AROUNF FOR STAFF ID
            log_activity('PAYMENT ERROR' . $e->getResponse()->getBody());
            return $e->getMessage();
        }
    }

    public function prepare_data($invoice, $token)
    {

        $contactid = get_primary_contact_user_id($invoice->clientid);

        $this->load->model('clients_model');
        $contact = $this->clients_model->get_contact($contactid);

        $data = [
            "Currency" => $invoice->currency_name,
            "CustomerIP" => $this->input->ip_address(),
            "country" => get_country_short_name($invoice->billing_country),
            "BillingDetails" => [
                "Address1" => $invoice->billing_street,
                "City" => $invoice->shipping_city,
                "CountryCode" => get_country_short_name($invoice->billing_country),
                "Email" => $contact->email,
                "FirstName" => $contact->firstname,
                "LastName" => $contact->lastname,
                "Phone" => $invoice->client->phonenumber,
                "State" => $invoice->billing_state,
                "Zip" => $invoice->billing_zip,
            ],
            "Items" => [
                [
                    "Code" => null,
                    "Name" => $this->two_checkout_gateway->description($invoice->id),
                    "Description" => $this->two_checkout_gateway->description($invoice->id),
                    "Quantity" => 1,
                    "IsDynamic" => true,
                    "Tangible" => false,
                    "PurchaseType" => "PRODUCT",
                    "Price" => [
                        "Amount" =>  $this->session->userdata('two_checkout_total'),
                        "Type" => "CUSTOM"
                    ],
                ]
            ],
            "PaymentDetails" => [
                "Currency" => $invoice->currency_name,
                "CustomerIP" => $this->input->ip_address(),
                "PaymentMethod" => [
                    "EesToken" => $token,
                    "RecurringEnabled" => false,
                ],
                "Type" => ($this->two_checkout_gateway->getSetting('test_mode_enabled') == '1') ? "TEST" : ''
            ]
        ];


        return $data;
    }
}

