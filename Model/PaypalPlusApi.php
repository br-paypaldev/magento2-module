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

use Magento\Framework\Serialize\Serializer\Json;
use Magento\Framework\Stdlib\CookieManagerInterface;
use Magento\Framework\Stdlib\Cookie\CookieMetadataFactory;
use Magento\Framework\Session\SessionManagerInterface;
use Magento\Quote\Model\Quote;
use PayPal\Api\Item;
use PayPal\Api\ItemList;
use PayPalBR\PayPal\Helper\Installment;
use PayPalBR\PayPal\Model\PayPal\Validate;
use Magento\Framework\Filesystem\DirectoryList;
use PayPal\Api\Amount;
use PayPal\Api\Details;

/**
 * Class PaypalPlusApi
 *
 * @package PayPalBR\PayPalPlus\Model
 */
class PaypalPlusApi extends PaypalCommonApi
{



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
     * @var
     */
    protected $shippingPreference;

    /**
     * @var
     */
    protected $productMetadata;

    /**
     * @var
     */
    protected $dir;

    /**
     * Name of cookie that holds private content version
     */
    const COOKIE_NAME = 'paymentID';

    /**
     * CookieManager
     *
     * @var CookieManagerInterface
     */
    private $cookieManager;

    /**
     * @var CookieMetadataFactory
     */
    private $cookieMetadataFactory;

    /**
     * @var SessionManagerInterface
     */
    private $sessionManager;

    protected $validate;

    protected $json;

    protected $installmentHelper;

    /**
     * PaypalPlusApi constructor.
     *
     * @param \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig
     * @param \Magento\Checkout\Model\Cart $cart
     * @param \Magento\Customer\Model\Session $customerSession
     * @param \Magento\Store\Model\StoreManagerInterface $storeManager
     */
    public function __construct(
        \Magento\Checkout\Model\Cart $cart,
        \Magento\Customer\Model\Session $customerSession,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        //\PayPalBR\PayPal\Model\PayPalPlus\ConfigProvider $configProvider,
        \Magento\Payment\Model\Cart\SalesModel\Factory $cartSalesModelFactory,
        \Magento\Checkout\Model\Session $checkoutSession,
        \Psr\Log\LoggerInterface $logger,
        \Magento\Framework\App\ProductMetadataInterface $productMetadata,
        CookieManagerInterface $cookieManager,
        CookieMetadataFactory $cookieMetadataFactory,
        SessionManagerInterface $sessionManager,
        Validate $validate,
        Json $json,
        DirectoryList $dir,
        Installment $installmentHelper
    ) {
        parent::__construct(
            $cart,
            $customerSession,
            $storeManager,
            $cartSalesModelFactory,
            $checkoutSession,
            $logger
        );
        $this->storeManager = $storeManager;
        //$this->configProvider = $configProvider;
        $this->checkoutSession = $checkoutSession;
        $this->productMetadata = $productMetadata;
        $this->cookieManager = $cookieManager;
        $this->cookieMetadataFactory = $cookieMetadataFactory;
        $this->sessionManager = $sessionManager;
        $this->validate = $validate;
        $this->json = $json;
        $this->dir = $dir;
        $this->installmentHelper = $installmentHelper;
    }

