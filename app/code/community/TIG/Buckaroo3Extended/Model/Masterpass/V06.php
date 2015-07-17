<?php
class TIG_Buckaroo3Extended_Model_Masterpass_v06
{
    public function lightbox()
    {
        try
        {
            // get quote from session
            $session = Mage::getSingleton('checkout/session');
            $quote = $session->getQuote();

            // set Masterpass as chosen payment method
            $paymentMethod = Mage::getModel('buckaroo3extended/paymentMethods_masterpass_paymentMethod');
            $quote->getPayment()->importData(array('method' => $paymentMethod->getCode()));

            // initiate request
            $quoteRequest = Mage::getModel('buckaroo3extended/request_quote', array('quote' => $quote));

            // append the vars with lightbox config
            $vars = $quoteRequest->getVars();
            $vars['customVars']['masterpass']['LightboxRequest'] = true;
            $vars['customVars']['masterpass']['InitializeUrl'] = Mage::app()->getStore()->getCurrentUrl(false);
            $quoteRequest->setVars($vars);

            // do the request
            $parameters = $quoteRequest->sendRequest();

            // return lightbox parameters
            return $parameters;
        }
        catch (Exception $e)
        {
            Mage::helper('buckaroo3extended')->logException($e);

            return Mage::getModel('buckaroo3extended/response_abstract', array(
                'response'   => false,
                'XML'        => false,
                'debugEmail' => isset($quoteRequest) ? $quoteRequest->getDebugEmail() : '',
            ))->processResponse();
        }
    }

    public function pay()
    {
        return true;
    }
}