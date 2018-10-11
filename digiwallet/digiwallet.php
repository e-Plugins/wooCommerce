<?php

/**
 * DigiWallet WooCommerce payment module
 *
 * @author DigiWallet.nl <techsupport@targetmedia.nl>
 * @license http://opensource.org/licenses/GPL-3.0 GNU General Public License, version 3 (GPL-3.0)
 * @copyright Copyright (C) 2018 e-plugins.nl
 *
 * Plugin Name: DigiWallet for WooCommerce
 * Plugin URI: https://www.digiwallet.nl
 * Description: Activates iDEAL, Bancontact, Sofort Banking, Visa / Mastercard Credit cards, PaysafeCard, AfterPay, BankWire, PayPal and Refunds in WooCommerce
 * Author: DigiWallet.nl
 * Author URI: https://www.digiwallet.nl
 * Version: 5.0.4
 * 10-09-2018 renaming from targetpay to digiwallet.
 */
define('DIGIWALLET_TABLE_NAME', 'woocommerce_digiwallet');

if (! in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))) {
    return;
}

if (! class_exists('DigiWalletInstall')) {
    require_once (realpath(dirname(__FILE__)) . '/includes/install.php');
}
// create db when active plugin
register_activation_hook(__FILE__, array(
    'DigiWalletInstall', 
    'install_db'));

/**
 * Hook refund action from CMS
 *
 * @param unknown $refund_id            
 * @param unknown $order_id            
 */
function tm_woocommerce_refund_deleted($refund_id, $order_id)
{
    $order = wc_get_order($order_id);
    
    $tmGateway = new WC_Gateway_DigiWallet_Bancontact();
    if (! $tmGateway->can_refund_order($order)) {
        $tmGateway->log('Refund Failed: No transaction ID.', 'error');
        wp_die();
    }
    
    $extOrder = $tmGateway->getExtOrder($order_id, $order->get_transaction_id());
    
    $digiWallet = new DigiWalletCore($extOrder->paymethod, $extOrder->rtlo);
    
    if (! $digiWallet->deleteRefund($tmGateway->token, $extOrder->paymethod, $order->get_transaction_id())) {
        $tmGateway->log('Delete Refund Failed API: #refund_id:' . $refund_id . ' #Reasons' . $digiWallet->getErrorMessage(), 'error');
        $order->add_order_note('Delete Refund Failed API: #refund_id:' . $refund_id . ' #Reasons' . $digiWallet->getErrorMessage() . '<br>#This refund operation might need to perform manually');
    }
}

add_action('woocommerce_refund_deleted', 'tm_woocommerce_refund_deleted', 10, 2);
add_action('plugins_loaded', 'init_digiwallet_class', 0);
add_action('plugins_loaded', 'wan_load_textdomain');
add_action( 'admin_enqueue_scripts', 'admin_enqueue_scripts');

function init_digiwallet_class()
{
    // If the parent WC_Payment_Gateway class doesn't exist
    // it means WooCommerce is not installed on the site
    // so do nothing
    if (! class_exists('WC_Payment_Gateway')) {
        return;
    }
    
    if (! class_exists('WC_Gateway_DigiWallet')) {
        require_once (realpath(dirname(__FILE__)) . '/includes/WC_Gateway_DigiWallet.php');
    }
    if (! class_exists('DigiWalletCore')) {
        require_once (realpath(dirname(__FILE__)) . '/includes/digiwallet.class.php');
    }
    // If we made it this far, then include our Gateway Class
    include_once (realpath(dirname(__FILE__)) . '/includes/WC_Gateway_DigiWallet_iDEAL.php');
    include_once (realpath(dirname(__FILE__)) . '/includes/WC_Gateway_DigiWallet_Bancontact.php');
    include_once (realpath(dirname(__FILE__)) . '/includes/WC_Gateway_DigiWallet_Sofort.php');
    include_once (realpath(dirname(__FILE__)) . '/includes/WC_Gateway_DigiWallet_Creditcard.php');
    include_once (realpath(dirname(__FILE__)) . '/includes/WC_Gateway_DigiWallet_Paysafecard.php');
    include_once (realpath(dirname(__FILE__)) . '/includes/WC_Gateway_DigiWallet_Afterpay.php');
    include_once (realpath(dirname(__FILE__)) . '/includes/WC_Gateway_DigiWallet_Bankwire.php');
    include_once (realpath(dirname(__FILE__)) . '/includes/WC_Gateway_DigiWallet_Paypal.php');
    
    // Now that we have successfully included our class,
    // Lets add it too WooCommerce
    add_filter('woocommerce_payment_gateways', 'add_target_payment_gateway');
}

function add_target_payment_gateway($methods)
{
    $methods[] = 'WC_Gateway_DigiWallet_iDEAL';
    $methods[] = 'WC_Gateway_DigiWallet_Bancontact';
    $methods[] = 'WC_Gateway_DigiWallet_Sofort';
    $methods[] = 'WC_Gateway_DigiWallet_Creditcard';
    $methods[] = 'WC_Gateway_DigiWallet_Paysafecard';
    $methods[] = 'WC_Gateway_DigiWallet_Bankwire';
    $methods[] = 'WC_Gateway_DigiWallet_Afterpay';
    $methods[] = 'WC_Gateway_DigiWallet_Paypal';
    
    return $methods;
}

function admin_enqueue_scripts() {
    wp_enqueue_style( 'wc-gateway-target-pay', plugin_dir_url( __FILE__ ) . '/assets/css/digiwallet_admin.css' );
}

function wan_load_textdomain() {
    load_plugin_textdomain( 'digiwallet', false, dirname( plugin_basename(__FILE__) ) . '/languages/' );
}
