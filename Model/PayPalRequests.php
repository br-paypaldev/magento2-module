<?php

declare(strict_types=1);

namespace PayPalBR\PayPal\Model;

use DateTime;
use GuzzleHttp\Client;
use PayPalBR\PayPal\Model\PayPalBCDC\ConfigProvider;
use Magento\Framework\App\Cache\TypeListInterface;
use Magento\Framework\App\Cache\Frontend\Pool;
use Magento\Checkout\Model\Session;
use PayPalBR\PayPal\Logger\Handler;
use PayPalBR\PayPal\Logger\Logger;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Backend\Model\UrlInterface;
use PayPalBR\PayPal\DatadogLogger\DatadogLogger;

class PayPalRequests
{
    const CACHE_KEY = '@paypalbr:access_token';
    const PARTNER_CACHE_KEY = '@paypalbr:partner_access_token';
    const CACHE_LIFETIME = 28800;

    protected $configProvider;

    protected $cacheTypeList;
    protected $cacheFrontendPool;
    protected $checkoutSession;

    protected $storeManager;

    /**
     * @var Logger
     */
    protected $customLogger;

    /**
     * @var Handler
     */
    protected $loggerHandler;

    /**
     * @var UrlInterface
     */
    protected $backendUrl;

    /**
     * @var DatadogLogger
     */
    protected $datadogLogger;

    public function __construct(
        ConfigProvider $configProvider,
        Session $checkoutSession,
        TypeListInterface $cacheTypeList,
        Pool $cacheFrontendPool,
        StoreManagerInterface $storeManager,
        UrlInterface $backendUrl,
        Logger $customLogger,
        Handler $loggerHandler,
    ) {
        $this->configProvider = $configProvider;
        $this->cacheTypeList = $cacheTypeList;
        $this->cacheFrontendPool = $cacheFrontendPool;
        $this->checkoutSession = $checkoutSession;
        $this->customLogger = $customLogger;
        $this->backendUrl = $backendUrl;
        $this->loggerHandler = $loggerHandler;
        $this->storeManager = $storeManager;
        $this->datadogLogger = new DatadogLogger();
    }

    public function getAccessToken($keys = false)
    {
        if ($keys != false && isset($keys['type']) && $keys['type'] == 'id') {
            $cacheFrontend = $this->cacheFrontendPool->get(self::PARTNER_CACHE_KEY);

            if ($cacheFrontend->load(self::PARTNER_CACHE_KEY)) {
                return $cacheFrontend->load(self::PARTNER_CACHE_KEY);
            } else {
                $accessToken = $this->createAccessToken($keys);
                $cacheFrontend->save($accessToken, self::PARTNER_CACHE_KEY, [], self::CACHE_LIFETIME);
                return $accessToken;
            }
        } else if ($keys != false) {
            $accessToken = $this->createAccessToken($keys);
            return $accessToken;
        }

        $cacheFrontend = $this->cacheFrontendPool->get(self::CACHE_KEY);

        if ($cacheFrontend->load(self::CACHE_KEY)) {
            return $cacheFrontend->load(self::CACHE_KEY);
        } else {
            $accessToken = $this->createAccessToken();
            $cacheFrontend->save($accessToken, self::CACHE_KEY, [], self::CACHE_LIFETIME);
            return $accessToken;
        }
    }

