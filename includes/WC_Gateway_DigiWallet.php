<?php

/* DigiWallet Payment Gateway Class */
abstract class WC_Gateway_DigiWallet extends WC_Payment_Gateway
{
    const WOO_ORDER_STATUS_PENDING = 'pending';

    const WOO_ORDER_STATUS_COMPLETED = 'completed';

    const WOO_ORDER_STATUS_PROCESSING = 'processing';

    const WOO_ORDER_STATUS_FAILED = 'failed';

    const WOO_ORDER_STATUS_ON_HOLD = 'on-hold';

    public static $log_enabled = true;

    /** @var WC_Logger Logger instance */
    public static $log = false;

    protected $payMethodId;

    protected $payMethodName;

    protected $maxAmount;

    protected $minAmount;

    public $list_success_status;

    public $enabled = true;

    public $enabledDescription = null;

    public $enabledErrorMessage = null;

    public $language = 'nl';
    public $has_fields = true;

    protected $defaultRtlo = "12345";
    protected $defaultApiKey = "api-key";

    /**
     * Setup our Gateway's id, description and other values.
     */
    public function __construct()
    {
        // The global ID for this Payment method
        $this->id = strtolower("DigiWallet_{$this->payMethodId}");
        $this->supports = array(
            'products', 
            'refunds');
        
        $this->setLanguage();
        $this->setListSuccessStatus();
        // This basically defines your settings which are then loaded with init_settings()
        $this->init_form_fields();
        
        // After init_settings() is called, you can get the settings and load them into variables
        $this->init_settings();
        foreach ($this->settings as $setting_key => $value) {
            $this->$setting_key = wc_clean($value);
        }
        
        // check if method valid to show in FE
        if (! $this->is_valid_for_use()) {
            $this->enabled = false;
        }
        
        // the description show in payment method(Text || payment option)
        $this->description = $this->getDigiWalletMethodOption();
        
        // The Title shown on the top of the Payment Gateways Page next to all the other Payment Gateways
        $this->method_title = $this->payMethodName;
        
        $this->method_description = __('You can enable test-mode for your outlet from your DigiWallet Organization Dashboard to test your payments through the DigiWallet Test Panel.', 'digiwallet');
        
        // The title to be used for the vertical tabs that can be ordered top to bottom
        $this->title = $this->payMethodName;
        
        // If you want to show an image next to the gateway's name on the frontend, enter a URL to an image.
        $this->icon = plugins_url('../', __FILE__) . '/assets/images/' . $this->payMethodId . '_24.png';
        
        // Bool. Can be set to true if you want payment fields to show on the checkout
        // if doing a direct integration, which we are doing in this case
        $this->has_fields = true;
        
        // Lets check for SSL
        /*add_action('admin_notices', array(
            $this, 
            'do_ssl_check'));*/
        // check response by method POST - report url
        add_action('woocommerce_api_wc_gateway_digiwallet' . strtolower($this->payMethodId) . 'report', array(
            $this, 
            'check_digiwallet_report'));
        // check response by method GET - return url
        add_action('woocommerce_api_wc_gateway_digiwallet'. strtolower($this->payMethodId) .'return', array(
            $this,
            'check_digiwallet_return'
        ));
        
        // Save settings
        if (is_admin()) {
            // Versions over 2.0
            // Save our administration options. Since we are not going to be doing anything special
            // we have not defined 'process_admin_options' in this class so the method in the parent
            // class will be used instead
            add_action('woocommerce_update_options_payment_gateways_' . $this->id, array(
                $this, 
                'process_admin_options'
            ));
        }
    }

    public function get_description(){
        return $this->description;
    }


