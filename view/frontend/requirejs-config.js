var config = {
    map: {
        '*': {
            'Magento_Checkout/js/action/select-shipping-method': 'PayPalBR_PayPal/js/action/select-shipping-method',
            'Magento_SalesRule/js/view/payment/discount':        'PayPalBR_PayPal/js/view/payment/discount',
            'Magento_Checkout/js/model/step-navigator':          'PayPalBR_PayPal/js/model/step-navigator',
            'Magento_Checkout/js/model/checkout-data-resolver':  'PayPalBR_PayPal/js/model/checkout-data-resolver',
            'Magento_Checkout/template/sidebar.html':            'PayPalBR_PayPal/template/sidebar.html',
            paypalButton:                                        'PayPalBR_PayPal/js/widget/paypal-button',
        }
    },
    paths: {
        "ppplus": "https://www.paypalobjects.com/webstatic/ppplusdcc/ppplusdcc.min",
        "pplec": "https://www.paypalobjects.com/api/checkout",
        'helperPaypal': 'PayPalBR_PayPal/js/helper/helper'
    },
    shim: {
        'ppplus': {
            'deps': [
                'jquery/jquery.cookie'
            ]
        },
        'pplec': {
            'deps': [
                'jquery/jquery.cookie'
            ]
        }
    },
    config: {
        mixins: {
            'Magento_Checkout/js/view/billing-address': {
                'PayPalBR_PayPal/js/view/billing-address': true
            },
        }
    }
};
