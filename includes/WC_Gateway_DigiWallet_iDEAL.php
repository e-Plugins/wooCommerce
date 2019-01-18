<?php
/*DigiWallet IDeal Payment Gateway Class */
class WC_Gateway_DigiWallet_iDEAL extends WC_Gateway_DigiWallet
{
    protected $payMethodId = "IDE";
    protected $payMethodName = "iDEAL";
    protected $maxAmount = 10000;
    protected $minAmount = 0.84;
    public $enabled = true;
    
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
                'description' => $this->enabledDescription ? __($this->enabledDescription, 'digiwallet') : null
            ),
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
                'default' => $this->defaultApiKey, // Default ApiKey
                'desc_tip' => false,
                'placeholder' => 'Token'
            ),
            'idealView' => array(
                'title' => __('iDEAL bank view', 'digiwallet'),
                'type' => 'checkbox',
                'label' => __('With radiobuttons', 'digiwallet'),
                'default' => 'no',
                'description' => __('If selected, the banklist will be formed with radiobuttons instead of a dropdownbox.', 'digiwallet')
            ),
            'orderStatus' => array(
                'title' => __('Status after payment is received', 'digiwallet'),
                'class' => 'tp-select',
                'type' => 'select',
                'description' => __('Choose whether you wish to set payment status after received.', 'digiwallet'),
                'default' => self::WOO_ORDER_STATUS_COMPLETED,
                'options' => array(
                    self::WOO_ORDER_STATUS_COMPLETED => __('Completed', 'digiwallet'),
                    self::WOO_ORDER_STATUS_PROCESSING => __('Processing', 'digiwallet')
                )
            )
        );
    }
    
    /**
     *  Bind bank ID
     */
    public function additionalParameters(WC_Order $order, DigiWalletCore $digiWallet)
    {
        if (isset($_POST["bank"])) {
            $digiWallet->setBankId(wc_clean($_POST["bank"]));
        }
    }

    /**
     * return method option for Ideal method
     * {@inheritDoc}
     * @see WC_Gateway_DigiWallet::getDigiWalletMethodOption()
     * @return string
     */
    protected function getDigiWalletMethodOption()
    {
        $html = '';
        $digiWallet = new DigiWalletCore($this->payMethodId);
        $temp = $digiWallet->getBankList();
        if (isset($this->idealView) && $this->idealView == 'yes') {
            foreach ($temp as $key => $value) {
                $html .= '<input type="radio" name="bank" id="'. esc_attr($key) . '" value="'. esc_attr($key) .
                '"><label for="'.esc_attr($key).'">'.esc_html($value).'</label><br />';
            }
        } else {
            $html .= '<select name="bank" style="width:170px; padding: 2px; margin-left: 7px">';
            foreach ($temp as $key => $value) {
                $html .= '<option value="'.esc_attr($key).'">'.esc_html($value).'</option>';
            }
            $html .= '</select>';
        }
        return $html;
    }
} // End Class
