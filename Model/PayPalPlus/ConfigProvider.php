<?php
namespace PayPalBR\PayPal\Model\PayPalPlus;

use PayPalBR\PayPal\Model\Config\Source\Mode;
use \Magento\Store\Model\ScopeInterface;

/**
 * Class ConfigProvider
 *
 * This class provides access to Magento 2 configuration over PayPal Plus
 *
 * @package PayPalBR\PayPal\Model
 */
class ConfigProvider
{
    /**
     * Contains scope config of Magento
     *
     * @var \Magento\Framework\App\Config\ScopeConfigInterface
     */
    protected $scopeConfig;

    /**
     * Contains the configurations
     *
     * @var \Magento\Framework\App\Config\ConfigResource\ConfigInterface
     */
    protected $config;

    /**
     * Contains if the module is active or not
     */
    const XML_PATH_ACTIVE = 'payment/paypalbr_paypalplus/active';

    /**
     * Contains the client ID of PayPal Plus (Sandbox)
     */
    const XML_PATH_CLIENT_ID_SBOX = 'payment/paypalbr_paypalplus/client_id_sandbox';

    /**
     * Contains the secret ID of PayPal Plus (Sandbox)
     */
    const XML_PATH_SECRET_ID_SBOX = 'payment/paypalbr_paypalplus/secret_id_sandbox';

    /**
     * Contains the secret ID of PayPal Plus (Production)
     */
    const XML_PATH_CLIENT_ID_PROD= 'payment/paypalbr_paypalplus/client_id_production';

    /**
     * Contains the secret ID of PayPal Plus (Production)
     */
    const XML_PATH_SECRET_ID_PROD = 'payment/paypalbr_paypalplus/secret_id_production';

    /**
     * Contains the secret ID of PayPal Plus (Production)
     */
    const XML_PATH_WEBHOOK_ID = 'payment/paypalbr_paypalplus/webhook_id';

    /**
     * Contains the current mode, sandbox or production (live)
     */
    const XML_PATH_MODE = 'payment/paypalbr_paypalplus/mode';

    /**
     * Contains the configuration path for showing customer tax show
     */
    const XML_CUSTOMER_TAX_SHOW = 'customer/address/taxvat_show';


    /**
     * Contains the configuration path for showing telephone
     */
    const XML_CUSTOMER_TEL = 'customer/address/telephone_show';

    /**
     * Contains the base currency of the store
     */
    const XML_PATH_CURRENCY_OPTIONS_BASE = 'currency/options/base';

    /**
     * Contains the debug status
     */
    const XML_PATH_DEBUG_STATUS = 'payment/paypalbr_paypalplus/depuration_mode';


    /**
     * Contains the view TAx in store front
     */
    const XML_PATH_TAX_VIEW = 'customer/create_account/vat_frontend_visibility';



    /**
     * ConfigProvider constructor.
     *
     * @param \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig
     */
    public function __construct(
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Magento\Framework\App\Config\ConfigResource\ConfigInterface $config
    )
    {
        $this->scopeConfig = $scopeConfig;
        $this->config = $config;
    }

    /**
     * Returns the debug configuration
     *
     * @return string
     */
    public function getDebug()
    {
        $debug = $this->scopeConfig->getValue(self::XML_PATH_DEBUG_STATUS, ScopeInterface::SCOPE_STORE);
        return $debug;
    }

    /**
     * Check if debug mode is enabled or not
     *
     * @return bool
     */
    public function isDebugEnabled()
    {
        $debug = $this->getDebug();

        return $debug == 1;
    }

    /**
     * Returns the current mode
     *
     * @return string
     */
    public function getMode()
    {
        $mode = $this->scopeConfig->getValue(self::XML_PATH_MODE, ScopeInterface::SCOPE_STORE);
        return $mode;
    }

    /**
     * Returns true if the mode is sandbox
     *
     * @return bool
     */
    public function isModeSandbox()
    {
        if ($this->getMode() === '1') {
            return true;
        }

        return false;
    }

    /**
     * Returns true if the mode is production
     *
     * @return bool
     */
    public function isModeProduction()
    {
        return $this->getMode() == '2';
    }

    /**
     * Returns the client id for sandbox
     *
     * @return string
     */
    protected function getClientIdSandbox()
    {
        $clientId = $this->scopeConfig->getValue(self::XML_PATH_CLIENT_ID_SBOX, ScopeInterface::SCOPE_STORE);
        return $clientId;
    }

    /**
     * Returns the secret id for sandbox
     *
     * @return string
     */
    protected function getSecretIdSandbox()
    {
        $secretId = $this->scopeConfig->getValue(self::XML_PATH_SECRET_ID_SBOX, ScopeInterface::SCOPE_STORE);
        return $secretId;
    }

    /**
     * Returns the client id for production
     *
     * @return string
     */
    protected function getClientIdProduction()
    {
        $clientId = $this->scopeConfig->getValue(self::XML_PATH_CLIENT_ID_PROD, ScopeInterface::SCOPE_STORE);
        return $clientId;
    }

