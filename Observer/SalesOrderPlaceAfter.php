<?php

/**
 * PayPalBR PayPal
 *
 * @package PayPalBR|PayPal
 * @author Vitor Nicchio Alves <vitor@imaginationmedia.com>
 * @copyright Copyright (c) 2021 Imagination Media (https://www.imaginationmedia.com/)
 * @license https://opensource.org/licenses/OSL-3.0.php Open Software License 3.0
 */

declare(strict_types=1);

namespace PayPalBR\PayPal\Observer;

use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Event\Observer as EventObserver;
use Magento\Checkout\Model\Session;
use PayPalBR\PayPal\Logger\Handler;
use PayPalBR\PayPal\Logger\Logger;
use Psr\Log\LoggerInterface;
use Magento\Sales\Model\Service\OrderService;
use Magento\Customer\Model\CustomerFactory;
use Magento\Customer\Model\SessionFactory;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Sales\Model\Service\InvoiceService;
use Magento\Sales\Model\Order\Email\Sender\InvoiceSender;
use Magento\Framework\DB\Transaction;
use Magento\Framework\Filesystem\DirectoryList;

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
        InvoiceSender $invoiceSender,
        DirectoryList $dir,
        Logger $customLogger,
        Handler $loggerHandler
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
        $this->dir = $dir;
        $this->customLogger = $customLogger;
        $this->loggerHandler = $loggerHandler;
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
                $order->getBaseGrandTotal(),
                true
            );
        $order->save();
        // notify customer
        if ($order->canInvoice()) {
            $invoice = $this->invoiceService->prepareInvoice($order);
            $invoice->getOrder()->setFee(2);
            $invoice->getOrder()->setBaseFee(2);
            $invoice->register();
            $invoice->save();
            $transactionSave = $this->transaction->addObject(
                $invoice
            )->addObject(
                $invoice->getOrder()
            );
            $transactionSave->save();
            $this->invoiceSender->send($invoice);

            if ($invoice && !$order->getEmailSent()) {
                $order->addStatusHistoryComment(
                    __(
                        'Notified customer about invoice #%1.',
                        $invoice->getIncrementId()
                    )
                )->setIsCustomerNotified(true)
                    ->save();
            }
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
        $this->loggerHandler->setFileName('paypal-SalesOrderPlaceAfter-' . date('Y-m-d'));
        $this->customLogger->info('Debug Initial SalesOrderPlaceAfter');
        $this->customLogger->info($data);
        $this->customLogger->info('Debug Final SalesOrderPlaceAfter');
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
