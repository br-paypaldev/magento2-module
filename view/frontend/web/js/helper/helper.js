define([
    'jquery',
    'mage/url',
    'Magento_Checkout/js/model/quote',
    'PayPalBR_PayPal/js/model/full-screen-loader-paypal',
], function (
    $,
    urlBuilder,
    quote,
    fullScreenLoaderPayPal,
) {
    'use strict';

    var totals = quote.getTotals();

    return {

        defaultQuote: quote,
        alreadyPlacedOrder: false,

        runPayPal: function(approvalUrl, context = this) {
            // fullScreenLoaderPayPal.startLoader();
            var storage;
            var self = context;
            var telephone = '';
            var firstName = '';
            var lastName = '';
            var email = '';
            var taxVat = '';
            var customerData = window.checkoutConfig.customerData;
            var mode = window.checkoutConfig.payment.paypalbr_paypalplus.mode === "1" ? 'sandbox' : 'live';

            storage = $.initNamespaceStorage('paypal-data');
            storage = $.localStorage;

            var isEmpty = true;
            for (var i in customerData) {
                if(customerData.hasOwnProperty(i)) {
                    isEmpty = false;
                }
            }

            if(isEmpty){
                telephone =  quote.shippingAddress().telephone ? quote.shippingAddress().telephone  : storage.get('telephone');
                if(!telephone){
                    telephone = quote.billingAddress().telephone;
                }
            }else{
                telephone = quote.shippingAddress().telephone;
            }

            if(isEmpty){
                firstName =  quote.shippingAddress().firstname ? quote.shippingAddress().firstname : storage.get('firstName');
                if(!firstName){
                    firstName = quote.billingAddress().firstname;
                }
            }else{
                firstName = customerData.firstname;
            }

            if(isEmpty){
                lastName =  quote.shippingAddress().lastname ? quote.shippingAddress().lastname : storage.get('lastName');
                if(!lastName){
                    lastName = quote.billingAddress().lastname;
                }
            }else{
                lastName = customerData.lastname;
            }

            if(isEmpty){
                email =  quote.guestEmail ? quote.guestEmail : storage.get('email');
                if(!email){
                    email = quote.billingAddress().email;
                }
            }else{
                email = customerData.email;
            }

            if ( window.checkoutConfig.customerData.taxvat === null || typeof window.checkoutConfig.customerData.taxvat === 'undefined') {
                this.customerData = quote.billingAddress._latestValue;
                if(!this.customerData.vatId){
                    this.customerData = quote.shippingAddress._latestValue;
                }
                taxVat = this.customerData.vatId;
            }else{
                taxVat = window.checkoutConfig.customerData.taxvat;
            }

            storage.set(
                'paypal-data',
                {
                    'firstName': firstName,
                    'lastName': lastName,
                    'email': email,
                    'taxVat':taxVat,
                    'telephone': telephone
                }
            );

            if (window.checkoutConfig.payment.paypalbr_paypalplus.iframe_height_active === '1') {
                var height = window.checkoutConfig.payment.paypalbr_paypalplus.iframe_height;
            }else{
                var height = '';
            }

            window.checkoutConfig.payment.paypalbr_paypalplus.paypalObject = PAYPAL.apps.PPP(
                {
                    "approvalUrl": approvalUrl,
                    "placeholder": "ppplus",
                    "mode": mode,
                    "payerFirstName": firstName,
                    "payerLastName": lastName,
                    "payerPhone": "055"+telephone,
                    "payerEmail": email,
                    "payerTaxId": taxVat,
                    "payerTaxIdType": "BR_CPF",
                    "language": "pt_BR",
                    "country": "BR",
                    "enableContinue": "continueButton",
                    "disableContinue": "continueButton",
                    "rememberedCards": window.checkoutConfig.payment.paypalbr_paypalplus.rememberedCard,
                    "iframeHeight": height,
                    "merchantInstallmentSelection": !window.checkoutConfig.payment.cost_to_buyer.enabled
                        ? 0
                        : window.checkoutConfig.payment.cost_to_buyer.option,
                    "merchantInstallmentSelectionOptional": false,

                    onLoad: function () {
                        fullScreenLoaderPayPal.stopLoader();
                        console.log("Iframe successfully lo aded !");
                        var height = $('#ppplus iframe').css('height');

                        $('#ppplus').css('max-height', height);
                        $('#ppplus').show();
                    },
                    onContinue: function (rememberedCardsToken, payerId, token, term) {
                        $('#continueButton').hide();
                        $('#payNowButton').show();

                        self.payerId = payerId;

                        var message = {
                            message: $.mage.__('Payment is being processed.')
                        };
                        self.messageContainer.addSuccessMessage(message);

                        if (typeof term !== 'undefined') {
                            term = term.term;
                            self.term = term.term;
                        }else{
                            term = '1';
                            self.term = term;
                        }

                        $('#paypalbr_paypalplus_rememberedCardsToken').val(rememberedCardsToken);
                        $('#paypalbr_paypalplus_payerId').val(payerId);
                        $('#paypalbr_paypalplus_token').val(token);
                        $('#paypalbr_paypalplus_term').val(term);

                        $('#ppplus').hide();
                        if (!self.alreadyPlacedOrder){
                            self.placePendingOrder();
                            self.alreadyPlacedOrder = true;
                        }
                    },

                    /**
                     * Handle iframe error
                     *
                     * @param {type} err
                     * @returns {undefined}
                     */
                    onError: function (err) {

                        var message = JSON.stringify(err.cause);
                        var ppplusError = message.replace(/[\\"]/g, '');
                        if (typeof err.cause !== 'undefined') {
                            switch (ppplusError)
                            {

                                case "INTERNAL_SERVICE_ERROR":
                                case "SOCKET_HANG_UP":
                                case "socket hang up":
                                case "connect ECONNREFUSED":
                                case "connect ETIMEDOUT":
                                case "UNKNOWN_INTERNAL_ERROR":
                                case "fiWalletLifecycle_unknown_error":
                                case "Failed to decrypt term info":
                                case "RESOURCE_NOT_FOUND":
                                case "INTERNAL_SERVER_ERROR":
                                    alert($.mage.__('An unexpected error occurred, please try again.'));
                                    location.reload();
                                case "RISK_N_DECLINE":
                                case "NO_VALID_FUNDING_SOURCE_OR_RISK_REFUSED":
                                    alert($.mage.__('Please use another card if the problem persists please contact PayPal (0800-047-4482).'));
                                    location.reload();
                                case "TRY_ANOTHER_CARD":
                                case "NO_VALID_FUNDING_INSTRUMENT":
                                    alert($.mage.__('Your payment was not approved. Please use another card if the problem persists please contact PayPal (0800-047-4482).'));
                                    location.reload();
                                    break;
                                case "CARD_ATTEMPT_INVALID":
                                    alert ($.mage.__('An unexpected error occurred, please try again.'));
                                    location.reload();
                                    break;
                                case "INVALID_OR_EXPIRED_TOKEN":
                                    alert ($.mage.__('Your session has expired, please try again.'));
                                    location.reload();
                                    break;
                                case "CHECK_ENTRY":
                                    alert ($.mage.__('Please review the credit card data entered.'));
                                    location.reload();
                                    break;
                                default: //unknown error & reload payment flow
                                    alert ($.mage.__('An unexpected error occurred, please try again.'));
                                    location.reload();
                            }
                        }


                    }
                }
            );

            if (self.shippingValue != this.defaultQuote.totals().base_shipping_amount) {
                self.shippingValue = this.defaultQuote.totals().base_shipping_amount;
            }
            fullScreenLoaderPayPal.stopLoader();
            $('#continueButton').prop("disabled", false);
            var height = $('#ppplus iframe').css('height');
            $('#ppplus').css('max-height', height);
            $('#ppplus').show();
        },

        initializeIframe: function (context) {
            var self = this;
            var serviceUrl = urlBuilder.build('paypalplus/payment/index');
            var approvalUrl = '';
            console.log('initialize do helper.js');

            jQuery.ajax({
                url: serviceUrl,
                type: "POST",
                dataType: 'json',
                data: {email: $('#customer-email').val(), installment: window.checkoutConfig.payment.cost_to_buyer.option},
            })
                .done(function (response) {
                    // console.log(response);
                    $('#paypalbr_paypalplus_payId').val(response.id);
                    for (var i = 0; i < response.links.length; i++) {
                        if (response.links[i].rel == 'approval_url') {
                            approvalUrl = response.links[i].href;
                        }
                    }
                    self.runPayPal(approvalUrl, context);
                })
                .fail(function (response) {
                    console.log("ERROR");
                    console.log(response);
                    var iframeErrorElem = '#iframe-error';

                    var message = "";
                    if(response.responseJSON.message) {
                        message = response.responseJSON.message;
                    }
                    message = message + $.mage.__('<div><span>Error loading the payment method. Please try again, if problem persists contact us.</span></div>');

                    $(iframeErrorElem).html('');
                    $(iframeErrorElem).append(message);

                    $(iframeErrorElem).show();
                    $('#iframe-warning').hide();
                    $('#continueButton').prop("disabled", true);
                    fullScreenLoaderPayPal.stopLoader();

                    setTimeout(function(){
                        $(iframeErrorElem).hide();
                        $('#ppplus').hide();
                        $('#paypalbr_paypalplus').prop("checked", false);
                    }, 6000);
                })
                .always(function () {
                    // fullScreenLoader.stopLoader();
                });
        },
    };
});
