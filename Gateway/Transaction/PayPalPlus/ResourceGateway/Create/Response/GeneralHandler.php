<?php

namespace PayPalBR\PayPal\Gateway\Transaction\PayPalPlus\ResourceGateway\Create\Response;

use Magento\Payment\Gateway\Response\HandlerInterface;
use PayPalBR\PayPal\Gateway\Transaction\Base\ResourceGateway\Response\AbstractHandler;
use PayPalBR\PayPal\Gateway\Transaction\Base\Config\ConfigInterface;

class GeneralHandler extends AbstractHandler implements HandlerInterface
{
	const PENDING = "pending";

    protected $config;

    /**
     * GeneralHandler constructor.
     * @param Config $config
     */
    public function __construct(
        ConfigInterface $config
    ) {
        $this->setConfig($config);
    }

    /**
     * {@inheritdoc}
     */
    protected function _handle($payment, $response)
    {
        if (get_class($response) == 'stdClass') {
            $payment->setAdditionalInformation('state_payPal', 'denied');
            $payment->setAdditionalInformation('state_error_name', $response->name);
            $payment->setAdditionalInformation('state_error_message', $response->message);
            $payment->setAdditionalInformation('state_error_details', $response->details);

            return $this;
        }

    	$transactions = $response->getTransactions();
    	foreach ($transactions as $id => $transaction) {
    		foreach ($transaction->getRelatedResources() as $id => $relatedResources) {
                $sale = $relatedResources->getSale();

                $parentTransactionId = $payment->getAdditionalInformation('pay_id');
                $payment->setTransactionId($sale->getId());
                $payment->setParentTransactionId($parentTransactionId);
                $payment->setIsTransactionClosed(false);
                $payment->setAdditionalInformation('state_payPal', $sale->getState());
    		}
    	}

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
