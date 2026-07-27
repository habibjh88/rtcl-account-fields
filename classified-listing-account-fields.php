<?php
/**
 * Plugin Name:       Classified Listing – Account Details Custom Fields
 * Plugin URI:        https://radiustheme.com/
 * Description:       Add-on for Classified Listing. Lets admins create extra custom fields for the front-end Account Details page and display them on the public author page. Requires Classified Listing 6.0.0 or newer.
 * Version:           1.0.0
 * Requires PHP:      7.4
 * Requires at least: 6.6
 * Requires Plugins:  classified-listing
 * Author:            RadiusTheme
 * Author URI:        https://radiustheme.com
 * Text Domain:       rtcl-account-fields
 * Domain Path:       /languages
 *
 * @package RtclAcf
 */

defined( 'ABSPATH' ) || exit;

define( 'RTCL_ACF_VERSION', '1.0.0' );
define( 'RTCL_ACF_FILE', __FILE__ );
define( 'RTCL_ACF_PATH', plugin_dir_path( __FILE__ ) );
define( 'RTCL_ACF_URL', plugin_dir_url( __FILE__ ) );

/**
 * The base plugin ("classified-listing") boots on `plugins_loaded` at the default
 * priority (10). We boot at 20 so `rtcl()` / `RTCL_VERSION` are guaranteed available
 * when the base is active — and we can reliably detect when it is NOT.
 */
add_action( 'plugins_loaded', 'rtcl_acf_bootstrap', 20 );

/**
 * Bootstrap the add-on, but only when the base plugin dependency is satisfied.
 *
 * If Classified Listing is missing, inactive, or older than the required version
 * (see Dependencies::MIN_RTCL), the add-on stays completely dormant — no hooks,
 * no admin page — and shows an explanatory admin notice instead.
 *
 * @return void
 */
function rtcl_acf_bootstrap() {
	require_once RTCL_ACF_PATH . 'includes/class-dependencies.php';

	if ( ! \RtclAcf\Dependencies::instance()->met() ) {
		return;
	}

	require_once RTCL_ACF_PATH . 'includes/class-fields.php';
	require_once RTCL_ACF_PATH . 'includes/class-rest.php';
	require_once RTCL_ACF_PATH . 'includes/class-admin.php';
	require_once RTCL_ACF_PATH . 'includes/class-frontend.php';
	require_once RTCL_ACF_PATH . 'includes/class-profile.php';
	require_once RTCL_ACF_PATH . 'includes/class-registration.php';

	// Translations.
	load_plugin_textdomain( 'rtcl-account-fields', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );

	new \RtclAcf\Rest();
	new \RtclAcf\Admin();
	new \RtclAcf\Frontend();
	new \RtclAcf\Profile();
	new \RtclAcf\Registration();
}
