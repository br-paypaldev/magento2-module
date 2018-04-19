<?php

namespace PayPalBR\PayPal\Gateway\Transaction\PayPalPlus\Command;

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
            throw new \InvalidArgumentException('PayPalPlus Request object should be provided');
        }

        return $request;
    }
}
