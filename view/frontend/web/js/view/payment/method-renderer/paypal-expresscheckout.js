define([
    'PayPalBR_PayPal/js/view/payment/default-paypal-ec',
    'Magento_Paypal/js/model/iframe',
    'jquery',
    'ko',
    'Magento_Checkout/js/checkout-data',
    'Magento_Checkout/js/action/select-payment-method',
    'Magento_Checkout/js/model/quote',
    'Magento_Catalog/js/price-utils',
    'mage/translate'
], function (
        Component,
        iframe,
        $,
        ko,
        checkoutData,
        selectPaymentMethodAction,
        quote,
        priceUtils,
        $t
        ) {
    'use strict';

    return Component.extend({
        defaults: {
            template: 'PayPalBR_PayPal/payment/paypal-expresscheckout',
            paymentReady: true,
            isPaymentReady: false,
            formattedTotalLabel: ko.observable('')
        },
        isPaymentReady: false,

        getNamePay: function () {
            return "PayPal " + window.checkoutConfig.payment.paypalbr_expresscheckout.exibitionName;
        },

        isActive: function () {
            return window.checkoutConfig.payment.paypalbr_expresscheckout.active;
        },

        initialize: function () {
            this._super();

            quote.totals.subscribe(() => {
                const total = quote.totals().grand_total;
                const formattedPrice = priceUtils.formatPrice(total, quote.getPriceFormat());
                this.formattedTotalLabel($t('Order Total: ') + formattedPrice);
            });
        },

        getUrlMagento: function (argument) {
            return window.checkoutConfig.staticBaseUrl  + 'frontend/Magento/luma/pt_BR/PayPalBR_PayPal/images/';
        },

        /**
         * Select current payment token
         */
        selectPaymentMethod: function () {

            if (!this.isPaymentReady) {
                this.isPaymentReady = true;
                this.runPayPal();
            }

            selectPaymentMethodAction(this.getData());
            checkoutData.setSelectedPaymentMethod(this.item.method);

            return true;
        },

        runPayPal: function (approvalUrl) {

            var self = this;
            // Config buttons
            var colorButton = window.checkoutConfig.payment.paypalbr_expresscheckout.color;
            var shapeButton = window.checkoutConfig.payment.paypalbr_expresscheckout.shape;
            var buttonButton = window.checkoutConfig.payment.paypalbr_expresscheckout.button;

            paypal.Buttons({
                fundingSource: paypal.FUNDING.PAYPAL,
                style: {
                    label: buttonButton,
                    size: 'responsive', // small | medium | large | responsive
                    shape: shapeButton, // pill | rect
                    color: colorButton   // gold | blue | silver | black
                },

                createOrder: function(data, actions) {
                    return fetch('/expresscheckout/payment/paypal/', {
                        method: 'post'
                    }).then(function(res) {
                        return res.json();
                    }).then(function(orderData) {
                        if (!orderData.id) {
                            var jsonObj = JSON.parse(orderData.message);

                            messageList.addErrorMessage({
                                message: `${jsonObj.name}: ${jsonObj.message}`
                            });

                            return null;
                        }
                        self.orderData = orderData;
                        return orderData.id;
                    });
                },

                onApprove: function(data, actions) {
                    return fetch('/expresscheckout/transaction/approve/', {
                        method: 'post',
                    }).then(function(res) {
                        return res.json();
                    }).then(function(orderData) {
                        var errorDetail = Array.isArray(orderData.details) && orderData.details[0];

                        if (errorDetail && errorDetail.issue === 'INSTRUMENT_DECLINED') {
                            return actions.restart();
                        }

                        if (errorDetail) {
                            var msg = 'Sorry, your transaction could not be processed.';
                            if (errorDetail.description) msg += '\n\n' + errorDetail.description;
                            if (orderData.debug_id) msg += ' (' + orderData.debug_id + ')';
                            return alert(msg);
                        }

                        self.placePendingOrder();
                    });
                },
                onCancel: function(data, actions) {
                    messageList.addErrorMessage({
                        message: `aborted: payment canceled by the user`
                    });
                },
                onError: function(err) {
                    console.log(err);
                }

            }).render('#paypal-button-container');
        },

        placePendingOrder: function () {
            if (this.placeOrder()) {
                document.addEventListener('click', iframe.stopEventPropagation, true);
            }
        },

        getData: function () {
            return {
                'method': this.item.method,
                'additional_data': {
                    'orderData': JSON.stringify(this.orderData)
                }
            };
        },

    });
});


