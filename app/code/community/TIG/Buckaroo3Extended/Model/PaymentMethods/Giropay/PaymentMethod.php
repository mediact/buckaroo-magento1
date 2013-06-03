<?php
class TIG_Buckaroo3Extended_Model_PaymentMethods_Giropay_PaymentMethod extends TIG_Buckaroo3Extended_Model_PaymentMethods_PaymentMethod
{
    public $allowedCurrencies = array(
		'EUR',
	);

    protected $_code = 'buckaroo3extended_giropay';

    protected $_formBlockType = 'buckaroo3extended/paymentMethods_giropay_checkout_form';

    public function getOrderPlaceRedirectUrl()
    {
        $session = Mage::getSingleton('checkout/session');

        if(isset($_POST[$this->_code.'_BPE_Bankleitzahl']))
        {
            $session->setData('additionalFields', array('Bankleitzahl' => $_POST['buckaroo3extended_giropay_BPE_Bankleitzahl']));
        }

        return Mage::getUrl('buckaroo3extended/checkout/checkout', array('_secure' => true, 'method' => $this->_code));
    }

    public function isAvailable($quote = null)
    {
        //check if max amount for Giropay is set and if so, if the quote grandtotal exceeds that
        $maxAmount = Mage::getStoreConfig('buckaroo/buckaroo3extended_giropay/max_amount', Mage::app()->getStore()->getStoreId());
        if (!empty($maxAmount)
            && !empty($quote)
            && $quote->getGrandTotal() > $maxAmount)
        {
            return false;
        }

        return parent::isAvailable($quote);
    }
}