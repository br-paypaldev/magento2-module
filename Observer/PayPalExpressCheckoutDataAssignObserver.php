<?php

namespace PayPalBR\PayPal\Observer;


use Magento\Framework\DataObject;
use Magento\Payment\Observer\AbstractDataAssignObserver;
use Magento\Framework\Event\Observer;
use Magento\Quote\Api\Data\PaymentInterface;

class PayPalExpressCheckoutDataAssignObserver extends AbstractDataAssignObserver
{
    public function execute(Observer $observer)
    {
        $method = $this->readMethodArgument($observer);
        $info = $method->getInfoInstance();
        $data = $this->readDataArgument($observer);

        $additionalData = $data->getData(PaymentInterface::KEY_ADDITIONAL_DATA);

        if (!is_object($additionalData)) {
            $additionalData = new DataObject($additionalData ?: []);
        }
        
        $dataAdditional = $additionalData->getData();
        if (isset($dataAdditional['payId'])) {
            $info->setAdditionalInformation('pay_id', $dataAdditional['payId']);
            $info->setAdditionalInformation('payer_id', $dataAdditional['payerId']);
            $info->setAdditionalInformation('token', $dataAdditional['token']);
            $info->setAdditionalInformation('term', $dataAdditional['term']);
        }

        return $this;
    }
}
