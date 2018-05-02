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
    const XML_PATH_MODE = 'payment/paypalbr_expresscheckout/mode';

    /**
     * Contains the module active
     */
    const XML_PATH_ACTIVE = 'payment/paypalbr_expresscheckout/active';

    /**
     * Contains the module iframe height active
     */
    const XML_PATH_IFRAME_ACTIVE = 'payment/paypalbr_expresscheckout/iframe_height_active';

    /**
     * Contains the module iframe height
     */
    const XML_PATH_IFRAME_HEIGHT = 'payment/paypalbr_expresscheckout/iframe_height';

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
                    'login_paypal_active' => $this->configExpressCheckout->isLoginPayPalActive(),
                    'text' => 'payment/paypalbr_expresscheckout/text',
                    'exibitionName' => $exibition,
                    'mode' => $mode,
                    'options_payments' => $this->toOptionArrayPayments(),
                    'is_payment_ready' => false,
                    'mode'=> $this->configExpressCheckout->getModeToString(),
                    'color' => $this->configExpressCheckout->getPayPalLoginButtonColor(),
                    'shape' => $this->configExpressCheckout->getPayPalLoginButtonShape(),
                    'button' => $this->configExpressCheckout->getPayPalLoginButton(),
                    'mini_cart'=> $this->configExpressCheckout->getPayPalMiniCartActive(),
                    'locale' => $this->configExpressCheckout->getLocaleStore()
                ]
            ]
        ];
    }

    public function toOptionArrayPayments()
    {
        $payments = $this->_paymentModelConfig->getActiveMethods();
        $methods = array();
        foreach ($payments as $paymentCode => $paymentModel) {
            if ($paymentCode == 'free' || $paymentCode == 'paypal_billing_agreement') {
                continue;
            }
            $paymentTitle = $this->_scopeConfig->getValue('payment/'.$paymentCode.'/title');
            $methods[$paymentCode] = array(
                'label' => $paymentTitle,
                'value' => $paymentCode
            );
        }
        return count($methods);
    }
}