    protected function createAccessToken($keys = false)
    {
        if (!$keys) {
            $clientId = $this->configProvider->getClientId();
            $secretId = $this->configProvider->getSecretId();
            $key = base64_encode($clientId . ':' . $secretId);
            $options = [
                'grant_type' => 'client_credentials'
            ];
        } else if (isset($keys["type"]) && $keys["type"] == "id") {
            $key = $keys["authorization"];
            $options = [
                'grant_type' => 'client_credentials'
            ];
        } else if (isset($keys["type"]) && $keys["type"] == "shared") {
            $key = $keys["authorization"];
            $options = [
                'grant_type' => 'authorization_code',
                'code' => $keys["auth"],
                'code_verifier' => $keys["nonce"]
            ];
        }

        // $requestUrl = $this->configProvider->getRequestUrl() . '/v1/oauth2/token';
        $requestUrl = 'https://api-m.sandbox.paypal.com/v1/oauth2/token';
        $headers = [
            'Accept' => 'application/json',
            // 'Accept-Language' => 'en_US',
            // 'Content-Type' => 'application/x-www-form-urlencoded',
            'PayPal-Partner-Attribution-Id' => 'MagentoBrazil_Ecom_EC2',
            'Authorization' => 'Basic ' . $key
        ];

        $client = new Client();

        $this->logger('PayPal V2 - OAuth REQUEST', 'POST', json_encode($headers), json_encode($options));
        $this->datadogLogger->log(
            "info",
            $options,
            [
                'environment' => 'development',
                'api_version' => 'v2',
                'integration_type' => 'webhook',
                'message_custom' => "PayPal V2 - OAuth REQUEST",
            ]
        );

        try {
            $response = $client->post($requestUrl, [
                'headers' => $headers,
                'form_params' => $options,
            ]);
        } catch (\GuzzleHttp\Exception\ClientException $e) {
            $this->logger('PayPal V2 - Create Order EXCEPTION', 'POST', json_encode($headers), $e->getMessage());
            $this->datadogLogger->log(
                "error",
                $options,
                [
                    'environment' => 'development',
                    'api_version' => 'v2',
                    'integration_type' => 'webhook',
                    'message_custom' => ["PayPal V2 - Create Order EXCEPTION", $e->getMessage()],
                ]
            );
            throw new \Exception($e->getResponse()->getBody()->getContents());
        } catch (\GuzzleHttp\Exception\ServerException $e) {
            $this->logger('PayPal V2 - Create Order EXCEPTION', 'POST', json_encode($headers), $e->getMessage());
            $this->datadogLogger->log(
                "error",
                $options,
                [
                    'environment' => 'development',
                    'api_version' => 'v2',
                    'integration_type' => 'webhook',
                    'message_custom' => ["PayPal V2 - Create Order EXCEPTION", $e->getMessage()],
                ]
            );
            throw new \Exception($e->getResponse()->getBody()->getContents());
        }



        $content = $response->getBody()->getContents();

        $this->logger('PayPal V2 - OAuth RESPONSE', 'POST', json_encode($response->getHeaders()), $content, $response->getStatusCode());
        $this->datadogLogger->log(
            "info",
            json_decode($content, true),
            [
                'environment' => 'development',
                'api_version' => 'v2',
                'integration_type' => 'webhook',
                'message_custom' => "PayPal V2 - OAuth RESPONSE",
            ]
        );

        $auth = json_decode($content);

        return $auth->access_token;
    }

    public function createOrder($data)
    {

        $accessToken = $this->getAccessToken();

        $headers = [
            'PayPal-Partner-Attribution-Id' => 'MagentoBrazil_Ecom_PPPlus2',
            'Content-Type' => 'application/json',
            'Authorization' => 'Bearer ' . $accessToken
        ];

        $body = $data;

        $client = new Client();


        $this->logger('PayPal V2 - Create Order REQUEST', 'POST', json_encode($headers), $body);
        $this->datadogLogger->log(
            "info",
            json_decode($body, true),
            [
                'environment' => 'development',
                'api_version' => 'v2',
                'integration_type' => 'webhook',
                'message_custom' => "PayPal V2 - Create Order REQUEST",
            ]
        );

        $requestUrl = $this->configProvider->getRequestUrl() . '/v2/checkout/orders';

        try {
            $response = $client->post($requestUrl, [
                'headers' => $headers,
                'body' => $body,
            ]);
        } catch (\GuzzleHttp\Exception\ClientException $e) {
            $this->logger('PayPal V2 - Create Order EXCEPTION', 'POST', json_encode($headers), $e->getMessage());
            $this->datadogLogger->log(
                "error",
                json_decode($body, true),
                [
                    'environment' => 'development',
                    'api_version' => 'v2',
                    'integration_type' => 'webhook',
                    'message_custom' => ["PayPal V2 - Create Order EXCEPTION", $e->getMessage()],
                ]
            );
            throw new \Exception($e->getResponse()->getBody()->getContents());
        } catch (\GuzzleHttp\Exception\ServerException $e) {
            $this->logger('PayPal V2 - Create Order EXCEPTION', 'POST', json_encode($headers), $e->getMessage());
            $this->datadogLogger->log(
                "error",
                json_decode($body, true),
                [
                    'environment' => 'development',
                    'api_version' => 'v2',
                    'integration_type' => 'webhook',
                    'message_custom' => ["PayPal V2 - Create Order EXCEPTION", $e->getMessage()],
                ]
            );
            throw new \Exception($e->getResponse()->getBody()->getContents());
        }

        $content = $response->getBody()->getContents();

        $this->logger('PayPal V2 - Create Order RESPONSE', 'POST', json_encode($response->getHeaders()), $content, $response->getStatusCode());
        $this->datadogLogger->log(
            "info",
            json_decode($content, true),
            [
                'environment' => 'development',
                'api_version' => 'v2',
                'integration_type' => 'webhook',
                'message_custom' => "PayPal V2 - Create Order RESPONSE",
            ]
        );

        return json_decode($content);
    }

