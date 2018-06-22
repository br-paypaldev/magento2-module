/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

define([
    'jquery',
    'mage/mage',
    'pplec',
    'mage/url',
    'PayPalBR_PayPal/js/model/full-screen-loader-review-order'
], function (
    $,
    mage,
    pplec,
    urlBuilder,
    fullScreenLoader

) {
    'use strict';


    $.widget('mage.paypalButton', {
        options: {},

        /** @inheritdoc */
        _create: function () {
            var urlBase =  window.checkoutConfig.base_url;
            self.CREATE_URL = urlBase + 'expresscheckout/loginpaypal/create';
            self.EXECUTE_URL = urlBase + 'expresscheckout/loginpaypal/authorize';
            var mode   =  window.checkoutConfig.mode;
            var locale =  window.checkoutConfig.locale;

            paypal.Button.render({

                env: mode, // sandbox | production
                locale: locale,

                style: {
                    label: 'paypal',
                    size: 'large',    // small | medium | large | responsive
                    shape: 'rect',     // pill | rect
                    color: 'blue',     // gold | blue | silver | black
                    tagline: false
                },

                // Show the buyer a 'Pay Now' button in the checkout flow
                commit: true,

                // payment() is called when the button is clicked
                payment: function () {

                    // Make a call to your server to set up the payment
                    return paypal.request.post(self.CREATE_URL)
                        .then(function (res) {
                            // console.log(res.paymentID);
                            return res.paymentID;
                        });
                },

                // onAuthorize() is called when the buyer approves the payment
                onAuthorize: function (data, actions) {
                    fullScreenLoader.startLoader();

                    // Set up the data you need to pass to ypayour server
                    var data = {
                        paymentID: data.paymentID,
                        payerID: data.payerID,
                        paymentToken: data.paymentToken
                    };

                    // Make a call to your server to execute the payment
                    return paypal.request.post(self.EXECUTE_URL, data)
                        .then(function (res) {
                            window.location.href = res.redirect;
                        });
                },onError: function (err) {
                    alert($.mage.__('An unexpected error occurred, please try again.'));
                    location.reload();
                }

            }, '#paypal-button-container');

        }

    });

    return $.mage.paypalButton;
});
