<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace PayPalBR\PayPal\Block\Button;
use \Magento\Framework\View\Element\Template;
use \Magento\Framework\UrlInterface;
/**
 * Customer login form block
 *
 * @api
 * @author      Magento Core Team <core@magentocommerce.com>
 * @since 100.0.2
 */
class Button extends Template
{
    /**
     * @var int
     */
    private $_username = -1;

    /**
     * @var \Magento\Customer\Model\Session
     */
    protected $_customerSession;

    /**
     * @var \Magento\Customer\Model\Url
     */
    protected $_customerUrl;

    /**
     * @var \Magento\Checkout\Helper\Cart
     */
    protected $_cart;

    /**
     * @var \PayPalBR\PayPal\Model\PayPalExpressCheckout\ConfigProvider
     */
    protected $_config;
    /**
     * @var Config
     */
    protected $configExpressCheckout;

    /**
     * @var Magento\Framework\UrlInterface
     */
    protected $_urlBuilder;

    /**
     * LoginPayPal constructor.
     * @param \Magento\Framework\View\Element\Template\Context $context
     * @param \Magento\Customer\Model\Session $customerSession
     * @param \Magento\Checkout\Helper\Cart $cart
     * @param \Magento\Customer\Model\Url $customerUrl
     * @param array $data
     */
    public function __construct(
        \Magento\Framework\View\Element\Template\Context $context,
        \Magento\Customer\Model\Session $customerSession,
        \Magento\Checkout\Helper\Cart $cart,
        \Magento\Customer\Model\Url $customerUrl,
        \PayPalBR\PayPal\Model\PayPalExpressCheckout\ConfigProvider $config,
        \Magento\Framework\UrlInterface $urlBuilder,
        array $data = []
    ) {
        parent::__construct($context, $data);
        $this->_isScopePrivate = false;
        $this->_customerUrl = $customerUrl;
        $this->_customerSession = $customerSession;
        $this->_cart = $cart;
        $this->_config = $config;        
        $this->_urlBuilder = $urlBuilder;
    }

    /**
     * @return $this
     */
    protected function _prepareLayout()
    {
        $this->pageConfig->getTitle()->set(__('Customer Login'));
        return parent::_prepareLayout();
    }

    /**
     * Retrieve form posting url
     *
     * @return string
     */
    public function getPostActionUrl()
    {
        return $this->_customerUrl->getLoginPostUrl();
    }

    /**
     * Retrieve password forgotten url
     *
     * @return string
     */
    public function getForgotPasswordUrl()
    {
        return $this->_customerUrl->getForgotPasswordUrl();
    }

    /**
     * Retrieve username for form field
     *
     * @return string
     */
    public function getUsername()
    {
        if (-1 === $this->_username) {
            $this->_username = $this->_customerSession->getUsername(true);
        }
        return $this->_username;
    }

    /**
     * Check if autocomplete is disabled on storefront
     *
     * @return bool
     */
    public function isAutocompleteDisabled()
    {
        return (bool)!$this->_scopeConfig->getValue(
            \Magento\Customer\Model\Form::XML_PATH_ENABLE_AUTOCOMPLETE,
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        );
    }

    /**
     * @return int
     */
    public function getCartItemsCount()
    {
        return $this->_cart->getItemsCount();
    }

    /**
     * @return boolean
     */
    public function getLoginPayPalActive()
    {
        return $this->_config->isLoginPayPalActive();
    }
    
    public function getButtonActive()
    {
        return $this->_config->getPayPalMiniCartActive();
    }

    public function getCreateUrl()
    {
        return $this->_urlBuilder->getUrl('expresscheckout/loginpaypal/create');
    }

    public function getExecuteUrl()
    {
        return $this->_urlBuilder->getUrl('expresscheckout/loginpaypal/authorize');
    }

}
