<?php

namespace PayPalBR\PayPal\DatadogLogger;

class DatadogLogger
{
    private $apiKey;
    private $url;

    private $fieldsToMask = [
        "address_line_1",
        "address_line_2",
        "admin_area_1",
        "admin_area_2",
        "country_code",
        "postal_code",
        "email_address",
        "surname",
        "national_number",
        "tax_id",
        "tax_id_type",
        "email",
        "full_name",
        "document",
        "documentType",
        "phone"
    ];

    public function __construct()
    {
        $this->apiKey = 'pubce889544c49928691440f38945c7e248';
        $this->url = "https://http-intake.logs.datadoghq.com/v1/input";
    }

    public function log(string $level, array|object $message, array $context = []): bool
    {
        try {
            $payload = $this->buildPayloadPayPal($level, $this->objectToArray($message), $context);
            $this->send($payload);
            return true;
        } catch (\Exception $e) {
            throw new \Exception("Error cURL sending log to Datadog: " . $e->getMessage());
            return false;
        }
    }

    public function buildPayloadPayPal(string $level, array $message, array $context = [])
    {
        $resource = $message["resource"] ?? [];

        $merchantId = $resource["payee"]["merchant_id"] ?? null;

        $amount = $resource['amount']['value']
            ?? ($resource['amount']['total'] ?? null);

        $currency = $resource['amount']['currency_code']
            ?? ($resource['amount']['currency'] ?? null);

        $transactionId = $resource["id"] ?? null;

        $orderId = $resource["invoice_id"] ?? null;

        $links = [];

        if (isset($message["links"])) {
            foreach ($message["links"] as $link) {
                $links[] = [
                    "path"              => $link["href"] ?? null,
                    "http_method"       => $link["method"] ?? null,
                    "rel"               => $link["rel"] ?? null,
                    "http_response_code" => $link["http_response_code"] ?? null
                ];
            }
        }

        $sanitizedMessage = $this->maskSensitiveData($message);

        return [
            "ddsource" => "paypal-magento",
            "service" => "paypal-plugin",
            "hostname" => gethostname(),
            "message"  => $context["message_custom"] ?? null,
            "status"   => strtolower($level),
            // "ddtags"   => $this->formatTags($context),

            "timestamp"      => $message["create_time"] ?? date(DATE_ATOM),
            "application"    => "Magento-PayPal-Plugin",
            "environment"    => $context["environment"] ?? "production",
            "event_type"     => $message["event_type"] ?? null,
            "transaction_id" => $transactionId,
            "correlation_id" => $message["id"] ?? null,
            "order_id"       => $orderId,
            "debug_id"       => $message["debug_id"] ?? null,
            "merchant_id"    => $merchantId,
            "payment_method" => isset($resource["payment_source"]["paypal"]) ? "paypal_wallet" : "credit_card",
            "currency"       => $currency,
            "amount"         => $amount ? (float) $amount : null,
            "status_payment"         => $resource["status"] ?? null,
            "error_code"     => $resource["error_code"] ?? null,
            "error_message"  => $resource["error_message"] ?? null,
            "tags"           => $context,
            "version"        => "1.1.4",
            "placement"      => "checkout",
            "payload"        => $sanitizedMessage,
            "event_attributes" => $links,
            "message_custom" => $message["summary"] ?? "Evento PayPal recebido"
        ];
    }

    private function send(array $payload)
    {
        $ch = curl_init($this->url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "Content-Type: application/json",
            "DD-API-KEY: {$this->apiKey}"
        ]);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);

        $response = curl_exec($ch);

        if ($response === false) {
            $error = curl_error($ch);
            curl_close($ch);
            throw new \RuntimeException("Error cURL sending log to Datadog: {$error}");
        }

        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode < 200 || $httpCode >= 300) {
            throw new \RuntimeException("Datadog return an error {$httpCode}: {$response}");
        }
    }

    /**
     * Máscara recursiva dos campos sensíveis.
     */
    private function maskSensitiveData($data)
    {
        if (is_array($data)) {
            foreach ($data as $key => $value) {
                if (in_array($key, $this->fieldsToMask, true)) {
                    $data[$key] = $this->applyMask($key, $value);
                } elseif (is_array($value)) {
                    $data[$key] = $this->maskSensitiveData($value);
                }
            }
        }
        return $data;
    }

    /**
     * Define como mascarar dependendo do tipo do campo.
     */
    private function applyMask(string $key, $value)
    {
        if (!is_string($value)) {
            return $value;
        }

        // Email
        if (stripos($key, "email") !== false) {
            $parts = explode("@", $value);
            if (count($parts) === 2) {
                return substr($parts[0], 0, 1) . "*****@" . $parts[1];
            }
            return "*****";
        }

        // Documento, telefone ou números longos
        if (preg_match("/^[0-9]{6,}$/", $value)) {
            return str_repeat("*", strlen($value) - 3) . substr($value, -3);
        }

        // Nome ou texto livre → só primeiras 2 letras
        if (strlen($value) > 2) {
            return substr($value, 0, 2) . str_repeat("*", strlen($value) - 2);
        }

        return "***";
    }

    private function objectToArray($data)
    {
        if (is_object($data)) {
            $data = get_object_vars($data);
        }
        if (is_array($data)) {
            return array_map([$this, 'objectToArray'], $data);
        }
        return $data;
    }
}
