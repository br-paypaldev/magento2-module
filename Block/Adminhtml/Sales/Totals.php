<?php

namespace PayPalBR\PayPal\Block\Adminhtml\Sales;

class Totals extends \Magento\Framework\View\Element\Template
{
    public function __construct(
        \Magento\Framework\View\Element\Template\Context $context,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    /**
     * Retrieve current order model instance
     *
     * @return \Magento\Sales\Model\Order
     */
    public function getOrder()
    {
        return $this->getParentBlock()->getOrder();
    }

    /**
     * @return mixed
     */
    public function getSource()
    {
        return $this->getParentBlock()->getSource();
    }

    /**
     *
     *
     * @return $this
     */
    public function initTotals()
    {
        $this->getParentBlock();
        $this->getOrder();

        if(!$this->getOrder()->getPayment()->getAdditionalInformation('paypal_custom_fee')) {
            return $this;
        }

        $total = new \Magento\Framework\DataObject(
            [
                'code' => 'paypal_fee',
                'value' => $this->getOrder()->getPayment()->getAdditionalInformation('paypal_custom_fee'),
                'label' => $this->getOrder()->getPayment()->getAdditionalInformation('base_paypal_custom_fee_description'),
            ]
        );
        $this->getParentBlock()->addTotalBefore($total, 'grand_total');

        return $this;
    }
}
