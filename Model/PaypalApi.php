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
use PayPal\Api\RedirectUrls;
use PayPal\Api\ShippingAddress;
use Psr\Log\LoggerInterface;

class PaypalApi
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
     * Contains checkout session
     *
     * @var \Magento\Checkout\Model\Session
     */
    protected $checkoutSession;

    /**
     * @var \PayPalBR\PayPal\Model\PayPalRequests
     */
    protected $paypalRequests;

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

    protected $shippingPreference;

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
        \PayPalBR\PayPal\Model\PayPalRequests $payPalRequests,
        LoggerInterface $logger
    ) {
        $this->cart = $cart;
        $this->customerSession = $customerSession;
        $this->storeManager = $storeManager;
        $this->checkoutSession = $checkoutSession;
        $this->logger = $logger;
        /** @var Quote $quote */
        $quote = $cart->getQuote();
        $this->paypalRequests = $payPalRequests;
        $this->cartSalesModelQuote = $cartSalesModelFactory->create($quote);
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

    protected function createAndGetPayment()
    {
        $quote = $this->checkoutSession->getQuote();

        $amountTotal = $quote->getBaseGrandTotal();
        $amountItemsWithDiscount = $quote->getBaseSubtotalWithDiscount();
        $ship = $quote->getShippingAddress()->getBaseShippingAmount();
        $creditStore = $quote->getData('customer_balance_amount_used');
        $tax = $quote->getShippingAddress()->getBaseTaxAmount();
        $rewards = $quote->getData('reward_currency_amount');
        $giftCardAmount = $quote->getData('base_gift_cards_amount_used');

        $totalSum = $amountItemsWithDiscount + $ship + $tax - $creditStore - $rewards - $giftCardAmount;
        $amountTotal = (float)$amountTotal;
        $totalSum = (float)$totalSum;

        if (strval($amountTotal) != strval($totalSum)) {
            throw new \Exception("Discrepancy found in the total order amount.");
        }

        // $apiContext = $this->getApiContext();
        $applicationContext = $this->getApplicationContextValues();

        $applicationContext = [
            'brand_name' => $this->storeManager->getStore()->getName(),
            'cancel_url' => $this->storeManager->getStore()->getBaseUrl() . 'cancel',
            'locale' => 'pt-BR',//$this->storeManager->getStore()->getLocaleCode(),
            'return_url' => $this->storeManager->getStore()->getBaseUrl() . 'redirect.html',
            'shipping_preference' => 'SET_PROVIDED_ADDRESS'
        ];

        $items = [];
        $itemsTotal = 0;

        foreach ($quote->getAllVisibleItems() as $item) {
            $items[] = [
                'category' => 'PHYSICAL_GOODS',
                'description' => $item->getDescription(),
                'name' => $item->getName(),
                'quantity' => $item->getQty(),
                'sku' => $item->getSku(),
                'unit_amount' => ['currency_code' => $quote->getQuoteCurrencyCode(), 'value' => number_format( (float) $item->getPrice(), 2, '.', '')],
            ];
            $itemsTotal = $itemsTotal + ($item->getQty() * $item->getPrice());
        }

        $totals = $quote->getTotals();

        $amount = [
            'currency_code' => $quote->getQuoteCurrencyCode(),
            'value' => number_format((float) $totals['grand_total']->getValue(), 2, '.', ''),
            'breakdown' => [
                'discount' => ['currency_code' => $quote->getQuoteCurrencyCode(), 'value' => isset($totals['discount']) ? number_format((float) $totals['discount']->getValue(), 2, '.', '') : '0.00'],
                'item_total' => ['currency_code' => $quote->getQuoteCurrencyCode(), 'value' => number_format((float) $totals['subtotal']->getValue(), 2, '.', '')],
                'shipping' => ['currency_code' => $quote->getQuoteCurrencyCode(), 'value' => number_format((float) $quote->getShippingAddress()->getBaseShippingAmount(), 2, '.', '')],
                'shipping_discount' => ['currency_code' => $quote->getQuoteCurrencyCode(), 'value' => '0.00']
            ]
        ];

        $paymentSource = [
            'paypal' => [
                'name' => [
                    'given_name' => $quote->getBillingAddress()->getFirstname(),
                    'surname' => $quote->getBillingAddress()->getLastname()
                ],
                'email_address' => $quote->getBillingAddress()->getEmail(),
                'phone' => [
                    'phone_number' => [
                        'national_number' => $quote->getBillingAddress()->getTelephone()
                    ]
                ],
                'address' => [
                    'address_line_1' => $quote->getBillingAddress()->getStreet()[0] ?? '',
                    'address_line_2' => $quote->getBillingAddress()->getStreet()[1] ?? '',
                    'admin_area_1' => $quote->getBillingAddress()->getRegionCode() ?? '',
                    'admin_area_2' => $quote->getBillingAddress()->getRegion() ?? '',
                    'postal_code' => $quote->getBillingAddress()->getPostcode() ?? '',
                    'country_code' => $quote->getBillingAddress()->getCountryId() ?? ''
                ]
            ]
        ];

        if ($quote->getBillingAddress()->getVatId()) {
            $paymentSource['paypal']['tax_info']['tax_id'] = $quote->getBillingAddress()->getVatId();
            $paymentSource['paypal']['tax_info']['tax_id_type'] = $this->validateTaxVat($quote->getBillingAddress()->getVatId());
        }

        $purchaseUnits = [
            [
                'amount' => $amount,
                'description' => 'Creating a payment',
                'items' => $items,
                'invoice_id' => $quote->getId(),
                'shipping' => [
                    'address' => [
                        'address_line_1' => $quote->getShippingAddress()->getStreet()[0] ?? '',
                        'address_line_2' => $quote->getShippingAddress()->getStreet()[1] ?? '',
                        'admin_area_1' => $quote->getShippingAddress()->getRegionCode() ?? '',
                        'admin_area_2' => $quote->getShippingAddress()->getRegion() ?? '',
                        'postal_code' => $quote->getShippingAddress()->getPostcode() ?? '',
                        'country_code' => $quote->getShippingAddress()->getCountryId() ?? ''
                    ],
                ],
                'soft_descriptor' => str_replace(' ', '', $this->storeManager->getStore()->getName())
            ]
        ];

        // Criando o array final
        $data = [
            'application_context' => $applicationContext,
            'intent' => 'CAPTURE',
            'payment_source' => $paymentSource,
            'purchase_units' => $purchaseUnits
        ];

        $data = json_encode($data);

        $result = $this->paypalRequests->createOrder($data);

        $quoteUpdatedAt = $quote->getUpdatedAt();
        $this->checkoutSession->setAmountTotal($amountTotal);
        $this->checkoutSession->setPaypalPaymentId($result->id);
        $this->checkoutSession->setQuoteUpdatedAt($quoteUpdatedAt);

        return $result;
    }

    public function createOrder()
    {
        try {
            $paypalPayment = $this->createAndGetPayment();

            $result = [
                'status' => 'success',
                'message' => get_object_vars($paypalPayment)
            ];
        } catch (\Exception $e) {
            $result = [
                'status' => 'error',
                'message' => $e->getMessage()
            ];
        }
        return $result;
    }

    public function validateTaxVat($document)
    {
        $document = preg_replace('/[^0-9]/', '', $document);

        $size = strlen($document);

        if ($size === 14) {
            return "BR_CNPJ";
        }

        return "BR_CPF";
    }
}
