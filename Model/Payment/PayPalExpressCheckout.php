<?php
namespace PayPalBR\PayPal\Model\Payment;

/**
 * Pay In Store payment method model
 */
class PayPalExpressCheckout extends \Magento\Payment\Model\Method\AbstractMethod
{
    const METHOD_NAME = 'paypalbr_expresscheckout';

    /**
     * Payment code
     *
     * @var string
     */
    protected $_code = 'paypalbr_expresscheckout';
}