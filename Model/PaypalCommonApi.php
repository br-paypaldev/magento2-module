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

namespace PayPalBR\PayPal\Model;

use Magento\Checkout\Model\Cart;
use Magento\Customer\Model\Session;
use Magento\Framework\App\ObjectManager;
use Magento\Framework\UrlInterface;
use Magento\Payment\Model\Cart\SalesModel\Factory;
use Magento\Quote\Model\Quote;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;
use PayPal\Api\Amount;
use PayPal\Api\Details;
use PayPal\Api\Item;
use PayPal\Api\ItemList;
use PayPal\Api\MerchantPreferences;
use PayPal\Api\Patch;
use PayPal\Api\PatchRequest;
use PayPal\Api\Payment;
use PayPal\Api\PaymentOptions;
use PayPal\Api\RedirectUrls;
use PayPal\Api\ShippingAddress;
use PayPal\Api\Transaction;
use Psr\Log\LoggerInterface;

abstract class PaypalCommonApi
{
    const NO_SHIPPING = 'NO_SHIPPING';

    const SET_PROVIDED_ADDRESS = 'SET_PROVIDED_ADDRESS';

    /**
     * Contains the cart of current session
     *
     * @var Cart
     */
    protected $cart;

    /**
     * Contains the current customer session
     *
     * @var Session
     */
    protected $customerSession;

    /**
     * Contains the quote object for payment
     *
     * @var \Magento\Payment\Model\Cart\SalesModel\Quote
     */
    protected $cartSalesModelQuote;

    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * Contains the store manager of Magento
     *
     * @var StoreManagerInterface
     */
    protected $storeManager;

    /**
     * PaypalPlusApi constructor.
     *
     * @param Cart $cart
     * @param Session $customerSession
     * @param StoreManagerInterface $storeManager
     * @param Factory $cartSalesModelFactory
     * @param \Magento\Checkout\Model\Session $checkoutSession
     * @param LoggerInterface $logger
     */
    public function __construct(
        Cart $cart,
        Session $customerSession,
        StoreManagerInterface $storeManager,
        Factory $cartSalesModelFactory,
        \Magento\Checkout\Model\Session $checkoutSession,
        LoggerInterface $logger
    ) {
        $this->cart = $cart;
        $this->customerSession = $customerSession;
        $this->storeManager = $storeManager;
        $this->checkoutSession = $checkoutSession;
        $this->logger = $logger;
        /** @var Quote $quote */
        $quote = $cart->getQuote();
        $this->cartSalesModelQuote = $cartSalesModelFactory->create($quote);
    }

    abstract protected function getApiContext();

    /**
     * Returns the items in the cart
     *
     * @return ItemList
     */
    protected function getItemList()
    {
        /** @var Quote $quote */
        $quote = $this->cart->getQuote();

        /** @var string $storeCurrency */
        $storeCurrency = $quote->getBaseCurrencyCode();

        $itemList = new ItemList();
        $cartItems = $quote->getItems();
        foreach ($cartItems as $cartItem) {
            $this->checkProductType($cartItem->getProductType());

            $item = new Item();
            $item->setName($cartItem->getName())
                ->setDescription($cartItem->getDescription())
                ->setQuantity($cartItem->getQty())
                ->setPrice($cartItem->getPrice())
                ->setSku($cartItem->getSku())
                ->setCurrency($storeCurrency);
            $itemList->addItem($item);
        }

        if ($this->cartSalesModelQuote->getBaseDiscountAmount() !== '0.0000') {
            $item = new Item();
            $item->setName('Discount')
                ->setDescription('Discount')
                ->setQuantity('1')
                ->setPrice($this->cartSalesModelQuote->getBaseDiscountAmount())
                ->setSku('discountloja')
                ->setCurrency($storeCurrency);
            $itemList->addItem($item);
        }

        if (!$quote->isVirtual()) {
            $shippingAddress = $this->getShippingAddress();
            $itemList->setShippingAddress($shippingAddress);
        }

        return $itemList;
    }

