<?php
/**
 * Plugin Name:       Classified Listing – Account Details Custom Fields
 * Plugin URI:        https://radiustheme.com/
 * Description:       Add-on for Classified Listing. Lets admins create extra custom fields for the front-end Account Details page and display them on the public author page.
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
 * Bootstrap the add-on, but only when the base plugin is active.
 *
 * If the base plugin is deactivated the add-on stays completely dormant
 * (no hooks, no admin page) and shows a single admin notice instead.
 *
 * @return void
 */
function rtcl_acf_bootstrap() {
	if ( ! function_exists( 'rtcl' ) || ! defined( 'RTCL_VERSION' ) ) {
		add_action( 'admin_notices', 'rtcl_acf_missing_base_notice' );

		return;
	}

	require_once RTCL_ACF_PATH . 'includes/class-fields.php';
	require_once RTCL_ACF_PATH . 'includes/class-rest.php';
	require_once RTCL_ACF_PATH . 'includes/class-admin.php';
	require_once RTCL_ACF_PATH . 'includes/class-frontend.php';
	require_once RTCL_ACF_PATH . 'includes/class-profile.php';

	// Translations.
	load_plugin_textdomain( 'rtcl-account-fields', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );

	new \RtclAcf\Rest();
	new \RtclAcf\Admin();
	new \RtclAcf\Frontend();
	new \RtclAcf\Profile();
}

/**
 * Admin notice shown when the base "Classified Listing" plugin is not active.
 *
 * @return void
 */
function rtcl_acf_missing_base_notice() {
	if ( ! current_user_can( 'activate_plugins' ) ) {
		return;
	}
	?>
	<div class="notice notice-error">
		<p>
			<strong><?php esc_html_e( 'Classified Listing – Account Custom Fields', 'rtcl-account-fields' ); ?></strong>
			<?php esc_html_e( 'requires the Classified Listing plugin to be installed and active.', 'rtcl-account-fields' ); ?>
		</p>
	</div>
	<?php
}