    public function captureOrder($captureUrl)
    {
        $accessToken = $this->getAccessToken();
        $client = new Client();

        $headers = [
            'PayPal-Partner-Attribution-Id' => 'MagentoBrazil_Ecom_PPPlus2',
            'Content-Type' => 'application/json',
            'Authorization' => 'Bearer ' . $accessToken,
        ];
        $body = '{}';

        $this->logger('PayPal V2 - Capture Order REQUEST', 'POST', json_encode($headers), $body);
        $this->datadogLogger->log(
            "info",
            json_decode($body, true),
            [
                'environment' => 'development',
                'api_version' => 'v2',
                'integration_type' => 'webhook',
                'message_custom' => "PayPal V2 - Capture Order REQUEST",
            ]
        );

        try {
            $response = $client->post($captureUrl, [
                'headers' => $headers,
                'body' => $body,
            ]);
        } catch (\GuzzleHttp\Exception\ClientException $e) {
            $this->logger('PayPal V2 - Capture Order EXCEPTION', 'POST', json_encode($headers), $e->getMessage());
            $this->datadogLogger->log(
                "error",
                json_decode($body, true),
                [
                    'environment' => 'development',
                    'api_version' => 'v2',
                    'integration_type' => 'webhook',
                    'message_custom' => ["PayPal V2 - Capture Order EXCEPTION", $e->getMessage()],
                ]
            );
            throw new \Exception($e->getResponse()->getBody()->getContents());
        } catch (\GuzzleHttp\Exception\ServerException $e) {
            $this->logger('PayPal V2 - Capture Order EXCEPTION', 'POST', json_encode($headers), $e->getMessage());
            $this->datadogLogger->log(
                "error",
                json_decode($body, true),
                [
                    'environment' => 'development',
                    'api_version' => 'v2',
                    'integration_type' => 'webhook',
                    'message_custom' => ["PayPal V2 - Capture Order EXCEPTION", $e->getMessage()],
                ]
            );
            throw new \Exception($e->getResponse()->getBody()->getContents());
        }

        $content = $response->getBody()->getContents();

        $this->logger('PayPal V2 - Capture Order RESPONSE - ' . $captureUrl, 'POST', json_encode($response->getHeaders()), $content, $response->getStatusCode());
        $this->datadogLogger->log(
            "info",
            json_decode($content, true),
            [
                'environment' => 'development',
                'api_version' => 'v2',
                'integration_type' => 'webhook',
                'message_custom' => "PayPal V2 - Capture Order RESPONSE",
            ]
        );
        $result = json_decode($content);

        return $result;
    }

    public function getOrder()
    {
        $accessToken = $this->getAccessToken();
        $client = new Client();

        $headers = [
            'Authorization' => 'Bearer ' . $accessToken
        ];

        $this->logger('PayPal V2 - Get Order REQUEST', 'GET', json_encode($headers), '');
        $this->datadogLogger->log(
            "info",
            [],
            [
                'environment' => 'development',
                'api_version' => 'v2',
                'integration_type' => 'webhook',
                'message_custom' => "PayPal V2 - Get Order REQUEST",
            ]
        );

        $requestUrl = $this->configProvider->getRequestUrl() . '/v2/checkout/orders/';

        $response = $client->get($requestUrl . $this->checkoutSession->getPaypalPaymentId(), [
            'headers' => $headers
        ]);

        $content = $response->getBody()->getContents();

        $this->logger('PayPal V2 - Get Order RESPONSE', 'GET', json_encode($response->getHeaders()), $content, $response->getStatusCode());
        $this->datadogLogger->log(
            "info",
            json_decode($content, true),
            [
                'environment' => 'development',
                'api_version' => 'v2',
                'integration_type' => 'webhook',
                'message_custom' => "PayPal V2 - Get Order RESPONSE",
            ]
        );

        return json_decode($content);
    }

