<?php

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;

defined('BASEPATH') or exit('No direct script access allowed');

class Paddle extends App_Controller
{
    public function payment($invoice_id, $invoice_hash)
    {
        check_invoice_restrictions($invoice_id, $invoice_hash);

        $this->load->model('invoices_model');
        $invoice = $this->invoices_model->get($invoice_id);
        load_client_language($invoice->clientid);

        $data  = $invoice;
        $data->total = $this->session->userdata('paddle_total');
        $data->description = $this->paddle_gateway->description($invoice_id);
        $data->vendor_id = $this->paddle_gateway->vendor_id();
        $data->testMode = $this->paddle_gateway->getSetting('test_mode_enabled') == '1';


        $data->zip_code_required  = false;
        $data->billing_email = '';
        if (is_client_logged_in()) {
            $contact               = $this->clients_model->get_contact(get_contact_user_id());
            $data->billing_email = $contact->email;
            $data->billing_name = get_contact_full_name($contact->id);
        } else {
            $contact = $this->clients_model->get_contact(get_primary_contact_user_id($invoice->clientid));
            if ($contact) {
                $data->billing_email = $contact->email;
                $data->billing_firstname = $contact->firstname;
                $data->billing_lastname = $contact->lastname;
                $data->billing_name = get_contact_full_name($contact->id);
            }
        }

        $_data = $this->prepare_data($data);
        $response = $this->callPaddle($_data);
        if ($response->success) {
            $link = $response->response->url;
            echo $this->get_html($data, $link);
            die;
        }
        var_dump($response); die;
        set_alert('warning', $response->error->int . ' ' , $response->error->message);
        redirect(site_url('invoice/' .$invoice_id .'/'. $invoice_hash));
    }

    /**
     * Get payment gateway html view
     *
     * @param  array|object  $data
     *
     * @return string
     */
    protected function get_html($data, $link = '')
    {
        ob_start(); ?>
        <?php echo payment_gateway_head() ?>
        <script type="text/javascript" src="https://cdn.paddle.com/paddle/paddle.js"></script>
        <style>
            .mright25 {
                margin-right: 25px !important;
            }
        </style>

        <body class="gateway-paddle-checkout">
            <div class="container">
                <div class="col-md-6 col-md-offset-3 mtop30">
                    <div class="mbot30 text-center">
                        <?php echo payment_gateway_logo(); ?>
                    </div>
                    <div class="row">
                        <div class="panel_s">
                            <div class="panel-body">
                                <h4 class="no-margin">
                                    <?php echo _l('payment_for_invoice'); ?> <a href="<?php echo site_url('invoice/' . $data->id . '/' . $data->hash); ?>"><?php echo format_invoice_number($data->id); ?></a>
                                </h4>
                                <hr />
                                <h4 class="mbot20">
                                    <?php echo _l('payment_total', app_format_money($data->total, $data->currency_name)); ?>
                                </h4>
                                <a href="#!" class="btn btn-primary paddle_button" data-override="<?= $link ?>">Buy Now!</a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <?php echo payment_gateway_scripts(); ?>
            <script type="text/javascript">
                Paddle.Setup({
                    vendor: <?= $data->vendor_id ?>,
                    eventCallback: function(data) {
                        if (data.event == 'Checkout.Complete') {
                            location.replace("<?php echo html_escape(site_url('paddle/complete/' . $data->id . '/' . $data->hash)); ?>" + "/" + data.eventData.Checkout.id)
                        }
                        // console.log(data.event); // The data.event will specify the event type
                        // console.log(data.eventData); // Data specifics on the event
                    }
                });
                Paddle.Checkout.open({
                    override: "<?= $link ?>",
                    passthrough: "{\"invoiceId\": <?= $data->id ?>,\"hash\": \"<?= $data->hash ?>\", \"amount\": <?= $data->total ?>}"
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
        log_activity('new paddle payment; checkout-id;' . $token);
        $this->load->model('invoices_model');
        $invoice = $this->invoices_model->get($invoice_id);
        load_client_language($invoice->clientid);
        set_alert('primary', 'Thank You. Your transaction is being processed and you will receive an email notification shortly');
        $this->session->unset_userdata('paddle_total');
        redirect(site_url('invoice/' . $invoice_id . '/' . $invoice_hash));
    }

    public function callPaddle($data)
    {
        $options = [
            'headers' => [
                'Content-Type' => 'application/x-www-form-urlencoded',
            ],
            'form_params' => $data,
        ];

        $url =  'https://vendors.paddle.com/api/2.0/product/generate_pay_link';
        $client = new Client();
        try {
            $response = $client->post($url, $options);
            return json_decode($response->getBody());
        } catch (RequestException $e) {
            // TO DO: FIND A WORK AROUNF FOR STAFF ID
            log_activity('PAYMENT ERROR' . $e->getResponse()->getBody());
            return $e->getMessage();
        }
    }

    public function prepare_data($invoice)
    {
        $contactid = get_primary_contact_user_id($invoice->clientid);

        $this->load->model('clients_model');
        $contact = $this->clients_model->get_contact($contactid);
        $data = [
            "vendor_auth_code" => $this->paddle_gateway->auth_code(),
            "vendor_id" => $this->paddle_gateway->vendor_id(),
            "title" => $this->paddle_gateway->description($invoice->id),
            "prices" => [$invoice->currency_name . ':' . $this->session->userdata('paddle_total')],
            "quantity" => 1,
            "webhook_url" => $this->paddle_gateway->webhook($invoice->id, $invoice->hash),
            "customer_email" => $contact->email,
            "customer_country" => get_country_short_name($invoice->billing_country),
            "customer_postcode" =>  $invoice->billing_zip,
        ];
        return $data;
    }
}
