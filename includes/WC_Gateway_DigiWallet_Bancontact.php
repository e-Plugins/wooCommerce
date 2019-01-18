<?php
/*DigiWallet Bancontact Payment Gateway Class */
class WC_Gateway_DigiWallet_Bancontact extends WC_Gateway_DigiWallet
{
    protected $payMethodId = "MRC";
    protected $payMethodName = "Bancontact";
    protected $maxAmount = 10000;
    protected $minAmount = 0.49;
    public $enabled = true;
    
    /**
     * return method description
     * {@inheritDoc}
     * @see WC_Gateway_DigiWallet::getDigiWalletMethodOption()
     * @return string
     */
    protected function getDigiWalletMethodOption()
    {
        return 'Bancontact';
    }
} // End Class
