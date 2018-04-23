<?php
namespace PayPalBR\PayPal\Model;

use Braintree\Exception;
use PayPalBR\PayPal\Api\LoginPayPalCreateManagementInterface;


class LoginPayPalManagementApi implements LoginPayPalCreateManagementInterface
{

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
     * @var \Magento\Framework\Controller\Result\JsonFactory
     */
    protected $resultJsonFactory;

    /**
     * @var \Magento\Customer\Model\Customer
     */
    protected $customerModel;

    /**
     * @var \Magento\Customer\Model\CustomerFactory
     */
    protected $customerFactory;

    /**
     * @var \Magento\Customer\Model\AddressFactory
     */
    protected $address;

    /**
     * @var \Magento\Directory\Model\RegionFactory
     */
    protected $regionFactory;

    /**
     * @var \Magento\Quote\Api\Data\AddressInterface
     */
    protected $quoteAddress;

    /**
     * @var \Magento\Quote\Model\Quote
     */
    protected $quoteData;

    /**
     * @var \Magento\Customer\Model\EmailNotificationInterface
     */
    protected $emailNotification;

    /**
     * @var \Magento\Customer\Api\CustomerRepositoryInterface
     */
    protected $customerRepository;

    /**
     * @var \Magento\Framework\Message\ManagerInterface
     */
    protected $messageManager;

    /**
     * @var \Magento\Framework\Stdlib\DateTime\DateTime,
     */
    protected $date;

    /**
     * @var \Magento\Framework\Math\Random,
     */
    protected $mathRandom;

    /**
     * @var
     */
    protected $shippingPreference;

    /**
     * LoginPayPalManagementApi constructor.
     * @param \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig
     * @param \Magento\Checkout\Model\Cart $cart
     * @param \Magento\Customer\Model\Session $customerSession
     * @param \Magento\Store\Model\StoreManagerInterface $storeManager
     * @param ConfigProvider $configProvider
     * @param \Magento\Payment\Model\Cart\SalesModel\Factory $cartSalesModelFactory
     * @param \Magento\Checkout\Model\Session $checkoutSession
     * @param \Psr\Log\LoggerInterface $logger
     * @param \Magento\Framework\Controller\Result\JsonFactory $resultJsonFactory
     * @param \Magento\Customer\Model\Customer $customerModel
     * @param \Magento\Customer\Model\CustomerFactory $customerFactory
     * @param \Magento\Customer\Model\AddressFactory $address
     * @param \Magento\Directory\Model\RegionFactory $regionFactory
     * @param \Magento\Quote\Api\Data\AddressInterface $quoteAddress
     * @param \Magento\Customer\Model\EmailNotificationInterface $emailNotification
     * @param \Magento\Customer\Api\CustomerRepositoryInterface $customerRepository
     * @param \Magento\Framework\Message\ManagerInterface $messageManager
     * @param \Magento\Framework\Stdlib\DateTime\DateTime $date
     * @param \Magento\Framework\Math\Random $mathRandom
     */
    public function __construct(
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Magento\Checkout\Model\Cart $cart,
        \Magento\Customer\Model\Session $customerSession,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        \PayPalBR\PayPal\Model\PayPalExpressCheckout\ConfigProvider $configProvider,
        \Magento\Payment\Model\Cart\SalesModel\Factory $cartSalesModelFactory,
        \Magento\Checkout\Model\Session $checkoutSession,
        \Psr\Log\LoggerInterface $logger,
        \Magento\Framework\Controller\Result\JsonFactory $resultJsonFactory,
        \Magento\Customer\Model\Customer $customerModel,
        \Magento\Customer\Model\CustomerFactory $customerFactory,
        \Magento\Customer\Model\AddressFactory $address,
        \Magento\Directory\Model\RegionFactory $regionFactory,
        \Magento\Quote\Api\Data\AddressInterface $quoteAddress,
        \Magento\Customer\Model\EmailNotificationInterface $emailNotification,
        \Magento\Customer\Api\CustomerRepositoryInterface $customerRepository,
        \Magento\Framework\Message\ManagerInterface $messageManager,
        \Magento\Framework\Stdlib\DateTime\DateTime $date,
        \Magento\Framework\Math\Random $mathRandom

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
        $this->resultJsonFactory = $resultJsonFactory;
        $this->customerModel = $customerModel;
        $this->customerFactory = $customerFactory;
        $this->address = $address;
        $this->regionFactory = $regionFactory;
        $this->quoteAddress = $quoteAddress;
        $this->quoteData = $quote;
        $this->emailNotification = $emailNotification;
        $this->customerRepository = $customerRepository;
        $this->messageManager = $messageManager;
        $this->date = $date;
        $this->mathRandom = $mathRandom;
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
            'http.headers.PayPal-Partner-Attribution-Id' => 'MagentoBrazil_Ecom_Login2',
            'mode' => $this->configProvider->isModeSandbox() ? 'sandbox' : 'live',
            'log.LogEnabled' => $debug,
            'log.FileName' => BP . '/var/log/paypalbr/paypal_login/paypal-login-' . date('Y-m-d') . '.log',
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
            ->setReturnUrl($store->getUrl('expresscheckout/revieworder'))
            ->setCancelUrl($store->getUrl('expresscheckout/revieworder'));
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

//        $shippingAddress = $this->getShippingAddress();
//        $itemList->setShippingAddress($shippingAddress);

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
        $transaction->setAmount($amount);
        $transaction->setItemList($itemList);
        $transaction->setPaymentOptions($paymentOptions);

        return $transaction;
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

        /** @var \PayPal\Api\Payment $paypalPayment */
        $paypalPayment = $payment->create($apiContext);

        return $paypalPayment;
    }

