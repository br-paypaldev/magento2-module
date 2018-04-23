<?php

namespace PayPalBR\PayPal\Controller\Payment;

use Magento\Framework\Json\Helper\Data;
use Magento\Framework\App\Action\Context;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Controller\Result\RawFactory;
use Psr\Log\LoggerInterface;
use Magento\Customer\Model\Customer;
use Magento\Customer\Model\Session;

class Cards extends \Magento\Framework\App\Action\Action
{
    protected $_resultJsonFactory;
    protected $_resultRawFactory;
    protected $_request;
    protected $_helper;
    protected $_objectManager;
    protected $_encryptor;
    protected $_logger;
    protected $_customer;
    protected $_session;
    
    /**
     * @param Context $context
     */
    public function __construct(
            Context $context,
            JsonFactory $resultJsonFactory,
            RawFactory $resultRawFactory,
            Data $helper,
            LoggerInterface $logger,
            Customer $customer,
            Session $session

    ){
        $this->_resultJsonFactory = $resultJsonFactory;
        $this->_resultRawFactory = $resultRawFactory;
        $this->_helper = $helper;
        $this->_objectManager = $context->getObjectManager();
        $this->_logger = $logger;
        $this->_customer = $customer;
        $this->_session = $session;
        parent::__construct($context);
    }
    /**
     * Save tokenized cards
     * @param token
     */
    public function execute()
    {
        $resultJson = $this->_resultJsonFactory->create();
        $resultRaw = $this->_resultRawFactory->create();
        $httpBadRequestCode = '400';
        $httpErrorCode = '500';

        try {
            $requestData = $this->_helper->jsonDecode($this->getRequest()->getContent());
        } catch (\Exception $e) {
            $resultRaw->setData($e->getMessage());
            return $resultRaw->setHttpResponseCode($httpErrorCode);
        }
        if ($this->getRequest()->getMethod() !== 'POST' || !$this->getRequest()->isXmlHttpRequest()) {
            return $resultRaw->setHttpResponseCode($httpBadRequestCode);
        }

        $tokenId = $requestData['token_id'];

        if(!$tokenId || empty($tokenId)){
            return $resultRaw->setHttpResponseCode($httpBadRequestCode);
        }
        try{
            $customer = $this->_customer;
            $customerSession = $this->_session;
            if($customerSession->isLoggedIn()){
                $customerId = $customerSession->getCustomerId();
                $customer->load($customerId);
                $customer->setCardTokenId($tokenId);
                $customer->save();
            }
        } catch (Exception $e) {
            $resultRaw->setData($e->getMessage());
            return $resultRaw->setHttpResponseCode($httpErrorCode);
        }

        $response = json_encode(['success' => true]);
        return $resultJson->setData($response);
    }
}