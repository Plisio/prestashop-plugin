<?php

require_once _PS_MODULE_DIR_ . '/plisio/vendor/plisio/lib/PlisioClient.php';
require_once(_PS_MODULE_DIR_ . '/plisio/vendor/version.php');

class PlisioRedirectModuleFrontController extends ModuleFrontController
{
    public $ssl = true;
    private $plisio;

    private function get_plisio_receive_currencies ($source_currency) {
        $currencies = $this->plisio->getCurrencies($source_currency);
        return array_reduce($currencies, function ($acc, $curr) {
            $acc[$curr['cid']] = $curr;
            return $acc;
        }, []);
    }

    public function initContent()
    {
        parent::initContent();

        $this->plisio = new PlisioClient(Configuration::get('PLISIO_API_AUTH_TOKEN'));

        $cart = $this->context->cart;

        if (!$this->module->checkCurrency($cart)) {
            Tools::redirect('index.php?controller=order');
        }

        $total = (float)number_format($cart->getOrderTotal(true, 3), 2, '.', '');
        $currency = Context::getContext()->currency;
        $plisio_receive_currencies = $this->get_plisio_receive_currencies($currency->iso_code);
        $plisio_receive_cids = array_keys($plisio_receive_currencies);

        $description = array();

        foreach ($cart->getProducts() as $product) {
            $description[] = $product['cart_quantity'] . ' × ' . $product['name'];
        }

        $customer = new Customer($cart->id_customer);

        $link = new Link();
        $success_url = $link->getPageLink('order-confirmation', null, null, array(
          'id_cart'     => $cart->id,
          'id_module'   => $this->module->id,
          'key'         => $customer->secure_key
        ));

        $request = array(
            'order_number'     => $cart->id,
            'order_name'       => Configuration::get('PS_SHOP_NAME') . ' Order #' . $cart->id,
            'source_amount'    => $total,
            'source_currency'  => $currency->iso_code,
            'currency'         => $plisio_receive_cids[0],
            'cancel_url'       => $this->context->link->getModuleLink('plisio', 'cancel'),
            'callback_url'     => $this->context->link->getModuleLink('plisio', 'callback'),
            'success_url'      => $success_url,
            'description'      => join($description, ', '),
            'plugin' => 'PrestaShop',
            'version' => PLISIO_PRESTASHOP_EXTENSION_VERSION,
            'email' => $this->context->customer->email,
        );

        $response = $this->plisio->createTransaction($request);

        if ($response) {
            if (empty($response['data']['invoice_url'])) {
                $this->errors[] = $this->l('Error occurred while processing the payment:  ' . json_decode($response['data']['message'], true)['amount']);
                $this->redirectWithNotifications('index.php?controller=order&step=3');
            }

            $customer = new Customer($cart->id_customer);
            $this->module->validateOrder(
                $cart->id,
                Configuration::get('PLISIO_PENDING'),
                $total,
                $this->module->displayName,
                null,
                null,
                (int)$currency->id,
                false,
                $customer->secure_key
            );

            Tools::redirect($response['data']['invoice_url']);
        } else {
            $this->errors[] = $this->l('Error occurred while processing the payment:  ' . $response['data']['message']);
            $this->redirectWithNotifications('index.php?controller=order&step=3');
        }
    }
}
