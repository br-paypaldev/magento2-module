<?php

namespace PayPalBR\PayPal\Controller\Adminhtml\Credentials;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Backend\Model\UrlInterface;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Stdlib\CookieManagerInterface;
use PayPalBR\PayPal\Model\PayPalBCDC\ConfigProvider;
use PayPalBR\PayPal\Model\PayPalRequests;
use Magento\Framework\App\Cache\TypeListInterface;

class Index extends Action
{
    const AUTH_CODE_VALUE = 'authCode';
    const SHARED_ID_VALUE = 'sharedId';
    const SELLER_NONCE_VALUE = 'sellerNonce';

    /**
     * @var ConfigProvider
     */
    private $configProvider;

    /**
     * @var UrlInterface
     */
    private $backendUrl;

    /**
     * @var CookieManagerInterface
     */
    private $cookieManager;

    /**
     * @var PayPalRequests
     */
    private $payPalRequests;

    /**
     * @var TypeListInterface
     */
    private $cacheTypeList;

    public function __construct(
        Context $context,
        ConfigProvider $configProvider,
        UrlInterface $backendUrl,
        PayPalRequests $payPalRequests,
        TypeListInterface $cacheTypeList,
        CookieManagerInterface $cookieManager
    ) {
        $this->backendUrl = $backendUrl;
        $this->payPalRequests = $payPalRequests;
        $this->cookieManager = $cookieManager;
        $this->configProvider = $configProvider;
        $this->cacheTypeList = $cacheTypeList;
        parent::__construct($context);
    }

    public function execute()
    {
        $authCode = $this->cookieManager->getCookie(self::AUTH_CODE_VALUE);
        $sharedId = $this->cookieManager->getCookie(self::SHARED_ID_VALUE);
        $sellerNonce = $this->cookieManager->getCookie(self::SELLER_NONCE_VALUE);

        $merchantId = $this->getRequest()->getParam('merchantId');

        $accessToken = $this->payPalRequests->getAccessToken(
            [
                "authorization" => base64_encode($sharedId . ':'),
                "type" => "shared",
                "auth" => $authCode,
                "nonce" => $sellerNonce
            ]
        );

        $sellerTokens = $this->payPalRequests->getSellerCredentials($merchantId, $accessToken);

        $this->configProvider->setClientId($sellerTokens->client_id);
        $this->configProvider->setSecretId($sellerTokens->client_secret);
        $this->configProvider->setMerchantId($merchantId);

        $this->cookieManager->deleteCookie(self::AUTH_CODE_VALUE);
        $this->cookieManager->deleteCookie(self::SHARED_ID_VALUE);
        $this->cookieManager->deleteCookie(self::SELLER_NONCE_VALUE);

        $this->cacheTypeList->cleanType('config');

        $url = $this->backendUrl->getUrl('adminhtml/system_config/edit/section/payment');
        $resultRedirect = $this->resultFactory->create(ResultFactory::TYPE_REDIRECT);
        $resultRedirect->setUrl($url);
        return $resultRedirect;
    }
}
