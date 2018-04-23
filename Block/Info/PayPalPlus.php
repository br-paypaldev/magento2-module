<?php

namespace PayPalBR\PayPal\Block\Info;

use Magento\Payment\Block\Info;
use Magento\Framework\DataObject;

class PayPalPlus extends Info
{
    const TEMPLATE = 'PayPalBR_PayPal::info/paypal-plus.html';

    public function _construct()
    {
        $this->setTemplate(self::TEMPLATE);
    }

    /**
     * {@inheritdoc}
     */
    protected function _prepareSpecificInformation($transport = null)
    {
        $transport = new DataObject([
            (string)__('Title') => $this->getTitle()
        ]);

        $transport = parent::_prepareSpecificInformation($transport);
        return $transport;
    }

    public function getTitle()
    {
        return $this->getInfo()->getAdditionalInformation('method_title');
    }
}