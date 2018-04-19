<?php

namespace PayPalBR\PayPal\Controller\Order;

use Magento\Sales\Model\Order\Email\Sender\OrderSender;

/**
 * Class PlaceOrder
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class PlaceOrder extends \Magento\Framework\App\Action\Action
{
    /**
     * @var \Magento\Checkout\Api\AgreementsValidatorInterface
     */
    protected $agreementsValidator;

    protected $orderSender;

    protected $_checkoutSession;

    protected $_quote;

    protected $quoteManagement;

    /**
     * @param \Magento\Framework\App\Action\Context $context
     * @param \Magento\Customer\Model\Session $customerSession
     * @param \Magento\Checkout\Model\Session $checkoutSession
     * @param \Magento\Sales\Model\OrderFactory $orderFactory
     * @param \Magento\Paypal\Model\Express\Checkout\Factory $checkoutFactory
     * @param \Magento\Framework\Session\Generic $paypalSession
     * @param \Magento\Framework\Url\Helper\Data $urlHelper
     * @param \Magento\Customer\Model\Url $customerUrl
     * @param \Magento\Checkout\Api\AgreementsValidatorInterface $agreementValidator
     */
    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        \Magento\Customer\Model\Session $customerSession,
        \Magento\Checkout\Model\Session $checkoutSession,
        \Magento\Sales\Model\OrderFactory $orderFactory,
        \Magento\Paypal\Model\Express\Checkout\Factory $checkoutFactory,
        \Magento\Framework\Session\Generic $paypalSession,
        \Magento\Framework\Url\Helper\Data $urlHelper,
        \Magento\Customer\Model\Url $customerUrl,
        \Magento\Checkout\Api\AgreementsValidatorInterface $agreementValidator,
        OrderSender $orderSender,
        \Magento\Quote\Api\CartManagementInterface $quoteManagement
    ) {
        $this->agreementsValidator = $agreementValidator;
        $this->orderSender = $orderSender;
        $this->_quote = $checkoutSession->getQuote();
        $this->_checkoutSession = $checkoutSession;
        $this->quoteManagement = $quoteManagement;
        $this->_customerSession = $customerSession;
        parent::__construct($context);
    }

    /**
     * Submit the order
     *
     * @return void
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function execute()
    {
        try {


            $teste = $this->_customerSession->getCustomer()->getAddresses();

            $this->_eventManager->dispatch(
                'paypalbr_express_place_order_before',
                [
                    'quote' => $this->_quote
                ]
            );

            // $this->_initCheckout();
            $this->place();

            // prepare session to success or cancellation page
            $this->_checkoutSession->clearHelperData();

            // "last successful quote"
            $quoteId = $this->_quote->getId();
            $this->_checkoutSession->setLastQuoteId($quoteId)->setLastSuccessQuoteId($quoteId);

            // an order may be created
            $order = $this->_order;

            if ($order) {
                $this->_checkoutSession->setLastOrderId($order->getId())
                    ->setLastRealOrderId($order->getIncrementId())
                    ->setLastOrderStatus($order->getStatus());
            }

            $this->deleteQuoteItems();
            $this->_quote->removeAllItems();


            $this->_eventManager->dispatch(
                'paypalbr_express_place_order_success',
                [
                    'order' => $order,
                    'quote' => $this->_quote
                ]
            );

            $this->_redirect('checkout/onepage/success');

            return;
        } catch (\Exception $e) {
            $this->messageManager->addExceptionMessage(
                $e,
                __('We can\'t place the order.')
            );
            $this->_redirect('expresscheckout/revieworder');

            return;
        }
    }

    /**
     * Place the order when customer returned from PayPal until this moment all quote data must be valid.
     *
     * @param string $token
     * @param string|null $shippingMethodCode
     * @return void
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     * @SuppressWarnings(PHPMD.NPathComplexity)
     */
    public function place()
    {
        $this->_quote->collectTotals();
        $order = $this->quoteManagement->submit($this->_quote);

        if (!$order) {
            return;
        }

        switch ($order->getState()) {
            case \Magento\Sales\Model\Order::STATE_PENDING_PAYMENT:
            case \Magento\Sales\Model\Order::STATE_PROCESSING:
            case \Magento\Sales\Model\Order::STATE_COMPLETE:
            case \Magento\Sales\Model\Order::STATE_PAYMENT_REVIEW:
                $this->orderSender->send($order);
                $this->_checkoutSession->start();
                break;
            default:
                break;
        }

        $this->_order = $order;
    }

    protected function deleteQuoteItems(){

        $allItems = $this->_checkoutSession->getQuote()->getAllVisibleItems();//returns all teh items in session
        foreach ($allItems as $item) {
            $itemId = $item->getItemId();//item id of particular item
            $quoteItem=$this->getItemModel()->load($itemId);//load particular item which you want to delete by his item id
            $quoteItem->delete();//deletes the item
        }
    }

    protected function getItemModel(){
        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();//instance of object manager
        $itemModel = $objectManager->create('Magento\Quote\Model\Quote\Item');//Quote item model to load quote item
        return $itemModel;
    }

}
