<?php

namespace PayPalBR\PayPal\Block\Adminhtml\Sales\Order;

use Magento\Backend\Block\Template;
use Magento\Framework\Registry;
use Magento\Sales\Model\Order;

class Modal extends Template
{
    protected $registry;
    protected $order;

    public function __construct(
        Template\Context $context,
        Registry $registry,
        Order $order,
        array $data = []
    ) {
        parent::__construct($context, $data);
        $this->registry = $registry;
        $this->order = $order;
    }

    public function getOrder()
    {
        return $this->registry->registry('current_order');
    }

    /**
     * @return string
     */
    public function getTransactionLog()
    {
        $order = $this->getOrder();
        $payment = $order->getPayment();

        $jsonData = $payment->getAdditionalInformation('transaction_log') ? json_decode($payment->getAdditionalInformation('transaction_log')) : [];

        return json_encode($jsonData, JSON_PRETTY_PRINT);
    }
}
