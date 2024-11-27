<?php

namespace PayPalBR\PayPal\Gateway\Transaction\PayPalBCDC\ResourceGateway\Create;

use Magento\Payment\Gateway\Data\PaymentDataObjectInterface;
use Magento\Payment\Gateway\Request\BuilderInterface;

use PayPalBR\PayPal\Model\PayPalRequests;

class RequestBuilder implements BuilderInterface
{
    const MODULE_NAME = 'PayPalBR_PayPal';

    protected $paypalRequests;

    public function __construct(
        PayPalRequests $paypalRequests
    ) {
        $this->paypalRequests = $paypalRequests;

    }

    /**
     * {@inheritdoc}
     */
    public function build(array $buildSubject)
    {
        if (!isset($buildSubject['payment']) || !$buildSubject['payment'] instanceof PaymentDataObjectInterface) {
            throw new \InvalidArgumentException('Payment data object should be provided');
        }

        return $this->paypalRequests->getOrder();
    }
}
