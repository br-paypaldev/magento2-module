<?php

namespace PayPalBR\PayPal\Model\Ui\PayPalBCDC;

use Magento\Checkout\Model\ConfigProviderInterface;
use Magento\Framework\UrlInterface;
use Magento\Payment\Helper\Data as PaymentHelper;
use Magento\Customer\Model\SessionFactory;
use \Magento\Payment\Model\Config;

final class PayPalBCDCConfigProvider implements ConfigProviderInterface
{
    /**
     * @var string[]
     */
    protected $methodCode = \PayPalBR\PayPal\Model\PayPalBCDC::PAYMENT_METHOD_PAYPALBCDC_CODE;

    /**
     * Contains the configuration path for showing exibition name
     */
    const XML_CUSTOMER_EXHIBITION_SHOW = 'payment/paypalbr_bcdc/exhibition_name';


    /**
     * Contains the current mode, sandbox or production (live)
     */
    const XML_PATH_MODE = 'payment/paypalbr/mode';

    /**
     * Contains the module active
     */
    const XML_PATH_ACTIVE = 'payment/paypalbr_bcdc/active';

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

        if(empty($exibition)){
            $exibition = "";
        }

        return [
            'payment' => [
                $this->methodCode => [
                    'active' => $active,
                    'text' => 'payment/paypalbr_bcdc/text',
                    'exibitionName' => $exibition,
                    'mode' => $mode,
                    'is_payment_ready' => false,
                    'paypalObject' => []
                ]
            ]
        ];
    }
}
