<?php
namespace PayPalBR\PayPal\Block\Checkout;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\View\Element\Template;
use Magento\Store\Model\StoreManagerInterface;

class OSC extends Template
{
    const SCOPE_STORE = 'store';
    const XML_PATH_OSC  = 'payment/paypalbr_paypalplus/osc';

    /**
     * @var ScopeConfigInterface
     */
    private $_scopeconfig;

    private $storeManager;

    /**
     * Constructor
     *
     * @param StoreManagerInterface $storeManager
     * @param Template\Context $context
     * @param array $data
     */
    public function __construct(
        StoreManagerInterface $storeManager,
        Template\Context $context,
        array $data = []
    ) {
        $this->_scopeconfig = $context->getScopeConfig();
        $this->storeManager = $storeManager;
        parent::__construct($context, $data);
    }

    public function getOSC()
    {
        if ($this->getConfigValue(self::XML_PATH_OSC) == 1) { //Firecheckout
            return 'firecheckout';
        }
        return 0;
    }

    public function getConfigValue($configPath)
    {
        $value =  $this->_scopeConfig->getValue(
            $configPath,
            self::SCOPE_STORE
        );

        return $value;
    }
}
