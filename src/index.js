/**
 * Entry point: mount the Account Fields form builder.
 */
import { createRoot } from '@wordpress/element';
import App from './app';
import './style/app.scss';

document.addEventListener( 'DOMContentLoaded', () => {
	const root = document.getElementById( 'rtcl-acf-app' );
	if ( ! root ) {
		return;
	}
	root.innerHTML = '';
	createRoot( root ).render( <App /> );
} );
