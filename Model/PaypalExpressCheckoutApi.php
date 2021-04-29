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

/**
 * Class PaypalExpressCheckoutApi
 *
 * @package PayPalBR\PayPalPlus\Model
 */
class PaypalExpressCheckoutApi extends PaypalCommonApi
{
    /**
     * Contains the config provider for Magento 2 back-end configurations
     *
     * @var ConfigProvider
     */
    protected $configProvider;

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
    protected $dir;

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
        \PayPalBR\PayPal\Model\PayPalExpressCheckout\ConfigProvider $configProvider,
        \Magento\Payment\Model\Cart\SalesModel\Factory $cartSalesModelFactory,
        \Magento\Checkout\Model\Session $checkoutSession,
        \Psr\Log\LoggerInterface $logger,
        \Magento\Framework\Filesystem\DirectoryList $dir
    ) {
        parent::__construct(
            $cart,
            $customerSession,
            $storeManager,
            $cartSalesModelFactory,
            $checkoutSession,
            $logger
        );
        $this->configProvider = $configProvider;
        $this->dir = $dir;
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

        if ($debug == 1) {
            $debug = true;
        } else {
            $debug = false;
        }

        $sdkConfig = array(
            'http.headers.PayPal-Partner-Attribution-Id' => 'MagentoBrazil_Ecom_EC2',
            'mode' => $this->configProvider->isModeSandbox() ? 'sandbox' : 'live',
            'log.LogEnabled' => $debug,
            'log.FileName' => $this->dir->getPath('log') . '/paypal-express-checkout-' . date('Y-m-d') . '.log',
            'cache.FileName' => $this->dir->getPath('log') . '/auth.cache',
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
    protected function createAndGetPayment()
    {
        $quote = $this->checkoutSession->getQuote();

        $amountTotal = $quote->getBaseGrandTotal();
        $amountItemsWithDiscount = $quote->getBaseSubtotalWithDiscount();
        $ship = $quote->getShippingAddress()->getBaseShippingAmount();
        $tax = $quote->getShippingAddress()->getBaseTaxAmount();

        $totalSum = $amountItemsWithDiscount + $ship + $tax;
        $amountTotal = (float)$amountTotal;
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

        $apiContext = $this->getApiContext();

        //reset request ID
        // $apiContext->resetRequestId();

        /** @var \PayPal\Api\Payment $paypalPayment */
        $paypalPayment = $payment->create($apiContext);
        $paypalPaymentId = $paypalPayment->getId();
        $quoteUpdatedAt = $quote->getUpdatedAt();
        $this->checkoutSession->setPaypalPaymentId($paypalPaymentId);
        $this->checkoutSession->setQuoteUpdatedAt($quoteUpdatedAt);


        return $paypalPayment;
    }

    public function execute()
    {
        try {
            /*
            if ($this->isQuoteChanged()) {
                $paypalPayment = $this->patchAndGetPayment();
            } else {
                $paypalPayment = $this->createAndGetPayment();
            }
            */
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
        if ($productType == 'downloadable') {
            $this->shippingPreference = self::NO_SHIPPING;
        }
        if ($productType != 'downloadable') {
            $this->shippingPreference = self::SET_PROVIDED_ADDRESS;
        }
    }
}
