<?php

require_once(_PS_MODULE_DIR_ . '/plisio/vendor/plisio/init.php');
require_once(_PS_MODULE_DIR_ . '/plisio/vendor/version.php');

class PlisioRedirectModuleFrontController extends ModuleFrontController
{
    public $ssl = true;

    public function initContent()
    {
        parent::initContent();

        $cart = $this->context->cart;

        if (!$this->module->checkCurrency($cart)) {
            Tools::redirect('index.php?controller=order');
        }

        $total = (float)number_format($cart->getOrderTotal(true, 3), 2, '.', '');
        $currency = Context::getContext()->currency;

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

        $auth_token = Configuration::get('PLISIO_API_AUTH_TOKEN');

        $plConfig = array(
          'auth_token' => $auth_token,
          'user_agent' => 'Plisio - Prestashop v'._PS_VERSION_.' Extension v'.PLISIO_PRESTASHOP_EXTENSION_VERSION
        );

        \Plisio\Plisio::config($plConfig);

        $order = \Plisio\Merchant\Order::create(array(
            'order_number'     => $cart->id,
            'order_name'       => Configuration::get('PS_SHOP_NAME') . ' Order #' . $cart->id,
            'source_amount'    => $total,
            'source_currency'  => $currency->iso_code,
            'allowed_psys_cids' => Configuration::get('PLISIO_RECEIVE_CURRENCY'),
            'currency' => explode(',' , Configuration::get('PLISIO_RECEIVE_CURRENCY'))[0],
            'cancel_url'       => $this->context->link->getModuleLink('plisio', 'cancel'),
            'callback_url'     => $this->context->link->getModuleLink('plisio', 'callback'),
            'success_url'      => $success_url,
            'description'      => join($description, ', '),
            'plugin' => 'PrestaShop',
            'version' => '1.0.0',
            'api_key' => $auth_token,
            'email' => $this->context->customer->email,
        ));

        if ($order) {
            if (!$order->data['invoice_url']) {
                Tools::redirect('index.php?controller=order&step=3');
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

            Tools::redirect($order->data['invoice_url']);
        } else {
            Tools::redirect('index.php?controller=order&step=3');
        }
    }
}
