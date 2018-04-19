<?php

namespace PayPalBR\PayPal\Controller\LoginPayPal;

use Magento\Framework\App\Action\Context;
use PayPalBR\PayPal\Model\LoginPayPalManagementApi;
use Magento\Framework\Controller\Result\JsonFactory;

class Authorize extends \Magento\Framework\App\Action\Action
{
    /**
     * @var \PayPalBR\PayPal\Model\LoginPayPalManagementApi
     */
    protected $loginPayPalApi;

    /**
     * @var \Magento\Framework\Controller\Result\JsonFactory
     */
    protected $jsonFactory;

    /**
     * Index constructor.
     * @param Context $context
     * @param LoginPayPalManagementApi $loginPayPalApi
     * @param JsonFactory $jsonFactory
     */
    public function __construct(
        Context $context,
        LoginPayPalManagementApi $loginPayPalApi,
        JsonFactory $jsonFactory
    ) {
        $this->loginPayPalApi = $loginPayPalApi;
        $this->jsonFactory = $jsonFactory;

        parent::__construct($context);
    }

    public function execute()
    {
        $post = $this->getRequest()->getPost();

        $resultJson = $this->jsonFactory->create();

        $response = $this->loginPayPalApi->authorizePayPalLogin($post);

        if ($response['status'] == 'success') {
            $resultJson
                ->setHttpResponseCode(200)
                ->setData($response);
        } else {
            $resultJson
                ->setHttpResponseCode(400)
                ->setData([
                    'message' => $response
                ]);
        }
        return $resultJson;
    }
}