    /**
     * @return array
     */
    public function initPayPalLightBox()
    {
        try {

            $paypalPayment = $this->createAndGetPayment();

            $result = [
                'status' => 'success',
                'paymentID' => $paypalPayment->getId()
            ];

        } catch (\PayPal\Exception\PayPalConnectionException $e) {
            $result = [
                'status' => 'error',
                'message' => $e->getMessage()
            ];
        }
        return $result;
    }

    /**
     * @return array
     */
    public function authorizePayPalLogin($data)
    {

        try {

            $paypalPayment = $this->addAdditionalInformationOnQuote($data);

            $payerInfo = $paypalPayment->getPayer()->getPayerInfo();

            $userEmail = $payerInfo->getEmail();
            $websiteId = $this->storeManager->getWebsite()->getId();

            $customerResponse = $this->findCustomerByEmail($userEmail,$websiteId);

            if(!$customerResponse){
                $this->createUser($payerInfo, $websiteId);
            }

            if($customerResponse){
                $this->updateUserInfo($customerResponse, $paypalPayment);
                $this->customerSession->loginById($customerResponse->getId());
            }

            $result = [
                'status' => 'success',
                'paymentID' => $paypalPayment->getId(),
                'redirect' => $this->storeManager->getStore()->getUrl('expresscheckout/revieworder')
            ];

        } catch (\PayPal\Exception\PayPalConnectionException $e) {
            $result = [
                'status' => 'error',
                'message' => $e->getMessage()
            ];
        }
        return $result;
    }

    public function getUserInfo($paypalPaymentId)
    {
        $apiContext = $this->getApiContext();
        $paypalPayment = \PayPal\Api\Payment::get($paypalPaymentId, $apiContext);

        return $paypalPayment;
    }

    public function findCustomerByEmail($email, $websiteId)
    {
        $customer = $this->customerModel;
        if ($websiteId) {
            $customer->setWebsiteId($websiteId);
        }
        $customer->loadByEmail($email);

        if ($customer->getId()) {
            return $customer;
        }

        return false;
    }

    public function createUser($payerInfo, $websiteId)
    {
        $customer = $this->saveUser($payerInfo, $websiteId);
        $customerRepository = $this->customerRepository->getById($customer->getId());
        $this->quoteData->setCustomer($customerRepository);

        $this->saveAddress($payerInfo, $customer);

        $this->quoteData->setPaymentMethod('paypalbr_expresscheckout');
        $this->quoteData->getPayment()->importData(['method' => 'paypalbr_expresscheckout']);

        $this->quoteData->save();

        $this->customerSession->loginById($customer->getId());

        $message = __(
            'Identificamos pelo seu e-mail PayPal (<b>%1</b>) que você não possui uma conta na loja. Criamos uma conta para você, por favor acesse seu e-mail para gerar uma senha. <br> Você pode finalizar sua compra normalmente e criar a senha depois.',
            $payerInfo->getEmail()
        );
        $this->messageManager->addSuccess($message);

    }