    public function updateOrder($updateUrl, $orderData)
    {
        $accessToken = $this->getAccessToken();
        $client = new Client();

        $headers = [
            'PayPal-Partner-Attribution-Id' => 'MagentoBrazil_Ecom_PPPlus2',
            'Content-Type' => 'application/json',
            'Authorization' => 'Bearer ' . $accessToken
        ];

        $body[] = [
            'op' => 'replace',
            'path' => '/purchase_units/@reference_id==\'default\'/invoice_id',
            'value' => $orderData
        ];

        $this->logger('PayPal V2 - Update Order REQUEST', 'PATCH', json_encode($headers), json_encode($body));
        $this->datadogLogger->log(
            "info",
            $body,
            [
                'environment' => 'development',
                'api_version' => 'v2',
                'integration_type' => 'webhook',
                'message_custom' => "PayPal V2 - Update Order REQUEST",
            ]
        );

        try {
            $response = $client->patch($updateUrl, [
                'headers' => $headers,
                'json' => $body,
            ]);
        } catch (\GuzzleHttp\Exception\ClientException $e) {
            $this->logger('PayPal V2 - Update Order EXCEPTION', 'POST', json_encode($headers), $e->getMessage());
            $this->datadogLogger->log(
                "error",
                $body,
                [
                    'environment' => 'development',
                    'api_version' => 'v2',
                    'integration_type' => 'webhook',
                    'message_custom' => ["PayPal V2 - Update Order EXCEPTION", $e->getMessage()],
                ]
            );
            throw new \Exception($e->getResponse()->getBody()->getContents());
        } catch (\GuzzleHttp\Exception\ServerException $e) {
            $this->logger('PayPal V2 - Update Order EXCEPTION', 'POST', json_encode($headers), $e->getMessage());
            $this->datadogLogger->log(
                "error",
                $body,
                [
                    'environment' => 'development',
                    'api_version' => 'v2',
                    'integration_type' => 'webhook',
                    'message_custom' => ["PayPal V2 - Update Order EXCEPTION", $e->getMessage()],
                ]
            );
            throw new \Exception($e->getResponse()->getBody()->getContents());
        }

        $content = $response->getBody()->getContents();

        $this->logger('PayPal V2 - Update Order RESPONSE', 'POST', json_encode($response->getHeaders()), $content, $response->getStatusCode());
        $this->datadogLogger->log(
            "info",
            json_decode($content, true) ?? [],
            [
                'environment' => 'development',
                'api_version' => 'v2',
                'integration_type' => 'webhook',
                'message_custom' => "PayPal V2 - Update Order RESPONSE",
            ]
        );
    }

    public function refundTransaction($transactionId, $amount = null)
    {
        $accessToken = $this->getAccessToken();
        $client = new Client();

        $headers = [
            'PayPal-Partner-Attribution-Id' => 'MagentoBrazil_Ecom_PPPlus2',
            'Content-Type' => 'application/json',
            'Authorization' => 'Bearer ' . $accessToken
        ];

        if ($amount) {
            $body = json_encode([
                'amount' => [
                    'value' => $amount,
                    'currency_code' => $this->storeManager->getStore()->getCurrentCurrencyCode()
                ]
            ]);
        } else {
            $body = '{}';
        }

        $this->logger('PayPal V2 - Refund Order REQUEST', 'POST', json_encode($headers), $body);
        $this->datadogLogger->log(
            "info",
            json_decode($body, true),
            [
                'environment' => 'development',
                'api_version' => 'v2',
                'integration_type' => 'webhook',
                'message_custom' => "PayPal V2 - Refund Order REQUEST",
            ]
        );

        $requestUrl = $this->configProvider->getRequestUrl() . '/v2/payments/captures/' . $transactionId . '/refund';

        try {
            $response = $client->post($requestUrl, [
                'headers' => $headers,
                'body' => $body,
            ]);
        } catch (\GuzzleHttp\Exception\ClientException $e) {
            $this->logger('PayPal V2 - Refund Order EXCEPTION', 'POST', json_encode($headers), $e->getMessage());
            $this->datadogLogger->log(
                "error",
                json_decode($body, true),
                [
                    'environment' => 'development',
                    'api_version' => 'v2',
                    'integration_type' => 'webhook',
                    'message_custom' => ["PayPal V2 - Refund Order EXCEPTION", $e->getMessage()],
                ]
            );
            throw new \Exception($e->getResponse()->getBody()->getContents());
        } catch (\GuzzleHttp\Exception\ServerException $e) {
            $this->logger('PayPal V2 - Refund Order EXCEPTION', 'POST', json_encode($headers), $e->getMessage());
            $this->datadogLogger->log(
                "error",
                json_decode($body, true),
                [
                    'environment' => 'development',
                    'api_version' => 'v2',
                    'integration_type' => 'webhook',
                    'message_custom' => ["PayPal V2 - Refund Order EXCEPTION", $e->getMessage()],
                ]
            );
            throw new \Exception($e->getResponse()->getBody()->getContents());
        }

        $content = $response->getBody()->getContents();

        $this->logger('PayPal V2 - Refund Order RESPONSE', 'POST', json_encode($response->getHeaders()), $content, $response->getStatusCode());
        $this->datadogLogger->log(
            "info",
            json_decode($content, true) ?? [],
            [
                'environment' => 'development',
                'api_version' => 'v2',
                'integration_type' => 'webhook',
                'message_custom' => "PayPal V2 - Refund Order RESPONSE",
            ]
        );
        $result = json_decode($content);

        return $result;
    }

