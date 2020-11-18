<?php
/**
 * PayPalBR_PayPal
 *
 * PayPal BR extension
 *
 * @package PayPalBR\PayPal
 * @author Caian Monteiro Meireles <caian@imaginationmedia.com>
 * @copyright Copyright (c) 2020 Imagination Media (https://www.imaginationmedia.com/)
 * @license https://opensource.org/licenses/OSL-3.0.php Open Software License 3.0
 */

namespace PayPalBR\PayPal\Observer;

use Magento\Framework\Event\Observer as EventObserver;
use Magento\Framework\Event\ObserverInterface;
use Magento\Sales\Model\Order;

class RefundStatus implements ObserverInterface
{
    /**
     * @param EventObserver $observer
     * @return void
     */
    public function execute(EventObserver $observer)
    {

        $event = $observer->getEvent();
        $creditMemo = $observer->getCreditmemo();

        if(!empty($creditMemo->getData('customer_balance_amount'))) {
            $order  = $creditMemo->getOrder();
            $order->setData('state', Order::STATE_CLOSED);
            $order->setStatus('devolvido_parcialmente');
            $order->save();
        }

        return $this;
    }
}
