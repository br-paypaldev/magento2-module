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
    }

    public function processWebhookRequest($webhookEvent)
    {
        if ($webhookEvent->event_type !== null && in_array($webhookEvent->event_type,
                $this->getSupportedWebhookEvents())
        ) {
            $this->getOrder($webhookEvent);
            $this->{$this->eventTypeToHandler($webhookEvent->event_type)}($webhookEvent);
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
        return array(
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
        );
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
            if (!$key) {
                $eventParts[$key] = strtolower($eventPart);
                continue;
            }
            $eventParts[$key] = ucfirst(strtolower($eventPart));
        }
        return implode('', $eventParts);
    }

    protected function paymentCaptureCompleted($webhookEvent)
    {
        $paymentResource = $webhookEvent->resource;
        $parentTransactionId = $paymentResource['parent_payment'];
        $payment = $this->_order->getPayment();
        $payment->setTransactionId($paymentResource['id'])
            ->setCurrencyCode($paymentResource['amount']['currency'])
            ->setParentTransactionId($parentTransactionId)
            ->setIsTransactionClosed(true)
            ->setAdditionalInformation('state_payPal', 'completed')
            ->registerCaptureNotification(
                $paymentResource['amount']['total'],
                true
            );
        $this->_order->setState('processing');
        $this->_order->setStatus('processing');
        $this->_order->save();
        // notify customer
        $invoice = $payment->getCreatedInvoice();
        if ($invoice && !$this->_order->getEmailSent()) {
            $this->_order->addStatusHistoryComment(
                __(
                    'Notified customer about invoice #%1.',
                    $invoice->getIncrementId()
                )
            )->setIsCustomerNotified(true)
                ->save();
        }
    }

    protected function logger($array)
    {
        $this->loggerHandler->setFileName('paypal-webhook-' . date('Y-m-d'));
        $this->customLogger->info($array);
    }

    protected function paymentCaptureRefunded($webhookEvent)
    {
        $this->_order->getPayment()->setAdditionalInformation('state_payPal', 'refunded')->save();
        $creditmemo = $this->creditmemoFactory->createByOrder($this->_order);
        $creditmemoServiceRefund = $this->creditmemoService->refund($creditmemo, true);
    }

    protected function paymentCapturePending($webhookEvent)
    {
        $paymentResource = $webhookEvent->resource;
        $this->_order->getPayment()
            ->setPreparedMessage($webhookEvent->summary)
            ->setTransactionId($paymentResource['id'])
            ->setIsTransactionClosed(0);
        $this->_order->getPayment()->save();
    }

    protected function paymentCaptureReversed($webhookEvent)
    {
        $this->_order->setStatus(\Magento\Paypal\Model\Info::ORDER_STATUS_REVERSED);
        $this->_order->save();
        $this->_order
            ->addStatusHistoryComment(
                $webhookEvent->summary,
                \Magento\Paypal\Model\Info::ORDER_STATUS_REVERSED
            )->setIsCustomerNotified(false)
            ->save();
        $this->_order->getPayment()->setAdditionalInformation('state_payPal', 'refunded')->save();
    }

    protected function riskDisputeCreated($webhookEvent)
    {
        $this->_order->setStatus(\Magento\Paypal\Model\Info::ORDER_STATUS_REVERSED);
        $this->_order->save();
        $this->_order
            ->addStatusHistoryComment(
                $webhookEvent->summary,
                \Magento\Paypal\Model\Info::ORDER_STATUS_REVERSED
            )->setIsCustomerNotified(false)
            ->save();
    }

    protected function customerDisputeCreated($webhookEvent)
    {
        $this->_order->setStatus(\Magento\Paypal\Model\Info::ORDER_STATUS_REVERSED);
        $this->_order->save();
        $this->_order
            ->addStatusHistoryComment(
                $webhookEvent->summary,
                \Magento\Paypal\Model\Info::ORDER_STATUS_REVERSED
            )->setIsCustomerNotified(false)
            ->save();
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
        $resource = $webhookEvent->resource;
        if (!$resource) {
            throw new \Exception('Event resource not found.');
        }
        $type = $webhookEvent->event_type;
        if ($type == 'CUSTOMER.DISPUTE.CREATED') {
            $transactionId = $resource instanceof \stdClass ? $resource->disputed_transactions[0]->seller_transaction_id : $resource['disputed_transactions'][0]['seller_transaction_id'];
        } elseif ($type == 'RISK.DISPUTE.CREATED') {
            $transactionId = $resource instanceof \stdClass ? $resource->seller_payment_id : $resource['seller_payment_id'];
        } elseif ($type == 'PAYMENT.SALE.REFUNDED') {
            $transactionId = $resource instanceof \stdClass ? $resource->sale_id : $resource['sale_id'];
        } else {
            $transactionId = $resource instanceof \stdClass ? $resource->id : $resource['id'];
        }

        $transaction = $this->salesOrderPaymentTransactionFactory->create()->load($transactionId, 'txn_id');
        $this->_order = $this->salesOrderFactory->create()->load($transaction->getOrderId());
        if (!$this->_order->getId()) {
            throw new \Magento\Framework\Exception\LocalizedException(__('Order not found.'));
        }

        return $this->_order;
    }
}
