( function () {
    'use strict';

    var wcBlocksRegistry = window.wc.wcBlocksRegistry;
    var wcSettings       = window.wc.wcSettings;
    var createElement    = window.wp.element.createElement;
    var htmlEntities     = window.wp.htmlEntities;

    var settings = wcSettings.getSetting( 'cipherpay_data', {} );
    var title    = htmlEntities.decodeEntities( settings.title || 'Pay with Zcash (ZEC)' );

    var Content = function () {
        return createElement(
            'div',
            null,
            createElement( 'p', null, htmlEntities.decodeEntities( settings.description || '' ) ),
            createElement(
                'p',
                { style: { fontSize: '12px', color: '#666' } },
                'You will be redirected to a secure CipherPay checkout page to complete your payment with shielded ZEC.'
            )
        );
    };

    var Label = function () {
        var icon = settings.icon
            ? createElement( 'img', {
                src:   settings.icon,
                alt:   title,
                style: { display: 'inline', marginRight: '8px', maxHeight: '24px', verticalAlign: 'middle' },
            } )
            : null;

        return createElement(
            'span',
            { style: { display: 'flex', alignItems: 'center' } },
            icon,
            createElement( 'span', null, title )
        );
    };

    wcBlocksRegistry.registerPaymentMethod( {
        name:       'cipherpay',
        label:      createElement( Label, null ),
        content:    createElement( Content, null ),
        edit:       createElement( Content, null ),
        canMakePayment: function () { return true; },
        ariaLabel:  title,
        supports:   { features: settings.supports || [ 'products' ] },
    } );
} )();
