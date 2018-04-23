<?php

namespace PayPalBR\PayPal\Api;


interface PayPalPlusRequestDataProviderInterface extends BaseRequestDataProviderInterface
{
    /**
     * @return mixed
     */
    public function getPayId();

    /**
     * @return mixed
     */
    public function getRemeberedCard();

    /**
     * @return mixed
     */
    public function getPayerId();

    /**
     * @return mixed
     */
    public function getToken();

    /**
     * @return mixed
     */
    public function getTerm();
}
