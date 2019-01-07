<?php
/**
 *
 *          ..::..
 *     ..::::::::::::..
 *   ::'''''':''::'''''::
 *   ::..  ..:  :  ....::
 *   ::::  :::  :  :   ::
 *   ::::  :::  :  ''' ::
 *   ::::..:::..::.....::
 *     ''::::::::::::''
 *          ''::''
 *
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Creative Commons License.
 * It is available through the world-wide-web at this URL:
 * http://creativecommons.org/licenses/by-nc-nd/3.0/nl/deed.en_US
 * If you are unable to obtain it through the world-wide-web, please send an email
 * to servicedesk@tig.nl so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade this module to newer
 * versions in the future. If you wish to customize this module for your
 * needs please contact servicedesk@tig.nl for more information.
 *
 * @copyright   Copyright (c) Total Internet Group B.V. https://tig.nl/copyright
 * @license     http://creativecommons.org/licenses/by-nc-nd/3.0/nl/deed.en_US
 */
class TIG_Buckaroo3Extended_Model_Soap_ClientWSSEC extends SoapClient
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
     * TIG_Buckaroo3Extended_Model_Soap_ClientWSSEC constructor.
     *
     * @param string|array $wsdl
     * @param array|null   $options
     */
    public function __construct($wsdl, array $options = null)
    {
        $wsdlString = $wsdl;

        if (is_array($wsdl) && isset($wsdl['wsdl']) && isset($wsdl['options'])) {
            $wsdlString = $wsdl['wsdl'];
            $options = $wsdl['options'];
        }

        parent::__construct($wsdlString, $options);
    }

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
