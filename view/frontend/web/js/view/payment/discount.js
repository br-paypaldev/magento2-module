define(['Magento_Checkout/js/action/select-payment-method'], function (selectPaymentMethodAction) {
    'use strict';

    var mixin = {
        apply: function () {
            var origMethod = this._super();
            if (document.getElementById('paypalbr_paypalplus').checked) {
                document.getElementById('paypalbr_paypalplus').checked = false;
                selectPaymentMethodAction(null);
            }
            return origMethod;
        },
        cancel: function () {
            var origMethod = this._super();
            if (document.getElementById('paypalbr_paypalplus').checked) {
                document.getElementById('paypalbr_paypalplus').checked = false;
                selectPaymentMethodAction(null);
            }
            return origMethod;
        }
    };
    return function (target) {
        return target.extend(mixin);
    };
});
