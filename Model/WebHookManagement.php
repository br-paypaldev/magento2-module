<?php
namespace PayPalBR\PayPal\Model;

use oauth;
use PayPalBR\PayPal\Api\EventsInterface;
use PayPalBR\PayPal\Api\WebHookManagementInterface;
use PayPalBR\PayPal\Model\PayPalPlus\ConfigProvider;
use PayPal\Api\VerifyWebhookSignature;

class WebHookManagement implements WebHookManagementInterface
{
    protected $eventWebhook;
    protected $configProvider;

    public function __construct(
        EventsInterface $eventWebhook,
        ConfigProvider $configProvider
    ) {
        $this->setEventWebhook($eventWebhook);
        $this->setConfigProvider($configProvider);
    }

    /**
     * Builds and returns the api context to be used in PayPal Plus API
     *
     * @return \PayPal\Rest\ApiContext
     */
    protected function getApiContext()
    {

        $debug = $this->getConfigProvider()->isDebugEnabled();
        $this->configId = $this->getConfigProvider()->getClientId();
        $this->secretId = $this->getConfigProvider()->getSecretId();

        if($debug == 1){
            $debug = true;
        }else{
            $debug = false;
        }
        $apiContext = new \PayPal\Rest\ApiContext(
            new \PayPal\Auth\OAuthTokenCredential(
                $this->configId,
                $this->secretId
            )
        );
        $apiContext->setConfig(
            [
                //'http.headers.PayPal-Partner-Attribution-Id' => 'MagentoBrazil_Ecom_PPPlus2',
                'mode' => $this->configProvider->isModeSandbox() ? 'sandbox' : 'live',
                'log.LogEnabled' => $debug,
                'log.FileName' => BP . '/var/log/paypalbr/webhook/paypal-webhook-' . date('Y-m-d') . '.log',
                'log.LogLevel' => 'DEBUG',
                'cache.enabled' => true,
                'http.CURLOPT_SSLVERSION' => 'CURL_SSLVERSION_TLSv1_2'
            ]
        );
        return $apiContext;
    }

    /**
    * {@inheritdoc}
    */
    public function postWebHook($id, $create_time, $resource_type, $event_type, $summary, $resource, $links, $event_version)
    {
        $array = [
            'id' => $id,
            'create_time' => $create_time,
            'resource_type' => $resource_type,
            'event_type' => $event_type,
            'summary' => $summary,
            'resource' => $resource,
            'links' => $links,
            'event_version' => $event_version,
        ];

        try {
            $httpRequestObject = new \Zend_Controller_Request_Http();
            $paypalAuthAlgo = $httpRequestObject->getHeader('Paypal-Auth-Algo');
            $paypalTransmissionId = $httpRequestObject->getHeader('Paypal-Transmission-Id');
            $paypalCertUrl = $httpRequestObject->getHeader('Paypal-Cert-Url');
            $paypalTransmissionSig = $httpRequestObject->getHeader('Paypal-Transmission-Sig');
            $paypalTransmissionTime = $httpRequestObject->getHeader('Paypal-Transmission-Time');
            $requestBody = $httpRequestObject->getRawBody();
            
            $signatureVerification = new VerifyWebhookSignature();
            $signatureVerification->setAuthAlgo($paypalAuthAlgo);
            $signatureVerification->setTransmissionId($paypalTransmissionId);
            $signatureVerification->setCertUrl($paypalCertUrl);
            $signatureVerification->setWebhookId($this->getConfigProvider()->getWebhookId());
            $signatureVerification->setTransmissionSig($paypalTransmissionSig);
            $signatureVerification->setTransmissionTime($paypalTransmissionTime);

            $signatureVerification->setRequestBody($requestBody);

            if ($this->getConfigProvider()->isDebugEnabled()) {
                $this->logger($signatureVerification);
            }

            $output = $signatureVerification->post($this->getApiContext());

            if ($output->verification_status == 'FAILURE') {
                $this->logger('initial debug');
                $this->logger($signatureVerification);
                $this->logger($output);
                $this->logger('final debug');

                $return = [
                    [
                        'status' => 401,
                        'message' => 'Validation FAILURE'
                    ]
                ];

                return $return;
            }
        } catch (\Exception $e) {   
            $this->logger('initial debug');
            $this->logger($e);
            $this->logger('final debug');

            $return = [
                [
                    'status' => 200,
                    'message' => 'Validation FAILURE'
                ]
            ];

            return $return;
        }

        if (! $id) {
            return false;
        }

        $webhookApi = new \PayPal\Api\WebhookEvent;

        $webhookApi
            ->setId($id)
            ->setCreateTime($create_time)
            ->setResourceType($resource_type)
            ->setEventType($event_type)
            ->setSummary($summary)
            ->setResource($resource)
            ->setLinks($links);
        try {
            
            if ($this->getConfigProvider()->isDebugEnabled()) {
                $this->logger($array);
            }
            $this->getEventWebhook()->processWebhookRequest($webhookApi);
            $return = [
                [
                    'status' => 200,
                    'message' => $summary
                ]
            ];
        } catch (\Exception $e) {
            $this->logger('initial debug');
            $this->logger($e);
            $this->logger('final debug');

            $return = [
                [
                    'status' => 200,
                    'message' => $e->getMessage()
                ]
            ];
        }

        return $return;
    }

    protected function logger($array)
    {
        $writer = new \Zend\Log\Writer\Stream(BP . '/var/log/paypalbr/webhook/paypal-webhook-' . date('Y-m-d') . '.log');
        $logger = new \Zend\Log\Logger();
        $logger->addWriter($writer);
        $logger->info($array);
    }

    /**
     * @return mixed
     */
    public function getEventWebhook()
    {
        return $this->eventWebhook;
    }

    /**
     * @param mixed $eventWebhook
     *
     * @return self
     */
    public function setEventWebhook($eventWebhook)
    {
        $this->eventWebhook = $eventWebhook;

        return $this;
    }

    /**
     * @return mixed
     */
    public function getConfigProvider()
    {
        return $this->configProvider;
    }

    /**
     * @param mixed $configProvider
     *
     * @return self
     */
    public function setConfigProvider($configProvider)
    {
        $this->configProvider = $configProvider;

        return $this;
    }
}