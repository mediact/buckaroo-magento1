<?php
class TIG_Buckaroo3Extended_Model_Observer_Abstract extends TIG_Buckaroo3Extended_Model_Abstract 
{  
    protected $_order;
    protected $_bilingInfo;
    
    public function __construct()
    {
        $this->_loadLastOrder();
        $this->_setOrderBillingInfo();
    }
    
    protected function _isChosenMethod($observer)
    {
        $ret = false;
        
        $chosenMethod = $observer->getOrder()->getPayment()->getMethod();
        
        if ($chosenMethod === $this->_code) {
            $ret = true;
        }
        return $ret;
    }
    
    protected function _addCreditManagement(&$vars, $serviceName = 'creditmanagement')
    {
        $method = $this->_order->getPayment()->getMethod();
        
        $dueDaysInvoice = Mage::getStoreConfig('buckaroo/' . $method . '/due_date_invoice', Mage::app()->getStore()->getStoreId());
        $dueDays = Mage::getStoreConfig('buckaroo/' . $method . '/due_date', Mage::app()->getStore()->getStoreId());
        
        $invoiceDate = date('Y-m-d', mktime(0, 0, 0, date("m")  , (date("d") + $dueDaysInvoice), date("Y")));
        $dueDate = date('Y-m-d', mktime(0, 0, 0, date("m")  , (date("d") + $dueDaysInvoice + $dueDays), date("Y")));
        
        $VAT = 0;
        foreach($this->_order->getFullTaxInfo() as $taxRecord)
        {
            $VAT += $taxRecord['amount'];
        }
        $VAT = round($VAT * 100,0);
        
        if (is_array($vars['customVars'][$serviceName])) {
		    $vars['customVars'][$serviceName] = array_merge($vars['customVars'][$serviceName], array(
            	'DateDue'			     => $dueDate,
            	'InvoiceDate'			 => $invoiceDate,
            ));
		} else {
    	    $vars['customVars'][$serviceName] = array(
            	'DateDue'			     => $dueDate,
            	'InvoiceDate'			 => $invoiceDate,
    	    );
		}
        
        return $vars;
    }
    
