<?php
namespace PayPalBR\PayPal\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\App\Request\DataPersistorInterface;
use Magento\Framework\App\ObjectManager;
use PayPalBR\PayPal\Model\PayPalPlus\ConfigProvider as PayPalPlusConfigProvider;
use PayPalBR\PayPal\Model\PayPalExpressCheckout\ConfigProvider as PayPalExpressCheckoutConfigProvider;
use Magento\Framework\Message\ManagerInterface;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Framework\UrlInterface;
use Magento\Framework\App\ResponseFactory;
use Magento\Framework\App\Cache\TypeListInterface;
use Magento\Framework\App\Cache\Frontend\Pool;

class DataAssign implements ObserverInterface
{
     const WEBHOOK_URL_ALREADY_EXISTS = 'WEBHOOK_URL_ALREADY_EXISTS';

    /**
     * Contains the config provider for Paypal Plus
     *
     * @var \PayPalBR\PayPal\Model\PayPalPlus\ConfigProvider 
     */
    protected $configProviderPayPalPlus;

    /**
     * Contains the config provider for Paypal Plus
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

    public function __construct(
        PayPalPlusConfigProvider $configProviderPayPalPlus,
        PayPalExpressCheckoutConfigProvider $configProviderPayPalExpressCheckout,
        ManagerInterface $messageManager,
        StoreManagerInterface $storeManager,
        UrlInterface $urlBuilder,
        ResponseFactory $responseFactory,
        TypeListInterface $cacheTypeList,
        Pool $cacheFrontendPool
    )
    {
        $this->storeManager = $storeManager;
        $this->configProviderPayPalPlus = $configProviderPayPalPlus;
        $this->messageManager = $messageManager;
        $this->urlBuilder = $urlBuilder;
        $this->responseFactory = $responseFactory;
        $this->cacheTypeList = $cacheTypeList;
        $this->cacheFrontendPool = $cacheFrontendPool;
        $this->configProviderPayPalExpressCheckout = $configProviderPayPalExpressCheckout;
    }

    /**
     * @param \Magento\Framework\Event\Observer $observer
     * @return \Magento\Framework\Message\ManagerInterface
     */
    public function execute(\Magento\Framework\Event\Observer $observer)
    {
        $this->validateConfigMagento();
        $resultPayPalPlus = $this->validatePayPalPlus();
        $resultPayPalExpressCheckout = $this->validatePayPalExpressCheckout();
        
        if ($resultPayPalPlus || $resultPayPalExpressCheckout) {
            $url = $this->urlBuilder->getUrl('adminhtml/system_config/edit/section/payment');
            $disableMessage= []; 
            if (is_array($resultPayPalPlus) && $resultPayPalPlus['status']) {
                $this->configProviderPayPalPlus->desactivateModule();
                $this->configProviderPayPalPlus->desactivateClientId();
                $this->configProviderPayPalPlus->desactivateSecretId();
                foreach ($resultPayPalPlus['message'] as $key => $message) {
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

    protected function validatePayPalExpressCheckout()
    {
        $clientId = $this->configProviderPayPalExpressCheckout->getClientId();
        $secretId = $this->configProviderPayPalExpressCheckout->getSecretId();

        if (!$clientId) {
            return false;
        }
        $disableModule = false;
        
        $paypalConfig = $this->getPayPalConfigApi();
        $apiContext = $this->getNewApiContext($clientId, $secretId);

        try {
            
            $apiContext->setConfig($paypalConfig);

            $oauth = new \PayPal\Auth\OAuthTokenCredential($clientId, $secretId);
            $oauth->getAccessToken($paypalConfig);
        } catch (\Exception $e) {
            $disableModule = true;
            $disableMessage[] = __('Incorrect API credentials in PayPal Express Checkout, please review it.');
        }

        if ($disableModule) {
            return [
                'status' => true,
                'message' => $disableMessage
            ];
        }

        $this->createWebhook($apiContext);

        return false;
    }

    protected function validatePayPalPlus()
    {
        $clientId = $this->configProviderPayPalPlus->getClientId();
        $secretId = $this->configProviderPayPalPlus->getSecretId();

        if (!$clientId) {
            return false;
        }

        $disableModule = false;
        
        $paypalConfig = $this->getPayPalConfigApi();
        $apiContext = $this->getNewApiContext($clientId, $secretId);

        try {
            
            $apiContext->setConfig($paypalConfig);

            $oauth = new \PayPal\Auth\OAuthTokenCredential($clientId, $secretId);
            $oauth->getAccessToken($paypalConfig);
        } catch (\Exception $e) {
            $disableModule = true;
            $disableMessage[] = __('Incorrect API credentials in PayPal Plus, please review it.');
        }

        if ($disableModule) {
            return [
                'status' => true,
                'message' => $disableMessage
            ];
        }

        $this->createWebhook($apiContext);

        return false;
    }

    protected function createWebhook($apiContext)
    {
        try {
            $output = \PayPal\Api\Webhook::getAll($apiContext);
        } catch (Exception $e) {
            print_r("Error in list webhooks was: {$e->getMessage()}");
            die;
        }

        $newWebhook = true;
        $baseUrl = $this->storeManager->getStore()->getBaseUrl('link', true) .'rest/default/V1/notifications/webhooks';
        foreach ($output->webhooks as $webhook) {
            
            if ($webhook->url == $baseUrl) {
                $newWebhook = false;
                $this->configProviderPayPalPlus->saveWebhookId($webhook->id);
            }
        }

        if($newWebhook){
            $this->saveWebhook($baseUrl, $apiContext);
        }

        return $this;
    }

    protected function saveWebhook($baseUrl, $apiContext)
    {
        $webhook = new \PayPal\Api\Webhook();
        $webhook->setUrl($baseUrl);

        $webhook->setEventTypes($this->getWebhookEventsType());

        try {
            $output = $webhook->create($apiContext);
            $this->configProviderPayPalPlus->saveWebhookId($output->id);
        } catch (\PayPal\Exception\PayPalConnectionException $ex) {
            if ($ex->getData()) {
                $data = json_decode($ex->getData(), true);
                if (isset($data['name']) && $data['name'] == self::WEBHOOK_URL_ALREADY_EXISTS) {
                    return true;
                }
                if (isset($data['details']) && isset($data['details'][0]) && isset($data['details'][0]['field']) && $data['details'][0]['field'] == 'url') {
                    $disableMessage = $data['details'][0]['issue'] . '. Url must be contain https.';
                    $this->messageManager->addError(__($disableMessage));
                }
            }
            return false;
        } catch (Exception $ex) {
            $this->messageManager->addError($ex->getMessage());
        }

        return $this;
    }

    protected function getNewApiContext($clientId, $secretId)
    {
        return new \PayPal\Rest\ApiContext(
            new \PayPal\Auth\OAuthTokenCredential(
                $clientId,
                $secretId
            )
        );
    }

    protected function validateConfigMagento()
    {
        $disableModule = false;
        $disableMessage;
        $url = $this->urlBuilder->getUrl('adminhtml/system_config/edit/section/customer');

        if(! $this->configProviderPayPalPlus->isStoreFrontActive() && $this->configProviderPayPalPlus->isActive()){
            $disableModule = true;
            $disableMessage[] = __("We have identified that your store does not have the active TAX / VAT feature. To add it's support, go to <a href='%1'> Here </a> or go to Customers-> Customer Settings-> Create New Customer Account-> Display VAT number in frontend." , 
                $url
            );
        }
        if(! $this->configProviderPayPalPlus->isTelephoneSet() && $this->configProviderPayPalPlus->isActive()){
            $disableModule = true;
            $disableMessage[] = __('We have identified that your store does not have an active phone, please enable to activate the module');
        }

        if( ! $this->configProviderPayPalPlus->isCustomerTaxRequired() && $this->configProviderPayPalPlus->isActive()){
            $disableModule = true;
            $disableMessage[] = __('We have identified that your store does not have support for CPF / CNPJ (TAXVAT). To add support, go to <a href="%1"> Here </a> and go to Shop-> Settings-> Clients-> Name and address options-> Show TAX / VAT number.', 
                $url
            );
        }

        if (! $this->configProviderPayPalPlus->isCurrencyBaseBRL() && $this->configProviderPayPalPlus->isActive()) {
            $disableModule = true;
            $disableMessage[] = __("Your base currency has to be BRL in order to activate this module.");
        }

        if ($disableModule) {
            $this->configProviderPayPalPlus->desactivateModule();
            $this->configProviderPayPalExpressCheckout->desactivateModule();

            $this->disableModule($disableMessage, $url);
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

    protected function getPayPalConfigApi()
    {
        return [
            'http.headers.PayPal-Partner-Attribution-Id' => 'MagentoBrazil_Ecom_PPPlus2',
            'mode' => $this->configProviderPayPalPlus->isModeSandbox()? 'sandbox' : 'live',
            'log.LogEnabled' => true,
            'log.FileName' => BP . '/var/log/paypalbr/paypalconfig-' . date('Y-m-d') . '.log',
            'log.LogLevel' => 'DEBUG', // PLEASE USE `INFO` LEVEL FOR LOGGING IN LIVE ENVIRONMENTS
            'http.CURLOPT_SSLVERSION' => 'CURL_SSLVERSION_TLSv1_2'
        ];
    }

    protected function getWebhookEventsType()
    {
        $webhookEventTypes = [];
        $webhookEventTypes[] = new \PayPal\Api\WebhookEventType( '{ "name": "PAYMENT.SALE.COMPLETED" }' );
        $webhookEventTypes[] = new \PayPal\Api\WebhookEventType( '{ "name": "PAYMENT.SALE.DENIED" }' );
        $webhookEventTypes[] = new \PayPal\Api\WebhookEventType( '{ "name": "PAYMENT.SALE.PENDING" }'
        );
        $webhookEventTypes[] = new \PayPal\Api\WebhookEventType(
            '{
                "name": "PAYMENT.SALE.REFUNDED"
            }'
        );
        $webhookEventTypes[] = new \PayPal\Api\WebhookEventType(
            '{
                "name": "RISK.DISPUTE.CREATED"
            }'
        );
        $webhookEventTypes[] = new \PayPal\Api\WebhookEventType(
            '{
                "name": "CUSTOMER.DISPUTE.CREATED"
            }'
        );

        return $webhookEventTypes;
    }
}