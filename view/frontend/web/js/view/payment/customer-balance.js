/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

define([
    'jquery',
    'ko',
    'Magento_CustomerBalance/js/view/payment/customer-balance',
    'Magento_CustomerBalance/js/action/use-balance',
    'PayPalBR_PayPal/js/model/full-screen-loader-paypal',
    'Magento_Checkout/js/model/quote',
    'Magento_Checkout/js/model/full-screen-loader',
    'helperPaypal',
    'mage/url',
    'mage/storage',
    'mage/translate'
], function (
    $,
    ko,
    customerBalance,
    useBalanceAction,
    fullScreenLoaderPayPal,
    quote,
    fullScreenLoader,
    helper,
    urlBuilder,
    storage
) {
    'use strict';

    var amountSubstracted = ko.observable(window.checkoutConfig.payment.customerBalance.amountSubstracted);

    return customerBalance.extend({
        amountSubstracted: window.checkoutConfig.payment.customerBalance.amountSubstracted,
        defaultQuote: quote,
        shippingValue: quote.totals().base_shipping_amount,

        /**
         * Send request to use balance
         */
        sendRequest: function () {
            amountSubstracted(true);
            useBalanceAction();

            var customerBalanceAmount = window.checkoutConfig.payment.customerBalance.balance;
            var grandTotal = quote.totals().base_grand_total;
            var payPalPlusSelected = document.getElementById('paypalbr_paypalplus').checked;
            customerBalanceAmount = parseFloat(parseFloat(customerBalanceAmount).toFixed(2));
            grandTotal = parseFloat(parseFloat(grandTotal).toFixed(2));

            if((customerBalanceAmount < grandTotal) && (payPalPlusSelected)) {
                fullScreenLoader.startLoader();
                setTimeout(function(){
                    helper.initializeIframe();
                }, 1000);
            }
        }
    });
});
