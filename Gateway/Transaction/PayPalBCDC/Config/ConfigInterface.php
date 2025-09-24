<?php

namespace PayPalBR\PayPal\Gateway\Transaction\PayPalBCDC\Config;

interface ConfigInterface
{
    const PATH_TEXT = 'payment/paypalbr_bcdc/text';

    /**
     * @return string
     */
    public function getText();
}
