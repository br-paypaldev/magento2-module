<?php

namespace PayPalBR\PayPal\Gateway\Transaction\PayPalPlus\ResourceGateway\Create;

use function Couchbase\defaultDecoder;
use Magento\Payment\Gateway\Data\OrderAdapterInterface;
use Magento\Payment\Gateway\Data\PaymentDataObjectInterface;
use Magento\Payment\Gateway\Request\BuilderInterface;
use Magento\Payment\Model\InfoInterface;
use Magento\Sales\Model\Order\Item;
use Magento\Checkout\Model\Cart;
use Magento\Checkout\Model\Session;
use PayPalBR\PayPal\Gateway\Transaction\Base\Config\ConfigInterface;
use PayPalBR\PayPal\Api\PayPalPlusRequestDataProviderInterfaceFactory;
use PayPalBR\PayPal\Api\CartItemRequestDataProviderInterfaceFactory;
use PayPalBR\PayPal\Model\PayPalPlus\ConfigProvider;


class RequestBuilder implements BuilderInterface
{
    const MODULE_NAME = 'PayPalBR_PayPal';

    protected $requestDataProviderFactory;
    protected $cartItemRequestDataProviderFactory;
    protected $orderAdapter;
    protected $cart;
    protected $config;
    protected $checkoutSession;
    protected $configProvider;
    protected $_checkoutSession;

    /**
     * RequestBuilder constructor.
     * @param PayPalPlusRequestDataProviderInterfaceFactory $requestDataProviderFactory
     * @param CartItemRequestDataProviderInterfaceFactory $cartItemRequestDataProviderFactory
     * @param Cart $cart
     * @param ConfigInterface $config
     */
    public function __construct(
        PayPalPlusRequestDataProviderInterfaceFactory $requestDataProviderFactory,
        CartItemRequestDataProviderInterfaceFactory $cartItemRequestDataProviderFactory,
        Cart $cart,
        ConfigInterface $config,
        Session $checkoutSession,
        ConfigProvider $configProvider
    ) {
        $this->setRequestDataProviderFactory($requestDataProviderFactory);
        $this->setCartItemRequestProviderFactory($cartItemRequestDataProviderFactory);
        $this->setCart($cart);
        $this->setConfig($config);
        $this->setCheckoutSession($checkoutSession);
        $this->setConfigProvider($configProvider);
        $this->_checkoutSession = $checkoutSession;

    }

    protected $paymentData;

    /**
     * {@inheritdoc}
     */
    public function build(array $buildSubject)
    {
        if (!isset($buildSubject['payment']) || !$buildSubject['payment'] instanceof PaymentDataObjectInterface) {
            throw new \InvalidArgumentException('Payment data object should be provided');
        }

        /** @var PaymentDataObjectInterface $paymentDataObject */
        $paymentDataObject = $buildSubject['payment'];
        $this->setOrderAdapter($paymentDataObject->getOrder());

        $this->setPaymentData($paymentDataObject->getPayment());

        $requestDataProvider = $this->createRequestDataProvider();

        return $this->createNewRequest($requestDataProvider);

    }

    /**
     * @param Request $request
     * @return $this
     */
    protected function setRequest(Request $request)
    {
        $this->request = $request;
        return $this;
    }

    /**
     * @return Request
     */
    protected function getRequest()
    {
        return $this->request;
    }

    /**
     * @return BilletRequestDataProviderInterface
     */
    protected function createRequestDataProvider()
    {
        return $this->getRequestDataProviderFactory()->create([
            'orderAdapter' => $this->getOrderAdapter(),
            'payment' => $this->getPaymentData()
        ]);
    }

    /**
     * @return RequestDataProviderFactory
     */
    protected function getRequestDataProviderFactory()
    {
        return $this->requestDataProviderFactory;
    }

    /**
     * @param PayPalPlusRequestDataProviderInterfaceFactory $requestDataProviderFactory
     * @return RequestBuilder
     */
    protected function setRequestDataProviderFactory(PayPalPlusRequestDataProviderInterfaceFactory $requestDataProviderFactory)
    {
        $this->requestDataProviderFactory = $requestDataProviderFactory;
        return $this;
    }

