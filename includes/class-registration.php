<?php
/**
 * Registration-form integration: render, validate and save the custom account
 * fields that are flagged "Show on registration form".
 *
 * Uses the base plugin's registration hooks, so nothing runs when the base is
 * inactive. File fields are intentionally not supported here — uploads during
 * registration (before a user exists) are unreliable — and account-type
 * visibility (`visible_for`) is ignored because the account type is only being
 * chosen on this form.
 *
 * @package RtclAcf
 */

namespace RtclAcf;

defined( 'ABSPATH' ) || exit;

class Registration {

	/**
	 * Name of the $_POST array holding all custom-field values (shared with the
	 * account form so values line up in the same user meta).
	 */
	const INPUT = 'rtcl_acf';

	public function __construct() {
		// Render just above the submit button on the registration form.
		add_action( 'rtcl_register_form', [ $this, 'render_fields' ], 40 );

		// Validate submitted values as part of the base registration checks.
		add_filter( 'rtcl_process_registration_errors', [ $this, 'validate' ], 20, 5 );

		// Save to user meta once the new user exists.
		add_action( 'user_register', [ $this, 'save' ], 20 );
	}

	/**
	 * Fields to show on the registration form: flagged for registration and not
	 * a file field.
	 *
	 * @return array<int,array>
	 */
	private function fields() {
		return array_values(
			array_filter(
				Fields::all(),
				static function ( $field ) {
					return ! empty( $field['show_on_registration'] ) && 'file' !== $field['type'];
				}
			)
		);
	}

	/**
	 * Render the custom fields inside the registration form.
	 */
	public function render_fields() {
		$fields = $this->fields();
		if ( empty( $fields ) ) {
			return;
		}

		// Ensure the field styles + script are present (no-op if already enqueued
		// on the account page). Enqueuing this late still prints in the footer.
		wp_enqueue_style( 'rtcl-acf-frontend', RTCL_ACF_URL . 'assets/css/frontend.css', [], RTCL_ACF_VERSION );
		wp_enqueue_script( 'rtcl-acf-frontend', RTCL_ACF_URL . 'assets/js/frontend.js', [], RTCL_ACF_VERSION, true );
		wp_localize_script( 'rtcl-acf-frontend', 'rtclAcfFront', [ 'i18n' => Fields::frontend_i18n() ] );

		// If a phone field is shown, reuse the base plugin's international phone
		// widget (flag + dial code + validation) — the same one used on Account
		// Details — by loading its script and phone config here too.
		foreach ( $fields as $field ) {
			if ( 'tel' === $field['type'] ) {
				$this->enqueue_phone_widget();
				break;
			}
		}

		$posted = isset( $_POST[ self::INPUT ] ) && is_array( $_POST[ self::INPUT ] ) ? wp_unslash( $_POST[ self::INPUT ] ) : []; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- base form handles the nonce; values are re-sanitized on save.

		foreach ( $fields as $field ) {
			$value = isset( $posted[ $field['id'] ] ) ? $posted[ $field['id'] ] : '';
			$this->render_field( $field, $value );
		}
	}