    /**
     * @param $payerInfo
     * @param $websiteId
     * @return \Magento\Customer\Model\CustomerFactory
     */
    protected function saveUser($payerInfo, $websiteId)
    {
        $date = $this->date->gmtDate();

        $customer = $this->customerFactory->create();

        $store = $this->storeManager->getStore();

        $customer->setWebsiteId($websiteId)
            ->setStore($store)
            ->setEmail($payerInfo->getEmail())
            ->setFirstname($payerInfo->getFirstName())
            ->setLastname($payerInfo->getLastName())
            ->setTaxvat($payerInfo->getTaxid())
            ->setDefaultBilling(1)
            ->setDefaultShipping(1)
            ->setRpToken($this->mathRandom->getUniqueHash())
            ->setRpTokenCreatedAt($date);

        try{
            $customer->save();
            $customerRepository = $this->customerRepository->getById($customer->getId());
            $this->emailNotification->newAccount($customerRepository, 'registered_no_password' ,'checkout', $store->getId(), null);

        }catch (Exception $e){
            $this->messageManager->addError(__($e->getMessage()));
            $this->_redirect('/checkout/cart', ['_nosecret' => true]);
            $this->logger($e);
        }

        return $customer;

    }

    /**
     * @param $payerInfo
     * @param $customer
     * @return \Magento\Customer\Model\AddressFactory
     */
    protected function saveAddress($payerInfo, $customer)
    {
        $region = $this->regionFactory->create();
        $regionId = $region->loadByCode($payerInfo->getShippingAddress()->getState(), $payerInfo->getShippingAddress()->getCountryCode())->getId();

        $street = $payerInfo->getShippingAddress()->getLine1() . PHP_EOL . $payerInfo->getShippingAddress()->getLine2() ?? '';
        $payerPhone = $payerInfo->getPhone() ?? '000000000';

        $address = $this->address->create()
            ->setRegionId($regionId)
            ->setCustomerId($customer->getId())
            ->setFirstname($payerInfo->getFirstName())
            ->setLastname($payerInfo->getLastName())
            ->setCountryId($payerInfo->getShippingAddress()->getCountryCode())
            ->setPostcode($payerInfo->getShippingAddress()->getPostalCode())
            ->setCity($payerInfo->getShippingAddress()->getCity())
            ->setTelephone($payerPhone)
            ->setCompany($payerInfo->getShippingAddress()->getRecipientName())
            ->setStreet($street)
            ->setIsDefaultBilling('1')
            ->setIsDefaultShipping('1')
            ->setSaveInAddressBook('1');

        try{
            $address->save();
            $this->setQuoteBillingAndShippingAddress($payerInfo, $customer, $address, $regionId, $payerPhone, $street);


        }catch (Exception $e){
            $this->messageManager->addError(__($e->getMessage()));
            $this->_redirect('/checkout/cart', ['_nosecret' => true]);
            $this->logger($e);
        }

        return $address;
    }

    /**
     * @param $customerResponse
     * @param $payerInfo
     */
    protected function addressConfirmation($customerResponse, $payerInfo)
    {
        $addresses = $customerResponse->getAddresses();

        $addressMatch = false;

        foreach($addresses as $address){

            $magentoCustomerStreetArray = $address->getStreet();

            $magentoStreetLine2 = !empty($magentoCustomerStreetArray[1]) ? $magentoCustomerStreetArray[1] : '';
            $magentoCustomerStreetLine = $magentoCustomerStreetArray[0]  . PHP_EOL . $magentoStreetLine2;

            $paypalCustomerStreet = $payerInfo->getShippingAddress()->getLine1() . PHP_EOL . $payerInfo->getShippingAddress()->getLine2() ?? '';

            if($magentoCustomerStreetLine == $paypalCustomerStreet){
                $address->setIsDefaultBilling(true);
                $address->setIsDefaultShipping(true);
                $address->save();

                $payerPhone = $payerInfo->getPhone() ?? '00000000000';

                $this->setQuoteBillingAndShippingAddress($payerInfo, $customerResponse, $address, $address->getRegionId(), $payerPhone, $paypalCustomerStreet);

                $addressMatch = true;
                continue;
            }

        }

        if(!$addressMatch){
            $this->saveAddress($payerInfo, $customerResponse);
        }

    }

    protected function logger($array)
    {
        $writer = new \Zend\Log\Writer\Stream(BP . '/var/log/paypalbr/paypal_login/paypal_login-' . date('Y-m-d') . '.log');
        $logger = new \Zend\Log\Logger();
        $logger->addWriter($writer);
        $logger->info($array);
    }

