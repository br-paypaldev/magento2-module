<?php


namespace PayPalBR\PayPal\Block\Adminhtml\Sales\Order\Creditmemo;


class Totals extends \Magento\Framework\View\Element\Template
{
    /**
     * Order invoice
     *
     * @var \Magento\Sales\Model\Order\Creditmemo|null
     */
    protected $_creditmemo = null;

    /**
     * @var \Magento\Framework\DataObject
     */
    protected $_source;

    /**
     * @var \PayPalBR\PayPal\Helper\Installment
     */
    protected $_dataHelper;

    /**
     * OrderFee constructor.
     * @param \Magento\Framework\View\Element\Template\Context $context
     * @param array $data
     */
    public function __construct(
        \Magento\Framework\View\Element\Template\Context $context,
        \PayPalBR\PayPal\Helper\Installment $dataHelper,
        array $data = []
    ) {
        $this->_dataHelper = $dataHelper;
        parent::__construct($context, $data);
    }

    /**
     * Get data (totals) source model
     *
     * @return \Magento\Framework\DataObject
     */
    public function getSource()
    {
        return $this->getParentBlock()->getSource();
    }

    public function getCreditmemo()
    {
        return $this->getParentBlock()->getCreditmemo();
    }
    /**
     * Initialize payment fee totals
     *
     * @return $this
     */
    public function initTotals()
    {
        $this->getParentBlock();
        $this->getCreditmemo();
        $this->getSource();

        if(!$this->getSource()->getOrder()->getPayment()->getAdditionalInformation('paypal_custom_fee')) {
            return $this;
        }
        $fee = new \Magento\Framework\DataObject(
            [
                'code' => 'paypal_fee_invoice',
                'strong' => false,
                'value' => $this->getSource()->getOrder()->getPayment()->getAdditionalInformation('paypal_custom_fee'),
                'label' => $this->getSource()->getOrder()->getPayment()->getAdditionalInformation('base_paypal_custom_fee_description'),
            ]
        );

        $this->getParentBlock()->addTotalBefore($fee, 'grand_total');

        return $this;
    }
}
