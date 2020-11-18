define([
    'ko',
    'Magento_Checkout/js/model/quote',
    'Magento_Checkout/js/model/resource-url-manager',
    'mage/storage',
    'Magento_Checkout/js/model/payment-service',
    'Magento_Checkout/js/model/payment/method-converter',
    'Magento_Checkout/js/model/error-processor',
    'Magento_Checkout/js/model/full-screen-loader',
    'Magento_Checkout/js/action/select-billing-address'
], function (
    ko,
    quote,
    resourceUrlManager,
    storage,
    paymentService,
    methodConverter,
    errorProcessor,
    fullScreenLoader,
    selectBillingAddressAction
) {
    'use strict';

    return {
        /**
         * saveShippingAddress. shipping method may not be set at this point
         */
        saveShippingAddress: function () {
            var payload;

            if (!quote.billingAddress() &&
                quote.shippingAddress() &&
                quote.shippingAddress().canUseForBilling()) {

                selectBillingAddressAction(quote.shippingAddress());
            }

            //jscs:disable requireCamelCaseOrUpperCaseIdentifiers
            payload = {
                addressInformation: {
                    'extension_attributes': {},
                    'shipping_address': quote.shippingAddress(),
                    'billing_address': quote.billingAddress(),
                    'shipping_method_code': quote.shippingMethod() ? quote.shippingMethod().method_code : null,
                    'shipping_carrier_code': quote.shippingMethod() ? quote.shippingMethod().carrier_code : null
                }
            };
            //jscs:enable requireCamelCaseOrUpperCaseIdentifiers

            fullScreenLoader.startLoader();

            return storage.post(
                resourceUrlManager.getUrlForSetShippingAddress(quote),
                JSON.stringify(payload)
            ).done(
                function (response) {
                    quote.setTotals(response.totals);
                    //jscs:disable requireCamelCaseOrUpperCaseIdentifiers
                    paymentService.setPaymentMethods(methodConverter(response.payment_methods));
                    //jscs:enable requireCamelCaseOrUpperCaseIdentifiers
                    fullScreenLoader.stopLoader();
                }
            ).fail(
                function (response) {
                    errorProcessor.process(response);
                    fullScreenLoader.stopLoader();
                }
            );
        }
    };
});
