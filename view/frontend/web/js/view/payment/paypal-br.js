define(
    [
        'uiComponent',
        'Magento_Checkout/js/model/payment/renderer-list'
    ],
    function (
            Component,
            rendererList
            ) {
        'use strict';
        rendererList.push(
            {
                type: 'paypalbr_paypalplus',
                component: 'PayPalBR_PayPal/js/view/payment/method-renderer/paypal-plus'
            },
            {
                type: 'paypalbr_expresscheckout',
                component: 'PayPalBR_PayPal/js/view/payment/method-renderer/paypal-expresscheckout'
            }
        );

        return Component.extend({});
    }
);