<?php

namespace PayPalBR\PayPal\Block\Payment\Info;


use Magento\Payment\Block\Info;

class PayPalBCDC extends Info
{
    const TEMPLATE = 'PayPalBR_PayPal::info/paypalbcdc.phtml';

    public function _construct()
    {
        $this->setTemplate(self::TEMPLATE);
    }

    public function getTerm()
    {
        $term = $this->getInfo()->getAdditionalInformation('term');
        if ($term == '1' || !$term) {
            $term = $term . "x " . __("(In cash)");
        }else{
            $term = $term . "x";
        }

        return $term;
    }

    public function getInvoiceId()
    {
        return $this->getInfo()->getAdditionalInformation('invoice_id');
    }

    public function getLastTransId()
    {
        return $this->getInfo()->getLastTransId();
    }

    public function getStatePayPal()
    {
        $state = $this->getInfo()->getAdditionalInformation('state_payPal');

        if ($state == 'completed' || $state == 'approved') {
            $state = "<span style='color: #32dc13;'>" . __("APPROVED") . "</span>";
        }

        if ($state == 'pending') {
            $state = "<span style='color: #efef0b;'>" . __("IN ANALYSIS") . "</span>";
        }

        if ($state == 'denied') {
            $state = "<span style='color: red;'>" . __("NOT APPROVED") . "</span>";
        }

        if ($state == 'refunded') {
            $state = "<span style='color: blue;'>" . __("Refunded") . "</span>";
        }

        return $state;
    }
}
