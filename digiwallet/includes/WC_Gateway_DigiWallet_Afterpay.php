<?php
/*DigiWallet Afterpay Payment Gateway Class */
class WC_Gateway_DigiWallet_Afterpay extends WC_Gateway_DigiWallet
{
    protected $payMethodId = "AFP";
    protected $payMethodName = "Afterpay";
    protected $maxAmount = 10000;
    protected $minAmount = 5;
    public $enabled = true;
    private $array_tax = [1 => 21, 2 => 6, 3 => 0, 4 => 'none'];
    /**
     * return method description
     * {@inheritDoc}
     * @see WC_Gateway_DigiWallet::getDigiWalletMethodOption()
     * @return string
     */
    protected function getDigiWalletMethodOption()
    {
        return 'Afterpay';
    }

    /**
     * Detech woocomerce verion code
     * @return mixed|NULL
     */
    private function get_woo_version_number()
    {
        if ( ! function_exists( 'get_plugins' ) ){
            require_once( ABSPATH . 'wp-admin/includes/plugin.php' );
        }
        $plugin_folder = get_plugins( '/' . 'woocommerce' );
        $plugin_file = 'woocommerce.php';

        if ( isset( $plugin_folder[$plugin_file]['Version'] ) ) {
            return $plugin_folder[$plugin_file]['Version'];
        } else {
            return NULL;
        }
    }
    /**
     * Check to show/hide this payment method from checkout page
     * {@inheritDoc}
     * @see WC_Payment_Gateway::is_available()
     */
    public function is_available()
    {
        return $this->enabled == "yes" && !empty($this->rtlo);
    }
    /**
     *  Bind parameters
     */
    public function additionalParameters(WC_Order $order, DigiWalletCore $digiWallet)
    {
        $woo_version = $this->get_woo_version_number();
        // Check woocommerce version for order API
        if($woo_version < 3.0){
            $shipping_only = false;
            if(WC()->cart->needs_shipping_address()) {
                // If cart have set to another address for shipping
                // Check if address was provided
                $shipping_only = (!empty($order->shipping_first_name) && !empty($order->shipping_last_name));
            }
            $billingCountry = (strtoupper($order->billing_country) == 'BE' ? 'BEL' : 'NLD');
            $shippingCountry = (strtoupper($shipping_only ? $order->shipping_country : $order->billing_country) == 'BE' ? 'BEL' : 'NLD');
            
            $streetParts = self::breakDownStreet($order->billing_address_1);
            $digiWallet->bindParam('billingstreet', empty($streetParts['street']) ? $order->billing_address_1 : $streetParts['street']);
            $digiWallet->bindParam('billinghousenumber', empty($streetParts['houseNumber'].$streetParts['houseNumberAdd']) ? $order->billing_address_1 : $streetParts['houseNumber'] . ' ' .$streetParts['houseNumberAdd']);
            $digiWallet->bindParam('billingpostalcode', $order->billing_postcode);
            $digiWallet->bindParam('billingcity', $order->billing_city);
            $digiWallet->bindParam('billingpersonemail', $order->billing_email);
            $digiWallet->bindParam('billingpersoninitials', "");
            $digiWallet->bindParam('billingpersongender', "");
            $digiWallet->bindParam('billingpersonbirthdate', "");
            $digiWallet->bindParam('billingpersonfirstname', $order->billing_first_name);
            $digiWallet->bindParam('billingpersonsurname', $order->billing_last_name);
            $digiWallet->bindParam('billingcountrycode', $billingCountry);
            $digiWallet->bindParam('billingpersonlanguagecode', $billingCountry);
            $digiWallet->bindParam('billingpersonphonenumber', self::format_phone($billingCountry, $order->billing_phone));
            
            $address_result = $shipping_only ? $order->shipping_address_1 : $order->billing_address_1;
            $$streetParts = self::breakDownStreet($address_result);
            
            $digiWallet->bindParam('shippingstreet', empty($streetParts['street']) ? $address_result : $streetParts['street']);
            $digiWallet->bindParam('shippinghousenumber', empty($streetParts['houseNumber'].$streetParts['houseNumberAdd']) ? $address_result : $streetParts['houseNumber'] . ' ' . $streetParts['houseNumberAdd']);
            $digiWallet->bindParam('shippingpostalcode', $shipping_only ? $order->shipping_postcode : $order->billing_postcode);
            $digiWallet->bindParam('shippingcity', $shipping_only ? $order->shipping_city : $order->billing_city);
            $digiWallet->bindParam('shippingpersonemail', $shipping_only ? $order->shipping_email : $order->billing_email);
            $digiWallet->bindParam('shippingpersoninitials', "");
            $digiWallet->bindParam('shippingpersongender', "");
            $digiWallet->bindParam('shippingpersonbirthdate', "");
            $digiWallet->bindParam('shippingpersonfirstname', $shipping_only ? $order->shipping_first_name : $order->billing_first_name);
            $digiWallet->bindParam('shippingpersonsurname', $shipping_only ? $order->shipping_last_name : $order->billing_last_name);
            $digiWallet->bindParam('shippingcountrycode', $shippingCountry);
            $digiWallet->bindParam('shippingpersonlanguagecode', $shippingCountry);
            $digiWallet->bindParam('shippingpersonphonenumber', self::format_phone($shippingCountry, $shipping_only ? $order->shipping_phone : $order->billing_phone));
        } else {
            // For woocommerce from 3.0+
            $shipping_only = false;
            if(WC()->cart->needs_shipping_address()) {
                // If cart have set to another address for shipping
                // Check if address was provided
                $shipping_only = (!empty($order->get_shipping_first_name()) && !empty($order->get_shipping_last_name()));
            }
            
            $billingCountry = (strtoupper($order->get_billing_country()) == 'BE' ? 'BEL' : 'NLD');
            $shippingCountry = (strtoupper($shipping_only ? $order->get_shipping_country() : $order->get_billing_country()) == 'BE' ? 'BEL' : 'NLD');
            
            $streetParts = self::breakDownStreet($order->get_billing_address_1());
            
            $digiWallet->bindParam('billingstreet', empty($streetParts['street']) ? $order->get_billing_address_1() : $streetParts['street']);
            $digiWallet->bindParam('billinghousenumber', empty($streetParts['houseNumber'].$streetParts['houseNumberAdd']) ? $order->get_billing_address_1() : $streetParts['houseNumber'].$streetParts['houseNumberAdd']);
            $digiWallet->bindParam('billingpostalcode', $order->get_billing_postcode());
            $digiWallet->bindParam('billingcity', $order->get_billing_city());
            $digiWallet->bindParam('billingpersonemail', $order->get_billing_email());
            $digiWallet->bindParam('billingpersoninitials', "");
            $digiWallet->bindParam('billingpersongender', "");
            $digiWallet->bindParam('billingpersonbirthdate', "");
            $digiWallet->bindParam('billingpersonfirstname', $order->get_billing_first_name());
            $digiWallet->bindParam('billingpersonsurname', $order->get_billing_last_name());
            $digiWallet->bindParam('billingcountrycode', $billingCountry);
            $digiWallet->bindParam('billingpersonlanguagecode', $billingCountry);
            $digiWallet->bindParam('billingpersonphonenumber', self::format_phone($billingCountry, $order->get_billing_phone()));
            
            $address_result = $shipping_only ? $order->get_shipping_address_1() : $order->get_billing_address_1();
            $streetParts = self::breakDownStreet($address_result);
            
            $digiWallet->bindParam('shippingstreet', empty($streetParts['street']) ? $address_result : $streetParts['street']);
            $digiWallet->bindParam('shippinghousenumber', empty($streetParts['houseNumber'].$streetParts['houseNumberAdd']) ? $address_result : $streetParts['houseNumber'].$streetParts['houseNumberAdd']);
            $digiWallet->bindParam('shippingpostalcode', $shipping_only ? $order->get_shipping_postcode() : $order->get_billing_postcode());
            $digiWallet->bindParam('shippingcity', $shipping_only ? $order->get_shipping_city() : $order->get_billing_city());
            $digiWallet->bindParam('shippingpersonemail', $order->get_billing_email());
            $digiWallet->bindParam('shippingpersoninitials', "");
            $digiWallet->bindParam('shippingpersongender', "");
            $digiWallet->bindParam('shippingpersonbirthdate', "");
            $digiWallet->bindParam('shippingpersonfirstname', $shipping_only ? $order->get_shipping_first_name() : $order->get_billing_first_name());
            $digiWallet->bindParam('shippingpersonsurname', $shipping_only ? $order->get_shipping_last_name() : $order->get_billing_last_name());
            $digiWallet->bindParam('shippingcountrycode', $shippingCountry);
            $digiWallet->bindParam('shippingpersonlanguagecode', $shippingCountry);
            $digiWallet->bindParam('shippingpersonphonenumber', self::format_phone($shippingCountry, $order->get_billing_phone()));
        }
        // Getting the items in the order
        $total_amount_by_products = 0;
        $order_items = $order->get_items();
        $invoicelines = [];
        // Iterating through each item in the order
        foreach ($order_items as $item_id => $item_data) {
            // Get the product name
            $product_name = $item_data['name'];
            // Get the item quantity
            $item_quantity = wc_get_order_item_meta($item_id, '_qty', true);
            // Get the item line total
            $item_total = wc_get_order_item_meta($item_id, '_line_total', true);
            $tax = 0;
            $tax_rates = WC_Tax::get_rates( wc_get_order_item_meta($item_id, '_tax_class', true) );
            foreach ($tax_rates as $tax_rate) {
                $tax += $tax_rate['rate'];
            }
            $invoicelines[] = [
                'productCode' => (string)$item_id,
                'productDescription' => $product_name,
                'quantity' => $item_quantity,
                'price' => $item_total,
                'taxCategory' => $digiWallet->getTax($tax),
            ];
            $total_amount_by_products += $item_total;
        }
        $invoicelines[] = [
            'productCode' => '000000',
            'productDescription' => "Other fees (shipping, additional fees)",
            'quantity' => 1,
            'price' => $order->order_total - $total_amount_by_products,
            'taxCategory' => 1
        ];
        $digiWallet->bindParam('invoicelines', json_encode($invoicelines));
        $digiWallet->bindParam('userip', $_SERVER["REMOTE_ADDR"]);
    }
    
