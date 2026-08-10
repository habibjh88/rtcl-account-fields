<?php
/**
 * Admin field manager. Boots the React form builder and its supporting REST API.
 *
 * @package RtclAcf
 */

namespace RtclAcf;

defined( 'ABSPATH' ) || exit;

class Admin {

	const PAGE       = 'rtcl-account-fields';
	const CAPABILITY = 'manage_options';

	public function __construct() {
		add_action( 'admin_menu', [ $this, 'register_menu' ], 60 );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue' ] );
		add_action( 'in_admin_header', [ $this, 'suppress_admin_notices' ], 1000 );
	}

	/**
	 * Remove every admin notice on our own settings page, whether it comes from
	 * this plugin, WordPress core, or any third-party plugin.
	 */
	public function suppress_admin_notices() {
		if ( ! isset( $_GET['page'] ) || self::PAGE !== $_GET['page'] ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}
		remove_all_actions( 'admin_notices' );
		remove_all_actions( 'all_admin_notices' );
		remove_all_actions( 'user_admin_notices' );
		remove_all_actions( 'network_admin_notices' );
	}

	/**
	 * Add the submenu under the Classified Listing top-level menu.
	 */
	public function register_menu() {
		add_submenu_page(
			'rtcl-admin',
			__( 'Account Custom Fields', 'rtcl-account-fields' ),
			__( 'Account Fields', 'rtcl-account-fields' ),
			self::CAPABILITY,
			self::PAGE,
			[ $this, 'render_page' ]
		);
	}

	/**
	 * Enqueue the built React app on our page only.
	 *
	 * @param string $hook Current admin page hook.
	 */
	public function enqueue( $hook ) {
		if ( false === strpos( (string) $hook, self::PAGE ) ) {
			return;
		}

		$asset_path = RTCL_ACF_PATH . 'build/index.asset.php';
		$script_url = RTCL_ACF_URL . 'build/index.js';
		$style_url  = RTCL_ACF_URL . 'build/index.css';

		if ( ! file_exists( $asset_path ) ) {
			add_action( 'admin_notices', [ $this, 'build_missing_notice' ] );
			return;
		}

		$asset = require $asset_path;

		wp_enqueue_script(
			'rtcl-acf-app',
			$script_url,
			$asset['dependencies'],
			$asset['version'],
			true
		);

		if ( file_exists( RTCL_ACF_PATH . 'build/index.css' ) ) {
			wp_enqueue_style(
				'rtcl-acf-app',
				$style_url,
				[],
				$asset['version']
			);
			wp_add_inline_style( 'rtcl-acf-app', $this->primary_color_css() );
		}

		wp_set_script_translations( 'rtcl-acf-app', 'rtcl-account-fields', RTCL_ACF_PATH . 'languages' );

		wp_add_inline_script(
			'rtcl-acf-app',
			'window.rtclAcf = ' . wp_json_encode(
				[
					'restNamespace' => Rest::NAMESPACE,
					'fields'        => array_values( Fields::all() ),
					'types'         => Fields::types(),
					'defaults'      => Fields::defaults(),
					'visibility'    => $this->visibility_labels(),
					'contexts'      => $this->context_labels(),
					'fileTypes'     => Fields::available_file_types(),
				]
			) . ';',
			'before'
		);
	}

	/**
	 * Inline CSS that themes the builder with the Classified Listing primary
	 * color (and a darker shade for the gradient), scoped to our app.
	 *
	 * @return string
	 */
	private function primary_color_css() {
		$primary = '#3232ff'; // Base plugin default.

		if ( class_exists( '\Rtcl\Helpers\Functions' ) && method_exists( '\Rtcl\Helpers\Functions', 'get_option' ) ) {
			$style = \Rtcl\Helpers\Functions::get_option( 'rtcl_style_settings' );
			if ( is_array( $style ) && ! empty( $style['primary'] ) ) {
				$primary = $style['primary'];
			}
		}

		$primary = $this->normalize_hex( $primary, '#3232ff' );
		$dark    = $this->darken_hex( $primary, 18 );
		$rgb     = $this->hex_to_rgb( $primary );

		return sprintf(
			'.rtcl-acf-wrap{--rtcl-primary-color:%1$s;--rtcl-primary-dark:%2$s;--rtcl-primary-rgb:%3$d,%4$d,%5$d;}',
			$primary,
			$dark,
			$rgb['r'],
			$rgb['g'],
			$rgb['b']
		);
	}

	/**
	 * Coerce a value into a 6-digit hex color, or return the fallback.
	 *
	 * @param string $hex      Candidate color.
	 * @param string $fallback Fallback color.
	 *
	 * @return string
	 */
	private function normalize_hex( $hex, $fallback ) {
		$hex = ltrim( trim( (string) $hex ), '#' );
		if ( 3 === strlen( $hex ) ) {
			$hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
		}
		return preg_match( '/^[0-9a-fA-F]{6}$/', $hex ) ? '#' . strtolower( $hex ) : $fallback;
	}

