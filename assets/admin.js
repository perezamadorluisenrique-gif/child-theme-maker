/* Child Theme Maker admin screen. No dependencies. */
( function () {
	'use strict';

	var form = document.getElementById( 'ctmaker-create' );
	if ( form ) {
		var parent = document.getElementById( 'ctmaker_parent' );
		var name = document.getElementById( 'ctmaker_name' );
		var slug = document.getElementById( 'ctmaker_slug' );
		var nameTouched = false;
		var slugTouched = false;

		var slugify = function ( value ) {
			return value
				.normalize( 'NFD' )
				.replace( /[̀-ͯ]/g, '' )
				.toLowerCase()
				.replace( /[^a-z0-9]+/g, '-' )
				.replace( /^-+|-+$/g, '' );
		};

		parent.addEventListener( 'change', function () {
			var option = parent.options[ parent.selectedIndex ];
			if ( ! nameTouched ) {
				name.value = option.getAttribute( 'data-name' );
			}
			if ( ! slugTouched ) {
				slug.value = nameTouched ? slugify( name.value ) : option.getAttribute( 'data-slug' );
			}
		} );
		name.addEventListener( 'input', function () {
			nameTouched = true;
			if ( ! slugTouched ) {
				slug.value = slugify( name.value );
			}
		} );
		slug.addEventListener( 'input', function () {
			slugTouched = true;
		} );
	}

	var filter = document.getElementById( 'ctmaker-filter' );
	if ( filter ) {
		var items = document.querySelectorAll( '.ctmaker-files li' );
		filter.addEventListener( 'input', function () {
			var term = filter.value.toLowerCase();
			items.forEach( function ( item ) {
				item.hidden = term !== '' && item.textContent.toLowerCase().indexOf( term ) === -1;
			} );
		} );
	}

	document.querySelectorAll( '.ctmaker-confirm' ).forEach( function ( button ) {
		button.addEventListener( 'click', function ( event ) {
			if ( ! window.confirm( button.getAttribute( 'data-confirm' ) ) ) {
				event.preventDefault();
			}
		} );
	} );
} )();
