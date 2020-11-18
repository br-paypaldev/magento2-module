<?php
namespace PayPalBR\PayPal\Model;

use oauth;
use PayPalBR\PayPal\Api\EventsInterface;
use PayPalBR\PayPal\Api\WebHookManagementInterface;
use PayPalBR\PayPal\Model\PayPalPlus\ConfigProvider;
use PayPal\Api\VerifyWebhookSignature;
use Magento\Framework\Filesystem\DirectoryList;

class WebHookManagement implements WebHookManagementInterface
{
    protected $eventWebhook;
    protected $configProvider;
    protected $dir;

    public function __construct(
        EventsInterface $eventWebhook,
        ConfigProvider $configProvider,
        DirectoryList $dir
    ) {
        $this->setEventWebhook($eventWebhook);
        $this->setConfigProvider($configProvider);
        $this->dir = $dir;
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
                'log.FileName' => $this->dir->getPath('log') . '/paypal-webhook-' . date('Y-m-d') . '.log',
                'cache.FileName' => $this->dir->getPath('log') . '/auth.cache',
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
            $paypalAuthAlgo = $this->getHeader1('Paypal-Auth-Algo');
            $paypalTransmissionId = $this->getHeader1('Paypal-Transmission-Id');
            $paypalCertUrl = $this->getHeader1('Paypal-Cert-Url');
            $paypalTransmissionSig = $this->getHeader1('Paypal-Transmission-Sig');
            $paypalTransmissionTime = $this->getHeader1('Paypal-Transmission-Time');

            $requestBody = false;
            $body = file_get_contents('php://input');

            if (strlen(trim($body)) > 0) {
                $requestBody = $body;
            } else {
                $requestBody = false;
            }

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

    protected function getHeader1($header)
    {
        if (empty($header)) {
            #require_once 'Zend/Controller/Request/Exception.php';
            throw new Zend_Controller_Request_Exception('An HTTP header name is required');
        }

        // Try to get it from the $_SERVER array first
        $temp = strtoupper(str_replace('-', '_', $header));
        if (isset($_SERVER['HTTP_' . $temp])) {
            return $_SERVER['HTTP_' . $temp];
        }

        /*
         * Try to get it from the $_SERVER array on POST request or CGI environment
         * @see https://www.ietf.org/rfc/rfc3875 (4.1.2. and 4.1.3.)
         */
        if (isset($_SERVER[$temp])
            && in_array($temp, array('CONTENT_TYPE', 'CONTENT_LENGTH'))
        ) {
            return $_SERVER[$temp];
        }

        // This seems to be the only way to get the Authorization header on
        // Apache
        if (function_exists('apache_request_headers')) {
            $headers = apache_request_headers();
            if (isset($headers[$header])) {
                return $headers[$header];
            }
            $header = strtolower($header);
            foreach ($headers as $key => $value) {
                if (strtolower($key) == $header) {
                    return $value;
                }
            }
        }

        return false;
    }

    protected function logger($array)
    {
        $writer = new \Zend\Log\Writer\Stream($this->dir->getPath('log') . '/paypal-webhook-' . date('Y-m-d') . '.log');
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