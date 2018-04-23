<?php

namespace PayPalBR\PayPal\Model\Config\Source;

class Color implements \Magento\Framework\Option\ArrayInterface {

    const GOLD = 'gold';
    const BLUE = 'blue';
    const SILVER = 'silver';
    const BLACK = 'black';

    /*
     * Option getter
     * @return array
     */

    public function toOptionArray() {
        $options = [
            self::BLUE => __("Blue"),
            self::GOLD => __("Gold"),
            self::SILVER => __("Silver"),
            self::BLACK => __("Black")
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
