var config = {
    map: {
        '*': {
            'Magento_Checkout/js/action/select-shipping-method':            'PayPalBR_PayPal/js/action/select-shipping-method',
            //'Magento_SalesRule/js/view/payment/discount':                   'PayPalBR_PayPal/js/view/payment/discount',
            'Magento_Checkout/js/model/step-navigator':                     'PayPalBR_PayPal/js/model/step-navigator',
            'Magento_Checkout/js/model/checkout-data-resolver':             'PayPalBR_PayPal/js/model/checkout-data-resolver',
            'Magento_Checkout/template/sidebar.html':                       'PayPalBR_PayPal/template/sidebar.html',
            'Magento_Reward/js/view/payment/reward':                        'PayPalBR_PayPal/js/view/payment/reward',
            'Magento_GiftCardAccount/js/view/payment/gift-card-account':    'PayPalBR_PayPal/js/view/payment/gift-card-account',
            'Magento_GiftCardAccount/js/view/summary/gift-card-account':    'PayPalBR_PayPal/js/view/summary/gift-card-account',
            paypalButton:                                                   'PayPalBR_PayPal/js/widget/paypal-button',
        }
    },
    paths: {
        "ppplus": "https://www.paypalobjects.com/webstatic/ppplusdcc/ppplusdcc.min",
        "pplec": "https://www.paypalobjects.com/api/checkout",
        'helperPaypal': 'PayPalBR_PayPal/js/helper/helper'
    },
    config: {
        mixins: {
            'Magento_Checkout/js/view/billing-address': {
                'PayPalBR_PayPal/js/view/billing-address': true
            },
        }
    }
};