    /**
     * @return CartItemRequestDataProviderInterfaceFactory
     */
    protected function getCartItemRequestProviderFactory()
    {
        return $this->cartItemRequestDataProviderFactory;
    }

    /**
     * @param CartItemRequestDataProviderInterfaceFactory $cartItemRequestDataProviderFactory
     * @return self
     */
    protected function setCartItemRequestProviderFactory(CartItemRequestDataProviderInterfaceFactory $cartItemRequestDataProviderFactory)
    {
        $this->cartItemRequestDataProviderFactory = $cartItemRequestDataProviderFactory;
        return $this;
    }

    /**
     * @param Item $item
     * @return CartItemRequestDataProviderInterface
     */
    protected function createCartItemRequestDataProvider(Item $item)
    {
        return $this->getCartItemRequestProviderFactory()->create([
            'item' => $item
        ]);
    }

    /**
     * @return BoletoTransaction
     */
    protected function getTransaction()
    {
        return $this->transaction;
    }

    /**
     * @param BoletoTransaction $transaction
     * @return RequestBuilder
     */
    protected function setTransaction(BoletoTransaction $transaction)
    {
        $this->transaction = $transaction;
        return $this;
    }

    /**
     * @return OrderAdapterInterface
     */
    protected function getOrderAdapter()
    {
        return $this->orderAdapter;
    }

    /**
     * @param OrderAdapterInterface $orderAdapter
     * @return $this
     */
    protected function setOrderAdapter(OrderAdapterInterface $orderAdapter)
    {
        $this->orderAdapter = $orderAdapter;
        return $this;
    }

    /**
     * @return InfoInterface
     */
    public function getPaymentData()
    {
        return $this->paymentData;
    }

