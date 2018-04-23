<?php

namespace PayPalBR\PayPal\Gateway\Transaction\PayPalExpressCheckout\Command;

use PayPalBR\PayPal\Gateway\Transaction\Base\Command\AbstractApiCommand;

class AuthorizeCommand extends AbstractApiCommand
{
    /**
     * @param $request
     * @return mixed
     */
    protected function sendRequest($request)
    {
        if (!isset($request)) {
            throw new \InvalidArgumentException('PayPalExpressCheckout Request object should be provided');
        }

        return $request;
    }
}
