<?php
class TIG_Buckaroo3Extended_Model_Observer extends Mage_Core_Model_Abstract 
{    
    /**
     * When config settings are saved in the backend, retrieve the title of all buckaroo payment methods that have been activated.
     * These titles are using the following path: 'buckaroo/buckaroo2012PAYMENTCODE/title'.
     * Retrieve this and enter it in the DB using the following path: 'payment/buckaroo2012PAYMENTMETHOD/title'.
     * This way, magento will know what label to give the payment methods in the frontend.
     * The same goes for the sort_order
     * 
     * Secondly, calls the 'logExpertSettings' method which will log any changes made to expert settings in a seperate table.
     * 
     * @param Varien_Event_Observer $observer
     */
    public function controller_action_postdispatch_adminhtml_system_config_save(Varien_Event_Observer $observer) 
    {
    	//check if the section being saved is 'buckaroo'
    	$buckarooRequest = strpos(Mage::getSingleton('core/app')->getRequest()->getRequestUri(), 'section/buckaroo/');
    	if ($buckarooRequest === false) {
    		return false;
    	}
    	
    	//get all activated payment methods
    	$payments = Mage::getSingleton('payment/config')->getActiveMethods();
        foreach ($payments as $payment) {
            
    		//get the code and check if its a buckaroo2012 payment method
    		$code = $payment->getCode();
    		$isBuckaroo = strpos($code, 'buckaroo3extended');
    		if ($isBuckaroo !== false) {
    		    foreach(Mage::app()->getStores() as $eachStore => $storeVal)
    		    {
        		    //retrieve the title as set in the backend
        			$title = mage::getStoreConfig('buckaroo/' . $code . '/title', Mage::app()->getStore($eachStore)->getId());
        			$sort_order = mage::getStoreConfig('buckaroo/' . $code . '/sort_order', Mage::app()->getStore($eachStore)->getId());
        			
        			if ($title) {
        				//set the title as the new path
        				Mage::getModel('core/config')->saveConfig('payment/' . $code . '/title', $title, 'stores', Mage::app()->getStore($eachStore)->getId());
        			}
        			if ($sort_order) {
        				//set the sort_order as the new path
        				Mage::getModel('core/config')->saveConfig('payment/' . $code . '/sort_order', $sort_order, 'stores', Mage::app()->getStore($eachStore)->getId());
        			}
    		    }
    			
    		}
    	}
    }
}