    /**
     * Build the administration fields for this specific Gateway.
     *
     * {@inheritdoc}
     *
     * @see WC_Settings_API::init_form_fields()
     */
    public function init_form_fields()
    {
        $this->form_fields = array(
            'enabled' => array(
                'title' => __('Enable / Disable', 'digiwallet'), 
                'label' => __('Enable this payment gateway', 'digiwallet'), 
                'type' => 'checkbox', 
                'default' => $this->enabled ? 'yes' : 'no', 
                'description' => $this->enabledDescription ? __($this->enabledDescription, 'digiwallet') : null), 
            'rtlo' => array(
                'title' => __('Digiwallet Outlet Identifier', 'digiwallet'),
                'type' => 'text',
                'description' => __('Your Digiwallet Outlet Identifier, You can find this in your organization dashboard under Websites & Outlets on <a href="https://www.digiwallet.nl" target="_blank">https://www.digiwallet.nl</a>', 'digiwallet'),
                'default' => $this->defaultRtlo, // Default Digiwallet Outlet Identifier
                'desc_tip' => false,
                'placeholder' => __('Digiwallet Outlet Identifier', 'digiwallet'),
            ),
            'token' => array(
                'title' => __('Digiwallet token', 'digiwallet'), 
                'type' => 'text', 
                'description' => __('Obtain a token from <a href="http://digiwallet.nl" target="_blank">http://digiwallet.nl</a>', 'digiwallet'), 
                'default' => $this->defaultApiKey,  // Default ApiKey
                'desc_tip' => false, 
                'placeholder' => 'Token'),
            'orderStatus' => array(
                'title' => __('Status after payment is received', 'digiwallet'), 
                'class' => 'tp-select', 
                'type' => 'select', 
                'description' => __('Choose whether you wish to set payment status after received.', 'digiwallet'), 
                'default' => self::WOO_ORDER_STATUS_COMPLETED, 
                'options' => $this->list_success_status));
    }

    /**
     * Submit payment and handle response
     *
     * {@inheritdoc}
     *
     * @see WC_Payment_Gateway::process_payment()
     */
    public function process_payment($order_id, $retry = true)
    {
        global $woocommerce, $wpdb;
        
        $DigiWalletTable = $this->getDigiWalletTableName();
        
        $order = new WC_Order($order_id);
        $orderID = $order->get_id();
        $amount = $order->get_total();
        if ($amount < $this->minAmount) {
            $message = sprintf(__('The total amount is lower than the minimum of %s euros for %s', 'digiwallet'), $this->minAmount, $this->payMethodName);
            wc_add_notice($message, $notice_type = 'error');
            $order->add_order_note($message);
            
            return false;
        }
        
        if ($amount > $this->maxAmount) {
            $message = sprintf(__('The total amount is higher than the maximum of %s euros for %s', 'digiwallet'), $this->maxAmount, $this->payMethodName);
            wc_add_notice($message, $notice_type = 'error');
            $order->add_order_note($message);
            
            return false;
        }
        
        $digiWallet = new DigiWalletCore($this->payMethodId, $this->rtlo, $this->language);
        $digiWallet->setAmount(round($amount * 100));
        $digiWallet->setDescription('Order ' . $order->get_order_number()); // $order->id
        // set return & report & cancel url
        $digiWallet->setReturnUrl(add_query_arg(array(
            'wc-api' => 'WC_Gateway_DigiWallet'. $this->payMethodId .'Return',
            'od' => $orderID
        ), home_url('/')));
        
        $digiWallet->setReportUrl(add_query_arg(array(
            'wc-api' => 'WC_Gateway_DigiWallet' . $this->payMethodId . 'Report', 
            'od' => $orderID
        ), home_url('/')));
        // Add additional parameters
        $this->additionalParameters($order, $digiWallet);
        
        $url = $digiWallet->startPayment();
        
        if (! $url) {
            $message = $digiWallet->getErrorMessage();
            wc_add_notice($message, $notice_type = 'error');
            $order->add_order_note("Payment could not be started: {$message}");
            
            return false;
        } else {
            $insert = $wpdb->insert($DigiWalletTable, array(
                'cart_id' => esc_sql($order->get_order_number()), 
                'site_id' => get_current_blog_id(),
                'order_id' => esc_sql($order->get_id()),
                'rtlo' => esc_sql($this->rtlo), 
                'paymethod' => esc_sql($this->payMethodId), 
                'transaction_id' => esc_sql($digiWallet->getTransactionId()), 
                'more' => esc_sql($digiWallet->getMoreInformation())), array(
                '%s', 
                '%d', 
                '%d', 
                '%s', 
                '%s', 
                '%s', 
                '%s'));
            if (! $insert) {
                $message = "Payment could not be started: can not insert into digiwallet table";
                wc_add_notice($message, $notice_type = 'error');
                $order->add_order_note($message);
                
                return false;
            }
            return $this->redirectAfterStart($url, $order, $digiWallet);
        }
    }

