<?php

namespace PayPalBR\PayPal\Model\Ui\PayPalPlus;

use Magento\Checkout\Model\ConfigProviderInterface;
use Magento\Framework\UrlInterface;
use Magento\Payment\Helper\Data as PaymentHelper;
use Magento\Customer\Model\SessionFactory;
use \Magento\Payment\Model\Config;

final class PayPalPlusConfigProvider implements ConfigProviderInterface
{
    /**
     * @var string[]
     */
    protected $methodCode = \PayPalBR\PayPal\Model\PayPalPlus::PAYMENT_METHOD_PAYPALPLUS_CODE;

    /**
     * Contains the configuration path for showing exibition name
     */
    const XML_CUSTOMER_EXHIBITION_SHOW = 'payment/paypalbr_paypalplus/exhibition_name';


    /**
     * Contains the current mode, sandbox or production (live)
     */
    const XML_PATH_MODE = 'payment/paypalbr_paypalplus/mode';

    /**
     * Contains the module active
     */
    const XML_PATH_ACTIVE = 'payment/paypalbr_paypalplus/active';

    /**
     * Contains the module iframe height active
     */
    const XML_PATH_IFRAME_ACTIVE = 'payment/paypalbr_paypalplus/iframe_height_active';

    /**
     * Contains the module iframe height
     */
    const XML_PATH_IFRAME_HEIGHT = 'payment/paypalbr_paypalplus/iframe_height';

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
     * @param ConfigInterface $payPalPlusConfig
     */
    public function __construct(
        PaymentHelper $paymentHelper,
        UrlInterface $urlBuilder,
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        SessionFactory $sessionFactory,
        Config $paymentModelConfig
    ) {
        $this->sessionFactory = $sessionFactory;
        $this->paymentHelper = $paymentHelper;
        $this->urlBuilder = $urlBuilder;
        $this->_scopeConfig = $scopeConfig;
        $this->_paymentModelConfig = $paymentModelConfig;
    }

    public function getConfig()
    {
        $storeScope = \Magento\Store\Model\ScopeInterface::SCOPE_STORE;
        $exibition = $this->_scopeConfig->getValue(self::XML_CUSTOMER_EXHIBITION_SHOW, $storeScope);
        $mode = $this->_scopeConfig->getValue(self::XML_PATH_MODE, $storeScope);
        $active = $this->_scopeConfig->getValue(self::XML_PATH_ACTIVE, $storeScope);
        $iframeHeightActive = $this->_scopeConfig->getValue(self::XML_PATH_IFRAME_ACTIVE, $storeScope);
        $iframeHeight = $this->_scopeConfig->getValue(self::XML_PATH_IFRAME_HEIGHT, $storeScope);

        if(empty($exibition)){
            $exibition = "";
        }
        $customerSession = $this->sessionFactory->create();
        $rememberedCard = '';

        if ($customerSession->isLoggedIn()){
            $customer = $customerSession->getCustomer();
            $data = $customer->getData();

            if (isset($data['remembered_card'])) {
                $rememberedCard = $data['remembered_card'];
            }
        }


        return [
            'payment' => [
                $this->methodCode => [
                    'active' => $active,
                    'text' => 'payment/paypalbr_paypalplus/text',
                    'exibitionName' => $exibition,
                    'mode' => $mode,
                    'rememberedCard' => $rememberedCard,
                    'iframe_height_active' => $iframeHeightActive,
                    'iframe_height' => $iframeHeight,
                    'options_payments' => $this->toOptionArrayPayments(),
                    'is_payment_ready' => false,
                    'paypalObject' => []
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
