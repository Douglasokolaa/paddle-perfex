<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Paddle_gateway extends App_gateway
{
    public function __construct()
    {
        /**
         * Call App_gateway __construct function
         */
        parent::__construct();
        /**
         * REQUIRED
         * Gateway unique id
         * The ID must be alpha/alphanumeric
         */
        $this->setId('paddle');

        /**
         * REQUIRED
         * Gateway name
         */
        $this->setName('Paddle');

        /**
         * Add gateway settings
         */
        $this->setSettings(
            [
                [
                    'name'      => 'paddle_vendor_id',
                    'encrypted' => true,
                    'label'     => 'paddle_vendor_id',
                ],
                [
                    'name'      => 'paddle_public_key',
                    'type'      => 'textarea',
                    'label'     => 'paddle_public_key',
                ],
                [
                    'name'      => 'paddle_auth_code',
                    'encrypted' => true,
                    'label'     => 'paddle_auth_code',
                ],
                [
                    'name'          => 'description',
                    'label'         => 'settings_paymentmethod_description',
                    'type'          => 'textarea',
                    'default_value' => 'Payment for Invoice {invoice_number}',
                ],
                [
                    'name'             => 'currencies',
                    'label'            => 'settings_paymentmethod_currencies',
                    'default_value'    => 'USD, EUR, GBP',
                ],
                [
                    'name'          => 'test_mode_enabled',
                    'type'          => 'yes_no',
                    'default_value' => 1,
                    'label'         => 'settings_paymentmethod_testing_mode',
                ],
            ]
        );
    }


    /**
     * REQUIRED FUNCTION
     * @param  array $data
     * @return mixed
     */
    public function process_payment($data)
    {
        $this->ci->session->set_userdata(['paddle_total' => $data['amount']]);
        redirect(site_url('paddle/payment/' . $data['invoice']->id . '/' . $data['invoice']->hash));
    }


    public function description($id)
    {
        return str_replace('{invoice_number}', format_invoice_number($id),  $this->getSetting('description'));
    }

    public function vendor_id()
    {
        return $this->decryptSetting('paddle_vendor_id');
    }

    public function public_key()
    {
        return $this->getSetting('paddle_public_key');
    }
    
    public function auth_code()
    {
        return $this->decryptSetting('paddle_auth_code');
    }

    public function webhook($id,$hash)
    {
        return site_url('paddle/gateways/paddle_ipn/notify/'. $id .'/' . $hash);
    }
}
