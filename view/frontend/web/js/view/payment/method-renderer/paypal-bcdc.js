define([
    'PayPalBR_PayPal/js/view/payment/default-paypal-ec',
    'Magento_Paypal/js/model/iframe',
    'jquery',
    'ko',
    'Magento_Ui/js/model/messageList',
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
        messageList,
        checkoutData,
        selectPaymentMethodAction,
        quote,
        priceUtils,
        $t
        ) {
    'use strict';

    return Component.extend({
        defaults: {
            template: 'PayPalBR_PayPal/payment/paypal-bcdc',
            paymentReady: true,
            formattedTotalLabel: ko.observable(''),
        },
        isPaymentReady: false,
        orderData: null,

        getNamePay: function () {
            return window.checkoutConfig.payment.paypalbr_bcdc.exibitionName;
        },

        isActive: function () {
            return window.checkoutConfig.payment.paypalbr_bcdc.active;
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
                this.runPaypal();
            }

            selectPaymentMethodAction(this.getData());
            checkoutData.setSelectedPaymentMethod(this.item.method);

            return true;
        },

        runPaypal: function () {
            var self = this
            paypal.Buttons({
                fundingSource: paypal.FUNDING.CARD,
                createOrder: function(data, actions) {
                    return fetch('/expresscheckout/payment/paypal/', {
                        method: 'post'
                    }).then(function(res) {
                        return res.json();
                    }).then(function(orderData) {
                        if (!orderData.id) {
                            var jsonObj = JSON.parse(orderData.message);

                            messageList.addErrorMessage({
                                message: `${jsonObj.name}: ${jsonObj.message}}`
                            });

                            return null;
                        }
                        self.orderData = orderData;
                        return orderData.id;
                    }).catch(function(err) {
                        messageList.addErrorMessage({
                            message: $t('An error occurred while processing your payment. Please try again.')
                        });
                    });
                },

                // Call your server to finalize the transaction
                onApprove: function(data, actions) {
                    console.log('data', data);
                    return fetch('/expresscheckout/transaction/approve/', {
                        method: 'post'
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
                        console.log('Capture result', self.orderData, JSON.stringify(self.orderData, null, 2));

                        self.placePendingOrder();
                    }).catch(function(err) {
                        messageList.addErrorMessage({
                            message: $t('An error occurred while approving your payment. Please try again.')
                        });
                    });
                },

                onError: function(err) {
                    console.log(err);
                    messageList.addErrorMessage({
                        message: $t('An unexpected error occurred. Please try again.')
                    });
                }

            }).render('#paypal-bcdc-button-container');
        },

        initObservable: function () {
            this._super()
                    .observe([
                        'paymentData',
                    ]);

            return this;
        },

        getData: function () {
            return {
                'method': this.item.method,
                'additional_data': {
                    'orderData': JSON.stringify(this.orderData)
                }
            };
        },

        placePendingOrder: function () {
            var self = this;
            if (this.placeOrder()) {
                document.addEventListener('click', iframe.stopEventPropagation, true);
            }
        },
    });
});
