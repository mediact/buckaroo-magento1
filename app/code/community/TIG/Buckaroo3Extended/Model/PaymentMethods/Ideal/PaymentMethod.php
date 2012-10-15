<?php
class TIG_Buckaroo3Extended_Model_PaymentMethods_Ideal_PaymentMethod extends Mage_Payment_Model_Method_Abstract
{
    public $allowedCurrencies = array(
		'EUR',
	);

    protected $_code = 'buckaroo3extended_ideal';

    protected $_formBlockType = 'buckaroo3extended/paymentMethods_ideal_checkout_form';
    
    protected $_isGateway               = true;
    protected $_canAuthorize            = true;
    protected $_canCapture              = true;
    protected $_canCapturePartial       = true;
    protected $_canRefund               = true;
    protected $_canRefundInvoicePartial = true;
    protected $_canVoid                 = true;
    protected $_canUseInternal          = false;
    protected $_canUseCheckout          = true;
    protected $_canUseForMultishipping  = false;
    protected $_canSaveCc 				= false;

    public function getAllowedCurrencies()
    {
        return $this->allowedCurrencies;
    }

    public function setAllowedCurrencies($allowedCurrencies)
    {
        $this->allowedCurrencies = $allowedCurrencies;
    }

    public function getOrderPlaceRedirectUrl()
    {
    	$session = Mage::getSingleton('checkout/session');

    	if(isset($_POST[$this->_code.'_BPE_Issuer']))
    	{
    		$session->setData('additionalFields', array('Issuer' => $_POST['buckaroo3extended_ideal_BPE_Issuer']));
    	}

    	return Mage::getUrl('buckaroo3extended/checkout/checkout', array('_secure' => true, 'method' => $this->_code));
    }

    public function isAvailable($quote = null)
    {
        if (!TIG_Buckaroo3Extended_Model_Request_Availability::canUseBuckaroo($quote)) {
    		return false;
    	}

    	//check if the country specified in the billing address is allowed to use this payment method
    	if (Mage::getStoreConfig('buckaroo/buckaroo3extended_ideal/allowspecific', Mage::app()->getStore()->getStoreId()) == 1
    		&& $quote->getBillingAddress()->getCountry())
    	{
    		$allowedCountries = explode(',',Mage::getStoreConfig('buckaroo/buckaroo3extended_ideal/specificcountry', Mage::app()->getStore()->getStoreId()));
    		$country = $quote->getBillingAddress()->getCountry();

    		if (!in_array($country,$allowedCountries)) {
    			return false;
    		}
    	}

    	//check if the module is set to enabled
    	if (!Mage::getStoreConfig('buckaroo/buckaroo3extended_ideal/active', Mage::app()->getStore()->getStoreId())) {
    		return false;
    	}

    	//limit by ip
    	if (mage::getStoreConfig('dev/restrict/allow_ips') && Mage::getStoreConfig('buckaroo/buckaroo3extended_ideal/limit_by_ip'))
    	{
    		$allowedIp = explode(',', mage::getStoreConfig('dev/restrict/allow_ips'));
    		if (!in_array($_SERVER['REMOTE_ADDR'], $allowedIp))
    		{
    			return false;
    		}
    	}

        // get current currency code
        $currency = Mage::app()->getStore()->getBaseCurrencyCode();

        // currency is not available for this module
        if (!in_array($currency, $this->allowedCurrencies))
        {
            return false;
        }

        return parent::isAvailable($quote);
    }
}