    public function getWebhooks()
    {
        $accessToken = $this->getAccessToken();
        $client = new Client();

        $headers = [
            'Authorization' => 'Bearer ' . $accessToken
        ];

        $this->logger('PayPal V2 - Get Webhooks REQUEST', 'GET', json_encode($headers), '');
        $this->datadogLogger->log(
            "info",
            [],
            [
                'environment' => 'development',
                'api_version' => 'v2',
                'integration_type' => 'webhook',
                'message_custom' => "PayPal V2 - Get Webhooks REQUEST",
            ]
        );

        $requestUrl = $this->configProvider->getRequestUrl() . '/v1/notifications/webhooks/';

        try {
            $response = $client->get($requestUrl, [
                'headers' => $headers
            ]);
        } catch (\GuzzleHttp\Exception\ClientException $e) {
            $this->logger('PayPal V2 - Refund Order EXCEPTION', 'POST', json_encode($headers), $e->getMessage());
            $this->datadogLogger->log(
                "error",
                [],
                [
                    'environment' => 'development',
                    'api_version' => 'v2',
                    'integration_type' => 'webhook',
                    'message_custom' => ["PayPal V2 - Get Webhooks EXCEPTION", $e->getMessage()],
                ]
            );
            throw new \Exception($e->getResponse()->getBody()->getContents());
        } catch (\GuzzleHttp\Exception\ServerException $e) {
            $this->logger('PayPal V2 - Refund Order EXCEPTION', 'POST', json_encode($headers), $e->getMessage());
            $this->datadogLogger->log(
                "error",
                [],
                [
                    'environment' => 'development',
                    'api_version' => 'v2',
                    'integration_type' => 'webhook',
                    'message_custom' => ["PayPal V2 - Get Webhooks EXCEPTION", $e->getMessage()],
                ]
            );
            throw new \Exception($e->getResponse()->getBody()->getContents());
        }

        $content = $response->getBody()->getContents();

        $this->logger('PayPal V2 - Get Webhooks RESPONSE', 'GET', json_encode($response->getHeaders()), $content, $response->getStatusCode());
        $this->datadogLogger->log(
            "info",
            json_decode($content, true) ?? [],
            [
                'environment' => 'development',
                'api_version' => 'v2',
                'integration_type' => 'webhook',
                'message_custom' => "PayPal V2 - Get Webhooks RESPONSE",
            ]
        );
        return json_decode($content);
    }

    public function createWebhook($baseUrl, $events)
    {
        $accessToken = $this->getAccessToken();
        $client = new Client();

        $headers = [
            'PayPal-Partner-Attribution-Id' => 'MagentoBrazil_Ecom_PPPlus2',
            'Content-Type' => 'application/json',
            'Authorization' => 'Bearer ' . $accessToken
        ];

        $body = json_encode([
            'url' => $baseUrl,
            'event_types' => $events
        ]);

        $this->logger('PayPal V2 - Create Webhook REQUEST', 'POST', json_encode($headers), $body);
        $this->datadogLogger->log(
            "info",
            json_decode($body, true),
            [
                'environment' => 'development',
                'api_version' => 'v2',
                'integration_type' => 'webhook',
                'message_custom' => "PayPal V2 - Create Webhook REQUEST",
            ]
        );

        $requestUrl = $this->configProvider->getRequestUrl() . '/v1/notifications/webhooks';

        try {
            $response = $client->post($requestUrl, [
                'headers' => $headers,
                'body' => $body,
            ]);
        } catch (\GuzzleHttp\Exception\ClientException $e) {
            $this->logger('PayPal V2 - Refund Order EXCEPTION', 'POST', json_encode($headers), $e->getMessage());
            $this->datadogLogger->log(
                "error",
                json_decode($body, true),
                [
                    'environment' => 'development',
                    'api_version' => 'v2',
                    'integration_type' => 'webhook',
                    'message_custom' => ["PayPal V2 - Create Webhook EXCEPTION", $e->getMessage()],
                ]
            );
            throw new \Exception($e->getResponse()->getBody()->getContents());
        } catch (\GuzzleHttp\Exception\ServerException $e) {
            $this->logger('PayPal V2 - Refund Order EXCEPTION', 'POST', json_encode($headers), $e->getMessage());
            $this->datadogLogger->log(
                "error",
                json_decode($body, true),
                [
                    'environment' => 'development',
                    'api_version' => 'v2',
                    'integration_type' => 'webhook',
                    'message_custom' => ["PayPal V2 - Create Webhook EXCEPTION", $e->getMessage()],
                ]
            );
            throw new \Exception($e->getResponse()->getBody()->getContents());
        }

        $content = $response->getBody()->getContents();

        $this->logger('PayPal V2 - Refund Order RESPONSE', 'POST', json_encode($response->getHeaders()), $content, $response->getStatusCode());
        $this->datadogLogger->log(
            "info",
            json_decode($content, true) ?? [],
            [
                'environment' => 'development',
                'api_version' => 'v2',
                'integration_type' => 'webhook',
                'message_custom' => "PayPal V2 - Create Webhook RESPONSE",
            ]
        );
        $result = json_decode($content);

        return $result;
    }

