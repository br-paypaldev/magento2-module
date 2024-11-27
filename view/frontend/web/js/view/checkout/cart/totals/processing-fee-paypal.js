define(
    [
        'PayPalBR_PayPal/js/view/checkout/summary/processing-fee-paypal'
    ],
    function (Component) {
        'use strict';
        return Component.extend({
            /**
             * @override
             */
            isDisplayed: function () {
                return this.getPureValue() !== 0;
            }
        });
    }
);
