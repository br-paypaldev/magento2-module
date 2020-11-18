/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

define([
    'uiComponent',
    'Magento_Customer/js/customer-data',
    'jquery',
    'ko',
    'underscore',
    'pplec',
    'mage/url',
    'sidebar',
    'mage/translate'
], function (
    Component,
    customerData,
    $,
    ko,
    _,
    pplec,
    urlBuilder
) {
    'use strict';

    var sidebarInitialized = false,
            addToCartCalls = 0,
            miniCart;

    miniCart = $('[data-block=\'minicart\']');

    /**
     * @return {Boolean}
     */
    function initSidebar() {
        if (miniCart.data('mageSidebar')) {
            miniCart.sidebar('update');
        }

        if (!$('[data-role=product-item]').length) {
            return false;
        }
        miniCart.trigger('contentUpdated');

        if (sidebarInitialized) {
            return false;
        }
        sidebarInitialized = true;
        miniCart.sidebar({
            'targetElement': 'div.block.block-minicart',
            'url': {
                'checkout': window.checkout.checkoutUrl,
                'update': window.checkout.updateItemQtyUrl,
                'remove': window.checkout.removeItemUrl,
                'loginUrl': window.checkout.customerLoginUrl,
                'isRedirectRequired': window.checkout.isRedirectRequired
            },
            'button': {
                'checkout': '#top-cart-btn-checkout',
                'remove': '#mini-cart a.action.delete',
                'close': '#btn-minicart-close'
            },
            'showcart': {
                'parent': 'span.counter',
                'qty': 'span.counter-number',
                'label': 'span.counter-label'
            },
            'minicart': {
                'list': '#mini-cart',
                'content': '#minicart-content-wrapper',
                'qty': 'div.items-total',
                'subtotal': 'div.subtotal span.price',
                'maxItemsVisible': window.checkout.minicartMaxItemsVisible
            },
            'item': {
                'qty': ':input.cart-item-qty',
                'button': ':button.update-cart-item'
            },
            'confirmMessage': $.mage.__('Are you sure you would like to remove this item from the shopping cart?')
        });
    }

    miniCart.on('dropdowndialogopen', function () {
        initSidebar();
    });

    return Component.extend({
        shoppingCartUrl: window.checkout.shoppingCartUrl,
        maxItemsToDisplay: window.checkout.maxItemsToDisplay,
        cart: {},
        defaults: {
            CREATE_URL: '',
            EXECUTE_URL: ''
        },

        /**
         * @override
         */
        initialize: function () {
            var self = this,
                    cartData = customerData.get('cart');

            this.update(cartData());
            cartData.subscribe(function (updatedCart) {
                addToCartCalls--;
                this.isLoading(addToCartCalls > 0);
                sidebarInitialized = false;
                this.update(updatedCart);
                initSidebar();
            }, this);
            $('[data-block="minicart"]').on('contentLoading', function () {
                addToCartCalls++;
                self.isLoading(true);
            });

            if (cartData()['website_id'] !== window.checkout.websiteId) {
                customerData.reload(['cart'], false);
            }

            return this._super();
        },
        isLoading: ko.observable(false),
        initSidebar: initSidebar,

        /**
         * Close mini shopping cart.
         */
        closeMinicart: function () {
            $('[data-block="minicart"]').find('[data-role="dropdownDialog"]').dropdownDialog('close');
        },

        /**
         * @return {Boolean}
         */
        closeSidebar: function () {
            var minicart = $('[data-block="minicart"]');

            minicart.on('click', '[data-action="close"]', function (event) {
                event.stopPropagation();
                minicart.find('[data-role="dropdownDialog"]').dropdownDialog('close');
            });

            return true;
        },

        /**
         * @param {String} productType
         * @return {*|String}
         */
        getItemRenderer: function (productType) {
            return this.itemRenderer[productType] || 'defaultRenderer';
        },

        /**
         * Update mini shopping cart content.
         *
         * @param {Object} updatedCart
         * @returns void
         */
        update: function (updatedCart) {
            _.each(updatedCart, function (value, key) {
                if (!this.cart.hasOwnProperty(key)) {
                    this.cart[key] = ko.observable();
                }
                this.cart[key](value);
            }, this);
        },

        /**
         * Get cart param by name.
         * @param {String} name
         * @returns {*}
         */
        getCartParam: function (name) {
            if (!_.isUndefined(name)) {
                if (!this.cart.hasOwnProperty(name)) {
                    this.cart[name] = ko.observable();
                }
            }

            return this.cart[name]();
        },

        /**
         * Returns array of cart items, limited by 'maxItemsToDisplay' setting
         * @returns []
         */
        getCartItems: function () {
            var items = this.getCartParam('items') || [];

            items = items.slice(parseInt(-this.maxItemsToDisplay, 10));

            return items;
        },

        /**
         * Returns count of cart line items
         * @returns {Number}
         */
        getCartLineItemsCount: function () {
            var items = this.getCartParam('items') || [];

            return parseInt(items.length, 10);
        },

        moduleConfigActive: function () {
            return window.checkout.payment.paypalbr_expresscheckout.mini_cart;
        },

        getPayPalLoginButton: function () {

            var miniCart = window.checkout.payment.paypalbr_expresscheckout.mini_cart;
            var mode = window.checkout.payment.paypalbr_expresscheckout.mode;
            var locale = window.checkout.payment.paypalbr_expresscheckout.locale;

            if (miniCart == 1) {

                self.CREATE_URL = urlBuilder.build('expresscheckout/loginpaypal/create');
                self.EXECUTE_URL = urlBuilder.build('expresscheckout/loginpaypal/authorize');


                var btn = paypal.Button.render({

                    env:  mode, // sandbox | production
                    locale: locale,

                    style: {
                        label: 'pay',
                        size: 'responsive', // small | medium | large | responsive
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
                                    var response;
                                    jQuery.ajax({
                                        url: self.CREATE_URL,
                                        type: "POST",
                                        dataType: 'json',
                                        async: false,
                                    }).done(function (data) {
                                        // console.log(data);
                                        // console.log(data.paymentID)
                                        response = data.paymentID;
                                    });
                                    return response;
                                });
                    },

                    // onAuthorize() is called when the buyer approves the payment
                    onAuthorize: function (data, actions) {

                        // Set up the data you need to pass to ypayour server
                        var data = {
                            paymentID: data.paymentID,
                            payerID: data.payerID
                        };

                        // Make a call to your server to execute the payment
                        return paypal.request.post(self.EXECUTE_URL, data)
                                .then(function (res) {
                                    jQuery.ajax({
                                        url: self.EXECUTE_URL,
                                        type: "POST",
                                        data: data,
                                        dataType: 'json',
                                        async: false,
                                    }).done(function (data) {
                                        // console.log(data);
                                        // console.log(data.paymentID)
                                        window.location.href = data.redirect;
                                    });
                                });
                    },
                    onError: function (err) {
                        alert($.mage.__('An unexpected error occurred, please try again.'));
                        location.reload();
                    }

                }, '#paypal-button-container');

            }
        }
    });
});


