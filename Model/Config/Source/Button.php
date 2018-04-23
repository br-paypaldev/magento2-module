<?php
namespace PayPalBR\PayPal\Model\Config\Source;

class Button implements \Magento\Framework\Option\ArrayInterface
{
    const BUTTON = 'checkout';
    const WITHPAYPAL = 'pay';
    const BUYNOW = 'buynow';

    /*
    * Option getter
    * @return array
    */
    public function toOptionArray()
    {
        $options = [
            self::BUTTON => __("Finish"),
            self::WITHPAYPAL => __("Pay with PayPal"),
            self::BUYNOW => __("Buy now")
        ];

        $ret = [];
        foreach ($options as $key => $value) {
            $ret[] = [
                "value" => $key,
                "label" => $value
            ];
        }

        return $ret;
    }
}