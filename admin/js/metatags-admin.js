/* global jQuery, wp, metatagsAdmin */
( function ( $ ) {
	'use strict';

	var i18n = window.metatagsAdmin || {};

	// Character counters.
	function updateCounter( $field ) {
		var max = parseInt( $field.data( 'metatags-max' ), 10 );
		var len = ( $field.val() || $field.attr( 'placeholder' ) || '' ).length;
		var $counter = $field.data( 'metatagsCounter' );

		if ( ! $counter ) {
			$counter = $( '<span class="metatags-counter" aria-live="polite"></span>' ).insertAfter( $field );
			$field.data( 'metatagsCounter', $counter );
		}
		$counter
			.text( ( i18n.counter || '%1$d / %2$d' ).replace( '%1$d', len ).replace( '%2$d', max ) )
			.toggleClass( 'is-too-long', len > max )
			.toggleClass( 'is-placeholder', ! $field.val() );
	}

	// Media library picker.
	function initImage( $wrap ) {
		var frame;
		var $input = $wrap.find( 'input[type="url"]' );
		var $preview = $wrap.find( '.metatags-image__preview' );
		var $remove = $wrap.find( '.metatags-image__remove' );
		var fallback = $wrap.data( 'fallback' ) || '';

		function refresh() {
			var url = $input.val() || fallback;
			$preview.attr( 'src', url ).prop( 'hidden', ! url );
			$remove.prop( 'hidden', ! $input.val() );
		}

		$wrap.on( 'click', '.metatags-image__select', function ( e ) {
			e.preventDefault();
			if ( ! frame ) {
				frame = wp.media( {
					title: i18n.chooseImage,
					button: { text: i18n.useImage },
					library: { type: 'image' },
					multiple: false
				} );
				frame.on( 'select', function () {
					var attachment = frame.state().get( 'selection' ).first().toJSON();
					var size = attachment.sizes && attachment.sizes.large ? attachment.sizes.large : attachment;
					$input.val( size.url ).trigger( 'change' );
				} );
			}
			frame.open();
		} );

		$wrap.on( 'click', '.metatags-image__remove', function ( e ) {
			e.preventDefault();
			$input.val( '' ).trigger( 'change' );
		} );

		$input.on( 'change input', refresh );
	}

	$( function () {
		$( '.metatags-box [data-metatags-max]' ).each( function () {
			var $field = $( this );
			updateCounter( $field );
			$field.on( 'input', function () {
				updateCounter( $field );
			} );
		} );

		$( '.metatags-box .metatags-image' ).each( function () {
			initImage( $( this ) );
		} );
	} );
} )( jQuery );
