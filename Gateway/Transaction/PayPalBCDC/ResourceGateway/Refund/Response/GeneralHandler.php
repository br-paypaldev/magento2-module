<?php

namespace PayPalBR\PayPal\Gateway\Transaction\PayPalBCDC\ResourceGateway\Refund\Response;

use Magento\Payment\Gateway\Response\HandlerInterface;
use PayPalBR\PayPal\Gateway\Transaction\Base\ResourceGateway\Response\AbstractHandler;
use Magento\Payment\Gateway\Helper\SubjectReader;
use PayPalBR\PayPal\Logger\Logger;
use PayPalBR\PayPal\Model\PayPalRequests;
use Magento\Sales\Model\Order;

class GeneralHandler extends AbstractHandler implements HandlerInterface
{
	const PENDING = "pending";

    protected $config;

    protected $logger;

    protected $paypalRequests;

    /**
     * GeneralHandler constructor.
     * @param Config $config
     */
    public function __construct(
        PayPalRequests $paypalRequests,
        Logger $logger
    ) {
        $this->paypalRequests = $paypalRequests;
        $this->logger = $logger;
    }

    /**
     * {@inheritdoc}
     */
    protected function _handle($payment, $response)
    {
        $fullRefund = $payment->getAmountRefunded() + $response['amount'] >= $payment->getAmountPaid();

        try {
            if ($fullRefund) {
                $refundResult = $this->paypalRequests->refundTransaction($response['transaction_id']);
                $payment->setIsTransactionPending(false);
                $payment->setIsTransactionClosed(true);
                $payment->setShouldCloseParentTransaction(true);
                $order = $payment->getOrder();
                $order->setData('state', Order::STATE_CLOSED);
                $order->setStatus('refunded');
                $order->save();
            } else {
                $refundResult = $this->paypalRequests->refundTransaction($response['transaction_id'], $response['amount']);
                $order = $payment->getOrder();
                $order->setStatus('partially_refunded');
                $order->save();
            }

            $payment->setAdditionalInformation('refund', $refundResult->id)->setAdditionalInformation('state_payPal', 'refunded')->save();

            $payment->setParentTransactionId($response['transaction_id']);
            $payment->setTransactionId($refundResult->id);
        } catch (\Exception $e) {
            $this->logger->info('REFUND HANDLER ERROR', [$e->getMessage()]);
        }
    }
}