    public function verifyWebhookSignature($signatureVerificationInfo)
    {

        $accessToken = $this->getAccessToken();
        // TODO: verificar qual o Partner-Attribution-Id correto
        // MagentoBrazil_Ecom_PPPlus2 - para BC/DC
        // MagentoBrazil_Ecom_EC2 - express checkout
        $headers = [
            'PayPal-Partner-Attribution-Id' => 'MagentoBrazil_Ecom_PPPlus2',
            'Content-Type' => 'application/json',
            'Authorization' => 'Bearer ' . $accessToken
        ];

        $client = new Client();

        $this->logger('PayPal V2 - Verify Webhook Signature REQUEST', 'POST', json_encode($headers), json_encode($signatureVerificationInfo));
        $this->datadogLogger->log(
            "info",
            $signatureVerificationInfo ?? [],
            [
                'environment' => 'development',
                'api_version' => 'v2',
                'integration_type' => 'webhook',
                'message_custom' => "PayPal V2 - Verify Webhook Signature REQUEST",
            ]
        );

        $requestUrl = $this->configProvider->getRequestUrl() . '/v1/notifications/verify-webhook-signature';

        try {
            $response = $client->post($requestUrl, [
                'headers' => $headers,
                'body' => json_encode($signatureVerificationInfo),
            ]);
        } catch (\GuzzleHttp\Exception\ClientException $e) {
            $this->logger('PayPal V2 - Verify Webhook Signature EXCEPTION', 'POST', json_encode($headers), $e->getMessage());
            $this->datadogLogger->log(
                "error",
                $signatureVerificationInfo ?? [],
                [
                    'environment' => 'development',
                    'api_version' => 'v2',
                    'integration_type' => 'webhook',
                    'message_custom' => ["PayPal V2 - Verify Webhook Signature EXCEPTION", $e->getMessage()],
                ]
            );
            throw new \Exception($e->getResponse()->getBody()->getContents());
        } catch (\GuzzleHttp\Exception\ServerException $e) {
            $this->logger('PayPal V2 - Verify Webhook Signature EXCEPTION', 'POST', json_encode($headers), $e->getMessage());
            $this->datadogLogger->log(
                "error",
                $signatureVerificationInfo ?? [],
                [
                    'environment' => 'development',
                    'api_version' => 'v2',
                    'integration_type' => 'webhook',
                    'message_custom' => ["PayPal V2 - Verify Webhook Signature EXCEPTION", $e->getMessage()],
                ]
            );
            throw new \Exception($e->getResponse()->getBody()->getContents());
        }

        $content = $response->getBody()->getContents();

        $this->logger('PayPal V2 - Create Order RESPONSE', 'POST', json_encode($response->getHeaders()), $content, $response->getStatusCode());
        $this->datadogLogger->log(
            "info",
            json_decode($content, true) ?? [],
            [
                'environment' => 'development',
                'api_version' => 'v2',
                'integration_type' => 'webhook',
                'message_custom' => "PayPal V2 - Verify Webhook Signature RESPONSE",
            ]
        );
        return json_decode($content);
    }