    /**
     * @param $payerInfo
     * @param $customer
     * @param $address
     * @param $regionId
     * @param $payerPhone
     * @param $street
     */
    protected function setQuoteBillingAndShippingAddress($payerInfo, $customer, $address, $regionId, $payerPhone, $street)
    {
        $quoteShippingAddress = $this->quoteData->getShippingAddress();

        $quoteShippingAddress->setCustomerAddressId(null)
            ->setRegionId($regionId)
            ->setCustomerId($customer->getId())
            ->setFirstname($payerInfo->getFirstName())
            ->setLastname($payerInfo->getLastName())
            ->setCountryId($payerInfo->getShippingAddress()->getCountryCode())
            ->setPostcode($payerInfo->getShippingAddress()->getPostalCode())
            ->setCity($payerInfo->getShippingAddress()->getCity())
            ->setTelephone($payerPhone)
            ->setCompany($payerInfo->getShippingAddress()->getRecipientName())
            ->setStreet($street)
            ->setSaveInAddressBook(0)
            ->setCollectShippingRates(true);

        $quoteShippingAddress->save();

        $quoteBillingAddress = $this->quoteData->getBillingAddress();

        $quoteBillingAddress->setCustomerAddressId(null)
            ->setRegionId($regionId)
            ->setCustomerId($customer->getId())
            ->setFirstname($payerInfo->getFirstName())
            ->setLastname($payerInfo->getLastName())
            ->setCountryId($payerInfo->getShippingAddress()->getCountryCode())
            ->setPostcode($payerInfo->getShippingAddress()->getPostalCode())
            ->setCity($payerInfo->getShippingAddress()->getCity())
            ->setTelephone($payerPhone)
            ->setCompany($payerInfo->getShippingAddress()->getRecipientName())
            ->setStreet($street)
            ->setSaveInAddressBook(0)
            ->setCollectShippingRates(true);

        $quoteBillingAddress->save();
    }

    protected function getPayPalEditUrl($paypalPayment)
    {
        $response = false;

        $links = $paypalPayment->getLinks();

        foreach($links as $link){

            if($link->getRel() == 'approval_url'){
                $response = $link->getHref();
            }

        }

        return $response;
    }

    protected function getPayerInstallments($paypalPayment)
    {
        $response = 1;

        if (!empty($paypalPayment->getCreditFinancingOffered())) {
            $response = $paypalPayment->getCreditFinancingOffered()->getTerm();
        }

        return $response;
    }

    /**
     * @param $customerResponse
     * @param $payerInfo
     */
    protected function updateUserInfo($customerResponse, $paypalPayment)
    {
        $customerRepository = $this->customerRepository->getById($customerResponse->getId());
        $this->quoteData->setCustomer($customerRepository);
        $this->addressConfirmation($customerResponse, $paypalPayment->getPayer()->getPayerInfo());

        $installments = $this->getPayerInstallments($paypalPayment);
        $this->quoteData->getPayment()->setAdditionalInformation('term', $installments);
        $this->quoteData->setPaymentMethod('paypalbr_expresscheckout');
        $this->quoteData->getPayment()->importData(['method' => 'paypalbr_expresscheckout']);
        $this->quoteData->save();
    }

    /**
     * @param $data
     * @return \PayPal\Api\Payment
     */
    protected function addAdditionalInformationOnQuote($data): \PayPal\Api\Payment
    {
        $paypalPayment = $this->getUserInfo($data->paymentID);

        $payPalEditUrl = $this->getPayPalEditUrl($paypalPayment);

        $installments = $this->getPayerInstallments($paypalPayment);

        $this->quoteData->getPayment()->setAdditionalInformation('method_title', 'PayPal');
        $this->quoteData->getPayment()->setAdditionalInformation('redirect_url', $payPalEditUrl);
        $this->quoteData->getPayment()->setAdditionalInformation('pay_id', $data->paymentID);
        $this->quoteData->getPayment()->setAdditionalInformation('payer_id', $data->payerID);
        $this->quoteData->getPayment()->setAdditionalInformation('token', $data->paymentToken);
        $this->quoteData->getPayment()->setAdditionalInformation('term', $installments);
        $this->quoteData->save();
//        $this->quoteData->getPayment()->save();

        return $paypalPayment;
    }

    protected function updateInstallments($installments)
    {
        $this->quoteData->getPayment()->setAdditionalInformation('term', $installments);
        $this->quoteData->save();
    }

    public function updateUserAddress()
    {
        $paymentID = $this->quoteData->getPayment()->getAdditionalInformation('pay_id');

        if (!$paymentID) {
            throw new Exception("Payment Id is null", 1);
            
        }

        $paypalPayment = $this->getUserInfo($paymentID);

        $websiteId = $this->storeManager->getWebsite()->getId();

        $userEmail = $this->customerSession->getCustomer()->getEmail();

        $customerResponse = $this->findCustomerByEmail($userEmail,$websiteId);

        $this->updateUserInfo($customerResponse, $paypalPayment);
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