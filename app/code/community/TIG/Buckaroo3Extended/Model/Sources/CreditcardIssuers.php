<?php
class TIG_Buckaroo3Extended_Model_Sources_CreditcardIssuers
{
    public function toOptionArray()
    {
        $helper = Mage::helper('buckaroo3extended');

        $array = array(
            array('value' => 'American Express', 'label' => $helper->__('American Express')),
            array('value' => 'Mastercard', 'label' => $helper->__('Mastercard')),
            array('value' => 'Visa', 'label' => $helper->__('Visa')),
            array('value' => 'Visa Electron', 'label' => $helper->__('Visa Electron')),
            array('value' => 'Maestro', 'label' => $helper->__('Maestro')),
            array('value' => 'Dankort', 'label' => $helper->__('Dankort')),
            array('value' => 'Carte Bancaire', 'label' => $helper->__('Carte Bancaire')),
            array('value' => 'Carte Bleue', 'label' => $helper->__('Carte Bleue')),
            array('value' => 'VPay', 'label' => $helper->__('VPay')),
            array('value' => 'Nexi', 'label' => $helper->__('Nexi')),
        );

        return $array;
    }
}
