define([
    'PayPalBR_PayPal/js/view/payment/default',
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
    'Magento_Checkout/js/model/payment/additional-validators',
    'PayPalBR_PayPal/js/model/shipping-address/save-processor',
    'helperPaypal',
    'ko',
    'mage/url',
    'mage/translate',
    'ppplus'
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
    additionalValidators,
    shippingAddressSaveProcessor,
    helper,
    ko,
    urlBuilder,
    $t,
    ppplus
) {
    'use strict';

    return Component.extend({
        defaults: {
            template: 'PayPalBR_PayPal/payment/paypal-plus',
            paymentReady: true,
            paypalPayerId: '',
            payerIdCustomer: '',
            token: '',
            term: '',
            isPaymentReady: false,
        },
        breakError: false,
        errorProcessor: errorProcesor,
        customerInfo: quote.billingAddress._latestValue,
        paymentApiServiceUrl: 'paypalplus/payment',
        isPaymentReady: false,
        defaultQuote: quote,
        shippingValue: quote.totals().base_shipping_amount,


        getNamePay: function(){
            return "Cartão de Crédito " + window.checkoutConfig.payment.paypalbr_paypalplus.exibitionName;
        },

        isActive: function(){
            return window.checkoutConfig.payment.paypalbr_paypalplus.active;
        },

        paypalObject: {},

        initialize: function () {
            this._super();
            // this._render();
            var self = this;

            if (window.checkoutConfig.payment.paypalbr_paypalplus.options_payments === 1) {
                fullScreenLoaderPayPal.startLoader();
                if (!$('#ppplus').length) {

                    if (this.breakError) {
                        $('#iframe-warning').hide();
                        $('#iframe-error').show();
                        $('#continueButton').prop("disabled", true);
                        return false;
                    }

                    helper.initializeIframe(self);
                }
                window.checkoutConfig.payment.paypalbr_paypalplus.is_payment_ready = true;
                self.isPaymentReady = true;
            }
        },

        /**
         * Select current payment token
         */
        selectPaymentMethod: function () {
            var self = this;

            $('#ppplus').css('display', 'none');
            $('#continueButton').prop("disabled", true);

            if(quote.billingAddress._latestValue || quote.shippingAddress.length){ //Not initialize iframe if quote don't have address.
                if (this.validate() && additionalValidators.validate()) {
                    $('#iframe-error').hide();
                    $('#ppplus').parent().find('.payment-method-billing-address').css('display', 'none');
                    $('#ppplus').hide();
                    fullScreenLoaderPayPal.startLoader();

                    if (window.checkoutConfig.isFirecheckout) {
                        shippingAddressSaveProcessor.saveShippingAddress();
                    }

                    setTimeout(function () {
                        // if (!self.isPaymentReady || self.shippingValue != this.defaultQuote.totals().base_shipping_amount) {
                        if (!self.isPaymentReady || self.shippingValue != self.defaultQuote.totals().base_shipping_amount) {

                            // fullScreenLoaderPayPal.startLoader();
                            if ($('#ppplus').length) {

                                // if (this.breakError) {
                                if (self.breakError) {
                                    $('#iframe-warning').hide();
                                    $('#iframe-error').show();
                                    $('#continueButton').prop("disabled", true);
                                    return false;
                                }
                                helper.initializeIframe(self);
                            }
                            window.checkoutConfig.payment.paypalbr_paypalplus.is_payment_ready = true;
                            self.isPaymentReady = true;
                        } else {
                            helper.initializeIframe();
                        }
                    }, 1000);
                } else {
                    $('#paypalbr_paypalplus').prop("checked", false);
                    selectPaymentMethodAction(null);
                    return false;
                }
            }

            selectPaymentMethodAction(this.getData());
            // checkoutData.setSelectedPaymentMethod(this.item.method);
            checkoutData.setSelectedPaymentMethod('paypalbr_paypalplus');

            return true;
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
                window.checkoutConfig.payment.paypalbr_paypalplus.paypalObject.doContinue();
            } else {
                var message = {
                    message: $.mage.__('Please verify shipping address.')
                };
                self.messageContainer.addErrorMessage(message);
            }
        },

        getData: function () {
            return {
                // 'method': this.item.method,
                'method': 'paypalbr_paypalplus',
                'additional_data': {
                    'payId': $('#paypalbr_paypalplus_payId').val(),
                    'rememberedCardsToken': $('#paypalbr_paypalplus_rememberedCardsToken').val(),
                    'payerId': $('#paypalbr_paypalplus_payerId').val(),
                    'token': $('#paypalbr_paypalplus_token').val(),
                    'term': $('#paypalbr_paypalplus_term').val(),
                }
            };
        },

        initObservable: function () {
            this._super()
                .observe([
                    'payId',
                    'rememberedCardsToken',
                    'payerId',
                    'token',
                    'term',
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
            if(!this.customerData.city){
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
            if (typeof quote.shippingAddress().email === undefined) {
                return false;
            }

            console.log("taxvat");
            console.log(window.checkoutConfig.customerData.taxvat);
            console.log("vatId");
            console.log(this.customerData.vatId);
            if ( window.checkoutConfig.customerData.taxvat === null || typeof window.checkoutConfig.customerData.taxvat === 'undefined') {
                if ( this.customerData.vatId === null || typeof this.customerData.vatId === 'undefined' || this.customerData.vatId.length === 0) {
                    return false;
                }
            }

            if (typeof this.customerData.telephone === 'undefined' || this.customerData.telephone.length === 0) {
                return false;
            }
            if (typeof this.customerData.firstname === 'undefined' || this.customerData.firstname.length === 0) {
                return false;
            }
            if (typeof this.customerData.lastname === 'undefined' || this.customerData.lastname.length === 0) {
                return false;
            }
            return true;
        },

        /**
         * Get payment method code.
         */
        getCode: function () {
            // return this.item.method;
            return 'paypalbr_paypalplus';
        },
    });
});
