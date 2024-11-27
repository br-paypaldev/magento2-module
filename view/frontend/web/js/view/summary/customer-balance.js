/**
 * PayPalBR PayPalEnterprise
 *
 * @package PayPalBR|PayPalEnterprise
 * @author Vitor Nicchio Alves <vitor@imaginationmedia.com>
 * @copyright Copyright (c) 2020 Imagination Media (https://www.imaginationmedia.com/)
 * @license https://opensource.org/licenses/OSL-3.0.php Open Software License 3.0
 */

define([
    'jquery',
    'ko',
    'Magento_CustomerBalance/js/view/summary/customer-balance',
    'Magento_CustomerBalance/js/action/use-balance',
    'Magento_CustomerBalance/js/action/remove-balance',
    'Magento_CustomerBalance/js/view/payment/customer-balance',
    'PayPalBR_PayPal/js/model/full-screen-loader-paypal',
    'helperPaypal',
    'Magento_Checkout/js/model/quote',
    'Magento_Checkout/js/model/totals',
    'Magento_Checkout/js/model/full-screen-loader',
    'mage/url',
    'mage/storage',
    'mage/translate'
], function (
    $,
    ko,
    customerBalance,
    useBalanceAction,
    removeCustomerBalance,
    customerBalancePayment,
    fullScreenLoaderPayPal,
    helper,
    quote,
    totals,
    fullScreenLoader,
    urlBuilder,
    storage
) {
    'use strict';

    var amountSubstracted = ko.observable(window.checkoutConfig.payment.customerBalance.amountSubstracted);

    return customerBalance.extend({
        defaults: {
            template: 'Magento_CustomerBalance/summary/customer-balance',
            storeCreditFormName: 'checkout.steps.billing-step.payment.afterMethods.storeCredit',
            modules: {
                storeCreditForm: '${ $.storeCreditFormName }'
            }
        },
        totals: totals.totals(),
        defaultQuote: quote,
        shippingValue: quote.totals().base_shipping_amount,

        /**
         * Send request to use balance
         */
        sendRequest: function () {
            removeCustomerBalance();
            this.storeCreditForm().setAmountSubstracted(false);
            customerBalancePayment();

            var customerBalanceAmount = window.checkoutConfig.payment.customerBalance.balance;
            var grandTotal = quote.totals().base_grand_total;
            var payPalPlusSelected = document.getElementById('paypalbr_paypalplus').checked;
            customerBalanceAmount = parseFloat(parseFloat(customerBalanceAmount).toFixed(2));
            grandTotal = parseFloat(parseFloat(grandTotal).toFixed(2));

            if(payPalPlusSelected) {
                fullScreenLoader.startLoader();
                setTimeout(function () {
                    helper.initializeIframe();
                }, 1000);
            }
        }
    });
});
