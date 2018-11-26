<?php
class TIG_Buckaroo3Extended_Model_PaymentMethods_Payperemail_PaymentMethod extends TIG_Buckaroo3Extended_Model_PaymentMethods_PaymentMethod
{
    public $allowedCurrencies = array(
        'ARS',
        'AUD',
        'BRL',
        'CAD',
        'CHF',
        'CNY',
        'CZK',
        'DKK',
        'EUR',
        'GBP',
        'HRK',
        'LTL',
        'LVL',
        'MXN',
        'NOK',
        'PLN',
        'SEK',
        'TRY',
        'USD',
    );

    protected $_code = 'buckaroo3extended_payperemail';

    protected $_formBlockType = 'buckaroo3extended/paymentMethods_payperemail_checkout_form';

    protected $_canUseInternal = true;

    public function assignData($data)
    {
        if (!Mage::helper('buckaroo3extended')->isAdmin()) {
            $session = Mage::getSingleton('checkout/session');
        } else {
            $session = Mage::getSingleton('core/session');
        }

        $session->setData('additionalFields', array(
            'gender'    => $_POST['buckaroo3extended_payperemail_BPE_Customergender'],
            'firstname' => $_POST['buckaroo3extended_payperemail_BPE_Customerfirstname'],
            'lastname'  => $_POST['buckaroo3extended_payperemail_BPE_Customerlastname'],
            'mail'      => $_POST['buckaroo3extended_payperemail_BPE_Customermail'],
        ));

        return parent::assignData($data);
    }
}