	/**
	 * Darken a hex color by a percentage.
	 *
	 * @param string $hex     6-digit hex color (with #).
	 * @param int    $percent Percentage to darken (0-100).
	 *
	 * @return string
	 */
	private function darken_hex( $hex, $percent ) {
		$rgb    = $this->hex_to_rgb( $hex );
		$factor = max( 0, min( 100, (int) $percent ) ) / 100;
		$out    = '#';
		foreach ( [ 'r', 'g', 'b' ] as $c ) {
			$out .= str_pad( dechex( (int) round( $rgb[ $c ] * ( 1 - $factor ) ) ), 2, '0', STR_PAD_LEFT );
		}
		return $out;
	}

	/**
	 * Split a hex color into R/G/B integers.
	 *
	 * @param string $hex 6-digit hex color (with #).
	 *
	 * @return array{r:int,g:int,b:int}
	 */
	private function hex_to_rgb( $hex ) {
		$hex = ltrim( (string) $hex, '#' );
		return [
			'r' => (int) hexdec( substr( $hex, 0, 2 ) ),
			'g' => (int) hexdec( substr( $hex, 2, 2 ) ),
			'b' => (int) hexdec( substr( $hex, 4, 2 ) ),
		];
	}

	/**
	 * Labels for the "Visible for" (account type) setting, honoring the base
	 * plugin's configured Seller/Buyer labels when available.
	 *
	 * @return array<string,string>
	 */
	private function visibility_labels() {
		$seller = __( 'Seller', 'rtcl-account-fields' );
		$buyer  = __( 'Buyer', 'rtcl-account-fields' );

		if ( class_exists( '\Rtcl\Helpers\Functions' ) ) {
			if ( method_exists( '\Rtcl\Helpers\Functions', 'get_user_type_seller_label' ) ) {
				$seller = \Rtcl\Helpers\Functions::get_user_type_seller_label();
			}
			if ( method_exists( '\Rtcl\Helpers\Functions', 'get_user_type_buyer_label' ) ) {
				$buyer = \Rtcl\Helpers\Functions::get_user_type_buyer_label();
			}
		}

		return [
			'all'    => __( 'All (seller & buyer)', 'rtcl-account-fields' ),
			'seller' => $seller,
			'buyer'  => $buyer,
		];
	}

	/**
	 * Labels + hints for the five per-context visibility switches shown in the
	 * builder's settings panel. Keys match Fields::context_keys().
	 *
	 * @return array<string,array{label:string,hint:string}>
	 */
	private function context_labels() {
		return [
			'show_on_user_profile'   => [
				'label' => __( 'User profile', 'rtcl-account-fields' ),
				'hint'  => __( 'Editable on the Account Details form and the WordPress profile screen.', 'rtcl-account-fields' ),
			],
			'show_on_user_frontend'  => [
				'label' => __( 'User front-end (author page)', 'rtcl-account-fields' ),
				'hint'  => __( 'Displayed publicly on the author profile page.', 'rtcl-account-fields' ),
			],
			'show_on_store_profile'  => [
				'label' => __( 'Store profile', 'rtcl-account-fields' ),
				'hint'  => __( 'Editable on the store settings form (My Account → Store).', 'rtcl-account-fields' ),
			],
			'show_on_store_frontend' => [
				'label' => __( 'Store front-end', 'rtcl-account-fields' ),
				'hint'  => __( 'Displayed in the single store “More details” popup.', 'rtcl-account-fields' ),
			],
			'show_on_registration'   => [
				'label' => __( 'Registration form', 'rtcl-account-fields' ),
				'hint'  => __( 'Also collected when a user registers.', 'rtcl-account-fields' ),
			],
		];
	}

	/**
	 * Render the mount point for the React app.
	 */
	public function render_page() {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			return;
		}
		echo '<div class="wrap rtcl-acf-wrap"><div id="rtcl-acf-app" class="rtcl-acf-app-root">';
		echo '<div class="rtcl-acf-app-loading"><span class="spinner is-active"></span> '
			. esc_html__( 'Loading form builder…', 'rtcl-account-fields' )
			. '</div>';
		echo '</div></div>';
	}

	/**
	 * Notice shown when the compiled React bundle is missing (dev checkout).
	 */
	public function build_missing_notice() {
		echo '<div class="notice notice-error"><p><strong>'
			. esc_html__( 'Account Custom Fields:', 'rtcl-account-fields' )
			. '</strong> '
			. esc_html__( 'the builder assets are not compiled. Run "npm install && npm run build" in the plugin folder.', 'rtcl-account-fields' )
			. '</p></div>';
	}
}
