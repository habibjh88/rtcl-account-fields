<?php
/**
 * Store add-on integration: render the custom fields on the front-end Store
 * settings form (my-account/store), validate + save them, and display them on
 * the public single-store "More details" popup.
 *
 * Unlike the user contexts (which store per-user meta), the store contexts store
 * **per-store-post meta** — a store is a post, so its field values belong to the
 * store, not to the owner user. A field enabled on both Account Details and the
 * Store profile therefore holds two independent values: the account value in
 * user meta, the store value in `_rtcl_acf_<id>` post meta on the store post.
 *
 * All integration goes through the Store add-on's own hooks, so nothing runs
 * unless the Store plugin is active (guarded in the constructor).
 *
 * @package RtclAcf
 */

namespace RtclAcf;

defined( 'ABSPATH' ) || exit;

class Store {

	public function __construct() {
		// Only wire up when the Store add-on is actually present.
		if ( ! class_exists( '\RtclStore\Models\Store' ) || ! function_exists( 'rtclStore' ) ) {
			return;
		}

		// Render inside the store settings form, just before the submit button.
		add_action( 'rtcl_store_my_account_form_end', [ $this, 'render_store_profile_fields' ], 20 );

		// Validate as part of the store-save AJAX (blocks the save on error).
		add_filter( 'rtcl_store_mata_data_before_update', [ $this, 'validate_store_profile' ], 20, 3 );

		// Persist to the store post's meta after the store is saved.
		add_action( 'rtcl_store_meta_data_saved', [ $this, 'save_store_profile' ], 20, 3 );

		// Display on the single-store "More details" popup.
		add_action( 'rtcl_store_details_modal_after_meta', [ $this, 'render_store_frontend_fields' ], 20 );
	}

	/**
	 * The current user's store post id, or 0 when they have no store yet.
	 *
	 * @return int
	 */
	private function current_store_id() {
		if ( class_exists( '\RtclStore\Helpers\Functions' ) ) {
			$store = \RtclStore\Helpers\Functions::get_current_user_store();
			if ( $store && method_exists( $store, 'get_id' ) ) {
				return (int) $store->get_id();
			}
		}
		return 0;
	}

	/**
	 * The owner user id used only for account-type (`visible_for`) gating.
	 *
	 * @param mixed $store Store object, or null.
	 *
	 * @return int
	 */
	private function owner_id( $store = null ) {
		if ( is_object( $store ) && method_exists( $store, 'owner_id' ) ) {
			$owner = (int) $store->owner_id();
			if ( $owner ) {
				return $owner;
			}
		}
		return get_current_user_id();
	}

	/**
	 * Extract a store post id from whatever the save hook passed (a Store object
	 * on update, a WP_Post on insert).
	 *
	 * @param mixed $store Store object or WP_Post.
	 *
	 * @return int
	 */
	private function store_id_from( $store ) {
		if ( is_object( $store ) ) {
			if ( method_exists( $store, 'get_id' ) ) {
				return (int) $store->get_id();
			}
			if ( isset( $store->ID ) ) {
				return (int) $store->ID;
			}
		}
		return 0;
	}

	/**
	 * Fields opted into the store-profile (editor) context and visible to $owner_id.
	 *
	 * @param int $owner_id Store owner id (for account-type gating).
	 *
	 * @return array<int,array>
	 */
	private function profile_fields( $owner_id ) {
		return array_values(
			array_filter(
				Fields::all(),
				static function ( $field ) use ( $owner_id ) {
					return ! empty( $field['show_on_store_profile'] )
						&& Fields::field_visible_for_user( $field, $owner_id );
				}
			)
		);
	}

