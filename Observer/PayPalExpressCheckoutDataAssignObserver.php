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
        if (isset($dataAdditional['orderData'])) {
            $info->setAdditionalInformation('order_data', $dataAdditional['orderData']);
        }

        return $this;
    }
}