    /**
     * Update order (if report not working) && show payment result.
     * note: paypalid use to get paypalid in return
     *
     * @return mixed
     */
    public function check_digiwallet_return()
    {
        global $woocommerce, $wpdb;
        
        $orderId = ! empty($_REQUEST['od']) ? wc_clean($_REQUEST['od']) : null;
        switch ($this->payMethodId) {
            case 'AFP':
                $trxid = wc_clean($_REQUEST['invoiceID']);
                break;
            case 'PYP':
                $trxid = wc_clean($_REQUEST['paypalid']);
                break;
            default:
                $trxid = wc_clean($_REQUEST['trxid']);
        }
        if ($orderId && $trxid) {
            $order = new WC_Order($orderId);
            if ($order->post == null) {
                echo 'Order ' . esc_html($orderId) . ' not found... ';
                die();
            }
            $extOrder = $this->getExtOrder($orderId, $trxid);
            
            if ($extOrder == null) { // Oeps something wrong... Some extra debug information for Digiwallet
                echo 'Transaction not found...';
                die();
            }
            if (!in_array($order->get_status(), array_keys($this->list_success_status))) {//check order in return if status != success
                $order = $this->checkOrder($order, $extOrder);
            }
            $this->redirectAfterCheck($order, $trxid);
        }
        echo 'Order ' . esc_html($orderId) . ' not found... ';
        die();
    }

    /**
     * Process report URL
     * Update order when status = pending.
     * note: acquirerID use to get paypalid in report
     * @return none
     */
    public function check_digiwallet_report()
    {
        global $woocommerce, $wpdb;
        $orderId = ! empty($_REQUEST['od']) ? wc_clean($_REQUEST['od']) : null;
        switch ($this->payMethodId) {
            case 'AFP':
                $trxid = wc_clean($_REQUEST['invoiceID']);
                break;
            case 'PYP':
                $trxid = wc_clean($_REQUEST['acquirerID']);
                break;
            default:
                $trxid = wc_clean($_REQUEST['trxid']);
        }
//         if ( substr($_SERVER['REMOTE_ADDR'],0,10) == "89.184.168" ||
//             substr($_SERVER['REMOTE_ADDR'],0,9) == "78.152.58" ) {
            if ($orderId && $trxid) {
                $order = new WC_Order($orderId);
                $extOrder = $this->getExtOrder($orderId, $trxid);
                if (!$order || !$extOrder) {
                    die("order is not found");
                }
                //Ignore updating Woo Order if Order Status is Paid (completed, processing)
                if (in_array($order->get_status(), array_keys($this->list_success_status))) {
                    echo "order " . esc_html($orderId) . " had been done";
                    die;
                }
                $log_msg = 'Prev status= ' . esc_html($order->get_status()) . PHP_EOL;
                $this->checkOrder($order, $extOrder);
                $log_msg .= 'current status= ' . esc_html($order->get_status()) . PHP_EOL;
                $log_msg .= 'order number= ' . esc_html($orderId) . PHP_EOL;
                $log_msg .= 'Version=wc 1.2.1';
                
                if(WP_DEBUG) {
                    error_log($log_msg);
                }
                
                die($log_msg);
             }
             die("orderId || trxid is empty");
//         } else {
//             die("IP address not correct... This call is not from Digiwallet");
//         }
    }
    
