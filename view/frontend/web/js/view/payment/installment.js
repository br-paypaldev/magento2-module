define([
    'PayPalBR_PayPal/js/view/payment/method-renderer/paypal-plus',
    'jquery',
    'mage/url',
    'ko',
    'helperPaypal',
    'Magento_Checkout/js/model/cart/cache',
    'Magento_Checkout/js/model/cart/totals-processor/default',
    'Magento_Checkout/js/model/quote',
    'PayPalBR_PayPal/js/model/full-screen-loader-paypal',
    'Magento_Checkout/js/model/totals'
], function (
    Component,
    $,
    urlBuilder,
    ko,
    helper,
    cartCache,
    totalsDefaultProvider,
    quote,
    fullScreenLoaderPayPal,
    totalsService
) {
    'use strict';

    return Component.extend({
        defaults: {
            template: 'PayPalBR_PayPal/payment/installment',
            total: quote.totals,
            totalLoading: totalsService.isLoading,
            tracks: {
                total: true,
                totalLoading: true
            }
        },
        availableOptions: ko.observableArray([]),
        selectedItem: ko.observable(-1),
        enabled: undefined,
        orderTotal: quote.totals().grand_total,
        orderShipping: quote.totals().base_shipping_amount,
        isLoadable: true,

        initialize: function () {
            this._super();

            this.populate();

            this.total.subscribe(function(newValue) {
                if (this.orderTotal !== newValue.grand_total || this.isLoadable) {
                    this.availableOptions([]);
                    this.populate();
                    this.isLoadable = false;
                }

                if(this.orderShipping !== newValue.base_shipping_amount) {
                    $('#ppplus').parent().css('display', 'none');
                    $('#ppplus').hide();
                    $('#paypalbr_paypalplus').prop("checked", false);
                    this.orderShipping = newValue.base_shipping_amount;
                }
            }.bind(this));

            this.totalLoading.subscribe(function(newValue) {
                if (newValue) {
                    fullScreenLoaderPayPal.startLoader();
                } else {
                    fullScreenLoaderPayPal.stopLoader();
                }
            })
        },

        selectInstallment: function () {
            var self = this;

            if (this.selectedItem() !== undefined) {
                window.checkoutConfig.payment.cost_to_buyer.option = this.selectedItem();
                this.orderTotal = this.availableOptions()[this.selectedItem() - 1].fee;

                fullScreenLoaderPayPal.startLoader();
                if (this.breakError) {
                    $('#iframe-warning').hide();
                    $('#iframe-error').show();
                    $('#continueButton').prop("disabled", true);
                    return false;
                }

                helper.initializeIframe(self);

                fullScreenLoaderPayPal.stopLoader();
                window.checkoutConfig.payment.paypalbr_paypalplus.is_payment_ready = true;
                self.isPaymentReady = true;

            }
            this.isLoadable = false;
            cartCache.set('totals',null);
            totalsDefaultProvider.estimateTotals();
        },

        isActive: function () {
            return true;
        },

        clearValues: function () {
            this.availableOptions([]);
        },

        populate: function() {
            var serviceUrl = urlBuilder.build('paypalplus/payment/installment');
            var self = this;

            jQuery.ajax({
                url: serviceUrl,
                type: "POST",
                dataType: 'json',
                async: false
            })
                .done(function (response) {
                    $.each(response.options, function() {
                        const value = this.value;
                        const label = this.label;
                        const fee = this.fee;
                        const option = {
                            'value': value,
                            'label': label,
                            'fee': fee
                        };
                        self.availableOptions.push(option);
                        self.enabled = response.enabled;
                    });
                })
                .fail(function (response) {
                    console.log("ERROR");
                    console.log(response);
                });

            window.checkoutConfig.payment.cost_to_buyer = {};
            window.checkoutConfig.payment.cost_to_buyer.enabled = this.enabled;
            this.installmentEnabled = this.enabled;
            window.checkoutConfig.payment.cost_to_buyer.option = null;
        }
    });
});
