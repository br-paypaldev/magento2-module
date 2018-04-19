<?php

namespace PayPalBR\PayPal\Api;


interface BaseRequestDataProviderInterface
{
    /**
     * @return float
     */
    public function getAmountInCents();

    /**
     * @return string
     */
    public function getTransactionReference();

    /**
     * @return string
     */
    public function getName();

    /**
     * @return string
     */
    public function getPersonType();

    /**
     * @return string
     */
    public function getDocumentNumber();

    /**
     * @return string
     */
    public function getDocumentType();

    /**
     * @return string
     */
    public function getEmail();

    /**
     * @return string
     */
    public function getHomePhone();

    /**
     * @return string
     */
    public function getBillingAddressStreet();

    /**
     * @return string
     */
    public function getBillingAddressNumber();

    /**
     * @return string
     */
    public function getBillingAddressComplement();

    /**
     * @return string
     */
    public function getBillingAddressDistrict();

    /**
     * @return string
     */
    public function getBillingAddressCity();

    /**
     * @return string
     */
    public function getBillingAddressState();

    /**
     * @return string
     */
    public function getBillingAddressZipCode();

    /**
     * @return string
     */
    public function getBillingAddressCountry();


    /**
     * @return string
     */
    public function getShippingAddressStreet();

    /**
     * @return string
     */
    public function getShippingAddressNumber();

    /**
     * @return string
     */
    public function getShippingAddressComplement();

    /**
     * @return string
     */
    public function getShippingAddressDistrict();

    /**
     * @return string
     */
    public function getShippingAddressCity();

    /**
     * @return string
     */
    public function getShippingAddressState();

    /**
     * @return string
     */
    public function getShippingAddressZipCode();

    /**
     * @return string
     */
    public function getShippingAddressCountry();

    /**
     * @return string
     */
    public function getIpAddress();

    /**
     * @return string
     */
    public function getSessionId();

    /**
     * @return \Magento\Sales\Model\Order\Item[]
     */
    public function getCartItems();

}
