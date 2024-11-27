<?php

namespace PayPalBR\PayPal\Controller\ReviewOrder;

use Magento\Framework\App\Action\Context;
use Magento\Customer\Model\Session;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\View\Result\PageFactory;

class Index extends \Magento\Framework\App\Action\Action
{

    protected $jsonFactory;

    protected $pageFactory;

    protected $context;

    protected $customerSession;

    protected $messageManager;

    public function __construct(
        \Magento\Framework\Message\ManagerInterface $messageManager,
        Context $context,
        JsonFactory $jsonFactory,
        PageFactory $pageFactory,
        Session $customerSession,
    ){
        parent::__construct($context);
        $this->jsonFactory = $jsonFactory;
        $this->pageFactory = $pageFactory;
        $this->customerSession = $customerSession;
        $this->messageManager = $messageManager;
    }

    public function execute()
    {
        if (!$this->customerSession->isLoggedIn()) {
           $this->_redirect('customer/account/login');

           return; # code...
        }

        try {
            $this->loginPayPal->updateUserAddress();
        } catch (Exception $e) {

            $this->messageManager->addExceptionMessage(
                $e,
                $e->getMessage()
            );

            $this->_redirect('customer/account/login');

           return; # code...
        }


        $result = $this->pageFactory->create();
        $result->getConfig()->getTitle()->set("Review Order");

        return $result;
    }


}
