<?php

namespace PayPalBR\PayPal\Controller\Payment;

use Magento\Framework\App\Action\Context;
use PayPalBR\PayPal\Model\PaypalPlusApi;
use Magento\Framework\Controller\Result\JsonFactory;

class Index extends \Magento\Framework\App\Action\Action
{
    /**
     * Contains paypal plus api
     *
     * @var \PayPalBR\PayPalPlus\Model\PaypalPlusApi
     */
    protected $paypalPlusApi;

    /**
     * @var \Magento\Framework\Controller\Result\JsonFactory
     */
    protected $jsonFactory;

    /**
     * Index constructor.
     *
     * @param \Magento\Framework\App\Action\Context $context
     * @param \PayPalBR\PayPal\Model\PaypalPlusApi $paypalPlusApi
     */
    public function __construct(
        Context $context,
        PaypalPlusApi $paypalPlusApi,
        JsonFactory $jsonFactory
    ) {
        $this->paypalPlusApi = $paypalPlusApi;
        $this->jsonFactory = $jsonFactory;

        parent::__construct($context);
    }

    public function execute()
    {
        $resultJson = $this->jsonFactory->create();

        $response = $this->paypalPlusApi->execute();
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