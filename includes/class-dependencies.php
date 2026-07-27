<?php
/**
 * Dependency guard for the add-on.
 *
 * Ensures the base "Classified Listing" plugin is installed, active, and at a
 * compatible version. When it is not, the add-on stays dormant and an admin
 * notice explains what is required — mirroring how Classified Listing Pro gates
 * itself.
 *
 * @package RtclAcf
 */

namespace RtclAcf;

defined( 'ABSPATH' ) || exit;

class Dependencies {

	/**
	 * Minimum required version of the base Classified Listing plugin.
	 */
	const MIN_RTCL = '6.0.0';

	/**
	 * Singleton instance.
	 *
	 * @var Dependencies|null
	 */
	private static $instance = null;

	/**
	 * Get the shared instance.
	 *
	 * @return Dependencies
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Whether every dependency is satisfied. Registers the relevant admin notice
	 * when it is not.
	 *
	 * @return bool
	 */
	public function met() {
		// Base plugin missing / inactive.
		if ( ! function_exists( 'rtcl' ) || ! defined( 'RTCL_VERSION' ) ) {
			add_action( 'admin_notices', [ $this, 'missing_notice' ] );

			return false;
		}

		// Base plugin present but too old.
		if ( version_compare( RTCL_VERSION, self::MIN_RTCL, '<' ) ) {
			add_action( 'admin_notices', [ $this, 'old_version_notice' ] );

			return false;
		}

		return true;
	}

	/**
	 * Thickbox "plugin information" install link for the base plugin.
	 *
	 * @return string
	 */
	private function base_plugin_link() {
		return esc_url(
			add_query_arg(
				[
					'tab'       => 'plugin-information',
					'plugin'    => 'classified-listing',
					'TB_iframe' => 'true',
					'width'     => '640',
					'height'    => '500',
				],
				admin_url( 'plugin-install.php' )
			)
		);
	}

	/**
	 * Notice: base plugin not installed / not active.
	 *
	 * @return void
	 */
	public function missing_notice() {
		if ( ! current_user_can( 'activate_plugins' ) ) {
			return;
		}

		$link    = $this->base_plugin_link();
		$message = wp_kses(
			sprintf(
				/* translators: 1: linked plugin name, 2: minimum version */
				__( '<strong>Classified Listing – Account Custom Fields</strong> is enabled but not effective. It requires <a class="thickbox open-plugin-details-modal" href="%1$s">Classified Listing</a> %2$s or newer to be installed and active.', 'rtcl-account-fields' ),
				$link,
				self::MIN_RTCL
			),
			[
				'strong' => [],
				'a'      => [
					'href'  => true,
					'class' => true,
				],
			]
		);

		printf( '<div class="notice notice-error"><p>%1$s</p></div>', $message ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped via wp_kses above.
	}

	/**
	 * Notice: base plugin present but older than the required version.
	 *
	 * @return void
	 */
	public function old_version_notice() {
		if ( ! current_user_can( 'activate_plugins' ) ) {
			return;
		}

		$link    = $this->base_plugin_link();
		$message = wp_kses(
			sprintf(
				/* translators: 1: current base version, 2: linked plugin name, 3: minimum version */
				__( '<strong>Classified Listing – Account Custom Fields</strong> is enabled but not effective. It needs <a class="thickbox open-plugin-details-modal" href="%2$s">Classified Listing</a> %3$s or newer — version %1$s is installed. Please update Classified Listing.', 'rtcl-account-fields' ),
				esc_html( RTCL_VERSION ),
				$link,
				self::MIN_RTCL
			),
			[
				'strong' => [],
				'a'      => [
					'href'  => true,
					'class' => true,
				],
			]
		);

		printf( '<div class="notice notice-error"><p>%1$s</p></div>', $message ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped via wp_kses above.
	}
}
