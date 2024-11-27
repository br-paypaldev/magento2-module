<?php
namespace PayPalBR\PayPal\Observer;
use Magento\Framework\Event\ObserverInterface;
use PayPal\Rest\ApiContext;
use PayPalBR\PayPal\Model\PayPalBCDC\ConfigProvider as PayPalBCDCConfigProvider;
use PayPalBR\PayPal\Model\PayPalExpressCheckout\ConfigProvider as PayPalExpressCheckoutConfigProvider;
use Magento\Framework\Message\ManagerInterface;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Framework\UrlInterface;
use Magento\Framework\App\ResponseFactory;
use Magento\Framework\App\Cache\TypeListInterface;
use Magento\Framework\App\Cache\Frontend\Pool;
use Magento\Framework\Filesystem\DirectoryList;
use PayPalBR\PayPal\Model\PayPalRequests;

class DataAssign implements ObserverInterface
{
     const WEBHOOK_URL_ALREADY_EXISTS = 'WEBHOOK_URL_ALREADY_EXISTS';

     const PAYPAL_BCDC = '1';

     const PAYPAL_EXPRESS_CHECKOUT = '2';

     /**
     * Contains the config provider for Paypal BCDC
     *
     * @var \PayPalBR\PayPal\Model\PayPalBCDC\ConfigProvider
     */
    protected $configProviderPayPalBCDC;

    /**
     * Contains the config provider for Paypal Express Checkout
     *
     * @var \PayPalBR\PayPal\Model\PayPalExpressCheckout\ConfigProvider
     */
    protected $configProviderPayPalExpressCheckout;

    /**
     *
     * @var \Magento\Store\Model\StoreManagerInterface
     */
    protected $storeManager;

    /**
     * @var \Magento\Framework\UrlInterface
     */
    protected $urlBuilder;

    /**
     * @var \Magento\Framework\App\ResponseFactory
     */
    protected $responseFactory;

    /**
     * @var \Magento\Framework\Message\ManagerInterface
     */
    protected $messageManager;

    /**
     * @var \Magento\Framework\App\Cache\TypeListInterface
     */
    protected $cacheTypeList;

    /**
     * @var \Magento\Framework\App\Cache\Frontend\Pool
     */
    protected $cacheFrontendPool;

    /**
     * @var PayPalRequests
     */
    protected $paypalRequests;

    /**
     * @var
     */
    protected $dir;

    public function __construct(
        PayPalBCDCConfigProvider $configProviderPayPalBCDC,
        PayPalExpressCheckoutConfigProvider $configProviderPayPalExpressCheckout,
        ManagerInterface $messageManager,
        StoreManagerInterface $storeManager,
        UrlInterface $urlBuilder,
        ResponseFactory $responseFactory,
        TypeListInterface $cacheTypeList,
        Pool $cacheFrontendPool,
        PayPalRequests $paypalRequests,
        DirectoryList $dir
    )
    {
        $this->storeManager = $storeManager;
        $this->messageManager = $messageManager;
        $this->urlBuilder = $urlBuilder;
        $this->responseFactory = $responseFactory;
        $this->cacheTypeList = $cacheTypeList;
        $this->cacheFrontendPool = $cacheFrontendPool;
        $this->configProviderPayPalBCDC = $configProviderPayPalBCDC;
        $this->configProviderPayPalExpressCheckout = $configProviderPayPalExpressCheckout;
        $this->paypalRequests = $paypalRequests;
        $this->dir = $dir;
    }

    /**
     * @param \Magento\Framework\Event\Observer $observer
     * @return \Magento\Framework\Message\ManagerInterface
     */
    public function execute(\Magento\Framework\Event\Observer $observer)
    {
        $resultPayPalBCDC = $this->validatePayPalBCDC();
        $resultPayPalExpressCheckout = $this->validatePayPalExpressCheckout();

        if ($resultPayPalBCDC || $resultPayPalExpressCheckout) {
            $url = $this->urlBuilder->getUrl('adminhtml/system_config/edit/section/payment');
            $disableMessage= [];
            if (is_array($resultPayPalBCDC) && $resultPayPalBCDC['status']) {
                $this->configProviderPayPalBCDC->desactivateModule();
                $this->configProviderPayPalBCDC->desactivateClientId();
                $this->configProviderPayPalBCDC->desactivateSecretId();
                foreach ($resultPayPalBCDC['message'] as $key => $message) {
                    $disableMessage[] = $message;
                }
            }

            if (is_array($resultPayPalExpressCheckout) && $resultPayPalExpressCheckout['status']) {
                $this->configProviderPayPalExpressCheckout->desactivateModule();
                $this->configProviderPayPalExpressCheckout->desactivateClientId();
                $this->configProviderPayPalExpressCheckout->desactivateSecretId();
                foreach ($resultPayPalExpressCheckout['message'] as $key => $message) {
                    $disableMessage[] = $message;
                }
            }

            $this->disableModule($disableMessage, $url);
        }

        return $this;
    }

