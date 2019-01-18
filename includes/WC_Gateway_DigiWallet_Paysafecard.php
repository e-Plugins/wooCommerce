<?php
/*DigiWallet Paysafecard Payment Gateway Class */
class WC_Gateway_DigiWallet_Paysafecard extends WC_Gateway_DigiWallet
{
    protected $payMethodId = "WAL";
    protected $maxAmount = 150;
    protected $minAmount = 0.1;
    protected $payMethodName = "Paysafecard";
    public $enabled = true;
    
    /**
     * return method description
     * {@inheritDoc}
     * @see WC_Gateway_DigiWallet::getDigiWalletMethodOption()
     * @return string
     */
    protected function getDigiWalletMethodOption()
    {
        return 'Paysafecard';
    }
} // End Class
