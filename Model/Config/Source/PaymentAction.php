<?php

namespace PayPalBR\PayPal\Model\Config\Source;

class PaymentAction implements \Magento\Framework\Option\ArrayInterface
{
	/*
	* Option getter
	* @return array
	*/
	public function toOptionArray()
	{
		$options = [
			"1" => __("Authorize and Capture")
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