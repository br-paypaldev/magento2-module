/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

define([
    'jquery',
    'ko',
    'uiComponent',
    'Magento_Checkout/js/model/quote',
    'Magento_SalesRule/js/action/set-coupon-code',
    'Magento_Checkout/js/model/full-screen-loader',
    'Magento_SalesRule/js/action/cancel-coupon',
    'mage/url',
    'mage/storage',
    'helperPaypal',
    'mage/translate'
], function (
    $,
    ko,
    Component,
    quote,
    setCouponCodeAction,
    fullScreenLoader,
    cancelCouponAction,
    urlBuilder,
    storage,
    helper
) {
    'use strict';

    var totals = quote.getTotals(),
        couponCode = ko.observable(null),
        isApplied;


    if (totals()) {
        couponCode(totals()['coupon_code']);
    }
    isApplied = ko.observable(couponCode() != null);

    return Component.extend({
        defaults: {
            template: 'Magento_SalesRule/payment/discount'
        },
        couponCode: couponCode,
        defaultQuote: quote,
        /**
         * Applied flag
         */
        isApplied: isApplied,

        /**
         * Coupon code application procedure
         */
        apply: function () {
            var self = this;
            if (this.validate()) {
                var method = document.getElementById('paypalbr_paypalplus').checked;
                var response = setCouponCodeAction(couponCode(), isApplied);

                setTimeout(function(){
                    if( method ){
                        fullScreenLoader.startLoader();
                        if (window.checkoutConfig.payment.cost_to_buyer.enabled){
                            $('#ppplus').parent().css('display', 'none');
                            $('#ppplus').hide();
                            $('#paypalbr_paypalplus').prop("checked", false);
                        }
                        fullScreenLoader.stopLoader();
                    }
                }, 3000);
            }
        },

        /**
         * Cancel using coupon
         */
        cancel: function () {
            var self = this;
            if (this.validate()) {
                var method = document.getElementById('paypalbr_paypalplus').checked;
                var response = couponCode('');
                var response = cancelCouponAction(isApplied);

                setTimeout(function(){
                    if( method ){
                        fullScreenLoader.startLoader();
                        if (window.checkoutConfig.payment.cost_to_buyer.enabled){
                            $('#ppplus').parent().css('display', 'none');
                            $('#ppplus').hide();
                            $('#paypalbr_paypalplus').prop("checked", false);
                        }
                        fullScreenLoader.stopLoader();
                    }
                }, 3000);
            }
        },

        /**
         * Coupon form validation
         *
         * @returns {Boolean}
         */
        validate: function () {
            var form = '#discount-form';

            return $(form).validation() && $(form).validation('isValid');
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

        doContinue: function () {
            var self = this;
            if (this.validateAddress() !== false) {
                console.log("customer-balance.js");
                var aux1 = self.paypalObject;
                console.log(self.paypalObject);
                window.checkoutConfig.payment.paypalbr_paypalplus.paypalObject.doContinue();
            } else {
                var message = {
                    message: $.mage.__('Please verify shipping address.')
                };
                self.messageContainer.addErrorMessage(message);
            }
        }
    });
});