    public function checkOrder(WC_Order $order, $extOrder)
    {
        $digiWallet = new DigiWalletCore($extOrder->paymethod, $extOrder->rtlo, $this->language);
        $result = $digiWallet->checkPayment($extOrder->transaction_id, $this->getAdditionParametersReport($extOrder));
        if ($result) {
            if ($extOrder->paymethod == 'BW' && $digiWallet->getBankwireAmountPaid() < $digiWallet->getBankwireAmountDue()) {
                $order->update_status(self::WOO_ORDER_STATUS_ON_HOLD,
                    "Method {$order->get_payment_method_title()}(Transaction ID $extOrder->transaction_id): " .
                    "Paid amount (" . number_format($digiWallet->getBankwireAmountPaid() / 100, 2) . ") is lower than due amount" .
                    " (" . number_format($digiWallet->getBankwireAmountDue() / 100, 2). "), so order is set to On Hold."
                );
                $order->set_transaction_id($extOrder->transaction_id);
                $order->save();
                $this->updateDigiWalletTable($order, array('message' => null));
            }
            else {
                $order->update_status($this->orderStatus, "Method {$order->get_payment_method_title()}(Transaction ID $extOrder->transaction_id): ");
                $order->set_transaction_id($extOrder->transaction_id);
                $order->save();
                $this->updateDigiWalletTable($order, array('message' => null));
            }
            do_action( 'woocommerce_payment_complete', $order->get_id());
        } else {
            $this->updateDigiWalletTable($order, array('message' => esc_sql($digiWallet->getErrorMessage())));
            $order->update_status(self::WOO_ORDER_STATUS_FAILED, "Method {$order->get_payment_method_title()}(Transaction ID $extOrder->transaction_id): ");
        }
        return $order;
    }
    
    protected function redirectAfterStart($url, WC_Order $order, DigiWalletCore $digiWallet)
    {
        return array(
            'result' => 'success', 
            'redirect' => $url);
    }

    /**
     * addition params for report
     *
     * @return array
     */
    protected function getAdditionParametersReport($extOrder)
    {
        return [];
    }

    /**
     * Update woocommerce_DigiWallet_Sales table
     *
     * @param Object $order            
     * @param array $data            
     * @return int|false The number of rows updated, or false on error.
     */
    public function updateDigiWalletTable($order, $data)
    {
        global $wpdb;
        $DigiWalletTable = $this->getDigiWalletTableName();
        return $wpdb->update($DigiWalletTable, $data, array(
            'site_id' => get_current_blog_id(),
            'order_id' => esc_sql($order->get_id())));
    }

    /**
     * Check order status and redirect to appropriate page
     *
     * @param Object $order            
     * @param Object $extOrder            
     * @return mixed
     */
    public function redirectAfterCheck($order, $trxid)
    {
        global $woocommerce;
        global $wpdb;
        
        switch ($order->status) {
            case self::WOO_ORDER_STATUS_PENDING:
                return wp_redirect(add_query_arg('wc_error', urlencode(__('The payment is under processing', 'digiwallet')), $woocommerce->cart->get_cart_url()));
                break;
            case self::WOO_ORDER_STATUS_FAILED:
                $extOrder = $this->getExtOrder($order->get_id(), $trxid);
                return wp_redirect(add_query_arg('wc_error', urlencode($extOrder->message), $woocommerce->cart->get_cart_url()));
                break;
            case self::WOO_ORDER_STATUS_COMPLETED:
            case self::WOO_ORDER_STATUS_PROCESSING:
                $woocommerce->cart->empty_cart();
                return wp_redirect($this->get_return_url($order));
                break;
        }
    }
    
    // Validate fields
    public function validate_fields()
    {
        return true;
    }

    /**
     * Return DigiWallet table name.
     *
     * @return string
     */
    public function getDigiWalletTableName()
    {
        global $wpdb;
        
        return $wpdb->base_prefix . DIGIWALLET_TABLE_NAME;
    }