    /**
     * Builds and returns the api context to be used in PayPal Plus API
     *
     * @return \PayPal\Rest\ApiContext
     */
    protected function getApiContext()
    {

        //$debug = $this->configProvider->isDebugEnabled();
        //$this->configId = $this->configProvider->getClientId();
        //$this->secretId = $this->configProvider->getSecretId();
        $edition = $this->productMetadata->getEdition();
        $logDir = $this->dir->getPath('log');

        if ($debug == 1) {
            $debug = true;
        } else {
            $debug = false;
        }
        $sdkConfig = array(
            'http.headers.PayPal-Partner-Attribution-Id' => 'MagentoBrazil_Ecom_PPPlus2',
            //'mode' => $this->configProvider->isModeSandbox() ? 'sandbox' : 'live',
            'log.LogEnabled' => $debug,
            'log.FileName' => $logDir . '/paypal-plus-' . date('Y-m-d') . '.log',
            'cache.FileName' => $logDir . '/auth.cache',
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
     * Creates and returns the payment object
     *
     * @return \PayPal\Api\Payment
     */
    protected function createAndGetPayment($params)
    {
        $quote = $this->checkoutSession->getQuote();

        if (!$quote->isVirtual()) {
            //Validate Customer Address
            $this->validateCustomerInformation($params);
        }

        $amountTotal = $quote->getBaseGrandTotal();
        $amountItemsWithDiscount = $quote->getBaseSubtotalWithDiscount();
        $ship = $quote->getShippingAddress()->getBaseShippingAmount();
        $creditStore = $quote->getData('customer_balance_amount_used');
        $tax = $quote->getShippingAddress()->getBaseTaxAmount();
        $rewards = $quote->getData('reward_currency_amount');
        $giftCardAmount = $quote->getData('base_gift_cards_amount_used');

        if ($params['installment'] === 1) {
            $installment = floatval("-".$this->installmentHelper->getDiscountValue());
        } else {
            $installment = $this->installmentHelper->getCostValue($params['installment']);
        }

        $this->checkoutSession->setInstallment($params['installment']);
        $this->checkoutSession->setFeeValue($installment);

        $quote->getPayment()->setAdditionalInformation('paypalplus_installment', $params['installment']);
        $quote->getPayment()->save();

        $totalSum = $amountItemsWithDiscount + $ship + $tax + $installment - $creditStore - $rewards - $giftCardAmount;
        $amountTotal = (float)$amountTotal + $installment;
        $totalSum = (float)$totalSum;

        if (strval($amountTotal) != strval($totalSum)) {
            throw new \PayPal\Exception\PayPalConnectionException(
                null,
                __("Discrepancy found in the total order amount.")
            );
        }

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
        $paypalPaymentId = $paypalPayment->getId();
        $quoteUpdatedAt = $quote->getUpdatedAt();
        $this->checkoutSession->setPaypalPaymentId($paypalPaymentId);
        $this->checkoutSession->setQuoteUpdatedAt($quoteUpdatedAt);
        $this->set($paypalPaymentId);

        //PAYP-95: Store amount send on the payment request(/payment)
        $this->checkoutSession->setAmountTotal($amountTotal);

        return $paypalPayment;
    }

    public function validateCustomerInformation($params = null)
    {
        $quote = $this->checkoutSession->getQuote();

        $customer = $quote->getCustomer();
        $address = $quote->getShippingAddress();
        $firstname = $address->getFirstname();
        $lastname = $address->getLastname();
        if ($address->getEmail()) {
            $email = strtolower($address->getEmail());
        }
        if (empty($email)) {
            $email = $customer->getEmail();
            if (empty($email) && isset($params['email'])) {
                $email = $params['email'];
            }
        }
        $payerTaxId = $address->getVatId();
        if (empty($payerTaxId)) {
            $payerTaxId = $customer->getTaxvat();
        }
        $phone = $address->getTelephone();
        $cep = $address->getPostcode();
        $estado = $address->getRegion();
        $cidade = $address->getCity();

        $errors = array();

        if (!$this->validate->is(array($firstname, $lastname), 'OnlyWords', true)) {
            $errors[] = "NOME/SOBRENOME";
        }
        if (!$this->validate->isValidTaxvat($payerTaxId)) {
            $errors[] = 'CPF';
        }
        if (!$this->validate->is($email, 'AddressMail', false)) {
            $errors[] = 'EMAIL';
        }
        if (!$this->validate->is($phone, 'OnlyNumbers', true)) {
            $errors[] = 'TELEFONE';
        }

        if (empty($address->getStreetLine(1))) {
            $errors[] = 'ENDERECO';
        }

        if (!$this->validate->is($this->validate->soNumero($cep), 'OnlyNumbers', true)) {
            $errors[] = 'CEP';
        }

        if (empty($estado)) {
            $errors[] = 'ESTADO';
        }

        if (empty($cidade)) {
            $errors[] = 'CIDADE';
        }

        if (!empty($errors)) {
            throw new \Exception(
                'Prezado cliente, favor preencher e/ou validar os campos: ' . $this->json->serialize($errors)
            );
        }
    }

    public function execute($params)
    {
        try {
            $paypalPayment = $this->createAndGetPayment($params);

            $result = [
                'status' => 'success',
                'message' => $paypalPayment->toArray()
            ];
        } catch (\PayPal\Exception\PayPalConnectionException $e) {
            $result = [
                'status' => 'error',
                'message' => $e->getMessage()
            ];
        } catch (\Exception $e) {
            $result = [
                'status' => 'error',
                'message' => $e->getMessage()
            ];
        }
        return $result;
    }

    /**
     * Get form key cookie
     *
     * @return string
     */
    public function get()
    {
        return $this->cookieManager->getCookie(self::COOKIE_NAME);
    }

    /**
     * @param string $value
     * @param int $duration
     * @return void
     */
    public function set($value, $duration = 86400)
    {
        $metadata = $this->cookieMetadataFactory
            ->createPublicCookieMetadata()
            ->setDuration($duration)
            ->setPath($this->sessionManager->getCookiePath())
            ->setDomain($this->sessionManager->getCookieDomain());

        $this->cookieManager->setPublicCookie(
            self::COOKIE_NAME,
            $value,
            $metadata
        );
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

        $installment = $quote->getPayment()->getAdditionalInformation('paypalplus_installment');
        $feeValue = 0;
        if ($installment != "1") {
            $feeValue = $this->installmentHelper->getCostValue($installment);
        }

        $storeCurrency = $quote->getBaseCurrencyCode();
        $grandTotal = $quote->getBaseGrandTotal() + $feeValue;
        $details = $this->getDetails();

        $amount = new Amount();
        $amount->setCurrency($storeCurrency);
        $amount->setTotal($grandTotal);
        $amount->setDetails($details);

        return $amount;
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

        if ($quote->getPayment()->getAdditionalInformation('paypalplus_installment') != "1") {
            $baseSubtotal += $this->installmentHelper->getCostValue(
                $quote->getPayment()->getAdditionalInformation('paypalplus_installment')
            );
        }

        if ($quote->getBaseCustomerBalAmountUsed()) {
            $baseSubtotal -= $quote->getBaseCustomerBalAmountUsed();
        }
        if ($quote->getData('base_reward_currency_amount')) {
            $baseSubtotal -= $quote->getData('base_reward_currency_amount');
        }

        if ($quote->getData('base_gift_cards_amount_used')) {
            $baseSubtotal -= $quote->getData('base_gift_cards_amount_used');
        }

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
     * Returns the items in the cart
     *
     * @return ItemList
     */
    protected function getItemList()
    {
        $itemList = parent::getItemList();
        /** @var Quote $quote */
        $quote = $this->cart->getQuote();

        /** @var string $storeCurrency */
        $storeCurrency = $quote->getBaseCurrencyCode();

        $valueInstallment = $this->installmentHelper
            ->getCostValue($quote->getPayment()->getAdditionalInformation('paypalplus_installment'));

        if ($quote->getPayment()->getAdditionalInformation('paypalplus_installment') != "1") {
            if ($valueInstallment !== 0.0) {
                $item = new Item();
                $item->setName($this->installmentHelper->getTitle())
                    ->setDescription($this->installmentHelper->getTitle())
                    ->setQuantity('1')
                    ->setPrice($valueInstallment)
                    ->setSku('PaypalBr' . $this->installmentHelper->getTitle())
                    ->setCurrency($storeCurrency);
                $itemList->addItem($item);
            }
        }

        return $itemList;
    }
}
