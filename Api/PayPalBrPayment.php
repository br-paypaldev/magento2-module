<?php

namespace PayPalBR\PayPal\Api;

use PayPal\Api\Payment;

/**
 * Class PayPalBrPayment
 *
 * Create the ApplicationContext
 *
 * @property \PayPal\Api application_context
 */
class PayPalBrPayment extends Payment {

    public function setApplicationContext($application_context) {
        $this->application_context = $application_context;
        return $this;
    }

    /**
     * Identifier for the payment experience.
     *
     * @return \PayPal\Api\Transaction[]
     */
    public function getApplicationContext() {
        return $this->application_context;
    }

}
