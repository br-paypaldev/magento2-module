<?php
namespace PayPalBR\PayPal\Model\Config\Source;

class Mode implements \Magento\Framework\Option\ArrayInterface
{
    const SANDBOX = 1;
    const PRODUCTION = 2;

    /*
    * Option getter
    * @return array
    */
    public function toOptionArray()
    {
        $options = [
            self::SANDBOX => __("Sandbox"),
            self::PRODUCTION => __("Production")
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