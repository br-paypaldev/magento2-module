<?php

/**
 * PayPalBR PayPal
 *
 * @package PayPalBR|PayPal
 * @author Vitor Nicchio Alves <vitor@imaginationmedia.com>
 * @copyright Copyright (c) 2020 Imagination Media (https://www.imaginationmedia.com/)
 * @license https://opensource.org/licenses/OSL-3.0.php Open Software License 3.0
 */

declare(strict_types=1);

namespace PayPalBR\PayPal\Helper;

use Magento\Framework\App\Helper\AbstractHelper;

class Installment extends AbstractHelper
{
    protected $scopeConfig;

    /**
     * Contains checkout session
     *
     * @var \Magento\Checkout\Model\Session
     */
    protected $checkoutSession;

    private $feeValue;

    public function __construct(
        \Magento\Framework\App\Helper\Context $context,
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Magento\Checkout\Model\Session $checkoutSession
    ) {
        parent::__construct($context);
        $this->scopeConfig = $scopeConfig;
        $this->checkoutSession = $checkoutSession;
    }

    public function getInstallmentPrice(float $tax, int $installment)
    {
        $amountTotal = $this->getOrderTotal();

        if ($installment == 1 && $this->hasDiscount()) {
            $discount = $this->scopeConfig->getValue('payment/paypalbr_paypalplus/cost_to_buyer_discount');
            return number_format(round(($amountTotal - ($amountTotal * $discount / 100)), 2), 2, '.', '');
        }
        return number_format(round(($amountTotal + ($amountTotal * $tax / 100)) / $installment, 2), 2, '.', '');
    }

    public function getFinalTotal(float $tax, int $installment)
    {
        $amountTotal = $this->getOrderTotal();

        if ($installment == 1 && $this->hasDiscount()) {
            $discount = $this->scopeConfig->getValue('payment/paypalbr_paypalplus/cost_to_buyer_discount');
            return number_format(round(($amountTotal - ($amountTotal * $discount / 100)), 2), 2, '.', '');
        }
        return number_format(round(($amountTotal + ($amountTotal * $tax / 100)), 2), 2, '.', '');
    }

    public function hasDiscount()
    {
        $hasDiscount = $this->scopeConfig->getValue('payment/paypalbr_paypalplus/cost_to_buyer_has_discount');
        return $hasDiscount;
    }

    public function getDiscountValue()
    {
        $amountTotal = $this->getOrderTotal();

        $discount = $this->scopeConfig->getValue('payment/paypalbr_paypalplus/cost_to_buyer_discount');
        return number_format(round(($amountTotal * $discount / 100), 2), 2, '.', '');
    }

    public function getCostValue(string $qtyInstallment)
    {
        $amountTotal = $this->getOrderTotal();

        if ($this->hasDiscount() && $qtyInstallment == 1) {
            $discount = $this->scopeConfig->getValue('payment/paypalbr_paypalplus/cost_to_buyer_discount');
            return round(($amountTotal * $discount / 100) * - 1, 2);
        }

        $percent = $this->scopeConfig->getValue('payment/paypalbr_paypalplus/cost_to_buyer_installment_'
            . $qtyInstallment);

        return round(($amountTotal * $percent / 100), 2);
    }

    /**
     * @return string
     */
    public function getTitle()
    {
        $label = $this->scopeConfig->getValue('payment/paypalbr_paypalplus/cost_to_buyer_installment_text');

        if ($label != "") {
            return $label;
        }

        if ($this->checkoutSession->getFeeValue() < 0) {
            return 'Desconto PayPal';
        }
        return 'Juros PayPal';
    }

    /**
     * @return float
     */
    public function getAmount()
    {
        if ($this->checkoutSession->getFeeValue() == null) {
            return 0;
        }
        return $this->checkoutSession->getFeeValue();
    }

    public function getOrderTotal() {

        $quote = $this->checkoutSession->getQuote();

        $amountItemsWithDiscount = $quote->getBaseSubtotalWithDiscount();
        $ship = $quote->getShippingAddress()->getBaseShippingAmount();
        $tax = $quote->getShippingAddress()->getBaseTaxAmount();

        return $amountItemsWithDiscount + $ship + $tax;
    }
}
