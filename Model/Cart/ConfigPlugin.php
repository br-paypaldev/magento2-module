<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace PayPalBR\PayPal\Model\Cart;

class ConfigPlugin
{
    /**
     * @var \Magento\Captcha\Model\Checkout\ConfigProvider
     */
    protected $configProvider;

    /**
     * @param \Magento\Captcha\Model\Checkout\ConfigProvider $configProvider
     */
    public function __construct(
        \PayPalBR\PayPal\Model\Ui\PayPalExpressCheckout\PayPalExpressCheckoutConfigProvider $configProvider
    ) {
        $this->configProvider = $configProvider;
    }

    /**
     * @param \Magento\Checkout\Block\Cart\Sidebar $subject
     * @param array $result
     * @return array
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function afterGetConfig(\Magento\Checkout\Block\Cart\Sidebar $subject, array $result)
    {
        return array_merge_recursive($result, $this->configProvider->getConfig());
    }
}
