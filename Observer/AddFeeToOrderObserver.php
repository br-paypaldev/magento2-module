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

namespace PayPalBR\PayPal\Observer;

use Magento\Framework\Event\Observer as EventObserver;
use Magento\Framework\Event\ObserverInterface;

class AddFeeToOrderObserver implements ObserverInterface
{
    /**
     * Set payment fee to order
     *
     * @param EventObserver $observer
     * @return $this
     */
    public function execute(\Magento\Framework\Event\Observer $observer)
    {
        $quote = $observer->getQuote();
        $CustomFeeFee = $quote->getCustom();
        $CustomFeeBaseFee = $quote->getBaseCustom();
        if (!$CustomFeeFee || !$CustomFeeBaseFee) {
            return $this;
        }
        //Set fee data to order
        $order = $observer->getOrder();
        $order->setTotalPaid($order->getGrandTotal());
        $order->setBaseTotalPaid($order->getBaseGrandTotal());
        $order->getPayment()->setAdditionalInformation('paypal_custom_fee', $CustomFeeFee);
        $order->getPayment()->setAdditionalInformation('base_paypal_custom_fee', $CustomFeeFee);
        $order->getPayment()->setAdditionalInformation('base_paypal_custom_fee_description', $quote->getCustomDescription());
        $order->save();

        return $this;
    }
}
