/**
 * PayPalBR PayPalEnterprise
 *
 * @package PayPalBR|PayPalEnterprise
 * @author Vitor Nicchio Alves <vitor@imaginationmedia.com>
 * @copyright Copyright (c) 2020 Imagination Media (https://www.imaginationmedia.com/)
 * @license https://opensource.org/licenses/OSL-3.0.php Open Software License 3.0
 */

define([
    'jquery',
    'ko',
    'Magento_Checkout/js/view/summary/abstract-total',
    'Magento_Checkout/js/model/quote',
    'mage/url',
    'Magento_Checkout/js/model/totals',
    'Magento_GiftCardAccount/js/action/remove-gift-card-from-quote',
    'helperPaypal',
    'Magento_Checkout/js/model/full-screen-loader'
], function ($, ko, generic, quote, url, totals, removeAction, helper, fullScreenLoader) {
    'use strict';

    return generic.extend({
        defaults: {
            template: 'Magento_GiftCardAccount/summary/gift-card-account'
        },

        /**
         * Get information about applied gift cards and their amounts
         *
         * @returns {Array}.
         */
        getAppliedGiftCards: function () {
            if (totals.getSegment('giftcardaccount')) {
                return JSON.parse(totals.getSegment('giftcardaccount')['extension_attributes']['gift_cards']);
            }

            return [];
        },

        /**
         * @return {Object|Boolean}
         */
        isAvailable: function () {
            return this.isFullMode() && totals.getSegment('giftcardaccount') &&
                totals.getSegment('giftcardaccount').value != 0; //eslint-disable-line eqeqeq
        },

        /**
         * @param {Number} usedBalance
         * @return {*|String}
         */
        getAmount: function (usedBalance) {
            return this.getFormattedPrice(usedBalance);
        },

        /**
         * @param {String} giftCardCode
         * @param {Object} event
         */
        removeGiftCard: function (giftCardCode, event) {
            event.preventDefault();

            if (giftCardCode) {
                removeAction(giftCardCode);
            }

            var method = document.getElementById('paypalbr_paypalplus').checked;
            setTimeout(function(){
                if( method ){
                    fullScreenLoader.startLoader();
                    helper.initializeIframe();
                }
            }, 1000);
        },

    });

});
