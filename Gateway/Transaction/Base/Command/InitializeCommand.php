<?php

namespace PayPalBR\PayPal\Gateway\Transaction\Base\Command;

use Magento\Payment\Gateway\Helper\SubjectReader;
use Magento\Sales\Model\Order;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Payment\Gateway\CommandInterface;
use Magento\Sales\Model\Order\Payment;

class InitializeCommand implements CommandInterface
{
    /**
     * @param array $commandSubject
     * @return $this
     */
    public function execute(Array $commandSubject)
    {
        /** @var \Magento\Framework\DataObject $stateObject */
        $stateObject = $commandSubject['stateObject'];
        $paymentDO = SubjectReader::readPayment($commandSubject);
        $payment = $paymentDO->getPayment();

        if (!$payment instanceof Payment) {
            throw new \LogicException('Order Payment should be provided');
        }

        $payment->getOrder()->setCanSendNewEmailFlag(false);
        $baseTotalDue = $payment->getOrder()->getBaseTotalDue();
        $totalDue = $payment->getOrder()->getTotalDue();
        $payment->authorize(true, $baseTotalDue);
        $payment->setAmountAuthorized($totalDue);
        $payment->setBaseAmountAuthorized($payment->getOrder()->getBaseTotalDue());
        $stateObject->setData(OrderInterface::STATE, Order::STATE_PENDING_PAYMENT);

        $stateObject->setData(OrderInterface::STATUS, $payment->getMethodInstance()->getConfigData('order_status'));

        if ($payment->getIsFraudDetected()) {
            $stateObject->setData(OrderInterface::STATE, Order::STATE_PAYMENT_REVIEW);
            $stateObject->setData(OrderInterface::STATUS, $payment->getMethodInstance()->getConfigData('reject_order_status'));
        }

        if ($payment->getIsTransactionPending()) {
            $stateObject->setData(OrderInterface::STATE, Order::STATE_PAYMENT_REVIEW);
            $stateObject->setData(OrderInterface::STATUS, $payment->getMethodInstance()->getConfigData('review_order_status'));
        }

        $stateObject->setData('is_notified', false);

        return $this;
    }
}
