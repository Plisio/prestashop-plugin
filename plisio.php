<?php
use PrestaShop\PrestaShop\Core\Payment\PaymentOption;

if (!defined('_PS_VERSION_')) {
    exit;
}

require_once _PS_MODULE_DIR_ . '/plisio/vendor/plisio/lib/PlisioClient.php';
require_once _PS_MODULE_DIR_ . '/plisio/vendor/version.php';

class Plisio extends PaymentModule
{
    private $html = '';
    private $postErrors = array();

    public $api_auth_token;

    public function __construct()
    {
        $this->name = 'plisio';
        $this->tab = 'payments_gateways';
        $this->version = PLISIO_PRESTASHOP_EXTENSION_VERSION;
        $this->author = 'plugins@plisio.net';
        $this->is_eu_compatible = 1;
        $this->controllers = array('payment', 'redirect', 'callback', 'cancel');
        $this->ps_versions_compliancy = array('min' => '1.6', 'max' => _PS_VERSION_);

        $this->currencies = true;
        $this->currencies_mode = 'checkbox';

        $this->bootstrap = true;

        $config = Configuration::getMultiple(
            array(
                'PLISIO_API_AUTH_TOKEN'
            )
        );

        if (!empty($config['PLISIO_API_AUTH_TOKEN'])) {
            $this->api_auth_token = $config['PLISIO_API_AUTH_TOKEN'];
        }

        parent::__construct();

        $this->displayName = $this->l('Accept Cryptocurrencies with Plisio');
        $this->description = $this->l('Accept Bitcoin and other cryptocurrencies as a payment method with Plisio');
        $this->confirmUninstall = $this->l('Are you sure you want to delete your details?');

        if (!isset($this->api_auth_token)) {
            $this->warning = $this->l('API Access details must be configured in order to use this module correctly.');
        }
    }

    public function install()
    {
        if (!function_exists('curl_version')) {
            $this->_errors[] = $this->l('This module requires cURL PHP extension in order to function normally.');

            return false;
        }

        $order_pending = new OrderState();
        $order_pending->name = array_fill(0, 10, 'Awaiting Plisio payment');
        $order_pending->send_email = 0;
        $order_pending->invoice = 0;
        $order_pending->color = 'RoyalBlue';
        $order_pending->unremovable = false;
        $order_pending->logable = 0;

        $order_expired = new OrderState();
        $order_expired->name = array_fill(0, 10, 'Plisio payment expired');
        $order_expired->send_email = 0;
        $order_expired->invoice = 0;
        $order_expired->color = '#DC143C';
        $order_expired->unremovable = false;
        $order_expired->logable = 0;

        $order_confirming = new OrderState();
        $order_confirming->name = array_fill(0, 10, 'Awaiting Plisio payment confirmations');
        $order_confirming->send_email = 0;
        $order_confirming->invoice = 0;
        $order_confirming->color = '#d9ff94';
        $order_confirming->unremovable = false;
        $order_confirming->logable = 0;

        $order_invalid = new OrderState();
        $order_invalid->name = array_fill(0, 10, 'Plisio invoice is invalid');
        $order_invalid->send_email = 0;
        $order_invalid->invoice = 0;
        $order_invalid->color = '#8f0621';
        $order_invalid->unremovable = false;
        $order_invalid->logable = 0;

        if ($order_pending->add()) {
            copy(
                _PS_ROOT_DIR_ . '/modules/plisio/logo.png',
                _PS_ROOT_DIR_ . '/img/os/' . (int)$order_pending->id . '.gif'
            );
        }

        if ($order_expired->add()) {
            copy(
                _PS_ROOT_DIR_ . '/modules/plisio/logo.png',
                _PS_ROOT_DIR_ . '/img/os/' . (int)$order_expired->id . '.gif'
            );
        }

        if ($order_confirming->add()) {
            copy(
                _PS_ROOT_DIR_ . '/modules/plisio/logo.png',
                _PS_ROOT_DIR_ . '/img/os/' . (int)$order_confirming->id . '.gif'
            );
        }

        if ($order_invalid->add()) {
            copy(
                _PS_ROOT_DIR_ . '/modules/plisio/logo.png',
                _PS_ROOT_DIR_ . '/img/os/' . (int)$order_invalid->id . '.gif'
            );
        }

        Configuration::updateValue('PLISIO_PENDING', $order_pending->id);
        Configuration::updateValue('PLISIO_EXPIRED', $order_expired->id);
        Configuration::updateValue('PLISIO_CONFIRMING', $order_confirming->id);
        Configuration::updateValue('PLISIO_INVALID', $order_invalid->id);

        if (!parent::install()
            || !$this->registerHook('payment')
            || !$this->registerHook('paymentReturn')
            || !$this->registerHook('paymentOptions')) {
            return false;
        }

        return true;
    }