    /**
     * Get order information from wp_woocommerce_DigiWallet_Sales table
     *
     * @param int $orderId            
     * @param int $trxid            
     */
    public function getExtOrder($orderId, $trxid)
    {
        global $wpdb;
        $DigiWalletTable = $this->getDigiWalletTableName();
        $sql = 'SELECT * FROM ' . $DigiWalletTable . " WHERE `site_id` = '" . get_current_blog_id() . "' AND `order_id` = '" . esc_sql($orderId) . "' AND `transaction_id` = '" . esc_sql($trxid) . "' ORDER BY `id` DESC";
        return $wpdb->get_row($sql, OBJECT);
    }

    /**
     * Plugin can only be used for payments in EURO.
     *
     * @return bool Plugin applicable
     */
    public function is_valid_for_use()
    {
        if (! in_array(get_woocommerce_currency(), apply_filters('woocommerce_digiwallet_supported_currencies', array(
            'EUR')))) {
            $this->enabledErrorMessage = __('DigiWallet does not support your store currency.', 'digiwallet');
            return false;
        }
        if (! $this->checkSqlTable()) {
            return false;
        }
        return true;
    }

    /**
     * Admin options for a payment method.
     */
    public function admin_options()
    {
        ob_start();
        $this->generate_settings_html();
        $settings = ob_get_contents();
        ob_end_clean();
        if ($this->is_valid_for_use()) {
            $logoDw = plugins_url('../', __FILE__) . '/assets/images/admin_header.png';
            $logoPayMethod = plugins_url('../', __FILE__) . '/assets/images/' . $this->payMethodId . '_60.png';
            $template = '<table class="form-table">
                 <div class="tp-method-conf">
                    <div class="tp-icon">
                    <img src="' . esc_attr($logoDw) .'">
                    </div>
                    <div class="tp-icon-method">
                    <img src="' . esc_attr($logoPayMethod) .'">
                    </div>
                </div>';
            $template .= '<div class="inline description"><p><strong>' . esc_html($this->method_description) . '</strong></p></div>';
            $template .= $settings;
            $template .= '</table>';
        } else {
            $template = '<div class="inline error"><p><strong>' . __('Gateway Disabled', 'woocommerce') . '</strong>: ' . esc_html($this->enabledErrorMessage) . '</p></div>';
        }
        echo $template;
    }

    /**
     * Checks if the mysql table is correct when it exists.
     * If not?
     * create error.
     */
    private function checkSqlTable()
    {
        global $wpdb;
        $DigiWalletTable = $this->getDigiWalletTableName();
        
        if ($wpdb->get_var("SHOW TABLES LIKE '$DigiWalletTable'") == $DigiWalletTable) {
            $dbColums = $wpdb->get_col('DESC ' . $DigiWalletTable, 0);
            $requiredColumns = array(
                'id', 
                'site_id',
                'cart_id', 
                'order_id', 
                'rtlo', 
                'paymethod', 
                'transaction_id', 
                'message', 
                'more');
            $missing = array();
            foreach ($requiredColumns as $col) {
                if (! in_array($col, $dbColums)) {
                    $missing[] = $col;
                }
            }
            
            if (count($missing)) {
                $error = '';
                $error .= '<h1 style="color:red">' . _n('WARNING: One database column is missing', 'WARNING: Multiple database columns are missing', count($missing), 'digiwallet') . '</h1>';
                if (count($missing) == 1) {
                    $error .= sprintf(__("<p>We want to inform you that one table column %s is missing in the plugin table. The plugin will <strong>not</strong> work properly.</p>", 'digiwallet'), array_shift(array_values($missing)));
                } else {
                    $error .= __('</p>We want to inform you that multiple table columns are missing in the plugin table. The plugin will <strong>not</strong> work properly. Below an overview of the missing columns</p>', 'digiwallet');
                    $error .= '<ul>';
                    foreach ($missing as $value) {
                        $error .= '<li>' . $value . '</li>';
                    }
                    $error .= '</ul>';
                }
                $this->enabledErrorMessage = $error;
                return false;
            }
            return true;
        }
        $this->enabledErrorMessage = "<h1 style='color:red'>" . sprintf(__("Table %s doesn't exists!!!", 'digiwallet'), $DigiWalletTable) . "</h1>";
        return false;
    }

