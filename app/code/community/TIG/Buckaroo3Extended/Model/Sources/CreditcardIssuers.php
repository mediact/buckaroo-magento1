<?php
class TIG_Buckaroo3Extended_Model_Sources_CreditcardIssuers
{
    public function toOptionArray()
    {
        $helper = Mage::helper('buckaroo3extended');

         return array(
            array('value' => 'American Express', 'label' => $helper->__('American Express'), 'code' => 'amex', 'logo' => '/creditcard_types/creditcard_americanexpress.png'),
            array('value' => 'Mastercard', 'label' => $helper->__('Mastercard'), 'code' => 'mastercard', 'logo' => "/creditcard_types/creditcard_mastercard.png"),
            array('value' => 'Visa', 'label' => $helper->__('Visa'), 'code' => 'visa', 'logo' => '/creditcard_types/creditcard_visa.png'),
            array('value' => 'Visa Electron', 'label' => $helper->__('Visa Electron'), 'code' => 'visa', 'logo' => '/creditcard_types/creditcard_visa.png'),
            array('value' => 'Maestro', 'label' => $helper->__('Maestro'), 'code' => 'maestro', 'logo' => '/creditcard_types/creditcard_maestro.png'),
            array('value' => 'Dankort', 'label' => $helper->__('Dankort'), 'code' => 'amex', 'logo' => '/creditcard_types/creditcard_americanexpress.png'),
            array('value' => 'Carte Bancaire', 'label' => $helper->__('Carte Bancaire'), 'code' => 'visa', 'logo'=> "/creditcard_types/creditcard_visa.png"),
            array('value' => 'Carte Bleue', 'label' => $helper->__('Carte Bleue'), 'code' => 'visa', 'logo' => '/creditcard_types/creditcard_visa.png'),
            array('value' => 'VPay', 'label' => $helper->__('VPay'), 'code' => 'visa', 'logo' => '/creditcard_types/creditcard_visa.png'),
            array('value' => 'Nexi', 'label' => $helper->__('Nexi'), 'code' => 'amex', 'logo' => '/creditcard_types/creditcard_americanexpress.png'),
            array('value' => 'Switch', 'label' => $helper->__('Switch'), 'code' => 'maestro', 'logo' => '/creditcard_types/maestro.png'),
        );
    }
}
