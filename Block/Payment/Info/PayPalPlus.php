<?php

namespace PayPalBR\PayPal\Block\Payment\Info;


use Magento\Payment\Block\Info;
use Magento\Framework\DataObject;

class PayPalPlus extends Info
{
    const TEMPLATE = 'PayPalBR_PayPal::info/paypalplus.phtml';

    public function _construct()
    {
        $this->setTemplate(self::TEMPLATE);
    }

    public function getTitle()
    {
        return $this->getInfo()->getAdditionalInformation('method_title');
    }

    public function getPayId()
    {
        return $this->getInfo()->getAdditionalInformation('pay_id');
    }

    public function getPayerId()
    {
        return $this->getInfo()->getAdditionalInformation('payer_id');
    }

    public function getLastTransId()
    {
        return $this->getInfo()->getLastTransId();
    }

    public function getToken()
    {
        return $this->getInfo()->getAdditionalInformation('token');
    }

    public function getTerm()
    {
        $term = $this->getInfo()->getAdditionalInformation('term');
        if ($term == '1') {
            $term = $term . "x " . __("(In cash)");
        }else{
            $term = $term . "x";
        }

        return $term;
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