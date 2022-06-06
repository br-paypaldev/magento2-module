<?php

namespace PayPalBR\PayPal\Model\Creditmemo\Total;

use Magento\Sales\Model\Order\Creditmemo\Total\AbstractTotal;

class Fee extends AbstractTotal
{
    /**
     * @param \Magento\Sales\Model\Order\Creditmemo $creditmemo
     * @return $this
     */
    public function collect(\Magento\Sales\Model\Order\Creditmemo $creditmemo)
    {
        $amount = $creditmemo->getOrder()->getPayment()->getAdditionalInformation('paypal_custom_fee');
        $creditmemo->setFee($amount);

        $amount = $creditmemo->getOrder()->getPayment()->getAdditionalInformation('base_paypal_custom_fee');
        $creditmemo->setBaseFee($amount);

        $creditmemo->setGrandTotal($creditmemo->getGrandTotal() + $creditmemo->getFee());
        $creditmemo->setBaseGrandTotal($creditmemo->getBaseGrandTotal() + $creditmemo->getBaseFee());

        return $this;
    }
}
