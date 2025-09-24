<?php

namespace PayPalBR\PayPal\Block\Checkout;

use Magento\Framework\View\Element\Template;
use Magento\Store\Model\StoreManagerInterface;
use PayPalBR\PayPal\Model\PayPalBCDC\ConfigProvider;

class ClientId extends Template {

    /**
     * @var ConfigProvider
     */
    protected $configProvider;

    /**
     * @var StoreManagerInterface
     */
    private $storeManager;

    public function __construct(
        ConfigProvider $configProvider,
        StoreManagerInterface $storeManager,
        Template\Context $context,
        array $data = []
    ) {
        $this->storeManager = $storeManager;
        $this->configProvider = $configProvider;
        parent::__construct($context, $data);
    }

    public function getClientId() {
        return $this->configProvider->getClientId();
    }

    public function getCurrentCurrencyCode()
    {
        return $this->storeManager->getStore()->getCurrentCurrencyCode();
    }
}
