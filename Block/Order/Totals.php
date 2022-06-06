<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace PayPalBR\PayPal\Block\Order;

use Magento\Sales\Block\Order\Totals as SalesTotals;

/**
 * Order totals.
 *
 * @api
 * @since 100.0.2
 */
class Totals extends SalesTotals
{

    /**
     * Initialize order totals array
     *
     * @return $this
     */
    protected function _initTotals()
    {
        $source = $this->getSource();

        $this->_totals = [];
        $this->_totals['subtotal'] = new \Magento\Framework\DataObject(
            ['code' => 'subtotal', 'value' => $source->getSubtotal(), 'label' => __('Subtotal')]
        );

        if ((double)$this->getOrder()->getPayment()->getAdditionalInformation('paypal_custom_fee') != 0) {
            $this->_totals['paypal_fee'] = new \Magento\Framework\DataObject(
                [
                    'code' => 'paypal_fee',
                    'field' => 'paypal_fee',
                    'value' => $this->getOrder()->getPayment()->getAdditionalInformation('paypal_custom_fee'),
                    'label' => $this->getOrder()->getPayment()->getAdditionalInformation('base_paypal_custom_fee_description'),
                ]
            );
        }


        /**
         * Add discount
         */
        if ((double)$this->getSource()->getDiscountAmount() != 0) {
            if ($this->getSource()->getDiscountDescription()) {
                $discountLabel = __('Discount (%1)', $source->getDiscountDescription());
            } else {
                $discountLabel = __('Discount');
            }
            $this->_totals['discount'] = new \Magento\Framework\DataObject(
                [
                    'code' => 'discount',
                    'field' => 'discount_amount',
                    'value' => $source->getDiscountAmount(),
                    'label' => $discountLabel,
                ]
            );
        }

        /**
         * Add shipping
         */
        if (!$source->getIsVirtual() && ((double)$source->getShippingAmount() || $source->getShippingDescription())) {
            $label = __('Shipping & Handling');
            if ($this->getSource()->getCouponCode() && !isset($this->_totals['discount'])) {
                $label = __('Shipping & Handling (%1)', $this->getSource()->getCouponCode());
            }

            $this->_totals['shipping'] = new \Magento\Framework\DataObject(
                [
                    'code' => 'shipping',
                    'field' => 'shipping_amount',
                    'value' => $this->getSource()->getShippingAmount(),
                    'label' => $label,
                ]
            );
        }

        $this->_totals['grand_total'] = new \Magento\Framework\DataObject(
            [
                'code' => 'grand_total',
                'field' => 'grand_total',
                'strong' => true,
                'value' => $source->getGrandTotal(),
                'label' => __('Grand Total'),
            ]
        );

        /**
         * Base grandtotal
         */
        if ($this->getOrder()->isCurrencyDifferent()) {
            $this->_totals['base_grandtotal'] = new \Magento\Framework\DataObject(
                [
                    'code' => 'base_grandtotal',
                    'value' => $this->getOrder()->formatBasePrice($source->getBaseGrandTotal()),
                    'label' => __('Grand Total to be Charged'),
                    'is_formated' => true,
                ]
            );
        }
        return $this;
    }
}
