<?php
namespace PayPalBR\PayPal\Model;

use oauth;
use PayPalBR\PayPal\Api\EventsInterface;
use PayPalBR\PayPal\Api\WebHookManagementInterface;
use PayPalBR\PayPal\Logger\Handler;
use PayPalBR\PayPal\Logger\Logger;
use PayPal\Api\VerifyWebhookSignature;
use Magento\Framework\Filesystem\DirectoryList;
use Magento\PaypalCaptcha\Model\Checkout\ConfigProviderPayPal;
use PayPalBR\PayPal\Model\PayPalBCDC\ConfigProvider as PayPalBCDCConfigProvider;
use PayPalBR\PayPal\DatadogLogger\DatadogLogger;

class WebHookManagement implements WebHookManagementInterface
{
    protected $eventWebhook;
    protected $configProvider;
    protected $dir;

    /**
     * @var Logger
     */
    protected $customLogger;

    /**
     * @var Handler
     */
    protected $loggerHandler;

    protected $paypalRequests;

    /**
     * @var DatadogLogger
     */
    protected $datadogLogger;

    public function __construct(
        EventsInterface $eventWebhook,
        DirectoryList $dir,
        PayPalBCDCConfigProvider $configProvider,
        PayPalRequests $paypalRequests,
        Logger $customLogger,
        Handler $loggerHandler
    ) {
        $this->setEventWebhook($eventWebhook);
        $this->dir = $dir;
        $this->customLogger = $customLogger;
        $this->loggerHandler = $loggerHandler;
        $this->configProvider = $configProvider;
        $this->paypalRequests = $paypalRequests;
        $this->datadogLogger = new DatadogLogger();
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

            $requestBody = json_decode($requestBody);

            // $signatureVerification = new VerifyWebhookSignature();
            $signatureVerificationInfo = [
                "transmission_id" => $paypalTransmissionId,
                "transmission_time" => $paypalTransmissionTime,
                "cert_url" => $paypalCertUrl,
                "auth_algo" => $paypalAuthAlgo,
                "transmission_sig" => $paypalTransmissionSig,
                "webhook_id" => $this->configProvider->getWebhookId(),
                "webhook_event" => $requestBody
            ];

            $output = $this->paypalRequests->verifyWebhookSignature($signatureVerificationInfo);

            if ($output->verification_status == 'FAILURE') {
                $this->logger('initial debug');
                $this->logger($signatureVerificationInfo);
                $this->logger($output);
                $this->logger('final debug');

                $this->datadogLogger->log(
                    "error",
                    $requestBody,
                    [
                        'environment' => 'development',
                        'api_version' => 'v1',
                        'integration_type' => 'webhook',
                        'message_custom' => $output,
                    ]
                );

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
            $this->datadogLogger->log(
                    "error",
                    $requestBody,
                    [
                        'environment' => 'development',
                        'api_version' => 'v1',
                        'integration_type' => 'webhook',
                        'message_custom' => $e->getMessage(),
                    ]
                );
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
        try {
            $this->getEventWebhook()->processWebhookRequest($requestBody);
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

            $this->datadogLogger->log(
                "error",
                $requestBody,
                [
                    'environment' => 'development',
                    'api_version' => 'v1',
                    'integration_type' => 'webhook',
                    'message_custom' => $summary,
                ]
            );
        }

        return [
            [
                'status' => 200,
                'message' => 'SUCESSO'
            ]
        ];
    }

    protected function getHeader1($header)
    {
        if (empty($header)) {
            throw new \Exception('An HTTP header name is required');
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
        $this->loggerHandler->setFileName('paypal-webhook-' . date('Y-m-d'));
        $this->customLogger->info('Webhook management',$array);
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
}
