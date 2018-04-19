<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace PayPalBR\PayPal\Model;

/**
 * Class PayPalPlus
 *
 * @method \Magento\Quote\Api\Data\PaymentMethodExtensionInterface getExtensionAttributes()
 *
 * @api
 */
class PayPalExpressCheckout extends \Magento\Payment\Model\Method\AbstractMethod
{
    const PAYMENT_METHOD_PAYPALEXPRESSCHECKOUT_CODE = 'paypalbr_expresscheckout';

    /**
     * Payment method code
     *
     * @var string
     */
    protected $_code = self::PAYMENT_METHOD_PAYPALEXPRESSCHECKOUT_CODE;

    /**
     * @var string
     */
    protected $_formBlockType = \PayPalBR\PayPal\Block\Form\PayPalPlus::class;

    /**
     * @var string
     */
    protected $_infoBlockType = \PayPalBR\PayPal\Block\Info\PayPalPlus::class;

    /**
     * @var ConfigProvider
     */
    protected $configProvider;

    public function __construct(
        \Magento\Framework\Model\Context $context,
        \Magento\Framework\Registry $registry,
        \Magento\Framework\Api\ExtensionAttributesFactory $extensionFactory,
        \Magento\Framework\Api\AttributeValueFactory $customAttributeFactory,
        \Magento\Payment\Helper\Data $paymentData,
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Magento\Payment\Model\Method\Logger $logger,
        \Magento\Framework\Model\ResourceModel\AbstractResource $resource = null,
        \Magento\Framework\Data\Collection\AbstractDb $resourceCollection = null,
        array $data = [],
        \PayPalBR\PayPal\Model\PayPalExpressCheckout\ConfigProvider $configProvider
    )
    {
        parent::__construct(
            $context,
            $registry,
            $extensionFactory,
            $customAttributeFactory,
            $paymentData,
            $scopeConfig,
            $logger,
            $resource,
            $resourceCollection,
            $data
        );
        $this->configProvider = $configProvider;
    }

    /**
     * Check if it is available
     *
     * @param \Magento\Quote\Api\Data\CartInterface|null $quote
     * @return bool
     */
    public function isAvailable(\Magento\Quote\Api\Data\CartInterface $quote = null)
    {
        if ( ! $this->configProvider->isActive() ) {
            return false;
        }
        return parent::isAvailable($quote);
    }
}
