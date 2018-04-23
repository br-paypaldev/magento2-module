<?php

namespace PayPalBR\PayPal\Gateway\Transaction\Base\Config;

interface ConfigInterface
{
    const PATH_PUBLIC_KEY_TEST    = 'payment/paypalbr_paypalplus/public_key_test';
    const PATH_SECRET_KEY_TEST    = 'payment/paypalbr_paypalplus/secret_key_test';
    const PATH_PUBLIC_KEY         = 'payment/paypalbr_paypalplus/public_key';
    const PATH_SECRET_KEY         = 'payment/paypalbr_paypalplus/secret_key';
    const PATH_TEST_MODE          = 'payment/paypalbr_paypalplus/test_mode';
    const PATH_TOGGLE             = 'payment/paypalbr_paypalplus/toggle';
    const STORE_NAME              = 'general/store_information/name';
    const PATH_IFRAME_ACTIVE      = 'payment/paypalbr_paypalplus/iframe_height_active';
    const PATH_IFRAME_HEIGHT      = 'payment/paypalbr_paypalplus/iframe_height';

    /**
     * @return string
     */
    public function getSecretKey();

    /**
     * @return string
     */
    public function getPublicKey();

    /**
     * @return string
     */
    public function getTestMode();

    /**
     * @return string
     */
    public function getBaseUrl();

    /**
     * @return string
     */
    public function getToggle();

    /**
     * @return string
     */
    public function getStoreName();

    /**
     * @return string
     */
    public function getIframeActiveHeight();

    /**
     * @return string
     */
    public function getIframeHeight();
}