    public function getSellerCredentials($merchantId, $accessToken)
    {
        $headers = [
            'Authorization' => 'Bearer ' . $accessToken,
            'Content-Type' => 'application/json',
        ];

        $client = new Client();

        $this->logger('PayPal V1 - Get Seller Credential REQUEST', 'GET', json_encode($headers), '');
        $this->datadogLogger->log(
            "info",
            [],
            [
                'environment' => 'development',
                'api_version' => 'v1',
                'integration_type' => 'webhook',
                'message_custom' => "PayPal V1 - Get Seller Credential REQUEST",
            ]
        );

        $requestUrl = 'https://api-m.sandbox.paypal.com/v1/customer/partners/YZ4YR9LNRW4RQ/merchant-integrations/credentials/';
        // $requestUrl = $this->configProvider->getRequestUrl() . '/v1/customer/partners/JBMW273Y5U3LY/merchant-integrations/credentials/';

        try {
            $response = $client->get($requestUrl, [
                'headers' => $headers
            ]);
        } catch (\GuzzleHttp\Exception\ClientException $e) {
            $this->logger('PayPal V2 - Verify Webhook Signature EXCEPTION', 'POST', json_encode($headers), $e->getMessage());
            $this->datadogLogger->log(
                "error",
                [],
                [
                    'environment' => 'development',
                    'api_version' => 'v2',
                    'integration_type' => 'webhook',
                    'message_custom' => ["PayPal V2 - Verify Webhook Signature EXCEPTION", $e->getMessage()],
                ]
            );
            throw new \Exception($e->getResponse()->getBody()->getContents());
        } catch (\GuzzleHttp\Exception\ServerException $e) {
            $this->logger('PayPal V2 - Verify Webhook Signature EXCEPTION', 'POST', json_encode($headers), $e->getMessage());
            $this->datadogLogger->log(
                "error",
                [],
                [
                    'environment' => 'development',
                    'api_version' => 'v2',
                    'integration_type' => 'webhook',
                    'message_custom' => ["PayPal V2 - Verify Webhook Signature EXCEPTION", $e->getMessage()],
                ]
            );
            throw new \Exception($e->getResponse()->getBody()->getContents());
        }

        $content = $response->getBody()->getContents();

        $this->logger('PayPal V2 - Get Order RESPONSE', 'GET', json_encode($response->getHeaders()), $content, $response->getStatusCode());
        $this->datadogLogger->log(
            "info",
            json_decode($content, true) ?? [],
            [
                'environment' => 'development',
                'api_version' => 'v2',
                'integration_type' => 'webhook',
                'message_custom' => "PayPal V2 - Get Order RESPONSE",
            ]
        );
        return json_decode($content);
    }

