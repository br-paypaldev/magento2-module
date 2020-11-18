/**
 * @category  PayPalBRPayPal
 * @package   PayPalBR_PayPal
 * @author    Paulo Henrique <paulo@imaginationmedia.com>
 * @copyright Copyright (c) 2020 Imagination Media (https://www.imaginationmedia.com/)
 */
define([
    'jquery'
], function ($) {
    'use strict';
    $.widget('mage.firecheckout', {

        /**
         * Start methods
         */
        _init: function () {
            console.log('PayPal Plus Firecheckout JS: Init');
            this.firecheckout();
        },

        firecheckout: function () {
            let $widget = this;
        }

    });
    return $.mage.firecheckout;
});
