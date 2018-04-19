<?php

namespace PayPalBR\PayPal\Gateway\Transaction\Base\Config;


class Config extends AbstractConfig implements ConfigInterface
{
    /**
     * @return string
     */
    public function getSecretKey()
    {
        if ($this->getTestMode()) {
            return $this->getConfig(static::PATH_SECRET_KEY_TEST);
        }
        
        return $this->getConfig(static::PATH_SECRET_KEY);
    }

    /**
     * @return string
     */
    public function getPublicKey()
    {
        if ($this->getTestMode()) {
            return $this->getConfig(static::PATH_PUBLIC_KEY_TEST);
        }
        
        return $this->getConfig(static::PATH_PUBLIC_KEY);
    }

    /**
     * @return string
     */
    public function getTestMode()
    {
        return $this->getConfig(static::PATH_TEST_MODE);
    }

    /**
     * {@inheritdoc}
     */
    public function getBaseUrl()
    {
        if ($this->getTestMode()) {
            return $this->getConfig(static::PATH_SAND_BOX_URL);
        }

        return $this->getConfig(static::PATH_PRODUCTION_URL);
    }

    /**
     * {@inheritdoc}
     */
    public function getToggle()
    {
        return $this->getConfig(static::PATH_TOGGLE);
    }

    /**
     * {@inheritdoc}
     */
    public function getStoreName()
    {
        return $this->getConfig(static::STORE_NAME);
    }

    /**
     * {@inheritdoc}
     */
    public function getIframeActiveHeight()
    {
        return $this->getConfig(static::PATH_IFRAME_ACTIVE);
    }

    /**
     * {@inheritdoc}
     */
    public function getIframeHeight()
    {
        return $this->getConfig(static::PATH_IFRAME_HEIGHT);
    }
}
