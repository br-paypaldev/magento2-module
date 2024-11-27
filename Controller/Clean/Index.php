<?php

namespace PayPalBR\PayPal\Controller\Clean;

use Magento\Framework\App\Action\Context;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Checkout\Model\Session as CheckoutSession;

class Index extends \Magento\Framework\App\Action\Action
{
    /**
     * @var \Magento\Framework\Controller\Result\JsonFactory
     */
    protected $jsonFactory;

    protected $checkoutSession;



    /**
     * Index constructor.
     *
     * @param \Magento\Framework\App\Action\Context $context
     * @param JsonFactory $jsonFactory
     * @param CheckoutSession $checkoutSession
     */
    public function __construct(
        Context $context,
        JsonFactory $jsonFactory,
        CheckoutSession $checkoutSession
    ) {
        $this->paypalPlusApi = $paypalPlusApi;
        $this->jsonFactory = $jsonFactory;
        $this->checkoutSession = $checkoutSession;
        parent::__construct($context);
    }

    public function execute()
    {
        $this->checkoutSession->clearStorage(); // Clean Checkout Session
        $resultJson = $this->jsonFactory->create();
        $resultJson
            ->setHttpResponseCode(200)
            ->setData('Success');

        return $resultJson;
    }
}
