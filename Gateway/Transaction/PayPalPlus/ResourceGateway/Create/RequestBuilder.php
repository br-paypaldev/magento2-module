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
use Magento\Framework\App\ProductMetadataInterface;
use PayPalBR\PayPal\Gateway\Transaction\Base\Config\ConfigInterface;
use PayPalBR\PayPal\Api\PayPalPlusRequestDataProviderInterfaceFactory;
use PayPalBR\PayPal\Api\CartItemRequestDataProviderInterfaceFactory;
use PayPalBR\PayPal\Model\PayPalPlus\ConfigProvider;
use PayPalBR\PayPal\Model\PaypalPlusApi;
use Magento\Framework\Filesystem\DirectoryList;


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
    protected $_productMetadata;
    protected $paypalPlusApi;
    protected $dir;

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
        ProductMetadataInterface $productMetadata,
        ConfigProvider $configProvider,
        PaypalPlusApi $paypalPlusApi,
        DirectoryList $dir
    ) {
        $this->setRequestDataProviderFactory($requestDataProviderFactory);
        $this->setCartItemRequestProviderFactory($cartItemRequestDataProviderFactory);
        $this->setCart($cart);
        $this->setConfig($config);
        $this->setCheckoutSession($checkoutSession);
        $this->setConfigProvider($configProvider);
        $this->_checkoutSession = $checkoutSession;
        $this->_productMetadata = $productMetadata;
        $this->paypalPlusApi = $paypalPlusApi;
        $this->dir = $dir;

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
        $edition = $this->_productMetadata->getEdition();

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
                'log.FileName' => $this->dir->getPath('log') . '/paypal-auth.cache',
                'cache.FileName' => $this->dir->getPath('log') . '/auth.cache',
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
        if(empty($paypalPaymentId)) {
            $paypalPaymentId = $this->paypalPlusApi->get();
        }
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
                case 'INTERNAL_SERVICE_ERROR':
                    try{
                        $paypalPayment->execute($paymentExecution, $apiContext);
                    } catch(\Exception $e){
                        $message = 'Ocorreu um erro na captura do pagamento, por favor tente novamente e caso o problema persista entre em contato. #' . $error_msg->debug_id;
                        throw new \Magento\Framework\Exception\NotFoundException(__($message));
                    }
                    break;
                case 'INSTRUMENT_DECLINED':
                    $message = 'O seu pagamento não foi aprovado pelo banco emissor, por favor tente novamente. #' . $error_msg->debug_id;
                    throw new \Magento\Framework\Exception\NotFoundException(__($message));
                    break;
                case 'CREDIT_CARD_REFUSED':
                case 'TRANSACTION_REFUSED_BY_PAYPAL_RISK':
                case 'PAYER_CANNOT_PAY':
                case 'PAYER_ACCOUNT_RESTRICTED':
                case 'PAYER_ACCOUNT_LOCKED_OR_CLOSED':
                case 'PAYEE_ACCOUNT_RESTRICTED':
                case 'TRANSACTION_REFUSED':
                    $message = 'O seu pagamento não foi aprovado, por favor tente novamente. #' . $error_msg->debug_id;
                    throw new \Magento\Framework\Exception\NotFoundException(__($message));
                    break;

                default:
                    $message = 'Ocorreu um erro na captura do pagamento, por favor tente novamente e caso o problema persista entre em contato. #' . $error_msg->debug_id;
                    throw new \Magento\Framework\Exception\NotFoundException(__($message));
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
        //PAYP-95: Check amount send on the payment request(/payment) with the current grand total.
        $paymentAmount =  $this->checkoutSession->getAmountTotal();
        $grandTotal =  $this->checkoutSession->getQuote()->getBaseGrandTotal();

        if(strval($paymentAmount) != strval($grandTotal)) {
            throw new \Magento\Framework\Exception\NotFoundException(__('Prezado cliente, identificamos uma divergência nos valores do seu carrinho, por favor recarregue a página ou refaça o processo de compra'));
        }

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
