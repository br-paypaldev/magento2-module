<?php
namespace PayPalBR\PayPal\Model\Webhook;

/**
 * PayPalBR PayPalPlus Event Handler
 *
 * @category   PayPalBR
 * @package    PayPalBR_PayPalPlus
 * @author Dev
 */
use PayPalBR\PayPal\Api\EventsInterface;
use Magento\Sales\Model\Order\CreditmemoFactory;
use Magento\Sales\Model\Service\CreditmemoService;
use Magento\Framework\Filesystem\DirectoryList;
use PayPalBR\PayPal\Logger\Handler;
use PayPalBR\PayPal\Logger\Logger;
use PayPalBR\PayPal\DatadogLogger\DatadogLogger;

class Event implements EventsInterface
{
    const PAYMENT_CAPTURE_COMPLETED = "PAYMENT.CAPTURE.COMPLETED";

    const PAYMENT_CAPTURE_DENIED = "PAYMENT.CAPTURE.DENIED";

    const PAYMENT_CAPTURE_REFUNDED = "PAYMENT.CAPTURE.REFUNDED";

    const PAYMENT_CAPTURE_REVERSED = "PAYMENT.CAPTURE.REVERSED";

    const PAYMENT_CAPTURE_PENDING = "PAYMENT.CAPTURE.PENDING";

    const CHECKOUT_ORDER_COMPLETED = "CHECKOUT.ORDER.COMPLETED";

    const CHECKOUT_ORDER_APPROVED = "CHECKOUT.ORDER.APPROVED";

    const CHECKOUT_ORDER_PROCESSED = "CHECKOUT.ORDER.PROCESSED";

    const PAYMENT_AUTHORIZATION_CREATED = "PAYMENT.AUTHORIZATION.CREATED";

    const PAYMENT_AUTHORIZATION_VOIDED = "PAYMENT.AUTHORIZATION.VOIDED";

    const RISK_DISPUTE_CREATED = "RISK.DISPUTE.CREATED";

    const CUSTOMER_DISPUTE_CREATED = "CUSTOMER.DISPUTE.CREATED";

    /**
     * Store order instance
     *
     * @var \Magento\Sales\Model\Order
     */
    protected $_order = null;

    /**
     * @var \Magento\Sales\Model\Order\Payment\TransactionFactory
     */
    protected $salesOrderPaymentTransactionFactory;

    /**
     * @var \Magento\Sales\Model\OrderFactory
     */
    protected $salesOrderFactory;

    /**
     * \Magento\Sales\Model\Order\CreditmemoFactory
     */
    protected $creditmemoFactory;

    /**
     * \Magento\Sales\Model\Service\CreditmemoService
     */
    protected $creditmemoService;

    /**
     * @var
     */
    protected $dir;

    /**
     * @var Logger
     */
    protected $customLogger;

    /**
     * @var Handler
     */
    protected $loggerHandler;

    /**
     * @var DatadogLogger
     */
    protected $datadogLogger;

    public function __construct(
        \Magento\Sales\Model\Order\Payment\TransactionFactory $salesOrderPaymentTransactionFactory,
        \Magento\Sales\Model\OrderFactory $salesOrderFactory,
        CreditmemoFactory $creditmemoFactory,
        CreditmemoService $creditmemoService,
        DirectoryList $dir,
        Logger $customLogger,
        Handler $loggerHandler
    ) {
        $this->salesOrderPaymentTransactionFactory = $salesOrderPaymentTransactionFactory;
        $this->salesOrderFactory = $salesOrderFactory;
        $this->creditmemoFactory = $creditmemoFactory;
        $this->creditmemoService = $creditmemoService;
        $this->dir = $dir;
        $this->customLogger = $customLogger;
        $this->loggerHandler = $loggerHandler;

        // Nome fixo de log diário
        $this->loggerHandler->setFileName('paypal-webhook-' . date('Y-m-d'));
        $this->datadogLogger = new DatadogLogger();
    }

