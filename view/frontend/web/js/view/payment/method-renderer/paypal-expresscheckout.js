define([
    'PayPalBR_PayPal/js/view/payment/default-paypal-ec',
    'Magento_Paypal/js/model/iframe',
    'jquery',
    'Magento_Checkout/js/model/quote',
    'mage/storage',
    'Magento_Checkout/js/model/error-processor',
    'Magento_Checkout/js/model/full-screen-loader',
    'PayPalBR_PayPal/js/model/full-screen-loader-paypal',
    'Magento_Checkout/js/checkout-data',
    'Magento_Checkout/js/action/select-payment-method',
    'Magento_Checkout/js/model/postcode-validator',
    'ko',
    'mage/url',
    'pplec',
    'mage/translate'
], function (
        Component,
        iframe,
        $,
        quote,
        storage,
        errorProcesor,
        fullScreenLoader,
        fullScreenLoaderPayPal,
        checkoutData,
        selectPaymentMethodAction,
        postcodeValidator,
        ko,
        urlBuilder,
        ppec
        ) {
    'use strict';

    return Component.extend({
        defaults: {
            template: 'PayPalBR_PayPal/payment/paypal-expresscheckout',
            paymentReady: true,
            paypalPayerId: '',
            payerIdCustomer: '',
            token: '',
            term: '',
            isPaymentReady: false,
            CREATE_URL: '',
        },
        breakError: false,
        errorProcessor: errorProcesor,
        customerInfo: quote.billingAddress._latestValue,
        paymentApiServiceUrl: 'paypalplus/payment',
        isPaymentReady: false,
        defaultQuote: quote,
        shippingValue: quote.totals().base_shipping_amount,

        getNamePay: function () {
            return "PayPal " + window.checkoutConfig.payment.paypalbr_expresscheckout.exibitionName;
        },

        isActive: function () {
            return window.checkoutConfig.payment.paypalbr_expresscheckout.active;
        },

        paypalObject: {},

        initialize: function () {

            this._super();
            // this._render();
            var self = this;

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
            var mode = window.checkoutConfig.payment.paypalbr_expresscheckout.mode;
            var locale = window.checkoutConfig.payment.paypalbr_expresscheckout.locale;

            // fullScreenLoaderPayPal.startLoader();

            self.CREATE_URL = urlBuilder.build('paypalplus/payment/expresscheckout');

            this.paypalObject = paypal.Button.render({

                env: mode, // sandbox | production
                locale: locale,

                // Show the buyer a 'Pay Now' button in the checkout flow
                // commit: true,


                style: {
                    label: buttonButton,
                    size: 'responsive', // small | medium | large | responsive
                    shape: shapeButton, // pill | rect
                    color: colorButton   // gold | blue | silver | black
                },

                // payment() is called when the button is clicked
                payment: function (data, actions) {

                    // Make a call to your server to set up the payment
                    return paypal.request.post(self.CREATE_URL)
                            .then(function (res) {
                                return res.id;
                            });
                },
                // onAuthorize() is called when the buyer approves the payment
                onAuthorize: function (data, actions) {

                    $('#paypalbr_expresscheckout_payId').val(data.paymentID);
                    $('#paypalbr_expresscheckout_payerId').val(data.payerID);
                    $('#paypalbr_v_token').val(data.paymentToken);

                    return actions.payment.get().then(function (data) {
                        var term;
                        if (typeof data.credit_financing_offered === 'undefined') {
                            term = '1';
                            term = term;
                        } else {
                            term = data.credit_financing_offered.term;
                        }

                        $('#paypalbr_expresscheckout_term').val(term);
                        self.placePendingOrder();
                    });

                },
                onError: function (err) {
                    alert($.mage.__('An unexpected error occurred, please try again.'));
                    location.reload();
                }

            }, '#paypal-button-container');
        },

        placePendingOrder: function () {
            var self = this;
            if (this.placeOrder()) {
                document.addEventListener('click', iframe.stopEventPropagation, true);
            }
        },

        doContinue: function () {
            var self = this;
            if (this.validateAddress() !== false) {
                self.paypalObject.doContinue();
            } else {
                var message = {
                    message: $.mage.__('Please verify shipping address.')
                };
                self.messageContainer.addErrorMessage(message);
            }
        },

        getData: function () {
            return {
                'method': this.item.method,
                'additional_data': {
                    'payId': $('#paypalbr_expresscheckout_payId').val(),
                    'payerId': $('#paypalbr_expresscheckout_payerId').val(),
                    'token': $('#paypalbr_expresscheckout_token').val(),
                    'term': $('#paypalbr_expresscheckout_term').val(),
                }
            };
        },

        initObservable: function () {
            this._super()
                    .observe([
                        'payId_expresscheckout',
                        'payerId_expresscheckout',
                        'token_expresscheckout',
                        'term_expresscheckout',
                    ]);

            return this;
        },

        /**
         * Validate shipping address.
         *
         * @returns {Boolean}
         */
        validateAddress: function () {


            this.customerData = quote.billingAddress._latestValue;
            if (!this.customerData.city) {
                this.customerData = quote.shippingAddress._latestValue;
            }
            if (typeof this.customerData.city === 'undefined' || this.customerData.city.length === 0) {
                return false;
            }

            if (typeof this.customerData.countryId === 'undefined' || this.customerData.countryId.length === 0) {
                return false;
            }

            if (typeof this.customerData.postcode === 'undefined' || this.customerData.postcode.length === 0) {
                return false;
            }

            if (typeof this.customerData.street === 'undefined' || this.customerData.street[0].length === 0) {
                return false;
            }
            if (typeof this.customerData.region === 'undefined' || this.customerData.region.length === 0) {
                return false;
            }
            return true;
        }
    });
});


