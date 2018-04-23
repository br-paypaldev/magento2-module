/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

/**
 * @api
 */
define([
    'Magento_Checkout/js/model/quote',
    'jquery',
    'Magento_Checkout/js/model/full-screen-loader',
    'PayPalBR_PayPal/js/model/full-screen-loader-paypal',
    'PayPalBR_PayPal/js/view/payment/default',
    'Magento_Paypal/js/model/iframe'
], function (quote, $, fullScreenLoader, fullScreenLoaderPayPal, Component, iframe) {
    'use strict';

    return function (approvalUrl) {

        debugger;
        // fullScreenLoaderPayPal.startLoader();
        var storage;
        var self = this;
        var telephone = '';
        var firstName = '';
        var lastName = '';
        var email = '';
        var taxVat = '';
        var customerData = window.checkoutConfig.customerData;
        var mode = window.checkoutConfig.payment.paypalbr_paypalplus.mode === "1" ? 'sandbox' : 'live';
        var paypalObject = {};
        var defaultQuote = quote;
        var shippingValue = quote.totals().base_shipping_amount;
        storage = $.initNamespaceStorage('paypal-data');
        storage = $.localStorage;

        var isEmpty = true;
        for (var i in customerData) {
            if (customerData.hasOwnProperty(i)) {
                isEmpty = false;
            }
        }

        if (isEmpty) {
            telephone = quote.shippingAddress().telephone ? quote.shippingAddress().telephone : storage.get('telephone');
        } else {
            telephone = quote.shippingAddress().telephone;
        }

        if (isEmpty) {
            firstName = quote.shippingAddress().firstname ? quote.shippingAddress().firstname : storage.get('firstName');
        } else {
            firstName = customerData.firstname;
        }

        if (isEmpty) {
            lastName = quote.shippingAddress().lastname ? quote.shippingAddress().lastname : storage.get('lastName');
        } else {
            lastName = customerData.lastname;
        }

        if (isEmpty) {
            email = quote.guestEmail ? quote.guestEmail : storage.get('email');
        } else {
            email = customerData.email;
        }

        if (isEmpty) {
            taxVat = quote.shippingAddress().vatId ? quote.shippingAddress().vatId : storage.get('taxVat');
        } else {
            taxVat = customerData.taxvat;
        }


        storage.set(
            'paypal-data',
            {
                'firstName': firstName,
                'lastName': lastName,
                'email': email,
                'taxVat': taxVat,
                'telephone': telephone
            }
        );

        if (window.checkoutConfig.payment.paypalbr_paypalplus.iframe_height_active === '1') {
            var height = window.checkoutConfig.payment.paypalbr_paypalplus.iframe_height;
        } else {
            var height = '';
        }

        window.checkoutConfig.payment.paypalbr_paypalplus.paypalObject = PAYPAL.apps.PPP(
            {
                "approvalUrl": approvalUrl,
                "placeholder": "ppplus",
                "mode": mode,
                "payerFirstName": firstName,
                "payerLastName": lastName,
                "payerPhone": "055" + telephone,
                "payerEmail": email,
                "payerTaxId": taxVat,
                "payerTaxIdType": "BR_CPF",
                "language": "pt_BR",
                "country": "BR",
                "enableContinue": "continueButton",
                "disableContinue": "continueButton",
                "rememberedCards": window.checkoutConfig.payment.paypalbr_paypalplus.rememberedCard,
                "iframeHeight": height,

                onLoad: function () {
                    fullScreenLoaderPayPal.stopLoader();
                    console.log("Iframe successfully lo aded !");
                    var height = $('#ppplus iframe').css('height');

                    $('#ppplus').css('max-height', height);
                },
                onContinue: function (rememberedCardsToken, payerId, token, term) {
                   debugger;
                    $('#continueButton').hide();
                    $('#payNowButton').show();

                    var payerId = payerId;

                    var message = {
                        message: $.mage.__('Payment is being processed.')
                    };
                    // self.messageContainer.addSuccessMessage(message);

                    if (typeof term !== 'undefined') {
                        term = term.term;
                        term = term.term;
                    } else {
                        term = '1';
                        term = term;
                    }
                    debugger;
                    $('#paypalbr_paypalplus_rememberedCardsToken').val(rememberedCardsToken);
                    $('#paypalbr_paypalplus_payerId').val(payerId);
                    $('#paypalbr_paypalplus_token').val(token);
                    $('#paypalbr_paypalplus_term').val(term);

                    $('#ppplus').hide();
                    // self.placePendingOrder();

                    if (iframe.placeOrder()) {
                        document.addEventListener('click', iframe.stopEventPropagation, true);
                    }

                },

                /**
                 * Handle iframe error
                 *
                 * @param {type} err
                 * @returns {undefined}
                 */
                onError: function (err) {
                    debugger;
                    var message = JSON.stringify(err.cause);
                    var ppplusError = message.replace(/[\\"]/g, '');
                    if (typeof err.cause !== 'undefined') {
                        switch (ppplusError) {

                            case "INTERNAL_SERVICE_ERROR":
                            case "SOCKET_HANG_UP":
                            case "socket hang up":
                            case "connect ECONNREFUSED":
                            case "connect ETIMEDOUT":
                            case "UNKNOWN_INTERNAL_ERROR":
                            case "fiWalletLifecycle_unknown_error":
                            case "Failed to decrypt term info":
                            case "RESOURCE_NOT_FOUND":
                            case "INTERNAL_SERVER_ERROR":
                                alert($.mage.__('An unexpected error occurred, please try again.'));
                                location.reload();
                            case "RISK_N_DECLINE":
                            case "NO_VALID_FUNDING_SOURCE_OR_RISK_REFUSED":
                                alert($.mage.__('Please use another card if the problem persists please contact PayPal (0800-047-4482).'));
                                location.reload();
                            case "TRY_ANOTHER_CARD":
                            case "NO_VALID_FUNDING_INSTRUMENT":
                                alert($.mage.__('Your payment was not approved. Please use another card if the problem persists please contact PayPal (0800-047-4482).'));
                                location.reload();
                                break;
                            case "CARD_ATTEMPT_INVALID":
                                alert($.mage.__('An unexpected error occurred, please try again.'));
                                location.reload();
                                break;
                            case "INVALID_OR_EXPIRED_TOKEN":
                                alert($.mage.__('Your session has expired, please try again.'));
                                location.reload();
                                break;
                            case "CHECK_ENTRY":
                                alert($.mage.__('Please review the credit card data entered.'));
                                location.reload();
                                break;
                            default: //unknown error & reload payment flow
                                alert($.mage.__('An unexpected error occurred, please try again.'));
                                location.reload();
                        }
                    }


                }
            }
        );

        if (shippingValue != defaultQuote.totals().base_shipping_amount) {
            shippingValue = defaultQuote.totals().base_shipping_amount;
            fullScreenLoaderPayPal.stopLoader();
        }

    };
});


