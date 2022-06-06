<?php

/**
 * PayPalBR PayPal
 *
 * @package PayPalBR|PayPal
 * @author Vitor Nicchio Alves <vitor@imaginationmedia.com>
 * @copyright Copyright (c) 2021 Imagination Media (https://www.imaginationmedia.com/)
 * @license https://opensource.org/licenses/OSL-3.0.php Open Software License 3.0
 */

declare(strict_types=1);

namespace PayPalBR\PayPal\Model\Total;

use Magento\Checkout\Model\Session;
use Magento\Quote\Model\Quote;
use Magento\Quote\Api\Data\ShippingAssignmentInterface;
use Magento\Quote\Model\Quote\Address\Total;
use PayPalBR\PayPal\Helper\Installment;

class Custom extends \Magento\Quote\Model\Quote\Address\Total\AbstractTotal
{
    /**
     * @var Installment
     */
    protected $helper;

    /**
     * Custom constructor.
     *
     * @param Installment $helper
     */
    public function __construct(
        Installment $helper
    ) {
        $this->helper = $helper;
        $this->setCode('processing_fee_paypal');
    }

    /**
     * @param Quote $quote
     * @param ShippingAssignmentInterface $shippingAssignment
     * @param Total $total
     * @return $this
     */
    public function collect(
        Quote $quote,
        ShippingAssignmentInterface $shippingAssignment,
        Total $total
    ) {
        parent::collect($quote, $shippingAssignment, $total);

        $items = $shippingAssignment->getItems();
        if (!count($items)) {
            return $this;
        }

        $amount = $this->helper->getAmount();

        $total->setTotalAmount('processing_fee_paypal', $amount);
        $total->setBaseTotalAmount('processing_fee_paypal', $amount);
        $total->setCustom($amount);
        $total->setBaseCustom($amount);
        $quote->setCustom($amount);
        $quote->setBaseCustom($amount);
        $quote->setCustomDescription($this->helper->getTitle());
        $quote->setBaseGrandTotal($quote->getBaseGrandTotal() + $amount);
        $quote->setGrandTotal($quote->getGrandTotal() + $amount);
        $total->setBaseGrandTotal($total->getBaseGrandTotal());
        $total->setGrandTotal($total->getGrandTotal());

        return $this;
    }

    /**
     * @param Total $total
     */
    protected function clearValues(Total $total)
    {
        $total->setTotalAmount('subtotal', 0);
        $total->setBaseTotalAmount('subtotal', 0);
        $total->setTotalAmount('tax', 0);
        $total->setBaseTotalAmount('tax', 0);
        $total->setTotalAmount('discount_tax_compensation', 0);
        $total->setBaseTotalAmount('discount_tax_compensation', 0);
        $total->setTotalAmount('shipping_discount_tax_compensation', 0);
        $total->setBaseTotalAmount('shipping_discount_tax_compensation', 0);
        $total->setSubtotalInclTax(0);
        $total->setBaseSubtotalInclTax(0);
    }

    /**
     * @param Quote $quote
     * @param Total $total
     * @return array
     */
    public function fetch(Quote $quote, Total $total)
    {
        return [
            'code' => $this->getCode(),
            'title' => $this->getLabel(),
            'value' => $this->helper->getAmount()
        ];
    }

    /**
     * @return \Magento\Framework\Phrase
     */
    public function getLabel()
    {
        return __($this->helper->getTitle());
    }
}