    public function getCredentialsUrl($authorization)
    {
        $accessToken = $this->getAccessToken($authorization);
        $client = new Client();

        $headers = [
            'PayPal-Partner-Attribution-Id' => 'MagentoBrazil_Ecom_PPPlus2',
            'Content-Type' => 'application/json',
            'Authorization' => 'Bearer ' . $accessToken
        ];

        $trackingId = "mage-" . $this->getStoreDomain($this->storeManager->getStore()->getBaseUrl()) . '-' . $this->generateAlphanumericID();
        $now = new DateTime();
        $sellerNonce = hash('sha256', $now->format('Y-m-d H:i:s'));

        setcookie("sellerNonce", $sellerNonce, time() + (8 * 60 * 60), "/");

        $body = json_encode(
            [
                "preferred_language_code" => "pt-BR",
                "tracking_id" => $trackingId,
                "operations" => [
                    [
                        "operation" => "API_INTEGRATION",
                        "api_integration_preference" => [
                            "rest_api_integration" => [
                                "integration_method" => "PAYPAL",
                                "integration_type" => "FIRST_PARTY",
                                "first_party_details" => [
                                    "features" => [
                                        "PAYMENT",
                                        "REFUND"
                                    ],
                                    "seller_nonce" => $sellerNonce
                                ]
                            ]
                        ]
                    ]
                ],
                "partner_config_override" => [
                    "partner_logo_url" => "https://www.paypalobjects.com/webstatic/mktg/logo/pp_cc_mark_111x69.jpg",
                    "return_url" => $this->backendUrl->getUrl('paypalbr/credentials'),
                    "return_url_description" => "Return to TestStore to finish connecting your PayPal account.",
                    "action_renewal_url" => "https://testenterprises.com/renew-exprired-url"
                ],
                "legal_consents" => [
                    [
                        "type" => "SHARE_DATA_CONSENT",
                        "granted" => true
                    ]
                ],
                "products" => [
                    "EXPRESS_CHECKOUT"
                ]
            ]
        );

        $this->logger('PayPal V2 - Create Webhook REQUEST', 'POST', json_encode($headers), $body);
        $this->datadogLogger->log(
            "info",
            json_decode($body, true),
            [
                'environment' => 'development',
                'api_version' => 'v2',
                'integration_type' => 'webhook',
                'message_custom' => "PayPal V2 - Create Webhook REQUEST",
            ]
        );

        // $requestUrl = $this->configProvider->getRequestUrl() . '/v2/customer/partner-referrals';

        $requestUrl = 'https://api-m.sandbox.paypal.com/v2/customer/partner-referrals';

        try {
            $response = $client->post($requestUrl, [
                'headers' => $headers,
                'body' => $body,
            ]);
        } catch (\GuzzleHttp\Exception\ClientException $e) {
            $this->logger('PayPal V2 - Refund Order EXCEPTION', 'POST', json_encode($headers), $e->getMessage());
            $this->datadogLogger->log(
                "error",
                json_decode($body, true),
                [
                    'environment' => 'development',
                    'api_version' => 'v2',
                    'integration_type' => 'webhook',
                    'message_custom' => ["PayPal V2 - Refund Order EXCEPTION", $e->getMessage()],
                ]
            );
            throw new \Exception($e->getResponse()->getBody()->getContents());
        } catch (\GuzzleHttp\Exception\ServerException $e) {
            $this->logger('PayPal V2 - Refund Order EXCEPTION', 'POST', json_encode($headers), $e->getMessage());
            $this->datadogLogger->log(
                "error",
                json_decode($body, true),
                [
                    'environment' => 'development',
                    'api_version' => 'v2',
                    'integration_type' => 'webhook',
                    'message_custom' => ["PayPal V2 - Refund Order EXCEPTION", $e->getMessage()],
                ]
            );
            throw new \Exception($e->getResponse()->getBody()->getContents());
        }

        $content = $response->getBody()->getContents();

        $this->logger('PayPal V2 - Refund Order RESPONSE', 'POST', json_encode($response->getHeaders()), $content, $response->getStatusCode());
        $this->datadogLogger->log(
            "info",
            json_decode($content, true) ?? [],
            [
                'environment' => 'development',
                'api_version' => 'v2',
                'integration_type' => 'webhook',
                'message_custom' => "PayPal V2 - Refund Order RESPONSE",
            ]
        );
        $result = json_decode($content);

        foreach ($result->links as $item) {
            if ($item->rel === 'action_url') {
                return $item->href;
            }
        }
    }

    public function getOrderDetails($url)
    {
        $accessToken = $this->getAccessToken();
        $client = new Client();

        $headers = [
            'Authorization' => 'Bearer ' . $accessToken
        ];

        $this->logger('PayPal V2 - Get Order REQUEST', 'GET', json_encode($headers), '');
        $this->datadogLogger->log(
            "info",
            [],
            [
                'environment' => 'development',
                'api_version' => 'v2',
                'integration_type' => 'webhook',
                'message_custom' => "PayPal V2 - Get Order REQUEST",
            ]
        );

        $response = $client->get($url, [
            'headers' => $headers
        ]);

        $content = $response->getBody()->getContents();

        $this->logger('PayPal V2 - Get Order RESPONSE', 'GET', json_encode($response->getHeaders()), $content, $response->getStatusCode());
        $this->datadogLogger->log(
            "info",
            json_decode($content, true) ?? [],
            [
                'environment' => 'development',
                'api_version' => 'v2',
                'integration_type' => 'webhook',
                'message_custom' => "PayPal V2 - Get Order RESPONSE",
            ]
        );
        return json_decode($content);
    }

    protected function logger($identifier, $httpMethod, $headers, $payload, $httpStatusCode = null)
    {
        $this->loggerHandler->setFileName('paypalbr');
        $this->customLogger->info('######################################################');
        $this->customLogger->info($identifier);
        $this->customLogger->info($httpMethod);
        if ($httpStatusCode) {
            $this->customLogger->info($httpStatusCode);
        }
        $this->customLogger->info($headers);
        $this->customLogger->info($payload);
        $this->customLogger->info('######################################################');
    }

    protected function getStoreDomain($url)
    {
        $parsedUrl = parse_url($url);
        $hostParts = explode('.', $parsedUrl['host']);
        $domain = $hostParts[count($hostParts) - 2];

        return $domain;
    }

    protected function generateAlphanumericID($length = 8)
    {
        $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $charactersLength = strlen($characters);
        $id = '';

        for ($i = 0; $i < $length; $i++) {
            $id .= $characters[rand(0, $charactersLength - 1)];
        }

        return $id;
    }
}
