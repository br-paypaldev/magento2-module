<?php
namespace PayPalBR\PayPal\Model;

/**
 * Class PaypalPlusApi
 *
 * @package PayPalBR\PayPalPlus\Model
 */
class PaypalPlusApi
{

    const NO_SHIPPING = 'NO_SHIPPING';

    const SET_PROVIDED_ADDRESS = 'SET_PROVIDED_ADDRESS';

    /**
     * Contains the cart of current session
     *
    * @var \Magento\Checkout\Model\Cart
    */
    protected $cart;

    /**
     * Contains the current customer session
     *
     * @var \Magento\Customer\Model\Session
     */
    protected $customerSession;

    /**
     * Contains the store manager of Magento
     *
     * @var \Magento\Store\Model\StoreManagerInterface
     */
    protected $storeManager;

    /**
     * Contains the config provider for Magento 2 back-end configurations
     *
     * @var ConfigProvider
     */
    protected $configProvider;

    /**
     * Contains the quote object for payment
     *
     * @var \Magento\Payment\Model\Cart\SalesModel\Quote
     */
    protected $cartSalesModelQuote;

    /**
     * Contains the config ID to be used in PayPal API
     *
     * @var string
     */
    protected $configId;

    /**
     * Contains the secret ID to be used in PayPal API
     *
     * @var string
     */
    protected $secretId;

    /**
     * Contains checkout session
     *
     * @var \Magento\Checkout\Model\Session
     */
    protected $checkoutSession;

    /**
     * @var \Psr\Log\LoggerInterface
     */
    protected $logger;

    /**
     * @var
     */
    protected $shippingPreference;

    /**
     * PaypalPlusApi constructor.
     *
     * @param \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig
     * @param \Magento\Checkout\Model\Cart $cart
     * @param \Magento\Customer\Model\Session $customerSession
     * @param \Magento\Store\Model\StoreManagerInterface $storeManager
     */
    public function __construct(
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Magento\Checkout\Model\Cart $cart,
        \Magento\Customer\Model\Session $customerSession,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        \PayPalBR\PayPal\Model\PayPalPlus\ConfigProvider $configProvider,
        \Magento\Payment\Model\Cart\SalesModel\Factory $cartSalesModelFactory,
        \Magento\Checkout\Model\Session $checkoutSession,
        \Psr\Log\LoggerInterface $logger
    ) {
        $this->scopeConfig = $scopeConfig;
        $this->cart = $cart;
        $this->customerSession = $customerSession;
        $this->storeManager = $storeManager;
        $this->configProvider = $configProvider;
        $this->checkoutSession = $checkoutSession;
        $this->logger = $logger;
        /** @var \Magento\Quote\Model\Quote $quote */
        $quote = $cart->getQuote();
        $this->cartSalesModelQuote = $cartSalesModelFactory->create($quote);
    }