    protected function _addCustomerVariables(&$vars, $serviceName = 'creditmanagement')
    {
        $additionalFields = Mage::getSingleton('checkout/session')->getData('additionalFields');
        
        $gender = $additionalFields['BPE_Customergender'];
        $mail   = $additionalFields['BPE_Customermail'];
        $dob    = $additionalFields['BPE_customerbirthdate'];
        
        $customerId = $this->_order->getCustomerId() 
        	? $this->_order->getCustomerId() 
        	: $this->_order->getIncrementId();
        
        $firstName              = $this->_billingInfo['firstname'];
		$lastName               = $this->_billingInfo['lastname'];
		$address                = $this->_processAddressCM();
		$houseNumber            = $address['house_number'];
		$houseNumberSuffix      = $address['number_addition'];
		$street                 = $address['street'];
		$zipcode                = $this->_billingInfo['zip'];
		$city                   = $this->_billingInfo['city'];
		$state                  = $this->_billingInfo['state'];
		$fax                    = $this->_billingInfo['fax'];
		$country                = $this->_billingInfo['countryCode'];
		$processedPhoneNumber   = $this->_processPhoneNumberCM();
		$customerLastNamePrefix = $this->_getCustomerLastNamePrefix();
		$customerInitials       = $this->_getInitialsCM();
		
		$array = array(
        	'CustomerCode'           => $customerId,
        	'CustomerFirstName'      => $firstName,
        	'CustomerLastName'       => $lastName,
        	'FaxNumber'              => $fax,
        	'CustomerInitials'       => $customerInitials,
        	'CustomerLastNamePrefix' => $customerLastNamePrefix,
        	'CustomerBirthDate'      => $dob,
        	'Customergender'         => $gender,
        	'Customeremail'          => $mail,
        	'ZipCode'                => array(
                'value' => $zipcode,
                'group' => 'address'
            ),
        	'City'                   => array(
                'value' => $city,
                'group' => 'address'
            ),
        	'State'                  => array(
                'value' => $state,
                'group' => 'address'
            ),
        	'Street'                 => array(
                'value' => $street,
                'group' => 'address'
            ),
        	'HouseNumber'            => array(
                'value' => $houseNumber,
                'group' => 'address'
            ),
        	'HouseNumberSuffix'      => array(
                'value' => $houseNumberSuffix,
                'group' => 'address'
            ),
        	'Country'                => array(
                'value' => $country,
                'group' => 'address'
            )
        );
        
		if (is_array($vars['customVars'][$serviceName])) {
		    $vars['customVars'][$serviceName] = array_merge($vars['customVars'][$serviceName], $array);
		} else {
    		$vars['customVars'][$serviceName] = $array;
		}
		
		if ($processedPhoneNumber['mobile']) {
		    $vars['customVars'][$serviceName] = array_merge($vars['customVars'][$serviceName], array(
		        'MobilePhoneNumber' => $processedPhoneNumber['clean'],
		    ));
		} else {
		    $vars['customVars'][$serviceName] = array_merge($vars['customVars'][$serviceName], array(
		        'PhoneNumber' => $processedPhoneNumber['clean'],
		    ));
		}
				
		return $vars;
    }
    
/**
	 * Processes billingInfo array to get the initials of the customer
	 * 
	 * @param array $billingInfo
	 * 
	 * @return string $initials
	 */
	protected function _getInitialsCM()
	{
		$firstname = $this->_billingInfo['firstname'];
		
		$initials = '';
		$firstnameParts = explode(' ', $firstname);
		
		foreach ($firstnameParts as $namePart) {
			$initials .= strtoupper($namePart[0]) . '.';
		}
		
		return $initials;
	}
	
	/**
	 * Processes the customer's billing_address so as to fit the SOAP request. returning an array
	 * 
	 * @param array $billingInfo
	 * 
	 * @return array $ret
	 */
	protected function _processAddressCM()
	{
		//get address from billingInfo
		$address = $this->_billingInfo['address'];
		
		$ret = array();
		$ret['house_number'] = '';
		$ret['number_addition'] = '';
		if (preg_match('#^(.*?)([0-9]+)(.*)#s', $address, $matches)) {
			if ('' == $matches[1]) {
				// Number at beginning
				$ret['house_number'] = trim($matches[2]);
				$ret['street']		 = trim($matches[3]);
			} else {
				// Number at end
				$ret['street']			= trim($matches[1]);
	 			$ret['house_number']    = trim($matches[2]);
	 			$ret['number_addition'] = trim($matches[3]);
			}
		} else {
	 		// No number
	 		$ret['street'] = $address;
		}
		
	 	return $ret;
	}
	