    /**
     * Returns the secret id for production
     *
     * @return string
     */
    protected function getSecretIdProduction()
    {
        $secretId = $this->scopeConfig->getValue(self::XML_PATH_SECRET_ID_PROD, ScopeInterface::SCOPE_STORE);
        return $secretId;
    }

    /**
     * Returns the client ID for the current selected mode
     *
     * @return string
     * @throws \Exception
     */
    public function getClientId()
    {
        $clientId = "";
        if ($this->isModeSandbox()) {
            $clientId = $this->getClientIdSandbox();
        } else if ($this->isModeProduction()) {
            $clientId = $this->getClientIdProduction();
        } else {
            throw new \Exception("Could not determine which mode is used!");
        }
        return $clientId;
    }

    /**
     * Returns the secret ID for the current selected mode
     *
     * @return string
     * @throws \Exception
     */
    public function getSecretId()
    {
        $secretId = "";
        if ($this->isModeSandbox()) {
            $secretId = $this->getSecretIdSandbox();
        } else if ($this->isModeProduction()) {
            $secretId = $this->getSecretIdProduction();
        } else {
            throw new \Exception("Could not determine which mode is used!");
        }
        return $secretId;
    }

    /**
     * Returns if the store front is activate
     *
     * This configuration uses \Magento\Config\Model\Config\Source\Yesno as backend.
     * 1 is for YES, and 0 is for NO.
     *
     * @return bool
     */
    public function isStoreFrontActive()
    {
        $active = $this->scopeConfig->getValue(self::XML_PATH_TAX_VIEW, ScopeInterface::SCOPE_STORE);

        return $active == 1;
    }

    /**
     * Returns if the module is activated
     *
     * This configuration uses \Magento\Config\Model\Config\Source\Yesno as backend.
     * 1 is for YES, and 0 is for NO.
     *
     * @return bool
     */
    public function isActive()
    {
        $active = $this->scopeConfig->getValue(self::XML_PATH_ACTIVE, ScopeInterface::SCOPE_STORE);

        return $active == 1;
    }

    /**
     * Checks if customer tax number is required or not.
     *
     * @return bool
     */
    public function isCustomerTaxRequired()
    {
        $customerTaxShow = $this->scopeConfig->getValue(self::XML_CUSTOMER_TAX_SHOW, ScopeInterface::SCOPE_STORE);

        return $customerTaxShow == 'req';
    }

    /**
     * Checks if customer tax number is required or not.
     *
     * @return bool
     */
    public function isTelephoneSet()
    {
        $telephone = $this->scopeConfig->getValue(self::XML_CUSTOMER_TEL, ScopeInterface::SCOPE_STORE);

        return $telephone == 'req';
    }

    /**
     * Deactivates module
     *
     * This functions sets the active configuration to 0 (zero), which will disable the module.
     */
    public function desactivateModule()
    {
        $this->config->saveConfig(self::XML_PATH_ACTIVE, 0, 'default', 0);
    }

    /**
     * Deactivates module
     *
     * This functions sets the active configuration to 0 (zero), which will disable the module.
     */
    public function desactivateClientId()
    {
        if ($this->isModeSandbox()) {
            $this->config->saveConfig(self::XML_PATH_CLIENT_ID_SBOX, '', 'default', 0);
        } else if ($this->isModeProduction()) {
            $this->config->saveConfig(self::XML_PATH_CLIENT_ID_PROD, '', 'default', 0);
        }
    }

    /**
     * Deactivates module
     *
     * This functions sets the active configuration to 0 (zero), which will disable the module.
     */
    public function desactivateSecretId()
    {
        if ($this->isModeSandbox()) {
            $this->config->saveConfig(self::XML_PATH_SECRET_ID_SBOX, '', 'default', 0);
        } else if ($this->isModeProduction()) {
            $this->config->saveConfig(self::XML_PATH_SECRET_ID_PROD, '', 'default', 0);
        }
    }

    /**
     * Save webhook id module
     */
    public function saveWebhookId($id)
    {
        $this->config->saveConfig(self::XML_PATH_WEBHOOK_ID, $id, 'default', 0);
    }

    /**
     * Get webhook id module
     */
    public function getWebhookId()
    {
        return $this->scopeConfig->getValue(self::XML_PATH_WEBHOOK_ID, ScopeInterface::SCOPE_STORE);
    }

    /**
     * Returns the base currency
     *
     * @return string
     */
    public function getCurrencyBase()
    {
        $currencyBase = $this->scopeConfig->getValue(self::XML_PATH_CURRENCY_OPTIONS_BASE, ScopeInterface::SCOPE_STORE);

        return $currencyBase;
    }

    /**
     * Checks if the base currency is BRL or not
     *
     * @return bool
     */
    public function isCurrencyBaseBRL()
    {
        return $this->getCurrencyBase() == 'BRL';
    }



}