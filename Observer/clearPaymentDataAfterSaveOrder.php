<?php

namespace PayPalBR\PayPal\Observer;

use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Event\Observer as EventObserver;
use PayPalBR\PayPal\Model\Http\Api;
use Magento\Checkout\Model\Session;
use Psr\Log\LoggerInterface;

class clearPaymentDataAfterSaveOrder implements ObserverInterface
{
    /**
     * @var \Magento\Checkout\Model\Session
     */
    protected $checkoutSession;

    /**
     * @var \Psr\Log\LoggerInterface
     */
    protected $logger;

    /**
     * @param \Psr\Log\LoggerInterface $logger
     * @param Api $api
     */
    public function __construct(
        Session $checkoutSession,
        LoggerInterface $logger
    ) {
        $this->checkoutSession = $checkoutSession;
        $this->logger = $logger;
    }

    /**
     * @param EventObserver $observer
     * @return void
     */
    public function execute(EventObserver $observer)
    {
        $event = $observer->getEvent();
        $order = $event->getOrder();

        if (
            $order &&
            $order->getId() &&
            $order->getPayment()->getMethod() == \PayPalBR\PayPal\Model\Payment\PayPalPlus::METHOD_NAME
        ) {
            $this->checkoutSession->setPaymentId(false);
            $this->checkoutSession->setIframeUrl(false);
            $this->checkoutSession->setExecuteUrl(false);
            $this->checkoutSession->setPaymentIdExpires(false);
            $this->checkoutSession->setPaypalPaymentId( null );
            $this->checkoutSession->setQuoteUpdatedAt( null );
        }

        return $this;
    }
}