	/**
	 * Whether our field section (any input / file / remove flag) posted with the
	 * store form.
	 *
	 * @return bool
	 */
	private function submitted() {
		return ( isset( $_POST[ Frontend::INPUT ] ) && is_array( $_POST[ Frontend::INPUT ] ) ) // phpcs:ignore WordPress.Security.NonceVerification.Missing -- store handler verifies the nonce; values re-sanitized on save.
			|| isset( $_FILES[ Fields::FILE_INPUT ] )
			|| isset( $_POST[ Fields::FILE_REMOVE ] ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
	}

	/**
	 * Render the custom fields inside the store settings form (single column).
	 *
	 * Reuses Frontend::render_account_field() for identical markup + input names
	 * (`rtcl_acf[<id>]`), but values come from the store post's meta.
	 *
	 * @param mixed $store Current Store object (or falsy when none yet).
	 */
	public function render_store_profile_fields( $store = null ) {
		$owner_id = $this->owner_id( $store );
		$store_id = $this->store_id_from( $store );

		$fields = $this->profile_fields( $owner_id );
		if ( empty( $fields ) ) {
			return;
		}

		// Front-end field styles/scripts already load on the account page via
		// Frontend::enqueue(); enqueue defensively in case a theme narrows that.
		wp_enqueue_style( 'rtcl-acf-frontend', RTCL_ACF_URL . 'assets/css/frontend.css', [], RTCL_ACF_VERSION );
		wp_enqueue_script( 'rtcl-acf-frontend', RTCL_ACF_URL . 'assets/js/frontend.js', [], RTCL_ACF_VERSION, true );
		wp_localize_script( 'rtcl-acf-frontend', 'rtclAcfFront', [ 'i18n' => Fields::frontend_i18n() ] );

		// If a phone field is shown, load the base international phone widget too.
		foreach ( $fields as $field ) {
			if ( 'tel' === $field['type'] ) {
				Fields::enqueue_phone_widget();
				break;
			}
		}
		?>
		<div class="rtcl-acf-store-wrap">
			<h3 class="rtcl-myaccount-content-title rtcl-acf-section-title">
				<?php echo esc_html( apply_filters( 'rtcl_acf_store_section_title', __( 'Additional Information', 'rtcl-account-fields' ) ) ); ?>
			</h3>
			<?php
			foreach ( $fields as $field ) {
				$value = $store_id ? Fields::object_value_raw( 'post', $store_id, $field ) : '';
				Frontend::render_account_field( $field, $value );
			}
			?>
			<?php if ( Frontend::has_file_field( $fields ) ) : ?>
				<script>( function () { var s = document.currentScript; var f = s && s.closest( 'form' ); if ( f ) { f.setAttribute( 'enctype', 'multipart/form-data' ); } } )();</script>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Validate the store-profile custom fields during the store-save AJAX.
	 *
	 * Hooked to `rtcl_store_mata_data_before_update` (a filter that runs before
	 * the store is persisted). On any error we emit the store handler's JSON
	 * error shape and exit, which blocks the save and shows the message.
	 *
	 * @param array $meta    Sanitized store meta about to be saved.
	 * @param array $data    Raw $_POST.
	 * @param array $request Raw $_REQUEST.
	 *
	 * @return array The unmodified $meta.
	 */
	public function validate_store_profile( $meta, $data = [], $request = [] ) {
		if ( ! $this->submitted() ) {
			return $meta;
		}

		$owner_id = $this->owner_id( null );
		$store_id = $this->current_store_id();
		// Raw (slashed) values: Fields::validate_value() unslashes once internally.
		$submitted = isset( $_POST[ Frontend::INPUT ] ) && is_array( $_POST[ Frontend::INPUT ] ) ? $_POST[ Frontend::INPUT ] : []; // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- store handler verified the nonce; validated in Fields::validate_value().

		$errors = [];
		foreach ( $this->profile_fields( $owner_id ) as $field ) {
			if ( 'file' === $field['type'] ) {
				$message = Fields::validate_file( $field, $store_id, 'post' );
			} else {
				$raw     = $submitted[ $field['id'] ] ?? '';
				$message = Fields::validate_value( $field, $raw );
			}
			if ( '' !== $message ) {
				$errors[] = $message;
			}
		}

		if ( ! empty( $errors ) ) {
			wp_send_json( [
				'error'   => true,
				'message' => implode( '<br>', array_map( 'esc_html', $errors ) ),
				'data'    => $data,
			] );
		}

		return $meta;
	}

	/**
	 * Persist the store-profile custom fields to the store post's meta.
	 *
	 * Hooked to `rtcl_store_meta_data_saved` (fires after the store is saved), so
	 * the store post id is available even when a brand-new store was just created.
	 *
	 * @param int   $store_owner_id Store owner user id (for account-type gating).
	 * @param mixed $store          Store object (update) or WP_Post (insert).
	 * @param array $request        Raw $_REQUEST (unused; we read $_POST).
	 */
	public function save_store_profile( $store_owner_id, $store = null, $request = [] ) {
		$store_id = $this->store_id_from( $store );
		if ( ! $store_id || ! $this->submitted() ) {
			return;
		}

		$owner_id = absint( $store_owner_id ) ?: $this->owner_id( $store );
		// Raw (slashed) values: Fields::sanitize_value() unslashes once internally.
		$submitted = isset( $_POST[ Frontend::INPUT ] ) && is_array( $_POST[ Frontend::INPUT ] ) ? $_POST[ Frontend::INPUT ] : []; // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- store handler verified the nonce; sanitized in Fields::sanitize_value().

		foreach ( $this->profile_fields( $owner_id ) as $field ) {
			if ( 'file' === $field['type'] ) {
				Fields::save_file_field( $field, $store_id, 'post', $owner_id );
				continue;
			}
			$raw = $submitted[ $field['id'] ] ?? '';
			update_post_meta( $store_id, Fields::meta_key( $field['id'] ), Fields::sanitize_value( $field, $raw ) );
		}
	}

	/**
	 * Display the custom fields on the single-store "More details" popup.
	 *
	 * Values come from the store post's meta; only fields opted into the store
	 * front-end context with a non-empty value are shown.
	 *
	 * @param mixed $store Store being displayed (passed by the modal hook).
	 */
	public function render_store_frontend_fields( $store = null ) {
		$store_id = $this->store_id_from( $store );
		if ( ! $store_id ) {
			return;
		}
		$owner_id = $this->owner_id( $store );

		$rows = [];
		foreach ( Fields::all() as $field ) {
			if ( empty( $field['show_on_store_frontend'] ) ) {
				continue;
			}
			if ( ! Fields::field_visible_for_user( $field, $owner_id ) ) {
				continue;
			}
			$value = Fields::object_value_raw( 'post', $store_id, $field );
			if ( is_array( $value ) ? empty( $value ) : ( '' === (string) $value ) ) {
				continue;
			}
			$rows[] = [ 'field' => $field, 'value' => $value ];
		}

		if ( empty( $rows ) ) {
			return;
		}

		foreach ( $rows as $row ) :
			?>
			<div class="more-item rtcl-acf-store-item rtcl-acf-store-<?php echo esc_attr( $row['field']['type'] ); ?>">
				<h3><?php echo esc_html( $row['field']['label'] ); ?></h3>
				<div class="rtcl-acf-store-value"><?php echo Fields::format_public_value( $row['field'], $row['value'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped per type in Fields::format_public_value(). ?></div>
			</div>
			<?php
		endforeach;
	}
}
