<?php

namespace PayPalBR\PayPal\Block\Checkout;

use Magento\Framework\View\Element\Template\Context as TemplateContext;
use Magento\Checkout\Model\Session;
use Magento\Sales\Model\Order\Config;
use Magento\Framework\App\Http\Context as HttpContext;
use Magento\Sales\Model\OrderFactory;
use Magento\Checkout\Block\Onepage\Success as OnepageSuccess;

class Success extends OnepageSuccess
{
    const SCOPE_STORE = 'store';
    const XML_PATH_IS_METHOD_ACTIVE        = 'payment/paypalbr_paypalplus/active';
    const PENDING_PAYMENT_STATUS_CODE      = 'payment_review';

    /**
     * @var \Magento\Framework\App\Config\ScopeConfigInterface
     */
    private $_scopeconfig;

    /**
     *
     * @var \Magento\Sales\Model\OrderFactory
     */
    private $_orderFactory;

    /**
     *
     * @var \Magento\Sales\Model\Order
     */
    private $_order = false;

    /**
     * Constructor method
     *
     * @param \Magento\Framework\View\Element\Template\Context $context
     * @param \Magento\Sales\Model\OrderFactory $orderFactory
     * @param \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig
     * @param array $data
     */
    public function __construct(
        TemplateContext $context,
        Session $checkoutSession,
        Config $orderConfig,
        HttpContext $httpContext,
        OrderFactory $orderFactory,
        array $data = []
    )
    {
        parent::__construct($context, $checkoutSession, $orderConfig, $httpContext, $data);
        $this->_scopeconfig = $context->getScopeConfig();
        $this->_orderFactory = $orderFactory;
    }

    /**
     * Returns if the payment is made by PayPal Plus
     * @return bool
     */
    protected function isPaymentPaypal()
    {
        $result = false;
        $payment = $this->_order->getPayment();
        if ($payment) {
            $code = $payment->getMethod();
            $result = ($code == \PayPalBR\PayPal\Model\Payment\PayPalPlus::METHOD_NAME);
        }

        return $result;
    }

    /**
     * Get if method is active
     *
     * @return bool
     */
    public function getIsMethodActive()
    {
        if($this->isPaymentPaypal()) {
            return $this->getConfigValue(self::XML_PATH_IS_METHOD_ACTIVE);
        }

        return false;
    }

    /**
     * Load current Order
     *
     * @return \Magento\Sales\Model\Order
     */
    public function  _initOrder()
    {
        /** @var \Magento\Sales\Model\Order $order */
        $this->_order = $this->_orderFactory->create()->loadByIncrementId($this->getOrderId());
    }

    /**
     * Check if order has pending payment status
     *
     * @return boolean
     */
    public function isPaymentPending()
    {
        if($this->_order->getStatus() == self::PENDING_PAYMENT_STATUS_CODE){
            return true;
        }

        return false;
    }

   /**
     * Get payment store config
     *
     * @return string
     */
    public function getConfigValue($configPath)
    {
        $value =  $this->_scopeConfig->getValue(
            $configPath,
            self::SCOPE_STORE
        );

        return $value;
    }
}
