<?php

namespace PayPalBR\PayPal\Controller\Payment;

use Magento\Framework\App\Action\Context;
use PayPalBR\PayPal\Model\PayPalApi;
use Magento\Framework\Controller\Result\JsonFactory;

class Paypal extends \Magento\Framework\App\Action\Action
{
    /**
     * Contains paypal plus api
     *
     * @var \PayPalBR\PayPalPlus\Model\PayPalPI
     */
    protected $paypalApi;

    /**
     * @var \Magento\Framework\Controller\Result\JsonFactory
     */
    protected $jsonFactory;

    /**
     * Index constructor.
     *
     * @param \Magento\Framework\App\Action\Context $context
     * @param \PayPalBR\PayPal\Model\PayPalApi $paypalApi
     */
    public function __construct(
        Context $context,
        PayPalApi $paypalApi,
        JsonFactory $jsonFactory
    ) {
        $this->paypalApi = $paypalApi;
        $this->jsonFactory = $jsonFactory;

        parent::__construct($context);
    }

    public function execute()
    {
        $resultJson = $this->jsonFactory->create();

        $response = $this->paypalApi->createOrder();
        if ($response['status'] == 'success') {
            $resultJson
                ->setHttpResponseCode(200)
                ->setData($response['message']);
        } else {
            $resultJson
                ->setHttpResponseCode(400)
                ->setData([
                    'message' => $response['message']
                ]);
        }
        return $resultJson;
    }
}
