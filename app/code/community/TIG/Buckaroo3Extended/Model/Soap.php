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
 * to servicedesk@tig.nl so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade this module to newer
 * versions in the future. If you wish to customize this module for your
 * needs please contact servicedesk@tig.nl for more information.
 *
 * @copyright   Total Internet Group B.V. https://tig.nl/copyright
 * @license     http://creativecommons.org/licenses/by-nc-nd/3.0/nl/deed.en_US
 */

final class TIG_Buckaroo3Extended_Model_Soap extends TIG_Buckaroo3Extended_Model_Abstract
{
    const WSDL_URL = 'https://checkout.buckaroo.nl/soap/Soap.svc?singleWsdl';

    private $_vars;
    private $_method;

    protected $_debugEmail;

    /**
     * @param array $vars
     */
    public function setVars($vars = array())
    {
        $this->_vars = $vars;
    }

    /**
     * @return mixed
     */
    public function getVars()
    {
        return $this->_vars;
    }

    /**
     * @param array $data
     */
    public function __construct($data = array())
    {
        if(!defined('LIB_DIR')) {
            define('LIB_DIR',
                Mage::getBaseDir()
                . DS
                . 'app'
                . DS
                . 'code'
                . DS
                . 'community'
                . DS
                . 'TIG'
                . DS
                . 'Buckaroo3Extended'
                . DS
                . 'lib'
                . DS
            );
        }

        $this->setVars($data['vars']);
        $this->setMethod($data['method']);
    }

    /**
     * @return mixed
     */
    public function getMethod()
    {
        return $this->_method;
    }

    /**
     * @param string $method
     */
    public function setMethod($method = '')
    {
        $this->_method = $method;
    }

