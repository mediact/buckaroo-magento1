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
            $client = new SoapClientWSSEC(
                self::WSDL_URL,
                array(
                    'trace' => 1,
                    'cache_wsdl' => WSDL_CACHE_DISK,
                ));
        } catch (SoapFault $e) {
            try {
                //second attempt: use an uncached WSDL
                ini_set('soap.wsdl_cache_ttl', 1);
                $client = new SoapClientWSSEC(
                    self::WSDL_URL,
                    array(
                        'trace' => 1,
                        'cache_wsdl' => WSDL_CACHE_NONE,
                    ));
            } catch (SoapFault $e) {
                try {
                    //third and final attempt: use the supplied wsdl found in the lib folder
                    $client = new SoapClientWSSEC(
                        LIB_DIR . 'Buckaroo.wsdl',
                        array(
                            'trace' => 1,
                            'cache_wsdl' => WSDL_CACHE_NONE,
                        ));
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

        $TransactionRequest = new Body();
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
            $transactionParameter = new RequestParameter();
            $transactionParameter->Key = $this->_vars['TransactionKey'];
            $TransactionRequest->Transaction = $transactionParameter;
        }

        if (isset($this->_vars['customParameters'])) {
            $TransactionRequest = $this->_addCustomParameters($TransactionRequest);
        }

        $TransactionRequest->Services = new Services();

        $this->_addServices($TransactionRequest);

        $TransactionRequest->ClientIP = new IPAddress();
        $TransactionRequest->ClientIP->Type = 'IPv4';
        $TransactionRequest->ClientIP->_ = Mage::helper('core/http')->getRemoteAddr();

        $Software = new Software();
        $Software->PlatformName = $this->_vars['Software']['PlatformName'];
        $Software->PlatformVersion = $this->_vars['Software']['PlatformVersion'];
        $Software->ModuleSupplier = $this->_vars['Software']['ModuleSupplier'];
        $Software->ModuleName = $this->_vars['Software']['ModuleName'];
        $Software->ModuleVersion = $this->_vars['Software']['ModuleVersion'];

        $Header = new Header();
        $Header->MessageControlBlock = new MessageControlBlock();
        $Header->MessageControlBlock->Id = '_control';
        $Header->MessageControlBlock->WebsiteKey = $this->_vars['merchantKey'];
        $Header->MessageControlBlock->Culture = $this->_vars['locale'];
        $Header->MessageControlBlock->TimeStamp = time();
        $Header->MessageControlBlock->Channel = $requestChannel;
        $Header->MessageControlBlock->Software = $Software;
        $Header->Security = new SecurityType();
        $Header->Security->Signature = new SignatureType();

        $Header->Security->Signature->SignedInfo = new SignedInfoType();
        $Header->Security->Signature->SignedInfo->CanonicalizationMethod = new CanonicalizationMethodType();
        $Header->Security->Signature->SignedInfo->CanonicalizationMethod->Algorithm = 'http://www.w3.org/2001/10/xml-exc-c14n#';
        $Header->Security->Signature->SignedInfo->SignatureMethod = new SignatureMethodType();
        $Header->Security->Signature->SignedInfo->SignatureMethod->Algorithm = 'http://www.w3.org/2000/09/xmldsig#rsa-sha1';

        $Reference = new ReferenceType();
        $Reference->URI = '#_body';
        $Transform = new TransformType();
        $Transform->Algorithm = 'http://www.w3.org/2001/10/xml-exc-c14n#';
        $Reference->Transforms=array($Transform);

        $Reference->DigestMethod = new DigestMethodType();
        $Reference->DigestMethod->Algorithm = 'http://www.w3.org/2000/09/xmldsig#sha1';
        $Reference->DigestValue = '';

        $Transform2 = new TransformType();
        $Transform2->Algorithm = 'http://www.w3.org/2001/10/xml-exc-c14n#';
        $ReferenceControl = new ReferenceType();
        $ReferenceControl->URI = '#_control';
        $ReferenceControl->DigestMethod = new DigestMethodType();
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

            $service = new Service();

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
                        $newParameter          = new RequestParameter();
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

            $requestParameter = new RequestParameter();
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

            $requestParameter = new RequestParameter();
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

/**
 * Class SoapClientWSSEC
 */
class SoapClientWSSEC extends SoapClient
{
    /**
     * Contains the request XML
     * @var DOMDocument
     */
    private $document;

    /**
     * Path to the privateKey file
     * @var string
     */
    public $privateKey = '';

    /**
     * Password for the privatekey
     * @var string
     */
    public $privateKeyPassword = '';

    /**
     * Thumbprint from Payment Plaza
     * @var string
     */
    public $thumbprint = '';

    /**
     * StoreId for Certificate
     * @var int
     */
    public $storeId = null;

    /**
     * @param string $request
     * @param string $location
     * @param string $action
     * @param int    $version
     * @param int    $one_way
     *
     * @return string
     * @throws Exception
     */
    public function __doRequest ($request , $location , $action , $version , $one_way = 0 )
    {
        // Add code to inspect/dissect/debug/adjust the XML given in $request here
        $domDOC = new DOMDocument();
        $domDOC->preserveWhiteSpace = FALSE;
        $domDOC->formatOutput = TRUE;
        $domDOC->loadXML($request);

        //Sign the document
        $domDOC = $this->SignDomDocument($domDOC);

        // Uncomment the following line, if you actually want to do the request
        return parent::__doRequest($domDOC->saveXML($domDOC->documentElement), $location, $action, $version, $one_way);
    }

    /**
     * Get nodeset based on xpath and ID
     *
     * @param          $ID
     * @param DOMXPath $xPath
     *
     * @return DOMNode
     */
    private function getReference($ID, $xPath)
    {
        $query = '//*[@Id="'.$ID.'"]';
        $nodeset = $xPath->query($query);
        return $nodeset->item(0);
    }

    /**
     * Canonicalize nodeset
     *
     * @param DOMNode $Object
     *
     * @return DOMNode
     */
    private function getCanonical($Object)
    {
        return $Object->C14N(true, false);
    }

    /**
     * Calculate digest value (sha1 hash)
     *
     * @param $input
     *
     * @return string
     */
    private function calculateDigestValue($input)
    {
        return base64_encode(pack('H*',sha1($input)));
    }

    /**
     * @param DOMDocument $domDocument
     *
     * @return DOMDocument
     * @throws Exception
     */
    private function signDomDocument($domDocument)
    {
        //create xPath
        $xPath = new DOMXPath($domDocument);

        //register namespaces to use in xpath query's
        $xPath->registerNamespace('wsse','http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-wssecurity-secext-1.0.xsd');
        $xPath->registerNamespace('sig','http://www.w3.org/2000/09/xmldsig#');
        $xPath->registerNamespace('soap','http://schemas.xmlsoap.org/soap/envelope/');

        //Set id on soap body to easily extract the body later.
        $bodyNodeList = $xPath->query('/soap:Envelope/soap:Body');
        $bodyNode = $bodyNodeList->item(0);
        $bodyNode->setAttribute('Id','_body');

        //Get the digest values
        $controlHash = $this->CalculateDigestValue($this->GetCanonical($this->GetReference('_control', $xPath)));
        $bodyHash = $this->CalculateDigestValue($this->GetCanonical($this->GetReference('_body', $xPath)));

        //Set the digest value for the control reference
        $Control = '#_control';
        $controlHashQuery = $query = '//*[@URI="'.$Control.'"]/sig:DigestValue';
        $controlHashQueryNodeset = $xPath->query($controlHashQuery);
        $controlHashNode = $controlHashQueryNodeset->item(0);
        $controlHashNode->nodeValue = $controlHash;

        //Set the digest value for the body reference
        $Body = '#_body';
        $bodyHashQuery = $query = '//*[@URI="'.$Body.'"]/sig:DigestValue';
        $bodyHashQueryNodeset = $xPath->query($bodyHashQuery);
        $bodyHashNode = $bodyHashQueryNodeset->item(0);
        $bodyHashNode->nodeValue = $bodyHash;

        //Get the SignedInfo nodeset
        $SignedInfoQuery = '//wsse:Security/sig:Signature/sig:SignedInfo';
        $SignedInfoQueryNodeSet = $xPath->query($SignedInfoQuery);
        $SignedInfoNodeSet = $SignedInfoQueryNodeSet->item(0);

        //Canonicalize nodeset
        $signedINFO = $this->GetCanonical($SignedInfoNodeSet);

        // If the storeId has been configured specifically, use the current value. Otherwise, try to get
        // the store Id from Magento. If there's only 1 store view, this default will always pick certificate #1
        if (!$this->storeId) {
            $this->storeId = Mage::app()->getStore()->getId();
        }

        $certificateId = Mage::getStoreConfig('buckaroo/buckaroo3extended/certificate_selection', $this->storeId);
        $certificate = Mage::getModel('buckaroo3extended/certificate')->load($certificateId)->getCertificate();

        $priv_key = substr($certificate, 0, 8192);

        if ($priv_key === false) {
            throw new Exception('Unable to read certificate.');
        }

        $pkeyid = openssl_get_privatekey($priv_key, '');
        if ($pkeyid === false) {
            throw new Exception('Unable to retrieve private key from certificate.');
        }

        //Sign signedinfo with privatekey
        $signature2 = null;
        $signatureCreate = openssl_sign($signedINFO, $signature2, $pkeyid);

        //Add signature value to xml document
        $sigValQuery = '//wsse:Security/sig:Signature/sig:SignatureValue';
        $sigValQueryNodeset = $xPath->query($sigValQuery);
        $sigValNodeSet = $sigValQueryNodeset->item(0);
        $sigValNodeSet->nodeValue = base64_encode($signature2);

        //Get signature node
        $sigQuery = '//wsse:Security/sig:Signature';
        $sigQueryNodeset = $xPath->query($sigQuery);
        $sigNodeSet = $sigQueryNodeset->item(0);

        //Create keyinfo element and Add public key to KeyIdentifier element
        $KeyTypeNode = $domDocument->createElementNS("http://www.w3.org/2000/09/xmldsig#","KeyInfo");
        $SecurityTokenReference = $domDocument->createElementNS('http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-wssecurity-secext-1.0.xsd','SecurityTokenReference');
        $KeyIdentifier = $domDocument->createElement("KeyIdentifier");
        $KeyIdentifier->nodeValue = $this->thumbprint;
        $KeyIdentifier->setAttribute('ValueType','http://docs.oasis-open.org/wss/oasis-wss-soap-message-security-1.1#ThumbPrintSHA1');
        $SecurityTokenReference->appendChild($KeyIdentifier);
        $KeyTypeNode->appendChild($SecurityTokenReference);
        $sigNodeSet->appendChild($KeyTypeNode);

        return $domDocument;
    }
}

/**
 * @property SecurityType Security
 */
class Header
{
    public $MessageControlBlock;
}

class SecurityType
{
    public $Signature;
}

class SignatureType
{
    public $SignedInfo;
    public $SignatureValue;
    public $KeyInfo;
}

class SignedInfoType
{
    public $CanonicalizationMethod;
    public $SignatureMethod;
    public $Reference;
}

class ReferenceType
{
    public $Transforms;
    public $DigestMethod;
    public $DigestValue;
    public $URI;
    public $Id;
}


class TransformType
{
    public $Algorithm;
}

class DigestMethodType
{
    public $Algorithm;
}


class SignatureMethodType
{
    public $Algorithm;
}

class CanonicalizationMethodType
{
    public $Algorithm;
}

class MessageControlBlock
{
    public $Id;
    public $WebsiteKey;
    public $Culture;
    public $TimeStamp;
    public $Channel;
    public $Software;
}

/**
 * @property float Amount
 * @property string ServicesSelectableByClient
 * @property bool ContinueOnIncomplete
 */
class Body
{
    public $Currency;
    public $AmountDebit;
    public $AmountCredit;
    public $Invoice;
    public $Order;
    public $Description;
    public $ClientIP;
    public $ReturnURL;
    public $ReturnURLCancel;
    public $ReturnURLError;
    public $ReturnURLReject;
    public $OriginalTransactionKey;
    public $StartRecurrent;
    public $Services;
    public $Transaction;
}

class Services
{
    public $Global;
    public $Service;
}

class Service
{
    public $RequestParameter;
    public $Name;
    public $Action;
    public $Version;
}

/**
 * @property int|string GroupID
 * @property int|string Key
 */
class RequestParameter
{
    public $_;
    public $Name;
    public $Group;
}

class IPAddress
{
    public $_;
    public $Type;
}

class Software
{
    public $PlatformName;
    public $PlatformVersion;
    public $ModuleSupplier;
    public $ModuleName;
    public $ModuleVersion;
}
