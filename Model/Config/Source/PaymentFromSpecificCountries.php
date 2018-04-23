<?php

namespace PayPalBR\PayPal\Model\Config\Source;

class PaymentFromSpecificCountries implements \Magento\Framework\Option\ArrayInterface
{
	/*
	* Option getter
	* @return array
	*/
	public function toOptionArray()
	{
		$options = [
			"1" => __("All Countries")
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