    /**
     * @return array
     */
    public function transactionRequest()
    {
        try
        {
            //first attempt: use the cached WSDL
            $client = Mage::getModel('buckaroo3extended/soap_clientWSSEC',
                array(
                    'wsdl' => self::WSDL_URL,
                    'options' => array('trace' => 1, 'cache_wsdl' => WSDL_CACHE_DISK)
                )
            );
        } catch (SoapFault $e) {
            try {
                //second attempt: use an uncached WSDL
                ini_set('soap.wsdl_cache_ttl', 1);
                $client = Mage::getModel('buckaroo3extended/soap_clientWSSEC',
                    array(
                        'wsdl' => self::WSDL_URL,
                        'options' => array('trace' => 1, 'cache_wsdl' => WSDL_CACHE_NONE)
                    )
                );
            } catch (SoapFault $e) {
                try {
                    //third and final attempt: use the supplied wsdl found in the lib folder
                    $client = Mage::getModel('buckaroo3extended/soap_clientWSSEC',
                        array(
                            'wsdl' => LIB_DIR . 'Buckaroo.wsdl',
                            'options' => array('trace' => 1, 'cache_wsdl' => WSDL_CACHE_NONE)
                        )
                    );
                } catch (SoapFault $e) {
                    return $this->_error();
                }
            }
        }

        /*when request is a refund; use 'CallCenter' else use channel 'Web' (case sensitive)*/
        $requestChannel = 'Web';
        $invoiceNumber = $this->_vars['orderId'];

        if (isset($this->_vars['invoiceId'])) {
            $invoiceNumber = $this->_vars['invoiceId'];

            if (round($this->_vars['amountDebit'], 2) == 0 && round($this->_vars['amountCredit'], 2) > 0) {
                $requestChannel = 'CallCenter';
            }
        }

        // The channel set in the vars takes precedence over the above condition
        if (isset($this->_vars['channel'])) {
            $requestChannel = $this->_vars['channel'];
        }

        $client->thumbprint = $this->_vars['thumbprint'];

        // Get the order so we can get the storeId relevant for this order
        /** @var Mage_Sales_Model_Order $order */
        $order = Mage::getModel('sales/order')->load($this->_vars['orderId'], 'increment_id');
        // And pass the storeId to the WSDL client
        $client->storeId = $order->getStoreId();

        $TransactionRequest = Mage::getModel('buckaroo3extended/soap_body');
        $TransactionRequest->Currency = $this->_vars['currency'];

        if (isset($this->_vars['amountDebit'])) {
            $TransactionRequest->AmountDebit = round($this->_vars['amountDebit'], 2);
        }
        if (isset($this->_vars['amountCredit'])) {
            $TransactionRequest->AmountCredit = round($this->_vars['amountCredit'], 2);
        }
        if (isset($this->_vars['amount'])) {
            $TransactionRequest->Amount = round($this->_vars['amount'], 2);
        }


        $TransactionRequest->Invoice = $invoiceNumber;
        $TransactionRequest->Order = $this->_vars['orderId'];
        $TransactionRequest->Description = $this->_vars['description'];
        $TransactionRequest->ReturnURL = $this->_vars['returnUrl'];
        $TransactionRequest->StartRecurrent = FALSE;

        if (isset($this->_vars['customVars']['servicesSelectableByClient']) && isset($this->_vars['customVars']['continueOnImcomplete'])) {
            $TransactionRequest->ServicesSelectableByClient = $this->_vars['customVars']['servicesSelectableByClient'];
            $TransactionRequest->ContinueOnIncomplete       = $this->_vars['customVars']['continueOnImcomplete'];
        }

        if (array_key_exists('OriginalTransactionKey', $this->_vars)) {
            $TransactionRequest->OriginalTransactionKey = $this->_vars['OriginalTransactionKey'];
        }

        if (!empty($this->_vars['request_type'])
            && $this->_vars['request_type'] == 'CancelTransaction'
            && !empty($this->_vars['TransactionKey'])
        ) {
            $transactionParameter = Mage::getModel('buckaroo3extended/soap_requestParameter');
            $transactionParameter->Key = $this->_vars['TransactionKey'];
            $TransactionRequest->Transaction = $transactionParameter;
        }

        if (isset($this->_vars['customParameters'])) {
            $TransactionRequest = $this->_addCustomParameters($TransactionRequest);
        }

        $TransactionRequest->Services = Mage::getModel('buckaroo3extended/soap_services');

        $this->_addServices($TransactionRequest);

        $TransactionRequest->ClientIP = Mage::getModel('buckaroo3extended/soap_iPAddress');
        $TransactionRequest->ClientIP->Type = 'IPv4';
        $TransactionRequest->ClientIP->_ = Mage::helper('core/http')->getRemoteAddr();

        $Software = Mage::getModel('buckaroo3extended/soap_software');
        $Software->PlatformName = $this->_vars['Software']['PlatformName'];
        $Software->PlatformVersion = $this->_vars['Software']['PlatformVersion'];
        $Software->ModuleSupplier = $this->_vars['Software']['ModuleSupplier'];
        $Software->ModuleName = $this->_vars['Software']['ModuleName'];
        $Software->ModuleVersion = $this->_vars['Software']['ModuleVersion'];

        $Header = Mage::getModel('buckaroo3extended/soap_header');
        $Header->MessageControlBlock = Mage::getModel('buckaroo3extended/soap_messageControlBlock');
        $Header->MessageControlBlock->Id = '_control';
        $Header->MessageControlBlock->WebsiteKey = $this->_vars['merchantKey'];
        $Header->MessageControlBlock->Culture = $this->_vars['locale'];
        $Header->MessageControlBlock->TimeStamp = time();
        $Header->MessageControlBlock->Channel = $requestChannel;
        $Header->MessageControlBlock->Software = $Software;
        $Header->Security = Mage::getModel('buckaroo3extended/soap_securityType');
        $Header->Security->Signature = $oldclassobject = Mage::getModel('buckaroo3extended/soap_signatureType');

        $Header->Security->Signature->SignedInfo = Mage::getModel('buckaroo3extended/soap_signedInfoType');
        $Header->Security->Signature->SignedInfo->CanonicalizationMethod = Mage::getModel('buckaroo3extended/soap_methodType');
        $Header->Security->Signature->SignedInfo->CanonicalizationMethod->Algorithm = 'http://www.w3.org/2001/10/xml-exc-c14n#';
        $Header->Security->Signature->SignedInfo->SignatureMethod = Mage::getModel('buckaroo3extended/soap_methodType');
        $Header->Security->Signature->SignedInfo->SignatureMethod->Algorithm = 'http://www.w3.org/2000/09/xmldsig#rsa-sha1';

        $Reference = Mage::getModel('buckaroo3extended/soap_referenceType');
        $Reference->URI = '#_body';
        $Transform = Mage::getModel('buckaroo3extended/soap_methodType');
        $Transform->Algorithm = 'http://www.w3.org/2001/10/xml-exc-c14n#';
        $Reference->Transforms=array($Transform);

        $Reference->DigestMethod = Mage::getModel('buckaroo3extended/soap_methodType');
        $Reference->DigestMethod->Algorithm = 'http://www.w3.org/2000/09/xmldsig#sha1';
        $Reference->DigestValue = '';

        $Transform2 = Mage::getModel('buckaroo3extended/soap_methodType');
        $Transform2->Algorithm = 'http://www.w3.org/2001/10/xml-exc-c14n#';
        $ReferenceControl = Mage::getModel('buckaroo3extended/soap_referenceType');
        $ReferenceControl->URI = '#_control';
        $ReferenceControl->DigestMethod = Mage::getModel('buckaroo3extended/soap_methodType');
        $ReferenceControl->DigestMethod->Algorithm = 'http://www.w3.org/2000/09/xmldsig#sha1';
        $ReferenceControl->DigestValue = '';
        $ReferenceControl->Transforms=array($Transform2);

        $Header->Security->Signature->SignedInfo->Reference = array($Reference,$ReferenceControl);
        $Header->Security->Signature->SignatureValue = '';

        $soapHeaders = array();
        $soapHeaders[] = new SOAPHeader('https://checkout.buckaroo.nl/PaymentEngine/', 'MessageControlBlock', $Header->MessageControlBlock);
        $soapHeaders[] = new SOAPHeader('http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-wssecurity-secext-1.0.xsd', 'Security', $Header->Security);
        $client->__setSoapHeaders($soapHeaders);

        //if the module is set to testmode, use the test gateway. Otherwise, use the default gateway
        if (Mage::getStoreConfig('buckaroo/buckaroo3extended/mode', Mage::app()->getStore()->getStoreId())
            || Mage::getStoreConfig('buckaroo/buckaroo3extended_' . $this->_method . '/mode', Mage::app()->getStore()->getStoreId())
        ) {
            $location = 'https://testcheckout.buckaroo.nl/soap/';
        } else {
            $location = 'https://checkout.buckaroo.nl/soap/';
        }

        $client->__SetLocation($location);

        $requestType = 'TransactionRequest';
        if (!empty($this->_vars['request_type'])) {
            $requestType = $this->_vars['request_type'];
        }

        try {
            $response = $client->$requestType($TransactionRequest);
        } catch (Exception $e) {
            $this->logException($e->getMessage());
            return $this->_error($client);
        }

        if (is_null($response)) {
            $response = false;
        }
        else {
            $response->requestType = $requestType;
        }

        $responseXML = $client->__getLastResponse();
        $requestXML = $client->__getLastRequest();

        $responseDomDOC = new DOMDocument();
        $responseDomDOC->loadXML($responseXML);
        $responseDomDOC->preserveWhiteSpace = FALSE;
        $responseDomDOC->formatOutput = TRUE;

        $requestDomDOC = new DOMDocument();
        $requestDomDOC->loadXML($requestXML);
        $requestDomDOC->preserveWhiteSpace = FALSE;
        $requestDomDOC->formatOutput = TRUE;

        return array($response, $responseDomDOC, $requestDomDOC);
    }

