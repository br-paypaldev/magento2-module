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
    'uiComponent',
    'Magento_GiftCardAccount/js/action/set-gift-card-information',
    'Magento_GiftCardAccount/js/action/get-gift-card-information',
    'Magento_Checkout/js/model/totals',
    'Magento_GiftCardAccount/js/model/gift-card',
    'Magento_Checkout/js/model/quote',
    'Magento_Catalog/js/price-utils',
    'helperPaypal',
    'Magento_Checkout/js/model/full-screen-loader',
    'mage/validation',
], function ($, ko, Component, setGiftCardAction, getGiftCardAction, totals, giftCardAccount, quote, priceUtils, helper, fullScreenLoader) {
    'use strict';

    return Component.extend({
        defaults: {
            template: 'Magento_GiftCardAccount/payment/gift-card-account',
            giftCartCode: ''
        },
        isLoading: getGiftCardAction.isLoading,
        giftCardAccount: giftCardAccount,

        /** @inheritdoc */
        initObservable: function () {
            this._super()
                .observe('giftCartCode');

            return this;
        },

        /**
         * Set gift card.
         */
        setGiftCard: function () {
            if (this.validate()) {
                setGiftCardAction([this.giftCartCode()]);
            }

            var method = document.getElementById('paypalbr_paypalplus').checked;
            setTimeout(function(){
                if( method ){
                    fullScreenLoader.startLoader();
                    helper.initializeIframe();
                }
            }, 1000);
        },

        /**
         * Check balance.
         */
        checkBalance: function () {
            if (this.validate()) {
                getGiftCardAction.check(this.giftCartCode());
            }
        },

        /**
         * @param {*} price
         * @return {String|*}
         */
        getAmount: function (price) {
            return priceUtils.formatPrice(price, quote.getPriceFormat());
        },

        /**
         * @return {jQuery}
         */
        validate: function () {
            var form = '#giftcard-form';

            return $(form).validation() && $(form).validation('isValid');
        }
    });
});
