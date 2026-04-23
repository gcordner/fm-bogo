/* global jQuery */
(function ( $ ) {
	'use strict';

	function toggleQualifyingFields() {
		var type = $( 'input[name="fm_bogo_qualifying_type"]:checked' ).val();
		if ( 'category' === type ) {
			$( '.fm-bogo-qualifying-products' ).hide();
			$( '.fm-bogo-qualifying-category' ).show();
		} else {
			$( '.fm-bogo-qualifying-products' ).show();
			$( '.fm-bogo-qualifying-category' ).hide();
		}
	}

	$( document ).ready( function () {
		$( 'input[name="fm_bogo_qualifying_type"]' ).on( 'change', toggleQualifyingFields );

		$( '.wc-product-search' ).filter( ':not(.select2-hidden-accessible)' ).each( function () {
			var $el = $( this );
			$el.selectWoo( {
				ajax: {
					url: woocommerce_admin.ajax_url,
					dataType: 'json',
					delay: 250,
					data: function ( params ) {
						return {
							term: params.term,
							action: $el.data( 'action' ) || 'woocommerce_json_search_products_and_variations',
							security: woocommerce_admin.search_products_nonce,
						};
					},
					processResults: function ( data ) {
						var terms = [];
						if ( data ) {
							$.each( data, function ( id, text ) {
								terms.push( { id: id, text: text } );
							} );
						}
						return { results: terms };
					},
					cache: true,
				},
				minimumInputLength: 3,
				placeholder: $el.data( 'placeholder' ) || '',
				allowClear: !! $el.data( 'allow_clear' ),
			} );
		} );
	} );
}( jQuery ) );