    /**
     * Builds and returns the api context to be used in PayPal Plus API
     *
     * @return \PayPal\Rest\ApiContext
     */
    protected function getApiContext()
    {

        $debug = $this->configProvider->isDebugEnabled();
        $this->configId = $this->configProvider->getClientId();
        $this->secretId = $this->configProvider->getSecretId();

        if($debug == 1){
            $debug = true;
        }else{
            $debug = false;
        }
        $sdkConfig = array(
                'http.headers.PayPal-Partner-Attribution-Id' => 'MagentoBrazil_Ecom_PPPlus2',
                'mode' => $this->configProvider->isModeSandbox() ? 'sandbox' : 'live',
                'log.LogEnabled' => $debug,
                'log.FileName' => BP . '/var/log/paypalbr/paypal_plus/paypal-plus-' . date('Y-m-d') . '.log',
                'log.LogLevel' => 'DEBUG', 
                'cache.enabled' => true,
                'http.CURLOPT_SSLVERSION' => 'CURL_SSLVERSION_TLSv1_2'
        );

        $apiContext = new \PayPal\Rest\ApiContext(
                new \PayPal\Auth\OAuthTokenCredential(
                    $this->configId,
                    $this->secretId
                )
            );

        $apiContext->setConfig($sdkConfig);

        $cred = new \PayPal\Auth\OAuthTokenCredential(
            $this->configId,
             $this->secretId
        );

        $this->checkoutSession->setAccessTokenBearer($cred->getAccessToken($sdkConfig));

        $apiContext = new \PayPal\Rest\ApiContext(
            new \PayPal\Auth\OAuthTokenCredential(
                $this->configId,
                $this->secretId
            )
        );

        $apiContext->setConfig($sdkConfig);
        return $apiContext;
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
     * @return \PayPal\Api\RedirectUrls
     */
    protected function getRedirectUrls()
    {
        /** @var \Magento\Store\Model\Store $store */
        $store = $this->storeManager->getStore();

        $redirectUrls = new \PayPal\Api\RedirectUrls();
        $redirectUrls
            ->setReturnUrl($store->getUrl('checkout/cart'))
            ->setCancelUrl($store->getUrl('checkout/cart'));
        return $redirectUrls;
    }

    /**
     * Returns shipping addresss for PayPalPlus
     *
     * @return \PayPal\Api\ShippingAddress
     */
    protected function getShippingAddress()
    {
        /** @var \Magento\Quote\Model\Quote $quote */
        $quote = $this->cart->getQuote();
        $cartShippingAddress = $quote->getShippingAddress();
        $customer = $this->customerSession->getCustomer();

        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        $billingID =  $this->customerSession->getCustomer()->getDefaultBilling();
        $billing = $objectManager->create('Magento\Customer\Model\Address')->load($billingID);
        $shippingAddress = new \PayPal\Api\ShippingAddress();
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
        }else{
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
     * Returns the items in the cart
     *
     * @return \PayPal\Api\ItemList
     */
    protected function getItemList()
    {
        /** @var \Magento\Quote\Model\Quote $quote */
        $quote = $this->cart->getQuote();
        $baseSubtotal = $this->cartSalesModelQuote->getBaseSubtotal();

        /** @var string $storeCurrency */
        $storeCurrency = $quote->getBaseCurrencyCode();

        $itemList = new \PayPal\Api\ItemList();
        $cartItems = $quote->getItems();
        foreach ($cartItems as $cartItem) {

            $this->checkProductType($cartItem->getProductType());

            $item = new \PayPal\Api\Item();
            $item->setName($cartItem->getName())
                ->setDescription($cartItem->getDescription())
                ->setQuantity($cartItem->getQty())
                ->setPrice($cartItem->getPrice())
                ->setSku($cartItem->getSku())
                ->setCurrency($storeCurrency);
            $itemList->addItem($item);
        }

        if($this->cartSalesModelQuote->getBaseDiscountAmount() !== '0.0000'){
            $item = new \PayPal\Api\Item();
            $item->setName('Discount')
                ->setDescription('Discount')
                ->setQuantity('1')
                ->setPrice($this->cartSalesModelQuote->getBaseDiscountAmount())
                ->setSku('discountloja')
                ->setCurrency($storeCurrency);
            $itemList->addItem($item);
        }

        $shippingAddress = $this->getShippingAddress();
        $itemList->setShippingAddress($shippingAddress);

        return $itemList;
    }

    /**
     * Returns details for PayPal Plus API
     *
     * @return \PayPal\Api\Details
     */
    protected function getDetails()
    {
        /** @var \Magento\Quote\Model\Quote $quote */
        $quote = $this->cart->getQuote();

        /**
         * If subtotal + shipping + tax not equals grand total,
         * then a disscount might be applying, get subtotal with disscount then.
         */
        $baseSubtotal = $this->cartSalesModelQuote->getBaseSubtotal();

        if ($quote->getBaseGiftCardsAmount()) {
            $baseSubtotal -= $quote->getBaseGiftCardsAmount();
        }

        if ($quote->getBaseCustomerBalAmountUsed()) {
            $baseSubtotal -= $quote->getBaseCustomerBalAmountUsed();
        }
        if($this->cartSalesModelQuote->getBaseDiscountAmount()){
            $subtotal = $baseSubtotal + $this->cartSalesModelQuote->getBaseDiscountAmount(); 
        }else{
            $subtotal = $baseSubtotal;
        }

        $details = new \PayPal\Api\Details();
        $details->setShipping($this->cartSalesModelQuote->getBaseShippingAmount())
            ->setSubtotal($subtotal);

        if($this->cartSalesModelQuote->getBaseDiscountAmount() !== '0.0000'){
            $details->setShippingDiscount('-0.0000');
        }

        return $details;
    }

    /**
     * Returns amount PayPal Plus API
     *
     * @return \PayPal\Api\Amount
     */
    protected function getAmount()
    {
        /** @var \Magento\Quote\Model\Quote $quote */
        $quote = $this->cart->getQuote();
        $storeCurrency = $quote->getBaseCurrencyCode();
        $grandTotal = $quote->getGrandTotal();
        $details = $this->getDetails();

        $amount = new \PayPal\Api\Amount();
        $amount->setCurrency($storeCurrency);
        $amount->setTotal($grandTotal);
        $amount->setDetails($details);

        return $amount;
    }

    /**
     * Return transaction object for PayPalPlus API
     *
     * @return \PayPal\Api\Transaction
     */
    protected function getTransaction()
    {
        $amount = $this->getAmount();
        $itemList = $this->getItemList();

        $paymentOptions = new \PayPal\Api\PaymentOptions();
        $paymentOptions->setAllowedPaymentMethod("IMMEDIATE_PAY");

        $transaction = new \PayPal\Api\Transaction();
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
     * @return \PayPal\Api\Transaction
     */
    protected function getMerchantPreferences()
    {
        /** @var \Magento\Store\Model\Store $store */
        $store = $this->storeManager->getStore();
        $merchant = new \PayPal\Api\MerchantPreferences();
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

        $baseUrl = $this->storeManager->getStore()->getBaseUrl(\Magento\Framework\UrlInterface::URL_TYPE_WEB);

        $applicationContext = array(
            'locale'=>'pt-BR',
            'brand_name'=> $baseUrl,
            'shipping_preference'=> $this->shippingPreference
        );

        return $applicationContext;
    }

    /**
     * Creates and returns the payment object
     *
     * @return \PayPal\Api\Payment
     */
    protected function createAndGetPayment()
    {
        $apiContext = $this->getApiContext();

        $payer = $this->getPayer();
        $redirectUrls = $this->getRedirectUrls();
        $transaction = $this->getTransaction();
        $applicationContext = $this->getApplicationContextValues();

        $payment = new \PayPalBR\PayPal\Api\PayPalBrPayment();
        $payment->setIntent("Sale");
        $payment->setPayer($payer);
        $payment->setApplicationContext($applicationContext);
        $payment->setRedirectUrls($redirectUrls);
        $payment->addTransaction($transaction);

        //reset request ID
        // $apiContext->resetRequestId();

        /** @var \PayPal\Api\Payment $paypalPayment */
        $paypalPayment = $payment->create($apiContext);
        $quote = $this->checkoutSession->getQuote();
        $paypalPaymentId = $paypalPayment->getId();
        $quoteUpdatedAt = $quote->getUpdatedAt();
        $this->checkoutSession->setPaypalPaymentId( $paypalPaymentId );
        $this->checkoutSession->setQuoteUpdatedAt( $quoteUpdatedAt );


        return $paypalPayment;
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
     * @return \PayPal\Api\Payment
     */
    protected function patchAndGetPayment()
    {
        $apiContext = $this->getApiContext();
        $paypalPayment = $this->restoreAndGetPayment();
        $patchRequest = new \PayPal\Api\PatchRequest();

        // Change item list
        $itemListPatch = new \PayPal\Api\Patch();
        $itemListPatch
            ->setOp('replace')
            ->setPath('/transactions/0/item_list')
            ->setValue($this->getItemList());
        $patchRequest->addPatch($itemListPatch);

        // Change amount
        $amountPatch = new \PayPal\Api\Patch();
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
     * @return \PayPal\Api\Payment
     */
    protected function restoreAndGetPayment()
    {
        $payment = new \PayPal\Api\Payment();
        $paypalPaymentId = $this->checkoutSession->getPaypalPaymentId();
        $apiContext = $this->getApiContext();

        $paypalPayment = \PayPal\Api\Payment::get($paypalPaymentId, $apiContext);
        return $paypalPayment;
    }

    public function execute()
    {
        try {

            // if ($this->isQuoteChanged()) {
            //     $paypalPayment = $this->patchAndGetPayment();
            // } else {
                
            // }
            $paypalPayment = $this->createAndGetPayment();

            $result = [
                'status' => 'success',
                'message' => $paypalPayment->toArray()
            ];
        } catch (\PayPal\Exception\PayPalConnectionException $e) {
            $result = [
                'status' => 'error',
                'message' => $e->getMessage()
            ];
        }
        return $result;
    }

    protected function checkProductType($productType)
    {
        if($productType == 'downloadable'){
            $this->shippingPreference = self::NO_SHIPPING;
        }
        if($productType != 'downloadable'){
            $this->shippingPreference = self::SET_PROVIDED_ADDRESS;
        }
    }

}