    protected function checkProductType($productType)
    {
        if (($productType == 'downloadable') || ($productType == 'virtual')) {
            $this->shippingPreference = self::NO_SHIPPING;
        }
        if (($productType != 'downloadable') && ($productType != 'virtual')) {
            $this->shippingPreference = self::SET_PROVIDED_ADDRESS;
        }
    }

    /**
     * Returns shipping addresss for PayPalPlus
     *
     * @return ShippingAddress
     */
    protected function getShippingAddress()
    {
        /** @var Quote $quote */
        $quote = $this->cart->getQuote();
        $cartShippingAddress = $quote->getShippingAddress();
        $customer = $this->customerSession->getCustomer();

        $objectManager = ObjectManager::getInstance();
        $billingID =  $this->customerSession->getCustomer()->getDefaultBilling();
        $billing = $objectManager->create('Magento\Customer\Model\Address')->load($billingID);
        $shippingAddress = new ShippingAddress();
        if ($quote->isVirtual() == true) {
            $shippingAddress
                ->setRecipientName($customer->getName())
                ->setLine1($billing->getStreetLine(1))
                ->setLine2($billing->getStreetLine(2))
                ->setCity($billing->getCity())
                ->setCountryCode($billing->getCountryId())
                ->setPostalCode($billing->getPostcode())
                ->setPhone($billing->getTelephone())
                ->setState($billing->getRegion());
        } else {
            $shippingAddress
                ->setRecipientName($customer->getName())
                ->setLine1($cartShippingAddress->getStreetLine(1))
                ->setLine2($cartShippingAddress->getStreetLine(2))
                ->setCity($cartShippingAddress->getCity())
                ->setCountryCode($cartShippingAddress->getCountryId())
                ->setPostalCode($cartShippingAddress->getPostcode())
                ->setPhone($cartShippingAddress->getTelephone())
                ->setState($cartShippingAddress->getRegion());
        }


        return $shippingAddress;
    }

    /**
     * Returns the payer
     *
     * @return \PayPal\Api\Payer
     */
    protected function getPayer()
    {
        $payer = new \PayPal\Api\Payer();
        $payer->setPaymentMethod('paypal');
        return $payer;
    }

    /**
     * Returns redirect urls
     *
     * These URLs are defined in the Brasil project
     *
     * @return RedirectUrls
     */
    protected function getRedirectUrls()
    {
        /** @var Store $store */
        $store = $this->storeManager->getStore();

        $redirectUrls = new RedirectUrls();
        $redirectUrls
            ->setReturnUrl($store->getUrl('checkout/cart'))
            ->setCancelUrl($store->getUrl('checkout/cart'));
        return $redirectUrls;
    }

    /**
     * Returns details for PayPal Plus API
     *
     * @return Details
     */
    protected function getDetails()
    {
        /** @var Quote $quote */
        $quote = $this->cart->getQuote();

        /**
         * If subtotal + shipping + tax not equals grand total,
         * then a disscount might be applying, get subtotal with disscount then.
         */
        $baseSubtotal = $this->cartSalesModelQuote->getBaseSubtotal();

        if ($this->cartSalesModelQuote->getBaseDiscountAmount()) {
            $subtotal = $baseSubtotal + $this->cartSalesModelQuote->getBaseDiscountAmount();
        } else {
            $subtotal = $baseSubtotal;
        }

        $details = new Details();
        $details->setShipping($this->cartSalesModelQuote->getBaseShippingAmount())
            ->setSubtotal($subtotal);

        if ($this->cartSalesModelQuote->getBaseDiscountAmount() !== '0.0000') {
            $details->setShippingDiscount('-0.0000');
        }

        return $details;
    }

    /**
     * Returns amount PayPal Plus API
     *
     * @return Amount
     */
    protected function getAmount()
    {
        /** @var Quote $quote */
        $quote = $this->cart->getQuote();
        $storeCurrency = $quote->getBaseCurrencyCode();
        $grandTotal = $quote->getGrandTotal();
        $details = $this->getDetails();

        $amount = new Amount();
        $amount->setCurrency($storeCurrency);
        $amount->setTotal($grandTotal);
        $amount->setDetails($details);

        return $amount;
    }