    /**
     * @param InfoInterface $paymentData
     * @return $this
     */
    protected function setPaymentData(InfoInterface $paymentData)
    {
        $this->paymentData = $paymentData;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getCart()
    {
        return $this->cart;
    }

    /**
     * @param $cart
     */
    public function setCart($cart)
    {
        $this->cart = $cart;
    }

    /**
     * @param $config
     */
    public function setConfig($config)
    {
        $this->config = $config;

    }

    /**
     * @return mixed
     */
    public function getConfig()
    {
        return $this->config;
    }

    /**
     * @return mixed
     */
    public function setConfigCreditCard($configCreditCard)
    {
        $this->configCreditCard = $configCreditCard;

        return $this;
    }
    /**
     * @return mixed
     */
    public function getModuleHelper()
    {
        return $this->moduleHelper;
    }

    /**
     * @return mixed
     */
    public function setModuleHelper($moduleHelper)
    {
        $this->moduleHelper = $moduleHelper;

        return $this;
    }

    /**
     * Builds and returns the api context to be used in PayPal Plus API
     *
     * @return \PayPal\Rest\ApiContext
     */
    protected function getApiContext()
    {

        $debug = $this->getConfigProvider()->isDebugEnabled();
        $this->configId = $this->getConfigProvider()->getClientId();
        $this->secretId = $this->getConfigProvider()->getSecretId();

        if($debug == 1){
            $debug = true;
        }else{
            $debug = false;
        }
        $apiContext = new \PayPal\Rest\ApiContext(
            new \PayPal\Auth\OAuthTokenCredential(
                $this->configId,
                $this->secretId
            )
        );
        $apiContext->setConfig(
            [
                'http.headers.PayPal-Partner-Attribution-Id' => 'MagentoBrazil_Ecom_PPPlus2',
                'mode' => $this->configProvider->isModeSandbox() ? 'sandbox' : 'live',
                'log.LogEnabled' => $debug,
                'log.FileName' => BP . '/var/log/paypalbr/paypal_plus/paypal-plus-' . date('Y-m-d') . '.log',
                'log.LogLevel' => 'DEBUG',
                'cache.enabled' => true,
                'http.CURLOPT_SSLVERSION' => 'CURL_SSLVERSION_TLSv1_2'
            ]
        );
        return $apiContext;
    }

    /**
     * Restores the payment from session and returns it
     *
     * @return \PayPal\Api\Payment
     */
    protected function restoreAndGetPayment($apiContext)
    {
        $paypalPaymentId = $this->getCheckoutSession()->getPaypalPaymentId();
        $paypalPayment = \PayPal\Api\Payment::get($paypalPaymentId, $apiContext);
        
        return $paypalPayment;
    }

    /**
     * @return mixed
     */
    protected function createPatch($apiContext, $requestDataProvider)
    {
        $paypalPayment = $this->restoreAndGetPayment($apiContext);
        $patchRequest = new \PayPal\Api\PatchRequest();

        $order = $this->_checkoutSession->getLastRealOrder();

        $itemListPatch = new \PayPal\Api\Patch();
        $itemListPatch
            ->setOp('add')
            ->setPath('/transactions/0/invoice_number')
            ->setValue($requestDataProvider->getTransactionReference());
        $patchRequest->addPatch($itemListPatch);

        if ($this->getConfig()->getStoreName()) {
            $descriptionValue = __('Invoice #%1 ', $requestDataProvider->getTransactionReference()) . __('- Store: #%1', $this->getConfig()->getStoreName());
        }else{
            $descriptionValue = __('Invoice #%1 ', $requestDataProvider->getTransactionReference());
        }

        $description = new \PayPal\Api\Patch();
        $description
            ->setOp('add')
            ->setPath('/transactions/0/description')
            ->setValue($descriptionValue );
        $patchRequest->addPatch($description);

        $paypalPayment->update($patchRequest, $apiContext);

        return $paypalPayment;
    }

    /**
     * @return mixed
     */
    protected function createPaymentExecution($paypalPayment, $apiContext, $payerId)
    {
        $paymentExecution = new \PayPal\Api\PaymentExecution();
        $paymentExecution->setPayerId($payerId);
        try {
            $paypalPayment->execute($paymentExecution, $apiContext);
        } catch (\Exception $e) {
            $error_msg = json_decode($e->getData());
            switch ($error_msg->name) {
                case 'INSTRUMENT_DECLINED':
                case 'CREDIT_CARD_REFUSED':
                case 'TRANSACTION_REFUSED_BY_PAYPAL_RISK':
                case 'PAYER_CANNOT_PAY':
                case 'PAYER_ACCOUNT_RESTRICTED':
                case 'PAYER_ACCOUNT_LOCKED_OR_CLOSED':
                case 'PAYEE_ACCOUNT_RESTRICTED':
                case 'TRANSACTION_REFUSED':
                    if (!$this->getConfig()->getToggle()) {
                        throw new \InvalidArgumentException($error_msg->name);
                    }
                    break;
                
                default:
                    throw new \LogicException($error_msg->name);
                    break;
            }

            return $error_msg;
        }
        

        return $paypalPayment;
    }

    /**
     * @param $requestDataProvider
     * @return mixed
     */
    protected function createNewRequest($requestDataProvider)
    {
        $apiContext = $this->getApiContext();
        $paypalPayment = $this->createPatch($apiContext, $requestDataProvider);
        $paypalPaymentExecution = $this->createPaymentExecution($paypalPayment, $apiContext, $requestDataProvider->getPayerId());

        return $paypalPaymentExecution;

    }

    /**
     * @return mixed
     */
    public function getCheckoutSession()
    {
        return $this->checkoutSession;
    }

    /**
     * @param mixed $checkoutSession
     *
     * @return self
     */
    public function setCheckoutSession($checkoutSession)
    {
        $this->checkoutSession = $checkoutSession;

        return $this;
    }

    /**
     * @return mixed
     */
    public function getConfigProvider()
    {
        return $this->configProvider;
    }

    /**
     * @param mixed $configProvider
     *
     * @return self
     */
    public function setConfigProvider($configProvider)
    {
        $this->configProvider = $configProvider;

        return $this;
    }
}
