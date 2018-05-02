/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

define([
    'jquery',
    'uiComponent',
    'ko',
    'Magento_Customer/js/model/customer',
    'Magento_Customer/js/action/check-email-availability',
    'Magento_Customer/js/action/login',
    'Magento_Checkout/js/model/quote',
    'Magento_Checkout/js/checkout-data',
    'Magento_Checkout/js/model/full-screen-loader',
    'mage/validation',
    'mage/url',
    'pplec'
], function (
        $,
        Component,
        ko,
        customer,
        checkEmailAvailability,
        loginAction,
        quote,
        checkoutData,
        fullScreenLoader,
        pplec,
        urlBuilder
        ) {
    'use strict';

    var validatedEmail = checkoutData.getValidatedEmailValue();

    if (validatedEmail && !customer.isLoggedIn()) {
        quote.guestEmail = validatedEmail;
    }

    return Component.extend({
        defaults: {
            template: 'PayPalBR_PayPal/form/element/email',
            email: checkoutData.getInputFieldEmailValue(),
            emailFocused: false,
            isLoading: false,
            isPasswordVisible: false,
            CREATE_URL: '',
            EXECUTE_URL: '',
            listens: {
                email: 'emailHasChanged',
                emailFocused: 'validateEmail'
            }
        },
        checkDelay: 2000,
        checkRequest: null,
        isEmailCheckComplete: null,
        isCustomerLoggedIn: customer.isLoggedIn,
        forgotPasswordUrl: window.checkoutConfig.forgotPasswordUrl,
        emailCheckTimeout: 0,

        /**
         * Initializes observable properties of instance
         *
         * @returns {Object} Chainable.
         */
        initObservable: function () {
            this._super()
                    .observe(['email', 'emailFocused', 'isLoading', 'isPasswordVisible']);

            return this;
        },

        /** @inheritdoc */
        initConfig: function () {
            this._super();

            this.isPasswordVisible = this.resolveInitialPasswordVisibility();

            return this;
        },

        buttonLoginPayPal: function () {

            var countItems = quote.getItems().length;

            var loginPayPalActive = window.checkoutConfig.payment.paypalbr_expresscheckout.login_paypal_active;
            var mode              = window.checkoutConfig.payment.paypalbr_expresscheckout.mode;
            var locale            = window.checkoutConfig.payment.paypalbr_expresscheckout.locale;

            if (countItems > 0 && loginPayPalActive) {

                self.CREATE_URL = urlBuilder.build('expresscheckout/loginpaypal/create');
                self.EXECUTE_URL = urlBuilder.build('expresscheckout/loginpaypal/authorize');

                paypal.Button.render({

                    env: mode, // sandbox | production
                    locale: locale,

                    style: {
                        label: 'paypal',
                        size: 'large', // small | medium | large | responsive
                        shape: 'rect', // pill | rect
                        color: 'blue', // gold | blue | silver | black
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
                    },
                    onError: function (err) {
                        alert($.mage.__('An unexpected error occurred, please try again.'));
                        location.reload();
                    }

                }, '#paypal-button-container-login');
            }
        },

        /**
         * Callback on changing email property
         */
        emailHasChanged: function () {
            var self = this;

            clearTimeout(this.emailCheckTimeout);

            if (self.validateEmail()) {
                quote.guestEmail = self.email();
                checkoutData.setValidatedEmailValue(self.email());
            }
            this.emailCheckTimeout = setTimeout(function () {
                if (self.validateEmail()) {
                    self.checkEmailAvailability();
                } else {
                    self.isPasswordVisible(false);
                }
            }, self.checkDelay);

            checkoutData.setInputFieldEmailValue(self.email());
        },

        /**
         * Check email existing.
         */
        checkEmailAvailability: function () {
            this.validateRequest();
            this.isEmailCheckComplete = $.Deferred();
            this.isLoading(true);
            this.checkRequest = checkEmailAvailability(this.isEmailCheckComplete, this.email());

            $.when(this.isEmailCheckComplete).done(function () {
                this.isPasswordVisible(false);
            }.bind(this)).fail(function () {
                this.isPasswordVisible(true);
                checkoutData.setCheckedEmailValue(this.email());
            }.bind(this)).always(function () {
                this.isLoading(false);
            }.bind(this));
        },

        /**
         * If request has been sent -> abort it.
         * ReadyStates for request aborting:
         * 1 - The request has been set up
         * 2 - The request has been sent
         * 3 - The request is in process
         */
        validateRequest: function () {
            if (this.checkRequest != null && $.inArray(this.checkRequest.readyState, [1, 2, 3])) {
                this.checkRequest.abort();
                this.checkRequest = null;
            }
        },

        /**
         * Local email validation.
         *
         * @param {Boolean} focused - input focus.
         * @returns {Boolean} - validation result.
         */
        validateEmail: function (focused) {
            var loginFormSelector = 'form[data-role=email-with-possible-login]',
                    usernameSelector = loginFormSelector + ' input[name=username]',
                    loginForm = $(loginFormSelector),
                    validator;

            loginForm.validation();

            if (focused === false && !!this.email()) {
                return !!$(usernameSelector).valid();
            }

            validator = loginForm.validate();

            return validator.check(usernameSelector);
        },

        /**
         * Log in form submitting callback.
         *
         * @param {HTMLElement} loginForm - form element.
         */
        login: function (loginForm) {
            var loginData = {},
                    formDataArray = $(loginForm).serializeArray();

            formDataArray.forEach(function (entry) {
                loginData[entry.name] = entry.value;
            });

            if (this.isPasswordVisible() && $(loginForm).validation() && $(loginForm).validation('isValid')) {
                fullScreenLoader.startLoader();
                loginAction(loginData).always(function () {
                    fullScreenLoader.stopLoader();
                });
            }
        },

        /**
         * Resolves an initial sate of a login form.
         *
         * @returns {Boolean} - initial visibility state.
         */
        resolveInitialPasswordVisibility: function () {
            if (checkoutData.getInputFieldEmailValue() !== '') {
                return checkoutData.getInputFieldEmailValue() === checkoutData.getCheckedEmailValue();
            }

            return false;
        },
        loginPayPalActive: function () {
            return window.checkoutConfig.payment.paypalbr_expresscheckout.login_paypal_active;
        }
    });
});
