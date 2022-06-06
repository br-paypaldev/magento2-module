<?php


namespace PayPalBR\PayPal\Model\Invoice\Total;

use Magento\Sales\Model\Order\Invoice\Total\AbstractTotal;

class Fee extends AbstractTotal
{
    /**
     * @param \Magento\Sales\Model\Order\Invoice $invoice
     * @return $this
     */
    public function collect(\Magento\Sales\Model\Order\Invoice $invoice)
    {
        $amount = $invoice->getOrder()->getPayment()->getAdditionalInformation('paypal_custom_fee');
        $invoice->setFee($amount);
        $amount = $invoice->getOrder()->getPayment()->getAdditionalInformation('base_paypal_custom_fee');
        $invoice->setBaseFee($amount);

        $invoice->setGrandTotal($invoice->getGrandTotal() + $invoice->getFee());
        $invoice->setBaseGrandTotal($invoice->getBaseGrandTotal() + $invoice->getFee());

        return $this;
    }
}
