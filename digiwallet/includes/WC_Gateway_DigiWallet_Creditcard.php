<?php
/*DigiWallet Creditcard Payment Gateway Class */
class WC_Gateway_DigiWallet_Creditcard extends WC_Gateway_DigiWallet
{
    protected $payMethodId = "CC";
    protected $payMethodName = "Visa/Mastercard";
    public $enabled = false;
    public $enabledDescription = 'Only possible when creditcard is activated on your DigiWallet account';
    protected $maxAmount = 10000;
    protected $minAmount = 1;
    
    /**
     * return method description
     * {@inheritDoc}
     * @see WC_Gateway_DigiWallet::getDigiWalletMethodOption()
     * @return string
     */
    protected function getDigiWalletMethodOption()
    {
        return 'Visa/Mastercard';
    }
} // End Class