    protected function validatePayPalBCDC()
    {
        $clientId = $this->configProviderPayPalBCDC->getClientId();
        $secretId = $this->configProviderPayPalBCDC->getSecretId();

        if (!$clientId) {
            return false;
        }
        $disableModule = false;

        try {
            $oauth = $this->paypalRequests->getAccessToken([
                "authorization" => base64_encode($clientId . ':' . $secretId),
                "type" => "id"
            ]);
        } catch (\Exception $e) {
            $disableModule = true;
            $disableMessage[] = __('Incorrect API credentials in PayPal Express Checkout, please review it. Also, check var/log/paypalbr folder permissions.');
        }

        if ($disableModule) {
            return [
                'status' => true,
                'message' => $disableMessage
            ];
        }

        $this->createWebhook();

        return false;
    }

    protected function validatePayPalExpressCheckout()
    {
        $clientId = $this->configProviderPayPalBCDC->getClientId();
        $secretId = $this->configProviderPayPalBCDC->getSecretId();

        if (!$clientId) {
            return false;
        }
        $disableModule = false;

        try {
            $oauth = $this->paypalRequests->getAccessToken([
                "authorization" => base64_encode($clientId . ':' . $secretId),
                "type" => "id"
            ]);
        } catch (\Exception $e) {
            $disableModule = true;
            $disableMessage[] = __('Incorrect API credentials in PayPal Express Checkout, please review it. Also, check var/log/paypalbr folder permissions.');
        }

        if ($disableModule) {
            return [
                'status' => true,
                'message' => $disableMessage
            ];
        }

        $this->createWebhook();

        return false;
    }

    protected function createWebhook()
    {
        try {
            $output = $this->paypalRequests->getWebhooks();
        } catch (\Exception $e) {
            print_r("Error in list webhooks was: {$e->getMessage()}");
            die;
        }

        $newWebhook = true;
        $baseUrl = $this->storeManager->getStore()->getBaseUrl('link', true) .'rest/default/V1/notifications/webhooks';
        foreach ($output->webhooks as $webhook) {

            if ($webhook->url == $baseUrl) {
                $newWebhook = false;
                $this->configProviderPayPalBCDC->saveWebhookId($webhook->id);
            }
        }

        if($newWebhook){
            $this->saveWebhook($baseUrl);
        }

        return $this;
    }

    protected function saveWebhook($baseUrl)
    {
        try {
            $output = $this->paypalRequests->createWebhook($baseUrl, $this->getWebhookEventsType());
            $this->configProviderPayPalBCDC->saveWebhookId($output->id);
        } catch (\Exception $ex) {
            $this->messageManager->addError($ex->getMessage());
        }

        return $this;
    }

    protected function disableModule($disableMessage, $url)
    {
        foreach ($disableMessage as $message) {
            $this->messageManager->addError($message);
        }

        $this->cleanCache();

        $this->responseFactory->create()
                ->setRedirect($url)
                ->sendResponse();
        exit(0);
    }

    protected function cleanCache()
    {
        $types = array('config','layout','block_html','collections','reflection','db_ddl','eav','config_integration','config_integration_api','full_page','translate','config_webservice');

        foreach ($types as $type) {
            $this->cacheTypeList->cleanType($type);
        }

        foreach ($this->cacheFrontendPool as $cacheFrontend) {
            $cacheFrontend->getBackend()->clean();
        }

        return $this;
    }

    protected function getWebhookEventsType()
    {
        $webhookEventTypes = [];
        $webhookEventTypes[] = ["name" => "PAYMENT.CAPTURE.COMPLETED"];
        $webhookEventTypes[] = ["name" => "PAYMENT.CAPTURE.DENIED"];
        $webhookEventTypes[] = ["name" => "PAYMENT.CAPTURE.REFUNDED"];
        $webhookEventTypes[] = ["name" => "PAYMENT.CAPTURE.REVERSED"];
        $webhookEventTypes[] = ["name" => "PAYMENT.CAPTURE.PENDING"];
        $webhookEventTypes[] = ["name" => "CHECKOUT.ORDER.COMPLETED"];
        $webhookEventTypes[] = ["name" => "CHECKOUT.ORDER.APPROVED"];
        $webhookEventTypes[] = ["name" => "CHECKOUT.ORDER.PROCESSED"];
        $webhookEventTypes[] = ["name" => "PAYMENT.AUTHORIZATION.CREATED"];
        $webhookEventTypes[] = ["name" => "PAYMENT.AUTHORIZATION.VOIDED"];
        $webhookEventTypes[] = ["name" => "RISK.DISPUTE.CREATED"];
        $webhookEventTypes[] = ["name" => "CUSTOMER.DISPUTE.CREATED"];

        return $webhookEventTypes;
    }
}
