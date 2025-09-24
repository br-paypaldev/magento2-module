<?php

namespace PayPalBR\PayPal\Model\Ui\PayPalExpressCheckout;

use Magento\Checkout\Model\ConfigProviderInterface;
use Magento\Framework\UrlInterface;
use Magento\Payment\Helper\Data as PaymentHelper;
use Magento\Customer\Model\SessionFactory;
use \Magento\Payment\Model\Config;
use \PayPalBR\PayPal\Model\PayPalExpressCheckout\ConfigProvider;

final class PayPalExpressCheckoutConfigProvider implements ConfigProviderInterface
{
    /**
     * @var string[]
     */
    protected $methodCode = \PayPalBR\PayPal\Model\PayPalExpressCheckout::PAYMENT_METHOD_PAYPALEXPRESSCHECKOUT_CODE;

    /**
     * Contains the configuration path for showing exibition name
     */
    const XML_CUSTOMER_EXHIBITION_SHOW = 'payment/paypalbr_expresscheckout/exhibition_name';


    /**
     * Contains the current mode, sandbox or production (live)
     */
    const XML_PATH_MODE = 'payment/paypalbr/mode';

    /**
     * Contains the module active
     */
    const XML_PATH_ACTIVE = 'payment/paypalbr_expresscheckout/active';

    /**
     * @var PaymentHelper
     */
    protected $paymentHelper;

    /**
     * @var UrlInterface
     */
    protected $urlBuilder;

    /**
    * @var \Magento\Framework\App\Config\ScopeConfigInterface
    */
    protected $_scopeConfig;

    /**
    * @var \Magento\Customer\Model\SessionFactory
    */
    protected $sessionFactory;

    /**
     * @var Config
     */
    protected $_paymentModelConfig;

    /**
     * @var Config
     */
    protected $configExpressCheckout;

    /**
     * @param ConfigInterface $payPalPlusConfig
     */
    public function __construct(
        PaymentHelper $paymentHelper,
        UrlInterface $urlBuilder,
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        SessionFactory $sessionFactory,
        Config $paymentModelConfig,
        ConfigProvider $configExpressCheckout
    ) {
        $this->sessionFactory = $sessionFactory;
        $this->paymentHelper = $paymentHelper;
        $this->urlBuilder = $urlBuilder;
        $this->_scopeConfig = $scopeConfig;
        $this->_paymentModelConfig = $paymentModelConfig;
        $this->configExpressCheckout = $configExpressCheckout;
    }

    public function getConfig()
    {
        $storeScope = \Magento\Store\Model\ScopeInterface::SCOPE_STORE;
        $exibition = $this->_scopeConfig->getValue(self::XML_CUSTOMER_EXHIBITION_SHOW, $storeScope);
        $mode = $this->_scopeConfig->getValue(self::XML_PATH_MODE, $storeScope);
        $active = $this->_scopeConfig->getValue(self::XML_PATH_ACTIVE, $storeScope);

        if(empty($exibition)){
            $exibition = "";
        }

        return [
            'payment' => [
                $this->methodCode => [
                    'active' => $active,
                    'text' => 'payment/paypalbr_expresscheckout/text',
                    'exibitionName' => $exibition,
                    'mode' => $mode,
                    'is_payment_ready' => false,
                    'color' => $this->configExpressCheckout->getPayPalLoginButtonColor(),
                    'shape' => $this->configExpressCheckout->getPayPalLoginButtonShape(),
                    'locale' => $this->configExpressCheckout->getLocaleStore()
                ]
            ]
        ];
    }
}
