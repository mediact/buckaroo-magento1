<?php
class TIG_Buckaroo3Extended_Model_Sources_CreditcardDesign
{
    const CREDITCARD_PAYMENT_METHOD_STYLED = 'card styled';
    const CREDITCARD_PAYMENT_METHOD_NOSTYLE = 'card blank';

    public function toOptionArray()
    {
        $array = array(
            array('value' => self::CREDITCARD_PAYMENT_METHOD_STYLED, 'label'=>Mage::helper('buckaroo3extended')->__('Yes')),
            array('value' => self::CREDITCARD_PAYMENT_METHOD_NOSTYLE, 'label'=>Mage::helper('buckaroo3extended')->__('No')),
        );

        return $array;
    }
}
