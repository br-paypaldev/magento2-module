define([
    'Magento_Checkout/js/checkout-data',
    'PayPalBR_PayPal/js/view/payment/method-renderer/paypal-plus'
], function (checkoutData, ppplus) {
    'use strict';

    var mixin = {
        updateAddress: function () {
            var origMethod = this._super();
            if (checkoutData.getSelectedPaymentMethod() == 'paypalbr_paypalplus') {
                ppplus().selectPaymentMethod();
            }
            return origMethod;
        }
    };
    return function (target) {
        return target.extend(mixin);
    };
});
