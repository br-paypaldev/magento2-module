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
    'Magento_Checkout/js/model/quote',
    'Magento_Checkout/js/model/full-screen-loader',
    'Magento_Reward/js/action/set-use-reward-points',
    'helperPaypal',
    'mage/url',
    'mage/storage',
    'mage/translate'
], function (
    $,
    ko,
    Component,
    quote,
    fullScreenLoader,
    setUseRewardPointsAction,
    helper,
    urlBuilder,
    storage,
    $t
) {
    'use strict';

    var rewardConfig = window.checkoutConfig.payment.reward;

    return Component.extend({
        defaults: {
            template: 'Magento_Reward/payment/reward'
        },
        label: rewardConfig.label,
        defaultQuote: quote,

        /**
         * @return {Boolean}
         */
        isAvailable: function () {
            var subtotal = parseFloat(quote.totals()['grand_total']),
                rewardUsedAmount = parseFloat(quote.totals()['extension_attributes']['base_reward_currency_amount']);

            return rewardConfig.isAvailable && subtotal > 0 && rewardUsedAmount <= 0;
        },

        /**
         * Use reward points.
         */
        useRewardPoints: function () {
            setUseRewardPointsAction();

            var method = document.getElementById('paypalbr_paypalplus').checked;
            var rewardAmount = window.checkoutConfig.payment.reward.balance;
            var grandTotal = quote.totals().base_grand_total;
            rewardAmount = parseFloat(parseFloat(rewardAmount).toFixed(2));
            grandTotal = parseFloat(parseFloat(grandTotal).toFixed(2));

            if((rewardAmount < grandTotal) && (method)) {
                setTimeout(function(){
                    fullScreenLoader.startLoader();
                    helper.initializeIframe();
                }, 1000);
            }
        }
    });
});
