/* global jQuery */
(function ( $ ) {
	'use strict';

	function toggleQualifyingFields() {
		var type = $( 'input[name="plk_bogo_qualifying_type"]:checked' ).val();
		if ( 'category' === type ) {
			$( '.plk-bogo-qualifying-products' ).hide();
			$( '.plk-bogo-qualifying-category' ).show();
		} else {
			$( '.plk-bogo-qualifying-products' ).show();
			$( '.plk-bogo-qualifying-category' ).hide();
		}
	}

	$( document ).ready( function () {
		// Initialize qualifying type toggle.
		$( 'input[name="plk_bogo_qualifying_type"]' ).on( 'change', toggleQualifyingFields );

		// Initialize selectWoo on product search fields.
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
