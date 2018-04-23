<?php
/**
 * Created by PhpStorm.
 * User: fabio
 * Date: 06/03/18
 * Time: 09:50
 */

namespace PayPalBR\PayPal\Api;

/**
 * Interface LoginPayPalCreateManagementInterface
 * @package PayPalBR\PayPal\Api
 */
interface LoginPayPalCreateManagementInterface
{

    const NO_SHIPPING = 'NO_SHIPPING';

    const SET_PROVIDED_ADDRESS = 'SET_PROVIDED_ADDRESS';

    /**
     * @return array
     */
    public function initPayPalLightBox();

    /**
     * @param string $paymentId
     * @return mixed
     */
    public function authorizePayPalLogin($paymentId);

}