    public function processWebhookRequest($webhookEvent)
    {
        try {
            if (
                $webhookEvent->event_type !== null
                && in_array($webhookEvent->event_type, $this->getSupportedWebhookEvents())
            ) {
                $this->getOrder($webhookEvent);
                $handler = $this->eventTypeToHandler($webhookEvent->event_type);

                if (method_exists($this, $handler)) {
                    $this->$handler($webhookEvent);
                } else {
                    $this->logger(['warning' => "No handler implemented for event {$webhookEvent->event_type}"]);
                    $this->datadogLogger->log(
                        "warn",
                        $this->objectToArray($webhookEvent),
                        [
                            'environment' => 'development',
                            'api_version' => 'v1',
                            'integration_type' => 'webhook',
                            'message_custom' => "No handler implemented for event {$webhookEvent->event_type}",
                        ]
                    );
                }
            }
        } catch (\Exception $e) {
            $this->logger(['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            $this->datadogLogger->log(
                "error",
                $this->objectToArray($webhookEvent),
                [
                    'environment' => 'development',
                    'api_version' => 'v1',
                    'integration_type' => 'webhook',
                    'message_custom' => $e->getMessage(),
                ]
            );
        }

        return $this;
    }

    /**
     * Get supported webhook events
     *
     * @return array
     */
    public function getSupportedWebhookEvents()
    {
        return [
            self::PAYMENT_CAPTURE_COMPLETED,
            self::PAYMENT_CAPTURE_DENIED,
            self::PAYMENT_CAPTURE_REFUNDED,
            self::PAYMENT_CAPTURE_REVERSED,
            self::PAYMENT_CAPTURE_PENDING,
            self::CHECKOUT_ORDER_COMPLETED,
            self::CHECKOUT_ORDER_APPROVED,
            self::CHECKOUT_ORDER_PROCESSED,
            self::PAYMENT_AUTHORIZATION_CREATED,
            self::PAYMENT_AUTHORIZATION_VOIDED,
            self::RISK_DISPUTE_CREATED,
            self::CUSTOMER_DISPUTE_CREATED
        ];
    }

    /**
     * Parse event type to handler function
     *
     * @param $eventType
     * @return string
     */
    protected function eventTypeToHandler($eventType)
    {
        $eventParts = explode('.', $eventType);
        foreach ($eventParts as $key => $eventPart) {
            $eventParts[$key] = $key === 0
                ? strtolower($eventPart)
                : ucfirst(strtolower($eventPart));
        }
        return implode('', $eventParts);
    }

    protected function paymentCaptureCompleted($webhookEvent)
    {
        try {
            $paymentResource = $this->objectToArray($webhookEvent->resource);

            $parentTransactionId = $paymentResource['supplementary_data']['related_ids']['order_id']
                ?? ($paymentResource['parent_payment'] ?? null);

            $currency = $paymentResource['amount']['currency_code']
                ?? ($paymentResource['amount']['currency'] ?? null);

            $amountValue = $paymentResource['amount']['value']
                ?? ($paymentResource['amount']['total'] ?? null);

            $payment = $this->_order->getPayment();
            $payment->setTransactionId($paymentResource['id'])
                ->setCurrencyCode($currency)
                ->setParentTransactionId($parentTransactionId)
                ->setIsTransactionClosed(true)
                ->setShouldCloseParentTransaction(true)
                ->setAdditionalInformation('state_payPal', 'completed');

            // Cria transação "capture completed"
            $transaction = $payment->addTransaction(\Magento\Sales\Model\Order\Payment\Transaction::TYPE_CAPTURE);
            $transaction->setIsClosed(true);
            $transaction->save();

            // Registra captura no pedido + gera invoice
            $payment->registerCaptureNotification($amountValue, true);

            $this->_order->setState(\Magento\Sales\Model\Order::STATE_PROCESSING);
            $this->_order->setStatus(
                $this->_order->getConfig()->getStateDefaultStatus(\Magento\Sales\Model\Order::STATE_PROCESSING)
            );
            $this->_order->save();

            // notifica cliente do invoice
            $invoice = $payment->getCreatedInvoice();
            if ($invoice && !$this->_order->getEmailSent()) {
                $this->_order->addStatusHistoryComment(
                    __('Notified customer about invoice #%1.', $invoice->getIncrementId())
                )->setIsCustomerNotified(true)->save();
            }

            $this->logger([
                'event' => 'completed',
                'order' => $this->_order->getIncrementId(),
                'transaction_id' => $paymentResource['id'],
            ]);

            $this->datadogLogger->log(
                "info",
                $this->objectToArray($webhookEvent),
                [
                    'environment' => 'development',
                    'api_version' => 'v1',
                    'integration_type' => 'webhook',
                    'message_custom' => "Order {$this->_order->getIncrementId()} completed",
                ]
            );
        } catch (\Exception $e) {
            $this->logger([
                'error' => 'event Completed failed',
                'order' => $this->_order->getIncrementId(),
                'transaction_id' => $paymentResource['id'],
                'message' => $e->getMessage()]);
            $this->datadogLogger->log(
                "error",
                $this->objectToArray($webhookEvent),
                [
                    'environment' => 'development',
                    'api_version' => 'v1',
                    'integration_type' => 'webhook',
                    'message_custom' => $e->getMessage(),
                ]
            );
        }
    }

    protected function logger($array)
    {
        $this->customLogger->info(json_encode($array));
    }

    protected function paymentCaptureRefunded($webhookEvent)
    {
        try {
            $this->_order->getPayment()->setAdditionalInformation('state_payPal', 'refunded')->save();

            $creditmemo = $this->creditmemoFactory->createByOrder($this->_order);
            $this->creditmemoService->refund($creditmemo, true);

            $this->logger(['event' => 'refunded', 'order' => $this->_order->getIncrementId()]);
            $this->datadogLogger->log(
                "info",
                $this->objectToArray($webhookEvent),
                [
                    'environment' => 'development',
                    'api_version' => 'v1',
                    'integration_type' => 'webhook',
                    'message_custom' => "Order {$this->_order->getIncrementId()} refunded",
                ]
            );
        } catch (\Exception $e) {
            $this->logger(['error' => 'Refund failed', 'message' => $e->getMessage()]);
            $this->datadogLogger->log(
                "error",
                $this->objectToArray($webhookEvent),
                [
                    'environment' => 'development',
                    'api_version' => 'v1',
                    'integration_type' => 'webhook',
                    'message_custom' => $e->getMessage(),
                ]
            );
        }
    }

    protected function paymentCapturePending($webhookEvent)
    {
        // try {
            $paymentResource = $this->objectToArray($webhookEvent->resource);
            $parentTransactionId = $paymentResource['supplementary_data']['related_ids']['order_id']
                ?? ($paymentResource['parent_payment'] ?? null);
            
            $status = $paymentResource['status'] ?? ($paymentResource['state'] ?? 'PENDING');
            $reason = $paymentResource['status_details']['reason'] ?? null;

            $payment = $this->_order->getPayment();
            $payment->setTransactionId($paymentResource['id'])
            ->setParentTransactionId($parentTransactionId)
            ->setIsTransactionClosed(false) // pendente ainda
            ->setPreparedMessage($webhookEvent->summary ?? 'Pending payment')
            ->setTransactionAdditionalInfo(
                \Magento\Sales\Model\Order\Payment\Transaction::RAW_DETAILS,
                [
                    'paypal_status' => $status,
                    'reason' => $reason
                ]
            );

            $payment->addTransaction(\Magento\Sales\Model\Order\Payment\Transaction::TYPE_CAPTURE);

            $this->_order->setState(\Magento\Sales\Model\Order::STATE_PENDING_PAYMENT);
            $this->_order->setStatus(\Magento\Sales\Model\Order::STATE_PENDING_PAYMENT);
            $this->_order->save();

            $this->logger([
                'event' => 'pending',
                'order' => $this->_order->getIncrementId(),
                'transaction_id' => $paymentResource['id'],
                'paypal_status' => $status
            ]);

            $this->datadogLogger->log(
                "info",
                $this->objectToArray($webhookEvent),
                [
                    'environment' => 'development',
                    'api_version' => 'v1',
                    'integration_type' => 'webhook',
                    'message_custom' => "Order {$this->_order->getIncrementId()} pending",
                ]
            );
        // } catch (\Exception $e) {
        //     $this->logger([
        //         'error' => 'event Pending failed',
        //         'order' => $this->_order->getIncrementId(),
        //         'transaction_id' => $paymentResource['id'],
        //         'message' => $e->getMessage()]);
        //     $this->datadogLogger->log(
        //         "error",
        //         $this->objectToArray($webhookEvent),
        //         [
        //             'environment' => 'development',
        //             'api_version' => 'v1',
        //             'integration_type' => 'webhook',
        //             'message_custom' => $e->getMessage(),
        //         ]
        //     );
        // }
    }

    protected function paymentCaptureReversed($webhookEvent)
    {
        try {
            $this->_order->setStatus(\Magento\Paypal\Model\Info::ORDER_STATUS_REVERSED);
            $this->_order->save();
            $this->_order
                ->addStatusHistoryComment(
                    $webhookEvent->summary,
                    \Magento\Paypal\Model\Info::ORDER_STATUS_REVERSED
                )->setIsCustomerNotified(false)
                ->save();
            $this->_order->getPayment()->setAdditionalInformation('state_payPal', 'refunded')->save();

            $this->logger(['event' => 'reversed', 'order' => $this->_order->getIncrementId()]);

            $this->datadogLogger->log(
                "info",
                $this->objectToArray($webhookEvent),
                [
                    'environment' => 'development',
                    'api_version' => 'v1',
                    'integration_type' => 'webhook',
                    'message_custom' => "Order {$this->_order->getIncrementId()} reversed",
                ]
            );
        } catch (\Exception $e) {
            $this->logger(['error' => 'event Reversed failed', 'order' => $this->_order->getIncrementId(), 'message' => $e->getMessage()]);
            $this->datadogLogger->log(
                "error",
                $this->objectToArray($webhookEvent),
                [
                    'environment' => 'development',
                    'api_version' => 'v1',
                    'integration_type' => 'webhook',
                    'message_custom' => $e->getMessage(),
                ]
            );
        }
    }

    protected function riskDisputeCreated($webhookEvent)
    {
        try {
            $this->_order->setStatus(\Magento\Paypal\Model\Info::ORDER_STATUS_REVERSED);
            $this->_order->save();
            $this->_order
                ->addStatusHistoryComment(
                    $webhookEvent->summary,
                    \Magento\Paypal\Model\Info::ORDER_STATUS_REVERSED
                )->setIsCustomerNotified(false)
                ->save();

            $this->logger(['event' => 'risk_dispute', 'order' => $this->_order->getIncrementId()]);
            $this->datadogLogger->log(
                "info",
                $this->objectToArray($webhookEvent),
                [
                    'environment' => 'development',
                    'api_version' => 'v1',
                    'integration_type' => 'webhook',
                    'message_custom' => "Order {$this->_order->getIncrementId()} risk_dispute",
                ]
            );
        } catch (\Exception $e) {
            $this->logger(['error' => 'event Risk Dispute failed', 'order' => $this->_order->getIncrementId(), 'message' => $e->getMessage()]);
            $this->datadogLogger->log(
                "error",
                $this->objectToArray($webhookEvent),
                [
                    'environment' => 'development',
                    'api_version' => 'v1',
                    'integration_type' => 'webhook',
                    'message_custom' => $e->getMessage(),
                ]
            );
        }
    }

    protected function customerDisputeCreated($webhookEvent)
    {
        try {
            $this->_order->setStatus(\Magento\Paypal\Model\Info::ORDER_STATUS_REVERSED);
            $this->_order->save();
            $this->_order
                ->addStatusHistoryComment(
                    $webhookEvent->summary,
                    \Magento\Paypal\Model\Info::ORDER_STATUS_REVERSED
                )->setIsCustomerNotified(false)
                ->save();

            $this->logger(['event' => 'customer_dispute', 'order' => $this->_order->getIncrementId()]);
            $this->datadogLogger->log(
                "info",
                $this->objectToArray($webhookEvent),
                [
                    'environment' => 'development',
                    'api_version' => 'v1',
                    'integration_type' => 'webhook',
                    'message_custom' => "Order {$this->_order->getIncrementId()} customer_dispute",
                ]
            );
        } catch (\Exception $e) {
            $this->logger(['error' => 'event Customer Dispute failed', 'order' => $this->_order->getIncrementId(), 'message' => $e->getMessage()]);
            $this->datadogLogger->log(
                "error",
                $this->objectToArray($webhookEvent),
                [
                    'environment' => 'development',
                    'api_version' => 'v1',
                    'integration_type' => 'webhook',
                    'message_custom' => $e->getMessage(),
                ]
            );
        }
    }

    /**
     * Load and validate order, instantiate proper configuration
     *
     * @return \Magento\Sales\Model\Order
     * @throws \Exception
     */
    protected function getOrder($webhookEvent)
    {
        // get proper order
        $resource = $this->objectToArray($webhookEvent->resource);

        if (!$resource) {
            throw new \Exception('Event resource not found.');
        }

        $type = $webhookEvent->event_type;

        // Log para debug
        $this->logger(['webhook_type' => $type, 'resource_id' => $resource instanceof \stdClass ? $resource->id : $resource['id']]);

        $transactionId = null;
        $order = null;

        // Primeiro, tentar buscar pelo invoice_id (que é o increment_id do pedido Magento)
        if (isset($resource['purchase_units'][0]['invoice_id'])) {
            $invoiceId = $resource['purchase_units'][0]['invoice_id'];
            $this->logger(['trying_invoice_id' => $invoiceId]);

            $order = $this->salesOrderFactory->create()->loadByIncrementId($invoiceId);
            if ($order->getId()) {
                $this->logger(['order_found_by_invoice_id' => $order->getId()]);
                $this->_order = $order;
                return $this->_order;
            }
        }

        // Se não encontrou pelo invoice_id, tentar pelos transaction_ids
        if ($type === 'CUSTOMER.DISPUTE.CREATED') {
            $transactionId = $resource['disputed_transactions'][0]['seller_transaction_id'] ?? null;
        } elseif ($type === 'RISK.DISPUTE.CREATED') {
            $transactionId = $resource['seller_payment_id'] ?? null;
        } elseif ($type === 'PAYMENT.SALE.REFUNDED') {
            $transactionId = $resource['sale_id'] ?? null;
        } elseif ($type === 'CHECKOUT.ORDER.APPROVED' || $type === 'CHECKOUT.ORDER.COMPLETED') {
            if (isset($resource['purchase_units'][0]['payments']['captures'][0]['id'])) {
                $transactionId = $resource['purchase_units'][0]['payments']['captures'][0]['id'];
                $this->logger(['using_capture_id' => $transactionId]);
            }
            if (!$transactionId) {
                $transactionId = $resource['id'] ?? null;
            }
        } else {
            $transactionId = $resource['id'] ?? null;
        }

        $this->logger(['webhook_type' => $type, 'extracted_transaction_id' => $transactionId]);

        if (empty($transactionId)) {
            throw new \Exception('Transaction ID not found in webhook resource.');
        }

        // Tentar buscar a transação
        $transaction = $this->salesOrderPaymentTransactionFactory->create()->load($transactionId, 'txn_id');

        $this->logger(['transaction_found' => $transaction->getId() ? 'yes' : 'no', 'transaction_id' => $transaction->getId()]);

        if (!$transaction->getId()) {
            // Fallback: buscar por qualquer campo que contenha o transaction_id
            $transaction = $this->salesOrderPaymentTransactionFactory->create()
                ->getCollection()
                ->addFieldToFilter('additional_information', ['like' => '%' . $transactionId . '%'])
                ->getFirstItem();

            $this->logger(['fallback_transaction_found' => $transaction->getId() ? 'yes' : 'no']);
        }

        if (!$transaction->getId()) {
            // Segundo fallback: buscar pelo resource id original
            $originalResourceId = $resource['id'] ?? null;
            if ($originalResourceId && $originalResourceId !== $transactionId) {
                $transaction = $this->salesOrderPaymentTransactionFactory->create()->load($originalResourceId, 'txn_id');
                $this->logger(['second_fallback_transaction_found' => $transaction->getId() ? 'yes' : 'no', 'original_resource_id' => $originalResourceId]);
            }
            throw new \Exception("Transaction {$transactionId} not found in Magento.");
        }

        $this->_order = $this->salesOrderFactory->create()->load($transaction->getOrderId());

        if (!$this->_order->getId()) {
            $this->logger(['error' => 'Order not found', 'transaction_id' => $transactionId, 'order_id' => $transaction->getOrderId()]);
            throw new \Magento\Framework\Exception\LocalizedException(__('Order not found.'));
        }

        $this->logger(['order_found' => $this->_order->getId(), 'order_increment_id' => $this->_order->getIncrementId()]);

        return $this->_order;
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
