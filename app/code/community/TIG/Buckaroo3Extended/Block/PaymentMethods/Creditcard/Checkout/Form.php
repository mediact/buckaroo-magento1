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
class TIG_Buckaroo3Extended_Block_PaymentMethods_Creditcard_Checkout_Form extends TIG_Buckaroo3Extended_Block_PaymentMethods_Checkout_Form_Abstract
{
    /**
     * {@inheritdoc}
     */
    public function __construct()
    {
        $this->setTemplate('buckaroo3extended/creditcard/checkout/form.phtml');
        parent::_construct();
    }

    public function getDesign()
    {
        return Mage::getStoreConfig(
            'buckaroo/' . $this->getMethodCode() . '/design', Mage::app()->getStore()->getStoreId()
        );
    }

    public function designValue()
    {
        $design = $this->getDesign();

        switch ($design) {
            case TIG_Buckaroo3Extended_Model_Sources_CreditcardDesign::CREDITCARD_PAYMENT_METHOD_STYLED:
                $style = 'card styled';
                break;
            case TIG_Buckaroo3Extended_Model_Sources_CreditcardDesign::CREDITCARD_PAYMENT_METHOD_NOSTYLE:
            default:
                $style = 'card blank';
                break;
        }

        return $style;
    }
}