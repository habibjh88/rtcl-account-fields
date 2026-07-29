<?php
/**
 * Licensing: register this add-on with the base plugin's license manager so it
 * gets a key field under Classified Listing → Tools → Licensing and receives
 * automatic updates from radiustheme.com.
 *
 * @package RtclAcf
 */

namespace RtclAcf;

defined( 'ABSPATH' ) || exit;

class License {

	/**
	 * EDD product id on radiustheme.com for this add-on.
	 */
	const PRODUCT_ID = 311319;

	public function __construct() {
		add_filter( 'rtcl_licenses', [ $this, 'register' ], 20 );
	}

	/**
	 * Append this add-on's license definition to the base plugin's list.
	 *
	 * The base plugin (LicensingController) collects these on `init` and hands
	 * each one to its RtclLicense model, which adds the settings field, the AJAX
	 * activation handler, and the plugin-update checks.
	 *
	 * @param array $licenses Licenses gathered from every add-on.
	 *
	 * @return array
	 */
	public function register( $licenses ) {
		$licenses[] = [
			'plugin_file' => RTCL_ACF_FILE,
			'api_data'    => [
				'key_name'    => 'license_rtcl_acf_key',
				'status_name' => 'license_rtcl_acf_status',
				'action_name' => 'rtcl_acf_manage_licensing',
				'product_id'  => self::PRODUCT_ID,
				'version'     => RTCL_ACF_VERSION,
			],
			'settings'    => [
				'title' => esc_html__( 'Account Fields Manager license key', 'rtcl-account-fields' ),
			],
		];

		return $licenses;
	}
}
