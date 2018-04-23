<?php

namespace PayPalBR\PayPal\Observer;

use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Event\Observer as EventObserver;
use Magento\Checkout\Model\Session;
use Psr\Log\LoggerInterface;
use Magento\Sales\Model\Service\OrderService;
use Magento\Customer\Model\CustomerFactory;
use Magento\Customer\Model\SessionFactory;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Sales\Model\Service\InvoiceService;
use Magento\Sales\Model\Order\Email\Sender\InvoiceSender;
use Magento\Framework\DB\Transaction;

class SalesOrderPlaceAfter implements ObserverInterface
{



    /** session factory */
    protected $_session;

    /** @var CustomerFactoryInterface */
    protected $customerFactory;
    /**
     * @var \Magento\Checkout\Model\Session
     */
    protected $checkoutSession;

    /**
     * @var \Psr\Log\LoggerInterface
     */
    protected $logger;

    /**
     * \Magento\Sales\Model\Service\OrderService
     */
    protected $orderService;

    /**
     * \Magento\Customer\Api\CustomerRepositoryInterface
     */
    protected $customerRepository;

    /**
     * \Magento\Sales\Model\Service\InvoiceService
     */
    protected $invoiceService;

    /**
     * \Magento\Framework\DB\Transaction
     */
    protected $transaction;

    /**
     * \Magento\Sales\Model\Order\Email\Sender\InvoiceSender
     */
    protected $invoiceSender;

    /**
     * @param \Magento\Checkout\Model\Session $checkoutSession
     * @param \Psr\Log\LoggerInterface $logger
     * @param Api $api
     */
    public function __construct(
        Session $checkoutSession,
        LoggerInterface $logger,
        OrderService $orderService,
        CustomerFactory $customerFactory,
        SessionFactory $sessionFactory,
        CustomerRepositoryInterface $customerRepository,
        InvoiceService $invoiceService,
        Transaction $transaction,
        InvoiceSender $invoiceSender
    ) {
        $this->setCheckoutSession($checkoutSession);
        $this->setLogger($logger);
        $this->setOrderService($orderService);
        $this->setTransaction($transaction);
        $this->setInvoiceService($invoiceService);
        $this->setInvoiceSender($invoiceSender);
        $this->customerFactory = $customerFactory;
        $this->sessionFactory = $sessionFactory;
        $this->customerRepository = $customerRepository;
    }

    /**
     * @param EventObserver $observer
     * @return void
     */
    public function execute(EventObserver $observer)
    {
        $event = $observer->getEvent();
        $order = $event->getOrder();
        $payment = $order->getPayment();

        if ($payment->getMethod() != 'paypalbr_paypalplus') {
            return $this;
        }

        $status = $payment->getAdditionalInformation('state_payPal');
        

        $customerSession = $this->sessionFactory->create();
        if ($customerSession->isLoggedIn()){
            $r_card = $payment->getAdditionalInformation('remembered_card');
            $customer = $customerSession->getCustomer();
            $customer = $this->customerRepository->getById($customer->getId());
            $customer->getCustomAttribute('remembered_card');
            $customer->setCustomAttribute('remembered_card', $r_card);
            $customer = $this->customerRepository->save($customer);
        }

        if ($order->canCancel() && $status == 'denied') {
            $result = $this->cancelOrder($order);
            $this->logger($result);
        }

        if ($order->getPayment()->getLastTransId() && 
            ( $order->canInvoice() && $status == 'approved' || $order->canInvoice() && $status == 'completed' ) 
        ) {
            $result = $this->createInvoice($order);
            $this->logger($result);
        }

        return $this;
    }

    /**
     * @param Order $order
     * @return $invoice
     */
    protected function createInvoice($order)
    {
        $payment = $order->getPayment();
        $payment->setTransactionId($order->getPayment()->getLastTransId())
            ->setCurrencyCode('BRL')
            ->setParentTransactionId($order->getPayment()->getAdditionalInformation('pay_id'))
            ->setIsTransactionClosed(true)
            ->registerCaptureNotification(
                $order->getGrandTotal(),
                true
            );
        $order->save();
        // notify customer
        $invoice = $payment->getCreatedInvoice();
        if ($invoice && !$order->getEmailSent()) {
            $order->addStatusHistoryComment(
                    __(
                        'Notified customer about invoice #%1.',
                        $invoice->getIncrementId()
                    )
                )->setIsCustomerNotified(true)
                ->save();
        }

        return true;
    }

    /**
     * @param Order $order
     * @return $cancel
     */
    protected function cancelOrder($order)
    {
        $cancel = $this->getOrderService()->cancel($order->getId());

        return $cancel;
    }

    /**
     * @param mixed $data
     */
    protected function logger($data){

        $writer = new \Zend\Log\Writer\Stream(BP . '/var/log/paypalbr/paypal-SalesOrderPlaceAfter-' . date('Y-m-d') . '.log');
        $logger = new \Zend\Log\Logger();
        $logger->addWriter($writer);
        $logger->info('Debug Initial SalesOrderPlaceAfter');
        $logger->info($data);
        $logger->info('Debug Final SalesOrderPlaceAfter');
    }

    /**
     * @return \Magento\Checkout\Model\Session
     */
    public function getCheckoutSession()
    {
        return $this->checkoutSession;
    }

    /**
     * @param \Magento\Checkout\Model\Session $checkoutSession
     *
     * @return self
     */
    public function setCheckoutSession(\Magento\Checkout\Model\Session $checkoutSession)
    {
        $this->checkoutSession = $checkoutSession;

        return $this;
    }

    /**
     * @return \Psr\Log\LoggerInterface
     */
    public function getLogger()
    {
        return $this->logger;
    }

    /**
     * @param \Psr\Log\LoggerInterface $logger
     *
     * @return self
     */
    public function setLogger(\Psr\Log\LoggerInterface $logger)
    {
        $this->logger = $logger;

        return $this;
    }

    /**
     * @return mixed
     */
    public function getOrderService()
    {
        return $this->orderService;
    }

    /**
     * @param mixed $orderService
     *
     * @return self
     */
    public function setOrderService($orderService)
    {
        $this->orderService = $orderService;

        return $this;
    }

    /**
     * @return mixed
     */
    public function getInvoiceService()
    {
        return $this->invoiceService;
    }

    /**
     * @param mixed $invoiceService
     *
     * @return self
     */
    public function setInvoiceService($invoiceService)
    {
        $this->invoiceService = $invoiceService;

        return $this;
    }

    /**
     * @return mixed
     */
    public function getTransaction()
    {
        return $this->transaction;
    }

    /**
     * @param mixed $transaction
     *
     * @return self
     */
    public function setTransaction($transaction)
    {
        $this->transaction = $transaction;

        return $this;
    }

    /**
     * @return mixed
     */
    public function getInvoiceSender()
    {
        return $this->invoiceSender;
    }

    /**
     * @param mixed $invoiceSender
     *
     * @return self
     */
    public function setInvoiceSender($invoiceSender)
    {
        $this->invoiceSender = $invoiceSender;

        return $this;
    }
}
