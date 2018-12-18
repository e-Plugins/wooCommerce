<?php
/*DigiWallet Sofort Payment Gateway Class */
class WC_Gateway_DigiWallet_Sofort extends WC_Gateway_DigiWallet
{
    protected $payMethodId = "DEB";
    protected $payMethodName = "Sofort Banking";
    protected $maxAmount = 5000;
    protected $minAmount = 0.1;
    public $enabled = true;
    
    /**
     *  Bind country ID
     */
    public function additionalParameters(WC_Order $order, DigiWalletCore $digiWallet)
    {
        if (isset($_POST["country"])) {
            $digiWallet->setCountryId(wc_clean($_POST["country"]));
        }
    }
    
    /**
     * build method option for Sofort Banking method
     * {@inheritDoc}
     * @see WC_Gateway_DigiWallet::getDigiWalletMethodOption()
     * @return string
     */
    protected function getDigiWalletMethodOption()
    {
        $html = '';
        $digiWallet = new DigiWalletCore($this->payMethodId);
        $temp = $digiWallet->getCountryList();
        $html .= '<select name="country" style="width:220px; padding: 2px; margin-left: 7px">';
        foreach ($temp as $key => $value) {
            $html .= '<option value="'.esc_attr($key).'">'.esc_html($value).'</option>';
        }
        $html .= '</select>';
        return $html;
    }
} // End Class