    /**
     * Return transaction object for PayPalPlus API
     *
     * @return Transaction
     */
    protected function getTransaction()
    {
        $amount = $this->getAmount();
        $itemList = $this->getItemList();

        $paymentOptions = new PaymentOptions();
        $paymentOptions->setAllowedPaymentMethod("IMMEDIATE_PAY");

        $transaction = new Transaction();
        $transaction->setDescription("Creating a payment");
        $transaction->setAmount($amount);
        $transaction->setItemList($itemList);
        $transaction->setPaymentOptions($paymentOptions);
        // $transaction->setNotifyUrl($this->getMerchantPreferences());

        return $transaction;
    }

    /**
     * Return transaction object for PayPalPlus API
     *
     * @return Transaction
     */
    protected function getMerchantPreferences()
    {
        /** @var Store $store */
        $store = $this->storeManager->getStore();
        $merchant = new MerchantPreferences();
        $merchant->setNotifyUrl($store->getUrl('V1/notifications/notificationUrl'));

        return $merchant;
    }

    /**
     * Creates Application Context Values to create and get payment
     *
     * @return $applicationContext
     */
    protected function getApplicationContextValues()
    {

        $baseUrl = $this->storeManager->getStore()->getBaseUrl(UrlInterface::URL_TYPE_WEB);

        $applicationContext = array(
            'locale' => 'pt-BR',
            'brand_name' => $baseUrl,
            'shipping_preference' => $this->shippingPreference
        );

        return $applicationContext;
    }

    /**
     * Checks if the payment has already been created and stored in the session
     * before.
     *
     * @return bool
     */
    protected function isPaymentCreated()
    {
        $paypalPaymentId = $this->checkoutSession->getPaypalPaymentId();

        return ! empty($paypalPaymentId);
    }

    /**
     * The opposite of self::isPaymentCreated()
     *
     * @return bool
     */
    protected function isNotPaymentCreated()
    {
        return ! $this->isPaymentCreated();
    }

    /**
     * Checks if the quote has been changed during this session
     *
     * @return bool
     */
    protected function isQuoteChanged()
    {
        $quote = $this->checkoutSession->getQuote();
        $lastQuoteUpdatedAt = $quote->getUpdatedAt();
        $sessionQuoteUpdatedAt = $this->checkoutSession->getQuoteUpdatedAt();
        return new \DateTime($lastQuoteUpdatedAt) > new \DateTime($sessionQuoteUpdatedAt);
    }

    /**
     * Send a patch and returns the payment
     *
     * @return Payment
     */
    protected function patchAndGetPayment()
    {
        $apiContext = $this->getApiContext();
        $paypalPayment = $this->restoreAndGetPayment();
        $patchRequest = new PatchRequest();

        // Change item list
        $itemListPatch = new Patch();
        $itemListPatch
            ->setOp('replace')
            ->setPath('/transactions/0/item_list')
            ->setValue($this->getItemList());
        $patchRequest->addPatch($itemListPatch);

        // Change amount
        $amountPatch = new Patch();
        $amountPatch
            ->setOp('replace')
            ->setPath('/transactions/0/amount')
            ->setValue($this->getAmount());
        $patchRequest->addPatch($amountPatch);
        $paypalPayment->update($patchRequest, $apiContext);

        // Load the payment after patch
        $paypalPayment = $this->restoreAndGetPayment();
        return $paypalPayment;
    }

    /**
     * Restores the payment from session and returns it
     *
     * @return Payment
     */
    protected function restoreAndGetPayment()
    {
        $payment = new Payment();
        $paypalPaymentId = $this->checkoutSession->getPaypalPaymentId();
        $apiContext = $this->getApiContext();

        $paypalPayment = Payment::get($paypalPaymentId, $apiContext);
        return $paypalPayment;
    }
}
