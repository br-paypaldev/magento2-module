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

class Event implements EventsInterface
{
    /**
     * Payment sale completed event type code
     */
    const PAYMENT_SALE_COMPLETED = 'PAYMENT.SALE.COMPLETED';

    /**
     * Payment sale pending  event type code
     */
    const PAYMENT_SALE_PENDING = 'PAYMENT.SALE.PENDING';

    /**
     * Payment sale refunded event type
     */
    const PAYMENT_SALE_REFUNDED = 'PAYMENT.SALE.REFUNDED';

    /**
     * Payment sale reversed event type code
     */
    const PAYMENT_SALE_REVERSED = 'PAYMENT.SALE.REVERSED';

    /**
     * Risk dispute created event type code
     */
    const RISK_DISPUTE_CREATED = 'RISK.DISPUTE.CREATED';

    /**
     * Customer dispute created event type code
     */
    const CUSTOMER_DISPUTE_CREATED = 'CUSTOMER.DISPUTE.CREATED';

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

    public function __construct(
        \Magento\Sales\Model\Order\Payment\TransactionFactory $salesOrderPaymentTransactionFactory,
        \Magento\Sales\Model\OrderFactory $salesOrderFactory,
        CreditmemoFactory $creditmemoFactory,
        CreditmemoService $creditmemoService
    ) {
        $this->salesOrderPaymentTransactionFactory = $salesOrderPaymentTransactionFactory;
        $this->salesOrderFactory = $salesOrderFactory;
        $this->creditmemoFactory = $creditmemoFactory;
        $this->creditmemoService = $creditmemoService;
    }
    /**
     * Process the given $webhookEvent
     *
     * @param \PayPal\Api\WebhookEvent $webhookEvent
     */
    public function processWebhookRequest(\PayPal\Api\WebhookEvent $webhookEvent)
    {
        if ($webhookEvent->getEventType() !== null && in_array($webhookEvent->getEventType(),
                $this->getSupportedWebhookEvents())
        ) {
            $this->getOrder($webhookEvent);
            $this->{$this->eventTypeToHandler($webhookEvent->getEventType())}($webhookEvent);
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
            self::PAYMENT_SALE_COMPLETED,
            self::PAYMENT_SALE_PENDING,
            self::PAYMENT_SALE_REFUNDED,
            self::PAYMENT_SALE_REVERSED,
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

    /**
     * Mark transaction as completed
     *
     * @param \PayPal\Api\WebhookEvent $webhookEvent
     */
    protected function paymentSaleCompleted(\PayPal\Api\WebhookEvent $webhookEvent)
    {
        $paymentResource = $webhookEvent->getResource();
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

    /**
     * Mark transaction as refunded
     *
     * @param \PayPal\Api\WebhookEvent $webhookEvent
     * @throws \Exception
     */
    protected function paymentSaleRefunded(\PayPal\Api\WebhookEvent $webhookEvent)
    {
        $paymentResource = $webhookEvent->getResource();
        $parentTransactionId = $paymentResource['parent_payment'];
        /** @var \Magento\Sales\Model\Order\Payment $payment */
        $this->_order->getPayment()->setAdditionalInformation('state_payPal', 'refunded')->save();
        $amount = $paymentResource['amount']['total'];
        $transactionId = $paymentResource['id'];

        $creditmemo = $this->creditmemoFactory->createByOrder($this->_order);

        $creditmemoServiceRefund = $this->creditmemoService->refund($creditmemo, true);
    }

    /**
     * Mark transaction as pending
     *
     * @param \PayPal\Api\WebhookEvent $webhookEvent
     */
    protected function paymentSalePending(\PayPal\Api\WebhookEvent $webhookEvent)
    {
        $paymentResource = $webhookEvent->getResource();
        $this->_order->getPayment()
            ->setPreparedMessage($webhookEvent->getSummary())
            ->setTransactionId($paymentResource['id'])
            ->setIsTransactionClosed(0);
        $this->_order->getPayment()->save();
    }

    /**
     * Mark transaction as reversed
     *
     * @param \PayPal\Api\WebhookEvent $webhookEvent
     */
    protected function paymentSaleReversed(\PayPal\Api\WebhookEvent $webhookEvent)
    {
        $this->_order->setStatus(\Magento\Paypal\Model\Info::ORDER_STATUS_REVERSED);
        $this->_order->save();
        $this->_order
            ->addStatusHistoryComment(
                $webhookEvent->getSummary(),
                \Magento\Paypal\Model\Info::ORDER_STATUS_REVERSED
            )->setIsCustomerNotified(false)
            ->save();
        $this->_order->getPayment()->setAdditionalInformation('state_payPal', 'refunded')->save();
    }

    /**
     * Add risk dispute to order comment
     *
     * @param \PayPal\Api\WebhookEvent $webhookEvent
     */
    protected function riskDisputeCreated(\PayPal\Api\WebhookEvent $webhookEvent)
    {
        $this->_order->setStatus(\Magento\Paypal\Model\Info::ORDER_STATUS_REVERSED);
        $this->_order->save();
        $this->_order
            ->addStatusHistoryComment(
                $webhookEvent->getSummary(),
                \Magento\Paypal\Model\Info::ORDER_STATUS_REVERSED
            )->setIsCustomerNotified(false)
            ->save();
    }

    /**
     * Add risk dispute to order comment
     *
     * @param \PayPal\Api\WebhookEvent $webhookEvent
     */
    protected function customerDisputeCreated(\PayPal\Api\WebhookEvent $webhookEvent)
    {
        $this->_order->setStatus(\Magento\Paypal\Model\Info::ORDER_STATUS_REVERSED);
        $this->_order->save();
        $this->_order
            ->addStatusHistoryComment(
                $webhookEvent->getSummary(),
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
    protected function getOrder(\PayPal\Api\WebhookEvent $webhookEvent)
    {
        if (empty($this->_order)) {
            // get proper order
            $resource = $webhookEvent->getResource();
            if (!$resource) {
                throw new \Exception('Event resource not found.');
            }
            $type = $webhookEvent->getEventType();
            if ($type == 'CUSTOMER.DISPUTE.CREATED') {
                $transactionId = $resource['disputed_transactions'][0]['seller_transaction_id'];
            }elseif ($type == 'RISK.DISPUTE.CREATED') {
                $transactionId = $resource['seller_payment_id'];
            }else{
                $transactionId = $resource['id'];
            }
            
            $transaction = $this->salesOrderPaymentTransactionFactory->create()->load($transactionId, 'txn_id');
            $this->_order = $this->salesOrderFactory->create()->load($transaction->getOrderId());
            if (!$this->_order->getId()) {
                throw new \Magento\Framework\Exception\LocalizedException(__('Order not found.'));
            }
        }

        return $this->_order;
    }
}