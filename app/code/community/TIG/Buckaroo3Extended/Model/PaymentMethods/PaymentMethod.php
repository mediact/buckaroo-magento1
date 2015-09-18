<?php
/**  ____________  _     _ _ ________  ___  _ _  _______   ___  ___  _  _ _ ___
 *   \_ _/ \_ _/ \| |   |_| \ \_ _/  \| _ || \ |/  \_ _/  / __\| _ |/ \| | | _ \
 *    | | | | | ' | |_  | |   || | '_/|   /|   | '_/| |  | |_ \|   / | | | | __/
 *    |_|\_/|_|_|_|___| |_|_\_||_|\__/|_\_\|_\_|\__/|_|   \___/|_\_\\_/|___|_|
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Creative Commons License.
 * If you are unable to obtain it through the world-wide-web, please send an email
 * to servicedesk@totalinternetgroup.nl so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade this module to newer
 * versions in the future. If you wish to customize this module for your
 * needs please contact servicedesk@totalinternetgroup.nl for more information.
 *
 * @copyright   2014 Total Internet Group B.V. (http://www.totalinternetgroup.nl)
 * @license     http://creativecommons.org/licenses/by-nc-nd/3.0/nl/deed.en_US
 */

class TIG_Buckaroo3Extended_Model_PaymentMethods_PaymentMethod extends Mage_Payment_Model_Method_Abstract
{
    public $allowedCurrencies = array();

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
    protected $_canSaveCc               = false;

    protected $_payment;

    public function setPayment($payment)
    {
        $this->_payment = $payment;
    }

    public function getPayment()
    {
        return $this->_payment;
    }

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
        return Mage::getUrl('buckaroo3extended/checkout/checkout', array('_secure' => true, 'method' => $this->_code));
    }

    public function getTitle()
    {
        if(Mage::helper('buckaroo3extended')->getIsKlarnaEnabled()) {
            return parent::getTitle();
        }

        if (!Mage::helper('buckaroo3extended')->isOneStepCheckout()) {
            return parent::getTitle();
        }

        $block = Mage::app()
                     ->getLayout()
                     ->createBlock('buckaroo3extended/paymentMethods_ideal_checkout_form')
                     ->setMethod($this);

        $title = parent::getTitle() . ' ' . $block->getMethodLabelAfterHtml(false);

        return $title;
    }

    public function refund(Varien_Object $payment, $amount)
    {
        if (!$this->canRefund() || !$this->isRefundAvailable($payment)) {
            Mage::throwException($this->_getHelper()->__('Refund action is not available.'));
        }

        $refundRequest = Mage::getModel(
            'buckaroo3extended/refund_request_abstract',
            array(
                'payment' => $payment,
                'amount' => $amount
            )
        );

        try {
            $refundRequest->sendRefundRequest();
            $this->setPayment($refundRequest->getPayment());
        } catch (Exception $e) {
            Mage::helper('buckaroo3extended')->logException($e);
            Mage::throwException($e->getMessage());
        }

        return $this;
    }

    public function isRefundAvailable($payment)
    {
        if (!$payment->getOrder()->getTransactionKey()) {
            Mage::getSingleton('adminhtml/session')
                ->addError(
                    Mage::helper('buckaroo3extended')->__(
                        'The order is missing a transaction key. Possibly this order was created using an older version of the Buckaroo module that did not yet support refunding.'
                    )
                );
            throw new Exception('The order is missing a transaction key. Possibly this order was created using an older version of the Buckaroo module that did not yet support refunding.');
            return false;
        }

        if (!Mage::getStoreConfig('buckaroo/buckaroo3extended_refund/active', Mage::app()->getStore()->getStoreId())) {
            Mage::getSingleton('adminhtml/session')
                ->addError(
                    Mage::helper('buckaroo3extended')->__(
                        'Buckaroo refunding is currently disabled in the configuration menu.'
                    )
                );
            throw new Exception('Buckaroo refunding is currently disabled in the configuration menu.');
            return false;
        }

        return true;
    }

    public function isAvailable($quote = null)
    {
        if(is_null($quote) || Mage::helper('buckaroo3extended')->isAdmin()){
            // Uncomment this code to get all active Buckaroo payment methods in the backend. (3th party extensions)
            if(Mage::getStoreConfigFlag('buckaroo/' . $this->_code . '/active', Mage::app()->getStore()->getId())){
                return true;
            }
            return false;
        }

        //check if the country specified in the billing address is allowed to use this payment method
        if (Mage::getStoreConfig('buckaroo/' . $this->_code . '/allowspecific', $quote->getStoreId()) == 1
            && $quote->getBillingAddress()->getCountry())
        {
            $allowedCountries = explode(',',Mage::getStoreConfig('buckaroo/' . $this->_code . '/specificcountry', $quote->getStoreId()));
            $country = $quote->getBillingAddress()->getCountry();

            if (!in_array($country,$allowedCountries)) {
                return false;
            }
        }

        $areaAllowed = null;
        if ($this->canUseInternal()) {
            $areaAllowed = Mage::getStoreConfig('buckaroo/' . $this->_code . '/area', $quote->getStoreId());
        }

        //check if the paymentmethod is available in the current shop area (frontend or backend)
        if ($areaAllowed == 'backend'
            && !Mage::helper('buckaroo3extended')->isAdmin()
        ) {
            return false;
        } elseif ($areaAllowed == 'frontend'
            && Mage::helper('buckaroo3extended')->isAdmin()
        ) {
            return false;
        }

        // check if max amount for the issued PaymentMethod is set and if the quote basegrandtotal exceeds that
        $maxAmount = Mage::getStoreConfig('buckaroo/' . $this->_code . '/max_amount', $quote->getStoreId());
        if (!empty($maxAmount)
            && !empty($quote)
            && $quote->getBaseGrandTotal() > $maxAmount)
        {
            return false;
        }

        // check if min amount for the issued PaymentMethod is set and if the quote basegrandtotal is less than that
        $minAmount = Mage::getStoreConfig('buckaroo/' . $this->_code . '/min_amount', $quote->getStoreId());
        if (!empty($minAmount)
            && !empty($quote)
            && $quote->getBaseGrandTotal() < $minAmount)
        {
            return false;
        }

        //check if the module is set to enabled
        if (!Mage::getStoreConfig('buckaroo/' . $this->_code . '/active', $quote->getStoreId())) {
            return false;
        }

        //limit by ip
        if (Mage::getStoreConfig('dev/restrict/allow_ips')
            && Mage::getStoreConfig('buckaroo/' . $this->_code . '/limit_by_ip')
        ) {
            $allowedIp = explode(',', mage::getStoreConfig('dev/restrict/allow_ips'));
            if (!in_array(Mage::helper('core/http')->getRemoteAddr(), $allowedIp)) {
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

        return TIG_Buckaroo3Extended_Model_Request_Availability::canUseBuckaroo($quote);
    }

    public function filterAccount($accountNumber)
    {
        $filteredAccount = str_replace('.', '', $accountNumber);

        return $filteredAccount;
    }

    public function saveAdditionalData($response)
    {
        // child modules will be able to save response info into the serialized additional_data array

        return $this;
    }
}
