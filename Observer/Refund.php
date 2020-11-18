<?php

namespace PayPalBR\PayPal\Observer;

use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Event\Observer as EventObserver;
use PayPalBR\PayPal\Model\PayPalPlus\ConfigProvider;
use PayPal\Api\Amount;
use PayPal\Api\Refund as PayPalRefund;
use PayPal\Api\Sale;
use Magento\Framework\Filesystem\DirectoryList;

class Refund implements ObserverInterface
{
    const MODULE_NAME = 'PayPalBR_PayPal';

    protected $configProvider;
    protected $dir;

    /**
     * RequestBuilder constructor.
     * @param ConfigProvider $configProvider
     */
    public function __construct(
        ConfigProvider $configProvider,
        DirectoryList $dir
    ) {
        $this->setConfigProvider($configProvider);
        $this->dir = $dir;
    }
    /**
     * @param EventObserver $observer
     * @return void
     */
    public function execute(EventObserver $observer)
    {
        $event = $observer->getEvent();
        
        $payment = $event->getPayment();

        if ($payment->getMethod() != 'paypalbr_paypalplus') {
            return $this;
        }

        try {
            $saleId = $payment->getLastTransId();
            $creditMemo = $event->getCreditmemo();
            
            //for store credit, do not refund anything to PayPal
            if(!empty($creditMemo->getData('customer_balance_amount'))) {
                return $this;
            } else {
                $amt = $this->setAmountRequest($payment->getAmountRefunded());
            }
            $refundRequest = $this->setRefundRequest($amt);
            $sale = $this->setSale($payment->getLastTransId());

            $apiContext = $this->getApiContext();
            $order = $payment->getOrder();
            $refundedSale = $sale->refund($refundRequest, $apiContext);
        } catch (\Exception $e) {
            $data = json_decode($e->getData());
            if (('TRANSACTION_REFUSED' == $data->name) || ('TRANSACTION_ALREADY_REFUNDED' == $data->name)) {
                $payment->setAdditionalInformation('refund', $data->name)->setAdditionalInformation('state_payPal', 'refunded')->save();

                $order->addStatusHistoryComment(
                    __(
                        'Notified customer about refund #%1.',
                        $order->getIncrementId()
                    )
                )->setIsCustomerNotified(true)
                ->save();

                return $this;
            }else{
                throw new \Exception('An error occurred with Refund');
            }
        }

        $payment->setAdditionalInformation('refund', $refundedSale->getId())->setAdditionalInformation('state_payPal', 'refunded')->save();

        $order->addStatusHistoryComment(
            __(
                'Notified customer about refund #%1.',
                $order->getIncrementId()
            )
        )->setIsCustomerNotified(true)
        ->save();

        return $this;
    }

    protected function setSale($saleId)
    {
        $sale = new Sale();
        $sale->setId($saleId);

        return $sale;
    }

    protected function setRefundRequest($amt)
    {
        $refundRequest = new PayPalRefund();
        $refundRequest->setAmount($amt);

        return $refundRequest;
    }

    protected function setAmountRequest($value)
    {
        $amt = new Amount();
        $amt->setCurrency('BRL')->setTotal($value);

        return $amt;
    }

    /**
     * Builds and returns the api context to be used in PayPal Plus API
     *
     * @return \PayPal\Rest\ApiContext
     */
    protected function getApiContext()
    {

        $debug = $this->getConfigProvider()->isDebugEnabled();
        $this->configId = $this->getConfigProvider()->getClientId();
        $this->secretId = $this->getConfigProvider()->getSecretId();

        if($debug == 1){
            $debug = true;
        }else{
            $debug = false;
        }
        $apiContext = new \PayPal\Rest\ApiContext(
            new \PayPal\Auth\OAuthTokenCredential(
                $this->configId,
                $this->secretId
            )
        );
        $apiContext->setConfig(
            [
                'http.headers.PayPal-Partner-Attribution-Id' => 'MagentoBrazil_Ecom_PPPlus2',
                'mode' => $this->getConfigProvider()->isModeSandbox() ? 'sandbox' : 'live',
                'log.LogEnabled' => $debug,
                'log.FileName' => $this->dir->getPath('log') . '/paypalplus-refund-' . date('Y-m-d') . '.log',
                'cache.FileName' => $this->dir->getPath('log') . '/auth.cache',
                'log.LogLevel' => 'DEBUG', // PLEASE USE `INFO` LEVEL FOR LOGGING IN LIVE ENVIRONMENTS
                'cache.enabled' => false,
                'http.CURLOPT_SSLVERSION' => 'CURL_SSLVERSION_TLSv1_2'
            ]
        );
        return $apiContext;
    }

    /**
     * @return mixed
     */
    public function getConfigProvider()
    {
        return $this->configProvider;
    }

    /**
     * @param mixed $configProvider
     *
     * @return self
     */
    public function setConfigProvider($configProvider)
    {
        $this->configProvider = $configProvider;

        return $this;
    }
}
