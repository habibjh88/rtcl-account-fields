/**
 * Classified Listing – Account Custom Fields (front-end).
 *
 * For each file-upload field:
 *  - Validates the chosen file the moment it changes (type + size), showing an
 *    inline error and clearing the selection — no need to submit the form.
 *  - Gives the "Remove" checkbox immediate visual feedback (the actual deletion
 *    happens server-side on save).
 */
( function () {
	'use strict';

	var L10N = ( window.rtclAcfFront && window.rtclAcfFront.i18n ) || {};

	function format( tpl, arg ) {
		return String( tpl || '' ).replace( /%[sd]/, arg );
	}

	// Live character counter for text / textarea fields that carry a min/max
	// length, so the limit (and why typing stops) is always visible.
	function bindCounter( el ) {
		var max = parseInt( el.getAttribute( 'maxlength' ) || '0', 10 );
		var min = parseInt( el.getAttribute( 'minlength' ) || '0', 10 );
		if ( max <= 0 && min <= 0 ) {
			return;
		}

		var note = document.createElement( 'div' );
		note.className = 'rtcl-acf-charcount';
		el.insertAdjacentElement( 'afterend', note );

		var update = function () {
			var len = el.value.length;
			note.classList.remove( 'is-min', 'is-max' );

			if ( min > 0 && len > 0 && len < min ) {
				note.textContent = format( L10N.charsMin || 'Please enter at least %s characters.', min )
					+ ( max > 0 ? ' (' + len + '/' + max + ')' : '' );
				note.classList.add( 'is-min' );
			} else if ( max > 0 ) {
				note.textContent = len + ' / ' + max;
				if ( len >= max ) {
					note.textContent += ' — ' + format( L10N.charsMax || 'Maximum %s characters reached.', max );
					note.classList.add( 'is-max' );
				}
			} else {
				note.textContent = String( len );
			}
		};

		el.addEventListener( 'input', update );
		update();
	}

	function extensionOf( name ) {
		var dot = name.lastIndexOf( '.' );
		return dot >= 0 ? name.slice( dot + 1 ).toLowerCase() : '';
	}

	function errorNode( wrap ) {
		var node = wrap.querySelector( '.rtcl-acf-file-error' );
		if ( ! node ) {
			node = document.createElement( 'p' );
			node.className = 'rtcl-acf-file-error';
			node.setAttribute( 'role', 'alert' );
			node.hidden = true;
			wrap.appendChild( node );
		}
		return node;
	}

	function showError( wrap, message ) {
		var node = errorNode( wrap );
		node.textContent = message;
		node.hidden = false;
		wrap.classList.add( 'has-error' );
	}

	function clearError( wrap ) {
		var node = wrap.querySelector( '.rtcl-acf-file-error' );
		if ( node ) {
			node.hidden = true;
			node.textContent = '';
		}
		wrap.classList.remove( 'has-error' );
	}

	// Validate the chosen file. Returns true when valid (or empty).
	function validateFile( input, wrap ) {
		if ( ! input.files || ! input.files.length ) {
			clearError( wrap );
			return true;
		}

		var file = input.files[ 0 ];
		var allowed = ( input.getAttribute( 'data-allowed' ) || '' )
			.split( ',' )
			.map( function ( s ) { return s.trim().toLowerCase(); } )
			.filter( Boolean );
		var maxSize = parseInt( input.getAttribute( 'data-maxsize' ) || '0', 10 );

		if ( allowed.length && allowed.indexOf( extensionOf( file.name ) ) === -1 ) {
			var label = input.getAttribute( 'data-allowed-label' ) || allowed.join( ', ' );
			showError( wrap, format( L10N.fileType || 'This file type is not allowed. Accepted types: %s.', label ) );
			input.value = '';
			return false;
		}

		if ( maxSize > 0 && file.size > maxSize ) {
			var maxLabel = input.getAttribute( 'data-maxlabel' ) || '';
			showError( wrap, format( L10N.fileSize || 'This file is too large. Maximum size is %s.', maxLabel ) );
			input.value = '';
			return false;
		}

		clearError( wrap );
		return true;
	}

	function bindWrap( wrap ) {
		var fileInput = wrap.querySelector( 'input[type="file"]' );
		var checkbox = wrap.querySelector( '.rtcl-acf-file-remove input[type="checkbox"]' );

		if ( checkbox ) {
			checkbox.addEventListener( 'change', function () {
				wrap.classList.toggle( 'is-removing', checkbox.checked );
				// Removing and uploading at once is contradictory: clear any pick.
				if ( checkbox.checked && fileInput ) {
					fileInput.value = '';
					clearError( wrap );
				}
			} );
		}

		if ( fileInput ) {
			fileInput.addEventListener( 'change', function () {
				var ok = validateFile( fileInput, wrap );
				// A valid new pick supersedes a pending removal.
				if ( ok && fileInput.value && checkbox && checkbox.checked ) {
					checkbox.checked = false;
					wrap.classList.remove( 'is-removing' );
				}
			} );
		}
	}

	function init() {
		var wraps = document.querySelectorAll( '.rtcl-acf-file' );
		Array.prototype.forEach.call( wraps, bindWrap );

		var lengthFields = document.querySelectorAll( '.rtcl-acf-field input[type="text"], .rtcl-acf-field textarea' );
		Array.prototype.forEach.call( lengthFields, bindCounter );
	}

	if ( 'loading' === document.readyState ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}
}() );
