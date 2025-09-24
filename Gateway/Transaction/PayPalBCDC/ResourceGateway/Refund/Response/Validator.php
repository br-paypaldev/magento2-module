<?php

namespace PayPalBR\PayPal\Gateway\Transaction\PayPalBCDC\ResourceGateway\Refund\Response;

use Magento\Payment\Gateway\Validator\ValidatorInterface;
use PayPalBR\PayPal\Gateway\Transaction\Base\ResourceGateway\Response\AbstractValidator;

class Validator extends AbstractValidator implements ValidatorInterface
{
    /**
     * {@inheritdoc}
     */
    public function validate(array $validationSubject)
    {
        return $this->createResult(true, []);
    }
}
