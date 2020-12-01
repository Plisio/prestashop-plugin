<?php

require_once(_PS_MODULE_DIR_ . '/plisio/vendor/plisio/init.php');
require_once(_PS_MODULE_DIR_ . '/plisio/vendor/version.php');

class PlisioCallbackModuleFrontController extends ModuleFrontController
{
    public $ssl = true;

    public function postProcess()
    {
        $cart_id = (int)Tools::getValue('order_number');
        $order_id = Order::getOrderByCartId($cart_id);
        $order = new Order($order_id);

        try {
            if (!$order) {
                $error_message = 'Plisio Order #' . Tools::getValue('order_number') . ' does not exists';

                $this->logError($error_message, $cart_id);
                throw new Exception($error_message);
            }

            $auth_token = Configuration::get('PLISIO_API_AUTH_TOKEN');

            $plConfig = array(
                'auth_token' => $auth_token,
                'user_agent' => 'Plisio - Prestashop v' . _PS_VERSION_
                    . ' Extension v' . PLISIO_PRESTASHOP_EXTENSION_VERSION
            );

            \Plisio\Plisio::config($plConfig);
            $plOrder = $_POST;

            if (!$plOrder) {
                $error_message = 'Plisio Order #' . Tools::getValue('order_number') . ' does not exists';

                $this->logError($error_message, $cart_id);
                throw new Exception($error_message);
            }

            if ($order->id_cart != $plOrder['order_number']) {
                $error_message = 'Plisio Order and PS cart does not match';

                $this->logError($error_message, $cart_id);
                throw new Exception($error_message);
            }


            switch ($plOrder['status']) {
                case 'mismatch':
                case 'completed':
                    $order_status = 'PS_OS_PAYMENT';
                    break;
                case 'new':
                    $order_status = 'PLISIO_PENDING';
                    break;
                case 'pending':
                    $order_status = 'PLISIO_CONFIRMING';
                    break;
                case 'expired':
                    $order_status = 'PLISIO_EXPIRED';
                    break;
                case 'error':
                    $order_status = 'PLISIO_INVALID';
                    break;
                case 'cancelled':
                    $order_status = 'PS_OS_CANCELED';
                    break;
                default:
                    $order_status = false;
            }

            if ($order_status !== false) {
                $history = new OrderHistory();
                $history->id_order = $order->id;
                $history->changeIdOrderState((int)Configuration::get($order_status), $order->id);
                $history->addWithemail(true, array(
                    'order_name' => Tools::getValue('order_number'),
                ));

                $this->context->smarty->assign(array(
                    'text' => 'OK'
                ));
            } else {
                $this->context->smarty->assign(array(
                    'text' => 'Order Status ' . $plOrder['status'] . ' not implemented'
                ));
            }
        } catch (Exception $e) {
            $this->context->smarty->assign(array(
                'text' => get_class($e) . ': ' . $e->getMessage()
            ));
        }
        if (_PS_VERSION_ >= '1.7') {
            $this->setTemplate('module:plisio/views/templates/front/payment_callback.tpl');
        } else {
            $this->setTemplate('payment_callback.tpl');
        }
    }

    private function logError($message, $cart_id)
    {
        PrestaShopLogger::addLog($message, 3, null, 'Cart', $cart_id, true);
    }
}
