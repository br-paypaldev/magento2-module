<?php

namespace PayPalBR\PayPal\Controller\Transaction;

use Magento\Framework\App\Action\Action;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\RequestInterface;

class Approve extends Action
{

    /**
     * @var JsonFactory
     */
    protected $jsonFactory;


    protected $request;

    /**
     * Index constructor.
     * @param Context $context
     * @param JsonFactory $jsonFactory
     */
    public function __construct(
        Context $context,
        RequestInterface $request,
        JsonFactory $jsonFactory
    ) {
        $this->jsonFactory = $jsonFactory;
        $this->request = $request;

        parent::__construct($context);
    }

    public function execute()
    {
        $resultJson = $this->jsonFactory->create();

        $resultJson
                ->setHttpResponseCode(200)
                ->setData("success");

        return $resultJson;
    }
}
