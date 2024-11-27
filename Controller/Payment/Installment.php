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

namespace PayPalBR\PayPal\Controller\Payment;

use Magento\Framework\App\Action\Context;
use Magento\Framework\Controller\Result\JsonFactory;

class Installment extends \Magento\Framework\App\Action\Action
{

    /**
     * @var \Magento\Framework\Controller\Result\JsonFactory
     */
    protected $jsonFactory;

    protected $scopeConfig;

    /**
     * Contains checkout session
     *
     * @var \Magento\Checkout\Model\Session
     */
    protected $checkoutSession;

    protected $installmentConfig;

    /**
     * Index constructor.
     *
     * @param \Magento\Framework\App\Action\Context $context
     * @param JsonFactory $jsonFactory
     */
    public function __construct(
        Context $context,
        JsonFactory $jsonFactory,
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Magento\Checkout\Model\Session $checkoutSession,
        \PayPalBR\PayPal\Helper\Installment $installmentConfig
    ) {
        $this->jsonFactory = $jsonFactory;
        $this->scopeConfig = $scopeConfig;
        $this->installmentConfig = $installmentConfig;
        $this->checkoutSession = $checkoutSession;

        parent::__construct($context);
    }

    public function execute()
    {
        $quote = $this->checkoutSession->getQuote();

        $this->checkoutSession->setInstallment(0);
        $this->checkoutSession->setFeeValue(0);
        $quote->getPayment()->unsAdditionalInformation('paypalplus_installment');
        $quote->getPayment()->save();
        $resultJson = $this->jsonFactory->create();

        $installments = $this->getValues();

        $object = [
            "enabled" => boolval($this->scopeConfig->getValue('payment/paypalbr_paypalplus/cost_to_buyer_active')),
            "options" => $installments
        ];

        $resultJson
            ->setHttpResponseCode(200)
            ->setData($object);

        return $resultJson;
    }

    private function getValues()
    {
        $result = array();

        $qty_installment = $this->scopeConfig->getValue('payment/paypalbr_paypalplus/cost_to_buyer_qty_installments');

        $hasDiscount = $this->scopeConfig->getValue('payment/paypalbr_paypalplus/cost_to_buyer_has_discount');

        for ($i = 1; $i <= $qty_installment; $i++) {
            $cost = $i == 1 ? 0 : $this->scopeConfig->getValue('payment/paypalbr_paypalplus/cost_to_buyer_installment_'
                . strval($i));

            $installmentPrice = $this->installmentConfig->getInstallmentPrice(floatval($cost), $i);

            $finalTotal = $this->installmentConfig->getFinalTotal(floatval($cost), $i);

            $element = [];

            $element["value"] = $i;
            $element["label"] = strval($i) . "x de R$ " . $installmentPrice . " - Total R$ " . $finalTotal;
            $element["fee"] = floatval($finalTotal);

            if($i != 1 && $cost != 0){
                $element["label"] = $element["label"] . " (Juros " . $cost . "%)";
            } elseif ($i == 1 && $hasDiscount) {
                $element["label"] = $element["label"] . " (Desconto de R$ " . $this->installmentConfig->getDiscountValue() . ")";
            } else {
                $element["label"] = $element["label"] . " (Sem Juros)";
            }

            $minimalInstallment = $this->scopeConfig->getValue(
                'payment/paypalbr_paypalplus/cost_to_buyer_minimal_installment'
            );
            $installmentPrice = $this->installmentConfig->getInstallmentPrice(floatval($cost), $i);

            if (floatval($installmentPrice) > 10 && floatval($installmentPrice) > floatval($minimalInstallment)) {
                array_push($result, $element);
            }
        }

        return $result;
    }
}
