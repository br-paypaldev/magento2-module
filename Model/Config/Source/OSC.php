<?php

namespace PayPalBR\PayPal\Model\Config\Source;

class OSC implements \Magento\Framework\Option\ArrayInterface
{
    /**
     * Options getter
     *
     * @return array
     */
    public function toOptionArray()
    {
        return [['value' => 0, 'label' => __('No')], ['value' => 1, 'label' => __('Firecheckout')]];
    }
}
