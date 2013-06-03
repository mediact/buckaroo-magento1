<?php
class TIG_Buckaroo3Extended_Model_Sources_Paypal_SuccessSellersProtection
{
    public function toOptionArray()
    {
        $state=Mage::getStoreConfig('buckaroo/buckaroo3extended_advanced/sellers_protection_success_status', Mage::app()->getStore()->getStoreId());
        $statuses=Mage::getSingleton('sales/order_config')->getStateStatuses($state);

        $options=array();
        $options[]=array('value'=>'', 'label'=>Mage::helper('buckaroo3extended')->__('-- Please Select --'));
        foreach($statuses as $value=>$label)
        {
            $options[]=array('value'=>$value, 'label'=>$label);
        }

        return $options;
    }

}