<?php

namespace PayPalBR\PayPal\Api;


interface CartItemRequestDataProviderInterface
{
    /**
     * @return string
     */
    public function getDescription();

    /**
     * @return string
     */
    public function getItemReference();

    /**
     * @return string
     */
    public function getName();

    /**
     * @return int
     */
    public function getQuantity();

    /**
     * @return float
     */
    public function getUnitCostInCents();

    /**
     * @return float
     */
    public function getTotalCostInCents();
}