    protected function _loadLastOrder()
    {
        return parent::_loadLastOrder(); // TODO: Change the autogenerated stub
    }

    /**
     * @param SoapClientWSSEC|bool $client
     *
     * @return array
     */
    protected function _error($client = false)
    {
        $response = false;

        $responseDomDOC = new DOMDocument();
        $requestDomDOC = new DOMDocument();
        if ($client) {
            $responseXML = $client->__getLastResponse();
            $requestXML = $client->__getLastRequest();

            if (!empty($responseXML)) {
                $responseDomDOC->loadXML($responseXML);
                $responseDomDOC->preserveWhiteSpace = FALSE;
                $responseDomDOC->formatOutput = TRUE;
            }

            if (!empty($requestXML)) {
                $requestDomDOC->loadXML($requestXML);
                $requestDomDOC->preserveWhiteSpace = FALSE;
                $requestDomDOC->formatOutput = TRUE;
            }
        }

        return array($response, $responseDomDOC, $requestDomDOC);
    }

    /**
     * @param $TransactionRequest
     */
    protected function _addServices(&$TransactionRequest)
    {
        if (!is_array($this->_vars['services']) || empty($this->_vars['services'])) {
            return;
        }

        $services = array();
        foreach($this->_vars['services'] as $fieldName => $value) {
            if (empty($value)) {
                continue;
            }

            $service = Mage::getModel('buckaroo3extended/soap_service');

            if(isset($value['name'])){
                $name = $value['name'];
            } else {
                $name = $fieldName;
            }

            $service->Name    = $name;
            $service->Action  = $value['action'];
            $service->Version = $value['version'];

            $this->_addCustomFields($service, $fieldName);

            $services[] = $service;
        }
        $TransactionRequest->Services->Service = $services;
    }