	/**
	 * processes the customer's phone number so as to fit the betaalgarant SOAP request
	 * 
	 * @param array $billingInfo
	 * 
	 * @return array
	 */
	protected function _processPhoneNumberCM()
	{
		$number = $this->_billingInfo['telephone'];
		
		//the final output must like this: 0031123456789 for mobile: 0031612345678
        //so 13 characters max else number is not valid
        //but for some error correction we try to find if there is some faulty notation
        
        $return = array("orginal" => $number, "clean" => false, "mobile" => false, "valid" => false);
        //first strip out the non-numeric characters:
        $match = preg_replace('/[^0-9]/Uis', '', $number);
        if ($match) {
            $number = $match;
        }
        
        if (strlen((string)$number) == 13) {
            //if the length equal to 13 is, then we can check if its a mobile number or normal number
            $return['mobile'] = $this->_isMobileNumber($number);
            //now we can almost say that the number is valid
            $return['valid'] = true;
            $return['clean'] = $number;
        } elseif (strlen((string) $number) > 13) {
            //if the number is bigger then 13, it means that there are probably a zero to much
            $return['mobile'] = $this->_isMobileNumber($number);
            $return['clean'] = $this->_isValidNotation($number);
            if(strlen((string)$return['clean']) == 13) {
                $return['valid'] = true;
            }
            
        } elseif (strlen((string)$number) == 12 or strlen((string)$number) == 11) {
            //if the number is equal to 11 or 12, it means that they used a + in their number instead of 00 
            $return['mobile'] = $this->_isMobileNumber($number);
            $return['clean'] = $this->_isValidNotation($number);
            if(strlen((string)$return['clean']) == 13) {
                $return['valid'] = true;
            }
            
        } elseif (strlen((string)$number) == 10) {
            //this means that the user has no trailing "0031" and therfore only
            $return['mobile'] = $this->_isMobileNumber($number);
            $return['clean'] = '0031'.substr($number,1);
            if (strlen((string) $return['clean']) == 13) {
                $return['valid'] = true;
            }
        } else {
            //if the length equal to 13 is, then we can check if its a mobile number or normal number
            $return['mobile'] = $this->_isMobileNumber($number);
            //now we can almost say that the number is valid
            $return['valid'] = true;
            $return['clean'] = $number;
        }
        
        return $return;
	}
	
    protected function _isValidNotation($number) {
        //checks if the number is valid, if not: try to fix it
        $invalidNotations = array("00310", "0310", "310", "31");
        foreach($invalidNotations as $invalid) {
            if( strpos( substr( $number, 0, 6 ), $invalid ) !== false ) {
                $valid = substr($invalid, 0, -1);
                if (substr($valid, 0, 2) == '31') { 
                    $valid = "00" . $valid;
                }
                if (substr($valid, 0, 2) == '03') { 
                    $valid = "0" . $valid;
                }
                if ($valid == '3'){ 
                    $valid = "0" . $valid . "1";
                }
                $number = str_replace($invalid, $valid, $number);
            }
        }
        return $number;
    }
	
	/**
	 * Checks if the number is a mobile number or not.
	 * 
	 * @param string $number
	 * 
	 * @return boolean
	 */
	protected function _isMobileNumber($number) {
        //this function only checks if it is a mobile number, not checking valid notation
        $checkMobileArray = array("3106","316","06","00316","003106");
        foreach($checkMobileArray as $key => $value) {
            
            if( strpos( substr( $number, 0, 6 ), $value ) !== false) {
                
                return true;
            }
        }
        return false;
    }
	
	protected function _getCustomerLastNamePrefix()
	{
	    $lastName = $this->_billingInfo['lastname'];
	    
	    $lastNameBits = explode(' ', $lastName);
	    
	    if (count($lastNameBits === 1)) {
	        return '';
	    }
	    
	    $lastNameEnd = end($lastNameBits);
	    unset($lastNameEnd);
	    
	    $prefix = implode(' ', $lastNameBits);
	    return $prefix;
	}
    
    protected function _getPaymentMethodsAllowed()
    {
        $configAllowed = Mage::getStoreConfig('buckaroo/' . $this->_code . '/allowed_methods', Mage::app()->getStore()->getStoreId());
        
        if ($configAllowed == 'all') {
            $allowedArray = array(
                'amex',
                'directdebit',
                'giropay',
                'ideal',
                'mastercard',
                'onlinegiro',
                'paypal',
                'paysafecard',
                'sofortueberweisung',
                'transfer',
                'visa',
            );
        } else {
            $allowedArray = explode(',', $configAllowed);
            
            if (in_array('all', $allowedArray)) {
                $allowedArray = array(
                    'amex',
                    'directdebit',
                    'giropay',
                    'ideal',
                    'mastercard',
                    'onlinegiro',
                    'paypal',
                    'paysafecard',
                    'sofortueberweisung',
                    'transfer',
                    'visa',
                );
            }
        }
        
        $allowedString = implode(',', $allowedArray);
        
        Return $allowedString;
    }
}