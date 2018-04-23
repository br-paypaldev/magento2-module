<?php
namespace PayPalBR\PayPal\Model\Config\Source;

class Shape implements \Magento\Framework\Option\ArrayInterface
{
    const PILL = 'pill';
    const RECT = 'rect';    

    /*
    * Option getter
    * @return array
    */
    public function toOptionArray()
    {
        $options = [
            self::RECT => __("Rectangular"),
            self::PILL => __("Rounded")            
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