    /**
     * Check if we are forcing SSL on checkout pages.
     */
    public function do_ssl_check()
    {
        if ($this->enabled == 'yes') {
            if (get_option('woocommerce_force_ssl_checkout') == 'no') {
                echo '<div class="error"><p>' . sprintf(__('<strong>%s</strong> is enabled and WooCommerce is not forcing the SSL certificate on your checkout page. Please ensure that you have a valid SSL certificate and that you are <a href="%s">forcing the checkout pages to be secured.</a>'), $this->method_title, admin_url('admin.php?page=wc-settings&tab=checkout')) . '</p></div>';
            }
        }
    }

    public function setListSuccessStatus()
    {
        $this->list_success_status = array(
            self::WOO_ORDER_STATUS_COMPLETED => __('Completed', 'digiwallet'), 
            self::WOO_ORDER_STATUS_PROCESSING => __('Processing', 'digiwallet'));
    }

    /**
     * Event handler to attach additional parameters.
     *
     * @param WC_Order $order
     *            Order info
     * @param DigiWalletCore $digiWallet
     *            Payment class to attach bindings to
     */
    public function additionalParameters(WC_Order $order, DigiWalletCore $digiWallet)
    {}

    public function setLanguage()
    {
        $this->language = strtolower(substr(get_locale(), 0, 2));
    }

    /**
     * Logging method.
     *
     * @param string $message
     *            Log message.
     * @param string $level
     *            Optional. Default 'info'.
     *            emergency|alert|critical|error|warning|notice|info|debug
     */
    public static function log($message, $level = 'info')
    {
        if (self::$log_enabled) {
            if (empty(self::$log)) {
                self::$log = wc_get_logger();
            }
            self::$log->log($level, wc_clean($message), array(
                'source' => 'digiwallet'));
        }
    }

    /**
     * Can the order be refunded via DigiWallet?
     *
     * @param WC_Order $order            
     * @return bool
     */
    public function can_refund_order($order)
    {
        return $order && $order->get_transaction_id();
    }

    /**
     * Process refund.
     *
     * If the gateway declares 'refunds' support, this will allow it to refund.
     * a passed in amount.
     *
     * @param int $order_id            
     * @param float $amount            
     * @param string $reason            
     * @return boolean True or false based on success, or a WP_Error object.
     */
    public function process_refund($order_id, $amount = null, $reason = '')
    {
        // return true; //TODO remove this once done
        $order = wc_get_order($order_id);
        
        if (! $this->can_refund_order($order)) {
            $this->log('Refund Failed: No transaction ID.', 'error');
            return new WP_Error('error', __('Refund failed: No transaction ID', 'woocommerce'));
        }
        
        $extOrder = $this->getExtOrder($order_id, $order->get_transaction_id());
        
        $dataRefund = array(
            'paymethodID' => $extOrder->paymethod, 
            'transactionID' => $order->get_transaction_id(), 
            'amount' => intval(floatval($amount) * 100), 
            'description' => $reason, 
            'internalNote' => 'Internal note - OrderId: ' . $order_id . ', Amount: ' . $amount . ', Customer Email: ' . $order->get_billing_email(), 
            'consumerName' => $order->get_billing_first_name() . ' ' . $order->get_billing_last_name());
        
        $digiWallet = new DigiWalletCore($extOrder->paymethod, $extOrder->rtlo);
        
        if (! $digiWallet->refund($this->token, $dataRefund)) {
            return new WP_Error('error', __($digiWallet->getErrorMessage(), 'woocommerce'));
        }
        
        return true;
    }

    abstract protected function getDigiWalletMethodOption();

    public function get_title(){
        return __($this->payMethodName, 'digiwallet');
    }

    public function payment_fields(){
        echo $this->getDigiWalletMethodOption();
    }
} // End class
