/**
 * Copyright © 2015 Magento. All rights reserved.
 * See COPYING.txt for license details.
 */
/*browser:true*/
/*global define*/
define(
    [
        'jquery',
        'Magento_Checkout/js/view/payment/default'
    ],
    function (
        $,
        Component
        ) {
        'use strict';

        return Component.extend({
            defaults: {
                template: 'Gabrielqs_Pagseguro/payment/pagseguro_redirect'
            },

            redirectAfterPlaceOrder: false,

            afterPlaceOrder: function() {
                $.mage.redirect(
                    window.checkoutConfig.payment.pagseguro_redirect.pre_redirect_url,
                    'replace',
                    0,
                    true
                );
            },

            getPagseguroImage: function() {
                return window.checkoutConfig.payment.pagseguro_redirect.checkout_image;
            }

        });
    }
);
