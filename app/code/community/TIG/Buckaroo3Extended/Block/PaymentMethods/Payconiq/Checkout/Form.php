<?php
class TIG_Buckaroo3Extended_Block_PaymentMethods_Payconiq_Checkout_Form extends TIG_Buckaroo3Extended_Block_PaymentMethods_Checkout_Form_Abstract
{
    public function __construct()
    {
        $this->setTemplate('buckaroo3extended/payconiq/checkout/form.phtml');
        parent::_construct();
    }
}
