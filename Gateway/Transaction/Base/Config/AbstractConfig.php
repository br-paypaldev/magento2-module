<?php

namespace PayPalBR\PayPal\Gateway\Transaction\Base\Config;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;

abstract class AbstractConfig
{
    protected $storeConfig;

    /**
     * @param ScopeConfigInterface $storeConfig
     */
    public function __construct(
        ScopeConfigInterface $storeConfig
    )
    {
        $this->setStoreConfig($storeConfig);
    }

    /**
     * @param $path
     * @param null $store
     * @return mixed
     */
    protected function getConfig($path, $store = null)
    {
        if (! $store){
            $store = ScopeInterface::SCOPE_STORE;
        }

        return $this->getStoreConfig()->getValue($path, $store);
    }

    /**
     * @return ScopeConfigInterface
     */
    protected function getStoreConfig()
    {
        return $this->storeConfig;
    }

    /**
     * @param ScopeConfigInterface $storeConfig
     * @return self
     */
    protected function setStoreConfig(ScopeConfigInterface $storeConfig)
    {
        $this->storeConfig = $storeConfig;
        return $this;
    }
}
