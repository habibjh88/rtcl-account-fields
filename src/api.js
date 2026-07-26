/**
 * REST helpers for the form builder.
 */
import apiFetch from '@wordpress/api-fetch';

const bootstrap = window.rtclAcf || {};
const NS = bootstrap.restNamespace || 'rtcl-acf/v1';

/**
 * Strip client-only keys before sending a field to the server.
 *
 * @param {Object} field Working field object.
 * @return {Object} Server-shaped field.
 */
function toServer( field ) {
	/* eslint-disable-next-line no-unused-vars */
	const { _uid, ...rest } = field;
	return rest;
}

export function fetchFields() {
	return apiFetch( { path: `/${ NS }/fields` } );
}

export function saveFields( fields ) {
	return apiFetch( {
		path: `/${ NS }/fields`,
		method: 'POST',
		data: { fields: fields.map( toServer ) },
	} );
}

export { bootstrap };