	/**
	 * Render a single registration field row (matches the base register markup).
	 *
	 * @param array $field Field definition.
	 * @param mixed $value Re-populated value (on a failed submit).
	 */
	private function render_field( array $field, $value ) {
		$input_id = 'rtcl-acf-reg-' . $field['id'];
		$name     = self::INPUT . '[' . $field['id'] . ']';
		$required = $field['required'] ? ' required="required"' : '';
		$selected = is_array( $value ) ? array_map( 'strval', $value ) : [];
		$scalar   = is_array( $value ) ? '' : (string) $value;
		?>
		<div class="rtcl-form-group rtcl-acf-field rtcl-acf-type-<?php echo esc_attr( $field['type'] ); ?>">
			<label for="<?php echo esc_attr( $input_id ); ?>" class="rtcl-field-label">
				<?php echo esc_html( $field['label'] ); ?>
				<?php if ( $field['required'] ) : ?><strong class="rtcl-required">*</strong><?php endif; ?>
			</label>
			<?php
			switch ( $field['type'] ) {
				case 'textarea':
					printf(
						'<textarea name="%1$s" id="%2$s" rows="4" class="rtcl-form-control" placeholder="%3$s"%4$s%5$s>%6$s</textarea>',
						esc_attr( $name ),
						esc_attr( $input_id ),
						esc_attr( $field['placeholder'] ),
						$required, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static markup.
						Fields::html_constraint_attrs( $field ), // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- pre-escaped in helper.
						esc_textarea( $scalar )
					);
					break;

				case 'select':
					echo '<select name="' . esc_attr( $name ) . '" id="' . esc_attr( $input_id ) . '" class="rtcl-form-control"' . $required . '>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
					echo '<option value="">' . esc_html__( '— Select —', 'rtcl-account-fields' ) . '</option>';
					foreach ( $field['options'] as $option ) {
						printf(
							'<option value="%1$s" %2$s>%1$s</option>',
							esc_attr( $option ),
							selected( $scalar, $option, false )
						);
					}
					echo '</select>';
					break;

				case 'radio':
					echo '<div class="rtcl-acf-radio-group">';
					foreach ( $field['options'] as $i => $option ) {
						printf(
							'<label class="rtcl-acf-radio"><input type="radio" name="%1$s" value="%2$s" %3$s%4$s> <span>%5$s</span></label>',
							esc_attr( $name ),
							esc_attr( $option ),
							checked( $scalar, $option, false ),
							0 === $i ? $required : '', // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static markup.
							esc_html( $option )
						);
					}
					echo '</div>';
					break;

				case 'checkbox':
					if ( ! empty( $field['options'] ) ) {
						echo '<div class="rtcl-acf-checkbox-group">';
						foreach ( $field['options'] as $option ) {
							printf(
								'<label class="rtcl-acf-checkbox"><input type="checkbox" name="%1$s[]" value="%2$s" %3$s> <span>%4$s</span></label>',
								esc_attr( $name ),
								esc_attr( $option ),
								in_array( (string) $option, $selected, true ) ? 'checked="checked"' : '',
								esc_html( $option )
							);
						}
						echo '</div>';
					} else {
						printf(
							'<label class="rtcl-acf-checkbox-label"><input type="hidden" name="%1$s" value=""><input type="checkbox" name="%1$s" id="%2$s" value="1" %3$s> %4$s</label>',
							esc_attr( $name ),
							esc_attr( $input_id ),
							checked( $scalar, '1', false ),
							esc_html( $field['placeholder'] ? $field['placeholder'] : $field['label'] )
						);
					}
					break;

				case 'switch':
					printf(
						'<label class="rtcl-acf-switch-field"><input type="hidden" name="%1$s" value=""><input type="checkbox" class="rtcl-acf-switch-input" name="%1$s" id="%2$s" value="1" %3$s><span class="rtcl-acf-switch-track"><span class="rtcl-acf-switch-thumb"></span></span><span class="rtcl-acf-switch-text">%4$s</span></label>',
						esc_attr( $name ),
						esc_attr( $input_id ),
						checked( $scalar, '1', false ),
						esc_html( $field['placeholder'] ? $field['placeholder'] : $field['label'] )
					);
					break;

				case 'tel':
					// Reuse the base plugin's international phone widget (see
					// enqueue_phone_widget). Its script auto-inits every
					// `.rtcl-intl-phone` element and syncs the full number into the
					// hidden input named by `data-target` — which is what we save.
					printf(
						'<div class="rtcl-intl-phone-field">'
						. '<input type="text" name="%1$s" id="%2$s" value="%3$s" class="rtcl-form-control rtcl-intl-phone" data-target="%4$s" data-rule-rtclintlphone="true" placeholder="%5$s" autocomplete="off"%6$s>'
						. '<input type="hidden" name="%4$s" value="%3$s">'
						. '</div>',
						esc_attr( self::INPUT . '_local[' . $field['id'] . ']' ),
						esc_attr( $input_id ),
						esc_attr( $scalar ),
						esc_attr( $name ),
						esc_attr( $field['placeholder'] ),
						$required // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static markup.
					);
					break;

				default:
					// text, number, email, url — a plain input.
					$input_type = in_array( $field['type'], [ 'number', 'email', 'url' ], true ) ? $field['type'] : 'text';
					printf(
						'<input type="%1$s" name="%2$s" id="%3$s" value="%4$s" class="rtcl-form-control" placeholder="%5$s"%6$s%7$s>',
						esc_attr( $input_type ),
						esc_attr( $name ),
						esc_attr( $input_id ),
						esc_attr( $scalar ),
						esc_attr( $field['placeholder'] ),
						$required, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static markup.
						Fields::html_constraint_attrs( $field ) // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- pre-escaped in helper.
					);
					break;
			}
			?>
			<?php if ( '' !== $field['description'] ) : ?>
				<span class="help-block rtcl-acf-desc"><?php echo esc_html( $field['description'] ); ?></span>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Load the base plugin's international phone widget on the registration page.
	 *
	 * The base registers the `rtcl-edit-account` script globally but only enqueues
	 * + configures it on the account page. Here we enqueue that same script and
	 * provide the phone config, so the builder's phone field gets the identical
	 * flag/dial-code widget and validation it has on Account Details — one source
	 * of truth, no duplicated logic.
	 *
	 * @return void
	 */
	private function enqueue_phone_widget() {
		// Already loaded (e.g. a combined account/register page) — the base has
		// configured it; nothing to do.
		if ( wp_script_is( 'rtcl-edit-account', 'enqueued' ) ) {
			return;
		}

		if ( ! wp_script_is( 'rtcl-edit-account', 'registered' ) ) {
			$suffix = ( defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ) ? '' : '.min';
			wp_register_script(
				'rtcl-edit-account',
				rtcl()->get_assets_uri( "js/edit-account{$suffix}.js" ),
				[ 'jquery', 'rtcl-validator' ],
				defined( 'RTCL_VERSION' ) ? RTCL_VERSION : RTCL_ACF_VERSION,
				true
			);
		}

		wp_enqueue_script( 'rtcl-edit-account' );
		wp_localize_script( 'rtcl-edit-account', 'rtcl_edit_account', $this->phone_config() );
	}

	/**
	 * Build the `rtcl_edit_account` config the base phone widget expects: the
	 * allowed country list and default country from Classified Listing's
	 * Misc → Phone settings. Only the config data is replicated here — the widget
	 * and its validation live entirely in the base plugin.
	 *
	 * @return array
	 */
	private function phone_config() {
		$allowed = [];
		$default = '';
		$list    = [];

		if ( class_exists( '\Rtcl\Helpers\Functions' ) ) {
			$allowed = (array) \Rtcl\Helpers\Functions::get_option_item( 'rtcl_misc_phone_settings', 'phone_allowed_countries' );
			$allowed = array_values( array_filter( array_map( 'strval', $allowed ) ) );
			$default = (string) \Rtcl\Helpers\Functions::get_option_item( 'rtcl_misc_phone_settings', 'phone_default_country' );
		}

		$countries = ( function_exists( 'rtcl' ) && isset( rtcl()->countries ) ) ? rtcl()->countries : null;
		if ( $countries ) {
			if ( '' === $default && method_exists( $countries, 'get_base_country' ) ) {
				$default = (string) $countries->get_base_country();
			}
			if ( ! empty( $allowed ) && ! in_array( $default, $allowed, true ) ) {
				$default = (string) reset( $allowed );
			}
			if ( method_exists( $countries, 'get_phone_country_list' ) ) {
				// Base plugin's shared list: built-in countries plus admin-added custom codes
				// (flagged `custom`, which the base widget shows as a text badge, not a flag).
				foreach ( (array) $countries->get_phone_country_list() as $entry ) {
					if ( empty( $entry['value'] ) ) {
						continue;
					}
					if ( ! empty( $allowed ) && ! in_array( (string) $entry['value'], $allowed, true ) ) {
						continue;
					}
					$list[] = $entry;
				}
			} else {
				foreach ( (array) $countries->get_countries() as $code => $name ) {
					$dial = $countries->get_country_calling_code( $code );
					if ( ! $dial ) {
						continue;
					}
					if ( ! empty( $allowed ) && ! in_array( (string) $code, $allowed, true ) ) {
						continue;
					}
					$list[] = [
						'value'     => $code,
						'label'     => $name . ' (' . $dial . ')',
						'dial_code' => $dial,
					];
				}
			}
		}

		return [
			'countryPhoneList' => $list,
			'default_country'  => $default,
			'i18n'             => [
				'search'        => __( 'Search', 'rtcl-account-fields' ),
				'need_country'  => __( 'Please select a country code.', 'rtcl-account-fields' ),
				'invalid_phone' => __( 'Please enter a valid phone number for the selected country.', 'rtcl-account-fields' ),
			],
		];
	}

	/**
	 * Validate the registration custom fields, adding to the base error object.
	 *
	 * Fires from `rtcl_process_registration_errors`.
	 *
	 * @param \WP_Error $errors   Base registration errors.
	 * @param string    $email    Submitted email (unused).
	 * @param string    $username Submitted username (unused).
	 * @param string    $password Submitted password (unused).
	 * @param array     $data     Submitted data (unused; we read $_POST).
	 *
	 * @return \WP_Error
	 */
	public function validate( $errors, $email = '', $username = '', $password = '', $data = [] ) {
		if ( ! is_wp_error( $errors ) ) {
			$errors = new \WP_Error();
		}

		$posted = isset( $_POST[ self::INPUT ] ) && is_array( $_POST[ self::INPUT ] ) ? $_POST[ self::INPUT ] : []; // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- base form handles the nonce; unslashed + validated in Fields::validate_value().

		foreach ( $this->fields() as $field ) {
			$raw     = $posted[ $field['id'] ] ?? '';
			$message = Fields::validate_value( $field, $raw );
			if ( '' !== $message ) {
				$errors->add( 'rtcl_acf_' . $field['id'], $message );
			}
		}

		return $errors;
	}

	/**
	 * Persist the registration custom fields to the new user's meta.
	 *
	 * Fires from `user_register` (core, inside wp_insert_user). Scoped to our own
	 * form by requiring our field group in the request.
	 *
	 * @param int $user_id Newly registered user id.
	 */
	public function save( $user_id ) {
		$user_id = (int) $user_id;
		if ( ! $user_id || ! isset( $_POST[ self::INPUT ] ) || ! is_array( $_POST[ self::INPUT ] ) ) {
			return;
		}

		$posted = $_POST[ self::INPUT ]; // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- base form handles the nonce; unslashed + sanitized in Fields::sanitize_value().

		foreach ( $this->fields() as $field ) {
			$raw   = $posted[ $field['id'] ] ?? '';
			$value = Fields::sanitize_value( $field, $raw );
			update_user_meta( $user_id, Fields::meta_key( $field['id'] ), $value );
		}
	}
}
