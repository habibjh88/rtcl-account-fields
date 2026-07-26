<?php
/**
 * REST API for the React form builder: read and bulk-save field definitions.
 *
 * @package RtclAcf
 */

namespace RtclAcf;

defined( 'ABSPATH' ) || exit;

class Rest {

	/**
	 * REST namespace.
	 */
	const NAMESPACE = 'rtcl-acf/v1';

	/**
	 * Capability required to manage fields.
	 */
	const CAPABILITY = 'manage_options';

	public function __construct() {
		add_action( 'rest_api_init', [ $this, 'register_routes' ] );
	}

	/**
	 * Register the field endpoints.
	 */
	public function register_routes() {
		register_rest_route(
			self::NAMESPACE,
			'/fields',
			[
				[
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => [ $this, 'get_fields' ],
					'permission_callback' => [ $this, 'can_manage' ],
				],
				[
					'methods'             => \WP_REST_Server::EDITABLE,
					'callback'            => [ $this, 'save_fields' ],
					'permission_callback' => [ $this, 'can_manage' ],
					'args'                => [
						'fields' => [
							'type'     => 'array',
							'required' => true,
						],
					],
				],
			]
		);
	}

	/**
	 * Permission check for every route.
	 *
	 * @return bool
	 */
	public function can_manage() {
		return current_user_can( self::CAPABILITY );
	}

	/**
	 * GET /fields — return the current fields plus supporting metadata.
	 *
	 * @return \WP_REST_Response
	 */
	public function get_fields() {
		return rest_ensure_response(
			[
				'fields' => array_values( Fields::all() ),
				'types'  => Fields::types(),
			]
		);
	}

	/**
	 * POST/PUT /fields — replace the whole collection with a normalized copy.
	 *
	 * @param \WP_REST_Request $request Request object.
	 *
	 * @return \WP_REST_Response
	 */
	public function save_fields( \WP_REST_Request $request ) {
		$raw    = (array) $request->get_param( 'fields' );
		$fields = Fields::normalize_collection( $raw );

		Fields::save( $fields );

		return rest_ensure_response(
			[
				'fields' => array_values( Fields::all() ),
				'types'  => Fields::types(),
			]
		);
	}
}
