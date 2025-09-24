<?php

namespace PayPalBR\PayPal\Gateway\Transaction\PayPalBCDC\Command;

use PayPalBR\PayPal\Gateway\Transaction\Base\Command\AbstractApiCommand;

class RefundCommand extends AbstractApiCommand
{
    /**
     * @param $request
     * @return mixed
     */
    protected function sendRequest($request)
    {
        if (!isset($request)) {
            throw new \InvalidArgumentException('PayPal BCDC Request object should be provided');
        }

        return $request;
    }
}
