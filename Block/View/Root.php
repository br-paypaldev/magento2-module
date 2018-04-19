<?php

namespace PayPalBR\PayPal\Block\View;
use Magento\Framework\View;
use Magento\Framework\App\Config\ScopeConfigInterface;


class Root {

    protected $_requestPath = array();
    protected $_request;
    protected $_scopeConfig;

    const PPPLUS_SCRIPT_SOURCE = 'https://www.paypalobjects.com/webstatic/ppplusdcc/ppplusdcc.min.js';
    const CHECKOUT_ROUTE       = 'checkout/index/index';

    const XML_PATH_IS_ACTIVE   = 'payment/paypalbr_paypalplus/active';


    /**
     *
     * @param \Magento\Framework\View\Element\Template\Context $context
     * @param ScopeConfigInterface $scopeConfig
     */
    public function __construct(
        \Magento\Framework\View\Element\Template\Context $context
    ){
        $this->_request = $context->getRequest();
        $this->_scopeConfig = $context->getScopeConfig();
    }
    /**
     * Match current request to checkout/index/index and check if Payment method is active
     *
     * @return string
     */
    public function canIncludePayPalScript()
    {
        $this->getRequestPathArray();
        return implode("/", $this->_requestPath) == self::CHECKOUT_ROUTE && $this->isModuleActive() ? true : false;
    }
    /**
     * Get if Payment Method is active
     *
     * @return int
     */
    public function isModuleActive()
    {
         $value =  $this->_scopeConfig->getValue(
                self::XML_PATH_IS_ACTIVE,
                \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        );
        return (bool)$value;
    }
    /**
     * Merge request path into array
     *
     * @return array
     */
    public function getRequestPathArray()
    {
        $this->_requestPath[] =  $this->_request->getRouteName();
        $this->_requestPath[] =  $this->_request->getControllerName();
        $this->_requestPath[] =  $this->_request->getActionName();

        return $this->_requestPath;
    }
    /**
     *
     * @return string
     */
    public function getScriptSource()
    {
        return self::PPPLUS_SCRIPT_SOURCE;
    }
}
