<?php

namespace PayPalBR\PayPal\Gateway\Transaction\PayPalExpressCheckout\ResourceGateway\Create\Response;

use Magento\Payment\Gateway\Response\HandlerInterface;
use PayPalBR\PayPal\Gateway\Transaction\Base\ResourceGateway\Response\AbstractHandler;
use PayPalBR\PayPal\Gateway\Transaction\Base\Config\ConfigInterface;
use PayPalBR\PayPal\Model\PayPalRequests;

class GeneralHandler extends AbstractHandler implements HandlerInterface
{
	const PENDING = "pending";

    protected $config;

    protected $paypalRequests;

    /**
     * GeneralHandler constructor.
     * @param Config $config
     */
    public function __construct(
        ConfigInterface $config,
        PayPalRequests $paypalRequests
    ) {
        $this->setConfig($config);
        $this->paypalRequests = $paypalRequests;
    }

    /**
     * {@inheritdoc}
     */
    protected function _handle($payment, $response)
    {
        $order = $payment->getOrder();

        $links = json_decode($payment->getAdditionalInformation('order_data'))->links;

        $patchUrl = "";
        $captureUrl = "";
        foreach ($links as $link) {
            if ($link->rel == 'self') {
                $patchUrl = $link->href;
                $captureUrl = $link->href . '/capture';
            }
        }

        $transaction = null;
        try {
            $this->paypalRequests->updateOrder($patchUrl ,$order->getIncrementid());

            $orderDetails = $this->paypalRequests->getOrderDetails($patchUrl);

            $transaction = $this->paypalRequests->captureOrder($captureUrl, $order->getIncrementid());
        } catch (\Exception $e) {
            throw new \Exception("Error Processing Request", 1);
        }

        $capture = $transaction->purchase_units[0]->payments->captures[0];

        $payment->setAdditionalInformation('transaction_log', json_encode($transaction));

        $order->save();

        $payment->setAdditionalInformation('pay_id', $capture->id);
        $payment->setAdditionalInformation('invoice_id', $capture->invoice_id);
        $payment->setAdditionalInformation('state_payPal', $capture->status);
        $payment->setAdditionalInformation('term', $orderDetails->credit_financing_offer->term);
        $payment->setTransactionId($capture->id);
        $payment->setParentTransactionId($response->id);
        $payment->setIsTransactionClosed(false);

        return $this;
    }

    /**
     * @return mixed
     */
    public function getConfig()
    {
        return $this->config;
    }

    /**
     * @param mixed $config
     *
     * @return self
     */
    public function setConfig($config)
    {
        $this->config = $config;

        return $this;
    }
}
