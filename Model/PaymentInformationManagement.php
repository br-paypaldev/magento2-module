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

use Magento\Framework\Exception\CouldNotSaveException;
use Psr\Log\LoggerInterface;
use Magento\Checkout\Model\PaymentInformationManagement as MPaymentInformationManagement;

/**
 * Payment information management service.
 *
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class PaymentInformationManagement extends MPaymentInformationManagement
{
    /**
     * @var \Psr\Log\LoggerInterface
     */
    private $logger;

    public function __construct(
        \Magento\Quote\Api\BillingAddressManagementInterface $billingAddressManagement,
        \Magento\Quote\Api\PaymentMethodManagementInterface $paymentMethodManagement,
        \Magento\Quote\Api\CartManagementInterface $cartManagement,
        \Magento\Checkout\Model\PaymentDetailsFactory $paymentDetailsFactory,
        \Magento\Quote\Api\CartTotalRepositoryInterface $cartTotalsRepository,
        LoggerInterface $logger
    ) {
        parent::__construct($billingAddressManagement, $paymentMethodManagement, $cartManagement, $paymentDetailsFactory,$cartTotalsRepository);
        $this->logger = $logger;
    }

    /**
     * @inheritdoc
     */
    public function savePaymentInformationAndPlaceOrder(
        $cartId,
        \Magento\Quote\Api\Data\PaymentInterface $paymentMethod,
        \Magento\Quote\Api\Data\AddressInterface $billingAddress = null
    ) {
        $this->savePaymentInformation($cartId, $paymentMethod, $billingAddress);
        try {
            $orderId = $this->cartManagement->placeOrder($cartId);
        } catch (\Magento\Framework\Exception\LocalizedException $e) {
            $this->logger->critical(
                'Placing an order with quote_id ' . $cartId . ' is failed: ' . $e->getMessage()
            );
            throw new CouldNotSaveException(
                __($e->getMessage()),
                $e
            );
        } catch (\Exception $e) {
            $this->logger->critical($e);
            throw new CouldNotSaveException(
                __('A server error stopped your order from being placed. Please try to place your order again. ' . $e->getMessage()),
                $e
            );
        }
        return $orderId;
    }

}
