<?php
namespace PayPalBR\PayPal\Model\Payment;

/**
 * Pay In Store payment method model
 */
class PayPalPlus extends \Magento\Payment\Model\Method\AbstractMethod
{
    const METHOD_NAME = 'paypalbr_paypalplus';

    /**
     * Payment code
     *
     * @var string
     */
    protected $_code = 'paypalbr_paypalplus';
}