    public function uninstall()
    {
        $order_state_pending = new OrderState(Configuration::get('PLISIO_PENDING'));
        $order_state_expired = new OrderState(Configuration::get('PLISIO_EXPIRED'));
        $order_state_confirming = new OrderState(Configuration::get('PLISIO_CONFIRMING'));

        return (
            Configuration::deleteByName('PLISIO_APP_ID') &&
            Configuration::deleteByName('PLISIO_API_AUTH_TOKEN') &&
            $order_state_pending->delete() &&
            $order_state_expired->delete() &&
            $order_state_confirming->delete() &&
            parent::uninstall()
        );
    }

    private function postValidation()
    {
        if (Tools::isSubmit('btnSubmit')) {
            if (!Tools::getValue('PLISIO_API_AUTH_TOKEN')) {
                $this->postErrors[] = $this->l('Secret key is required.');
            }
        }
    }

    private function postProcess()
    {
        if (Tools::isSubmit('btnSubmit')) {
            Configuration::updateValue(
                'PLISIO_API_AUTH_TOKEN',
                $this->stripString(Tools::getValue('PLISIO_API_AUTH_TOKEN'))
            );
        }

        $this->html .= $this->displayConfirmation($this->l('Settings updated'));
    }

    private function displayPlisio()
    {
        return $this->display(__FILE__, 'infos.tpl');
    }

    private function displayPlisioInformation($renderForm)
    {
        $this->html .= $this->displayPlisio();
        $this->context->controller->addCSS($this->_path . '/views/css/tabs.css', 'all');
        $this->context->controller->addJS($this->_path . '/views/js/javascript.js', 'all');
        $this->context->smarty->assign('form', $renderForm);
        return $this->display(__FILE__, 'information.tpl');
    }

    public function getContent()
    {
        if (Tools::isSubmit('btnSubmit')) {
            $this->postValidation();
            if (!count($this->postErrors)) {
                $this->postProcess();
            } else {
                foreach ($this->postErrors as $err) {
                    $this->html .= $this->displayError($err);
                }
            }
        } else {
            $this->html .= '<br />';
        }

        $renderForm = $this->renderForm();
        $this->html .= $this->displayPlisioInformation($renderForm);

        return $this->html;
    }

    public function hookPayment($params)
    {
        if (_PS_VERSION_ >= 1.7) {
            return;
        }
         if (!$this->active) {
            return;
        }
         if (!$this->checkCurrency($params['cart'])) {
            return;
        }
         $this->smarty->assign(array(
            'this_path'     => $this->_path,
            'this_path_bw'  => $this->_path,
            'this_path_ssl' => Tools::getShopDomainSsl(true, true) . __PS_BASE_URI__ . 'modules/' . $this->name . '/',
        ));
         return $this->display(__FILE__, 'payment.tpl');
    }