    private static function format_phone($country, $phone) {
        $function = 'format_phone_' . strtolower($country);
        if(method_exists('WC_Gateway_DigiWallet_Afterpay', $function)) {
            return self::$function($phone);
        } else {
            echo "unknown phone formatter for country: ". esc_html($function);
            exit;
        }
        return $phone;
    }
    
    private static function format_phone_nld($phone) {
        // note: making sure we have something
        if(!isset($phone{3})) { return ''; }
        // note: strip out everything but numbers
        $phone = preg_replace("/[^0-9]/", "", $phone);
        $length = strlen($phone);
        switch($length) {
            case 9:
                return "+31".$phone;
                break;
            case 10:
                return "+31".substr($phone, 1);
                break;
            case 11:
            case 12:
                return "+".$phone;
                break;
            default:
                return $phone;
                break;
        }
    }
    
    private static function format_phone_bel($phone) {
        // note: making sure we have something
        if(!isset($phone{3})) { return ''; }
        // note: strip out everything but numbers
        $phone = preg_replace("/[^0-9]/", "", $phone);
        $length = strlen($phone);
        switch($length) {
            case 9:
                return "+32".$phone;
                break;
            case 10:
                return "+32".substr($phone, 1);
                break;
            case 11:
            case 12:
                return "+".$phone;
                break;
            default:
                return $phone;
                break;
        }
    }
    
    private static function breakDownStreet($street)
    {
        $out = [
            'street' => null,
            'houseNumber' => null,
            'houseNumberAdd' => null,
        ];
        $addressResult = null;
        preg_match("/(?P<address>\D+) (?P<number>\d+) (?P<numberAdd>.*)/", $street, $addressResult);
        if (! $addressResult) {
            preg_match("/(?P<address>\D+) (?P<number>\d+)/", $street, $addressResult);
        }
        if (empty($addressResult)) {
            $out['street'] = $street;
            
            return $out;
        }
        
        $out['street'] = array_key_exists('address', $addressResult) ? $addressResult['address'] : null;
        $out['houseNumber'] = array_key_exists('number', $addressResult) ? $addressResult['number'] : null;
        $out['houseNumberAdd'] = array_key_exists('numberAdd', $addressResult) ? trim(strtoupper($addressResult['numberAdd'])) : null;
        
        return $out;
    }
} // End Class
