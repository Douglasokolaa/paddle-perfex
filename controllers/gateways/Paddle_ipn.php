<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Paddle_ipn extends App_Controller
{

    private function record($amount, $id, $transId)
    {
        $success = $this->paddle_gateway->addPayment(
            [
                'amount'        => $amount,
                'invoiceid'     => $id,
                'transactionid' => $transId,
                'paymentmethod' => 'Paddle',
            ]
        );
        if ($success) {
            log_activity('online_payment_recorded_success');
            set_alert('success', _l('online_payment_recorded_success'));
        } else {
            log_activity('online_payment_recorded_success_fail_database' . var_export($this->input->get(), true));
            set_alert('success', _l('online_payment_recorded_success_fail_database'));
        }
    }

    public function notifyx()
    {
        $payload = $this->input->post();

        if (empty($payload) || !isset($payload['p_signature']) || !isset($payload['passthrough'])) {
            header("HTTP/1.1 400 ");
            header("Status: 400 Bad Request");
            die();
        }

        // Your Paddle 'Public Key'
        $public_key_string = $this->paddle_gateway->public_key();

        $public_key = openssl_get_publickey($public_key_string);

        // Get the p_signature parameter & base64 decode it.
        $signature = base64_decode($_POST['p_signature']);

        // Get the fields sent in the request, and remove the p_signature parameter
        $fields = $_POST;
        unset($fields['p_signature']);

        // ksort() and serialize the fields
        ksort($fields);
        foreach ($fields as $k => $v) {
            if (!in_array(gettype($v), array('object', 'array'))) {
                $fields[$k] = "$v";
            }
        }
        $data = serialize($fields);

        // Verify the signature
        $verification = openssl_verify($data, $signature, $public_key, OPENSSL_ALGO_SHA1);

        if ($verification == 1) {
            $invoice = json_decode($fields['passthrough']);
            check_invoice_restrictions($invoice->invoiceId, $invoice->hash);
            $this->record($invoice->amount, $invoice->invoiceId, $fields['checkout_id']);
        } else {
            log_activity('inalid paddle IPN, IP=' . $this->input->ip_address());
            echo 'The signature is invalid!';
        }
    }
    public function notify($id, $hash)
    {
        $payload = $this->input->post();

        if (empty($payload) || !isset($payload['p_signature']) || !isset($payload['passthrough'])) {
            header("HTTP/1.1 400 ");
            header("Status: 400 Bad Request");
            die();
        }

        // Your Paddle 'Public Key'
        $public_key_string = $this->paddle_gateway->public_key();

        $public_key = openssl_get_publickey($public_key_string);

        // Get the p_signature parameter & base64 decode it.
        $signature = base64_decode($_POST['p_signature']);

        // Get the fields sent in the request, and remove the p_signature parameter
        $fields = $_POST;
        unset($fields['p_signature']);

        // ksort() and serialize the fields
        ksort($fields);
        foreach ($fields as $k => $v) {
            if (!in_array(gettype($v), array('object', 'array'))) {
                $fields[$k] = "$v";
            }
        }
        $data = serialize($fields);

        // Verify the signature
        $verification = openssl_verify($data, $signature, $public_key, OPENSSL_ALGO_SHA1);

        if ($verification == 1) {
            $invoice = json_decode($fields['passthrough']);
            check_invoice_restrictions($id, $hash);
            $this->record($invoice->amount, $invoice->invoiceId, $fields['checkout_id']);
        } else {
            log_activity('inalid paddle IPN, IP=' . $this->input->ip_address());
            echo 'The signature is invalid!';
        }
    }
}