    public function hookDisplayOrderConfirmation($params)
    {
        if (_PS_VERSION_ <= 1.7) {
            return;
        }

        if (!$this->active) {
            return;
        }

        if (!$this->checkCurrency($params['cart'])) {
            return;
        }

        $this->smarty->assign(array(
            'this_path' => $this->_path,
            'this_path_bw' => $this->_path,
            'this_path_ssl' => Tools::getShopDomainSsl(true, true) . __PS_BASE_URI__ . 'modules/' . $this->name . '/',
        ));

        return $this->context->smarty->fetch(__FILE__, 'payment.tpl');
    }


    public function hookPaymentReturn($params)
    {
        if (!$this->active) {
            return;
        }
        if (_PS_VERSION_ < 1.7) {
            $order = $params['objOrder'];
            $state = $order->current_state;
        } else {
            $state = $params['order']->getCurrentState();
        }
        $this->smarty->assign(array(
            'state' => $state,
            'paid_state' => (int)Configuration::get('PS_OS_PAYMENT'),
            'this_path' => $this->_path,
            'this_path_bw' => $this->_path,
            'this_path_ssl' => Tools::getShopDomainSsl(true, true) . __PS_BASE_URI__ . 'modules/' . $this->name . '/',
        ));
        return $this->display(__FILE__, 'payment_return.tpl');
    }


    public function hookPaymentOptions($params)
    {
        if (!$this->active) {
            return;
        }

        if (!$this->checkCurrency($params['cart'])) {
            return;
        }

        $newOption = new PrestaShop\PrestaShop\Core\Payment\PaymentOption();
        $newOption->setCallToActionText('Pay by Plisio cryptocurrencies')
            ->setAction($this->context->link->getModuleLink($this->name, 'redirect', array(), true));

        $payment_options = array($newOption);

        return $payment_options;
    }

    public function checkCurrency($cart)
    {
        $currency_order = new Currency($cart->id_currency);
        $currencies_module = $this->getCurrency($cart->id_currency);

        if (is_array($currencies_module)) {
            foreach ($currencies_module as $currency_module) {
                if ($currency_order->id == $currency_module['id_currency']) {
                    return true;
                }
            }
        }

        return false;
    }

    public function renderForm()
    {
        $fields_form = array(
            'form' => array(
                'legend' => array(
                    'title' => $this->l('Accept Cryptocurrencies with Plisio'),
                    'icon' => 'icon-bitcoin',
                ),
                'input' => array(
                    array(
                        'type' => 'text',
                        'label' => $this->l('Secret key'),
                        'name' => 'PLISIO_API_AUTH_TOKEN',
                        'desc' => $this->l('Your Secret key (created on Plisio)'),
                        'required' => true,
                    )
                ),
                'submit' => array(
                    'title' => $this->l('Save'),
                ),
            ),
        );

        $helper = new HelperForm();
        $helper->show_toolbar = false;
        $helper->table = $this->table;
        $lang = new Language((int)Configuration::get('PS_LANG_DEFAULT'));
        $helper->default_form_language = $lang->id;
        $helper->allow_employee_form_lang = (Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG')
            ? Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG') : 0);
        $this->fields_form = array();
        $helper->id = (int)Tools::getValue('id_carrier');
        $helper->identifier = $this->identifier;
        $helper->submit_action = 'btnSubmit';
        $helper->currentIndex = $this->context->link->getAdminLink('AdminModules', false)
            . '&configure=' . $this->name . '&tab_module='
            . $this->tab . '&module_name=' . $this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');
        $helper->tpl_vars = array(
            'fields_value' => $this->getConfigFieldsValues(),
            'languages' => $this->context->controller->getLanguages(),
            'id_language' => $this->context->language->id,
        );

        return $helper->generateForm(array($fields_form));
    }

    public function getConfigFieldsValues()
    {
        return array(
            'PLISIO_API_AUTH_TOKEN' => $this->stripString(Tools::getValue(
                'PLISIO_API_AUTH_TOKEN',
                Configuration::get('PLISIO_API_AUTH_TOKEN')
            ))
        );
    }

    private function stripString($item)
    {
        return preg_replace('/\s+/', '', $item);
    }
}
