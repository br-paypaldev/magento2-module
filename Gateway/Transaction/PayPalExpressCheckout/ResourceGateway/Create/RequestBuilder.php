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

namespace PayPalBR\PayPal\Gateway\Transaction\PayPalExpressCheckout\ResourceGateway\Create;

use Magento\Payment\Gateway\Data\PaymentDataObjectInterface;
use Magento\Payment\Gateway\Request\BuilderInterface;
use PayPalBR\PayPal\Model\PayPalRequests;

class RequestBuilder implements BuilderInterface
{
    const MODULE_NAME = 'PayPalBR_PayPal';

    protected $paypalRequests;

    public function __construct(
        PayPalRequests $paypalRequests,
    ) {
        $this->paypalRequests = $paypalRequests;
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

        return $this->paypalRequests->getOrder();
    }
}
