<?php
class TIG_Buckaroo3Extended_Model_PaymentMethods_Payconiq_PaymentMethod extends TIG_Buckaroo3Extended_Model_PaymentMethods_PaymentMethod
{
    public $allowedCurrencies = array(
        'AUD',
        'BRL',
        'CAD',
        'CHF',
        'DKK',
        'EUR',
        'GBP',
        'HKD',
        'HUF',
        'ILS',
        'JPY',
        'MYR',
        'NOK',
        'NZD',
        'PHP',
        'PLN',
        'SEK',
        'SGD',
        'THB',
        'TRY',
        'TWD',
        'USD',
    );

    protected $_code = 'buckaroo3extended_payconiq';

    protected $_formBlockType = 'buckaroo3extended/paymentMethods_payconiq_checkout_form';

    protected $_orderMailStatusses      = array( TIG_Buckaroo3Extended_Model_Response_Abstract::BUCKAROO_SUCCESS, TIG_Buckaroo3Extended_Model_Response_Abstract::BUCKAROO_PENDING_PAYMENT);

}
