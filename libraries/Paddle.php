<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Two_checkout_gateway extends App_gateway
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
        $this->setId('two_checkout');

        /**
         * REQUIRED
         * Gateway name
         */
        $this->setName('2Checkout');

        /**
         * Add gateway settings
         */
        $this->setSettings(
            [
                [
                    'name'      => 'merchant_code',
                    'encrypted' => true,
                    'label'     => 'two_checkout_merchant_code',
                ],
                [
                    'name'      => 'secret_key',
                    'encrypted' => true,
                    'label'     => 'two_checkout_secret_Key',
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
        $this->ci->session->set_userdata(['two_checkout_total' => $data['amount']]);
        redirect(site_url('gateways/two_checkout/payment/' . $data['invoice']->id . '/' . $data['invoice']->hash));
    }


    public function description($id)
    {
        return str_replace('{invoice_number}', format_invoice_number($id),  $this->getSetting('description'));
    }

    public function merchant_code()
    {
        return $this->decryptSetting('merchant_code');
    }

    public function secret_key()
    {
        return $this->decryptSetting('secret_key');
    }
}
