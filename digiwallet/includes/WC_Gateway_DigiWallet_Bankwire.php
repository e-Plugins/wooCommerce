<?php
/*DigiWallet Afterpay Payment Gateway Class */
class WC_Gateway_DigiWallet_Bankwire extends WC_Gateway_DigiWallet
{
    protected $payMethodId = "BW";
    protected $payMethodName = "Bankwire";
    protected $maxAmount = 10000;
    protected $minAmount = 0.84;
    public $enabled = true;
    private $salt = 'e381277';
    
    public function __construct()
    {
        parent::__construct();
        add_action( 'woocommerce_thankyou_digiwallet_bw', array( $this, 'thankyou_page' ), 10, 1 );
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
        $digiWallet->bindParam('salt', $this->salt);
        $digiWallet->bindParam('email', $order->get_billing_email());
        $digiWallet->bindParam('userip', $_SERVER["REMOTE_ADDR"]);
    }
    
    /**
     * return method description
     * {@inheritDoc}
     * @see WC_Gateway_DigiWallet::getDigiWalletMethodOption()
     * @return string
     */
    protected function getDigiWalletMethodOption()
    {
        return 'Bankwire';
    }
    
    protected function redirectAfterStart($url, WC_Order $order, DigiWalletCore $digiWallet)
    {
        return array(
            'result' => 'success',
            'redirect' => add_query_arg(array(
                'trxid' => $digiWallet->getTransactionId(),
            ), $this->get_return_url($order))
        );
    }
    /**
     * Output for the order received page.
     */
    public function thankyou_page($order_id) {
        $trxid = ! empty($_REQUEST['trxid']) ? wc_clean($_REQUEST['trxid']) : null;
        if($trxid) {
            $order = new WC_Order($order_id);
            $extOrder = $this->getExtOrder($order_id, $trxid);
            if (!$order || !$extOrder) { // Oeps something wrong... Some extra debug information for DigiWallet
                return wp_redirect(home_url('/'));
            }
            list($trxid, $accountNumber, $iban, $bic, $beneficiary, $bank) = explode("|", $extOrder->more);
            if(!empty(get_locale()) && substr(get_locale(), 0, 2) == "nl"){
                // Fix NL message, don't need to translate anymore
                echo '<div class="bankwire-info">
                        <h4>Bedankt voor uw bestelling in onze webwinkel!</h4>
                        <p>
                            U ontvangt uw bestelling zodra we de betaling per bank ontvangen hebben. <br>
                            Zou u zo vriendelijk willen zijn het totaalbedrag van € ' .  esc_html($order->get_total()) . ' over te maken op bankrekening <b>
                    		' . esc_html($iban) . ' </b> t.n.v. ' . esc_html($beneficiary) . '* ?
                        </p>
                        <p>
                            Vermeld daarbij als betaalkenmerk <b>' . esc_html($trxid) . '</b>, zodat de betaling automatisch verwerkt kan worden.
                            Zodra dit gebeurd is ontvangt u een mail op ' . esc_html($order->get_billing_email()) . ' ter bevestiging.
                        </p>
                        <p>
                            Mocht het nodig zijn voor betalingen vanuit het buitenland, dan is de BIC code van de bank ' . esc_html($bic) . ' en de naam van de bank is ' . esc_html($bank) . '.
                            Zorg ervoor dat u kiest voor kosten in het buitenland voor eigen rekening (optie: OUR), anders zal het bedrag wat binnenkomt te laag zijn.
                        <p>
                            <i>* De betalingen voor onze webwinkel worden verwerkt door TargetMedia. TargetMedia is gecertificeerd als Collecting Payment Service Provider door Currence.
                            Dat houdt in dat zij aan strenge eisen dient te voldoen als het gaat om de veiligheid van de betalingen voor jou als klant en ons als webwinkel.</i>
                        </p>
                   </div>';
            } else {
                // English message               
                echo '<div class="bankwire-info">
                    <h4>' . __('Thank you for ordering in our webshop!', 'digiwallet') . '</h4>
                    <p>' . __('You will receive your order as soon as we receive payment from the bank.', 'digiwallet') . 
                    '<br>' . sprintf( __('Would you be so friendly to transfer the total amount of €%s to the bankaccount <b>%s</b> in name of %s * ?', 'digiwallet' ), esc_html($order->get_total()), esc_html($iban), esc_html($beneficiary) ) .
                    '</p>'.
                    '<p>' . sprintf( __( 'State the payment feature <b>%s</b>, this way the payment can be automatically processed.', 'digiwallet' ), esc_html($trxid) ) . 
                    '<br>' . sprintf( __( 'As soon as this happens you shall receive a confirmation mail on %s.', 'digiwallet' ), esc_html($order->get_billing_email())) . 
                    '</p>' .
                    '<p>' . sprintf( __( 'If it is necessary for payments abroad, then the BIC code from the bank %s and the name of the bank is %s.', 'digiwallet' ), esc_html($bic), esc_html($bank) ) . 
                    '<p>' . __('<i>* Payment for our webstore is processed by TargetMedia. TargetMedia is certified as a Collecting Payment Service Provider by Currence. This means we set the highest security standards when is comes to security of payment for you as a customer and us as a webshop.</i>', 'digiwallet') . 
                    '</p>
                </div>';
            }
        }
    }
    
    /**
     * addition params for report
     * @return array
     */
    protected function getAdditionParametersReport($extOrder)
    {
        $param = [];
        $checksum = md5($extOrder->transaction_id . $extOrder->rtlo . $this->salt);
        $param['checksum'] = $checksum;
        return $param;
    }
} // End Class