    /**
     * @param $service
     * @param $name
     */
    protected function _addCustomFields(&$service, $name)
    {
        if (
            !isset($this->_vars['customVars'])
            || !isset($this->_vars['customVars'][$name])
            || empty($this->_vars['customVars'][$name])
        ) {
            unset($service->RequestParameter);
            return;
        }

        $requestParameters = array();

        foreach ($this->_vars['customVars'][$name] as $fieldName => $value) {

            if ($fieldName == 'Articles' && is_array($value) && !empty($value)) {
                foreach ($value as $groupId => $articleArray) {
                    if (!is_array($articleArray) || empty($articleArray)) {
                        continue;
                    }

                    foreach ($articleArray as $articleName => $articleValue) {
                        $newParameter          = Mage::getModel('buckaroo3extended/soap_requestParameter');
                        $newParameter->Name    = $articleName;
                        $newParameter->GroupID = isset($articleValue['groupId']) ? $articleValue['groupId'] : $groupId;
                        $newParameter->Group   = isset($articleValue['group']) ? $articleValue['group'] : "Article";
                        $newParameter->_       = $articleValue['value'];
                        $requestParameters[]   = $newParameter;
                    }
                }
                continue;
            }

            if ((is_null($value) || $value === '')
                || (
                    is_array($value)
                    && (is_null($value['value']) || $value['value'] === '')
                )
            ) {
                continue;
            }

            $requestParameter = Mage::getModel('buckaroo3extended/soap_requestParameter');
            $requestParameter->Name = $fieldName;
            if (is_array($value)) {
                $requestParameter->Group = $value['group'];
                $requestParameter->_ = $value['value'];

                if (isset($value['groupId']) && !empty($value['groupId'])) {
                    $requestParameter->GroupID = $value['groupId'];
                }
            } else {
                $requestParameter->_ = $value;
            }

            $requestParameters[] = $requestParameter;
        }

        if (empty($requestParameters)) {
            unset($service->RequestParameter);
            return;
        } else {
            $service->RequestParameter = $requestParameters;
        }
    }

    /**
     * @param $TransactionRequest
     *
     * @return mixed
     */
    protected function _addCustomParameters(&$TransactionRequest)
    {
        $requestParameters = array();
        foreach($this->_vars['customParameters'] as $fieldName => $value) {
            if (
                (is_null($value) || $value === '')
                || (
                    is_array($value)
                    && (is_null($value['value']) || $value['value'] === '')
                )
            ) {
                continue;
            }

            $requestParameter = Mage::getModel('buckaroo3extended/soap_requestParameter');
            $requestParameter->Name = $fieldName;
            if (is_array($value)) {
                $requestParameter->Group = $value['group'];
                $requestParameter->_ = $value['value'];

                if (isset($value['groupId']) && !empty($value['groupId'])) {
                    $requestParameter->GroupID = $value['groupId'];
                }
            } else {
                $requestParameter->_ = $value;
            }

            $requestParameters[] = $requestParameter;
        }

        if (empty($requestParameters)) {
            unset($TransactionRequest->AdditionalParameters);
            return;
        } else {
            $TransactionRequest->AdditionalParameters = $requestParameters;
        }

        return $TransactionRequest;
    }
}
