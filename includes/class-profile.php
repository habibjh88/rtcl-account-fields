<?php
/**
 * WordPress admin user-profile integration: render, validate and save the custom
 * account fields on profile.php (own profile) and user-edit.php (other users).
 *
 * @package RtclAcf
 */

namespace RtclAcf;

defined( 'ABSPATH' ) || exit;

class Profile {

	/**
	 * Name of the $_POST array holding all custom-field values.
	 */
	const INPUT = 'rtcl_acf';

	/**
	 * Nonce action / field name for the profile fields.
	 */
	const NONCE = 'rtcl_acf_profile';

	public function __construct() {
		// Render on the "Your Profile" (own) and "Edit User" (others) screens.
		add_action( 'show_user_profile', [ $this, 'render' ] );
		add_action( 'edit_user_profile', [ $this, 'render' ] );

		// Validate before core writes the user, so bad values block the save.
		add_action( 'user_profile_update_errors', [ $this, 'validate' ], 10, 3 );

		// Save on both the own-profile and edit-other-user update actions.
		add_action( 'personal_options_update', [ $this, 'save' ] );
		add_action( 'edit_user_profile_update', [ $this, 'save' ] );

		// Allow file uploads: the core profile form is not multipart by default.
		add_action( 'user_edit_form_tag', [ $this, 'form_enctype' ] );

		// Field styles (switch toggle etc.) on the profile screens.
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue' ] );
	}

	/**
	 * Enqueue field styles on the profile / edit-user screens only.
	 *
	 * @param string $hook Current admin page hook.
	 */
	public function enqueue( $hook ) {
		if ( 'profile.php' !== $hook && 'user-edit.php' !== $hook ) {
			return;
		}
		wp_enqueue_style( 'rtcl-acf-frontend', RTCL_ACF_URL . 'assets/css/frontend.css', [], RTCL_ACF_VERSION );
	}

	/**
	 * Add multipart encoding to the profile form when a file field exists.
	 */
	public function form_enctype() {
		foreach ( Fields::all() as $field ) {
			if ( 'file' === $field['type'] ) {
				echo 'enctype="multipart/form-data"';
				return;
			}
		}
	}

	/**
	 * Output the fields as a standard WordPress profile section.
	 *
	 * @param \WP_User $user The user being edited.
	 */
	public function render( $user ) {
		if ( ! ( $user instanceof \WP_User ) ) {
			return;
		}
		$fields = array_values(
			array_filter(
				Fields::all(),
				static function ( $field ) use ( $user ) {
					return Fields::field_visible_for_user( $field, $user->ID );
				}
			)
		);
		if ( empty( $fields ) ) {
			return;
		}
		?>
		<h2 class="rtcl-acf-profile-title">
			<?php echo esc_html( apply_filters( 'rtcl_acf_account_section_title', __( 'Additional Information', 'rtcl-account-fields' ) ) ); ?>
		</h2>
		<?php wp_nonce_field( self::NONCE, self::NONCE ); ?>
		<table class="form-table rtcl-acf-profile-table" role="presentation">
			<tbody>
			<?php foreach ( $fields as $field ) : ?>
				<?php $this->render_row( $field, Fields::user_value_raw( $user->ID, $field ) ); ?>
			<?php endforeach; ?>
			</tbody>
		</table>
		<?php
	}

	/**
	 * Render a single form-table row for one field.
	 *
	 * @param array  $field Field definition.
	 * @param string $value Current stored value.
	 */
	private function render_row( array $field, $value ) {
		$input_id = 'rtcl-acf-' . $field['id'];
		$name     = self::INPUT . '[' . $field['id'] . ']';
		$required = $field['required'] ? ' required="required"' : '';
		$selected = is_array( $value ) ? array_map( 'strval', $value ) : [];
		$scalar   = is_array( $value ) ? '' : (string) $value;
		?>
		<tr class="rtcl-acf-profile-field rtcl-acf-type-<?php echo esc_attr( $field['type'] ); ?>">
			<th scope="row">
				<label for="<?php echo esc_attr( $input_id ); ?>">
					<?php echo esc_html( $field['label'] ); ?>
					<?php if ( $field['required'] ) : ?><span class="description">(<?php esc_html_e( 'required', 'rtcl-account-fields' ); ?>)</span><?php endif; ?>
				</label>
			</th>
			<td>
				<?php
				switch ( $field['type'] ) {
					case 'textarea':
						printf(
							'<textarea name="%1$s" id="%2$s" rows="5" class="regular-text" placeholder="%3$s"%4$s%5$s>%6$s</textarea>',
							esc_attr( $name ),
							esc_attr( $input_id ),
							esc_attr( $field['placeholder'] ),
							$required, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- controlled static attribute.
							Fields::html_constraint_attrs( $field ), // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- pre-escaped in helper.
							esc_textarea( $scalar )
						);
						break;

					case 'select':
						echo '<select name="' . esc_attr( $name ) . '" id="' . esc_attr( $input_id ) . '"' . $required . '>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
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
						echo '<fieldset class="rtcl-acf-profile-choices">';
						foreach ( $field['options'] as $i => $option ) {
							printf(
								'<label><input type="radio" name="%1$s" value="%2$s" %3$s%4$s> %5$s</label>',
								esc_attr( $name ),
								esc_attr( $option ),
								checked( $scalar, $option, false ),
								0 === $i ? $required : '', // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- controlled static attribute.
								esc_html( $option )
							);
						}
						echo '</fieldset>';
						break;

					case 'checkbox':
						if ( ! empty( $field['options'] ) ) {
							echo '<fieldset class="rtcl-acf-profile-choices">';
							foreach ( $field['options'] as $option ) {
								printf(
									'<label><input type="checkbox" name="%1$s[]" value="%2$s" %3$s> %4$s</label>',
									esc_attr( $name ),
									esc_attr( $option ),
									in_array( (string) $option, $selected, true ) ? 'checked="checked"' : '',
									esc_html( $option )
								);
							}
							echo '</fieldset>';
						} else {
							printf(
								'<label><input type="hidden" name="%1$s" value=""><input type="checkbox" name="%1$s" id="%2$s" value="1" %3$s> %4$s</label>',
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

					case 'file':
						$this->render_file_input( $field, (int) $scalar, $input_id );
						break;

					default:
						$input_type = in_array( $field['type'], [ 'number', 'email', 'url', 'tel' ], true ) ? $field['type'] : 'text';
						printf(
							'<input type="%1$s" name="%2$s" id="%3$s" value="%4$s" class="regular-text" placeholder="%5$s"%6$s%7$s>',
							esc_attr( $input_type ),
							esc_attr( $name ),
							esc_attr( $input_id ),
							esc_attr( $scalar ),
							esc_attr( $field['placeholder'] ),
							$required, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- controlled static attribute.
							Fields::html_constraint_attrs( $field ) // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- pre-escaped in helper.
						);
						break;
				}
				?>
				<?php if ( '' !== $field['description'] ) : ?>
					<p class="description"><?php echo esc_html( $field['description'] ); ?></p>
				<?php endif; ?>
			</td>
		</tr>
		<?php
	}

	/**
	 * Render a file input with the current file preview + remove control.
	 *
	 * @param array  $field    Field definition.
	 * @param int    $att_id   Current attachment id (0 when none).
	 * @param string $input_id DOM id for the input.
	 */
	private function render_file_input( array $field, $att_id, $input_id ) {
		$url = $att_id ? wp_get_attachment_url( $att_id ) : '';
		echo '<div class="rtcl-acf-profile-file">';
		if ( $url ) {
			if ( wp_attachment_is_image( $att_id ) ) {
				echo wp_get_attachment_image( $att_id, [ 60, 60 ] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- core-escaped image markup.
			}
			echo ' <a href="' . esc_url( $url ) . '" target="_blank" rel="noopener">' . esc_html( wp_basename( (string) get_attached_file( $att_id ) ) ) . '</a> ';
			printf(
				'<label><input type="checkbox" name="%1$s[%2$s]" value="1"> %3$s</label><br>',
				esc_attr( Fields::FILE_REMOVE ),
				esc_attr( $field['id'] ),
				esc_html__( 'Remove', 'rtcl-account-fields' )
			);
		}
		$accept = Fields::file_accept_attr( $field );
		printf(
			'<input type="file" name="%1$s[%2$s]" id="%3$s"%4$s>',
			esc_attr( Fields::FILE_INPUT ),
			esc_attr( $field['id'] ),
			esc_attr( $input_id ),
			'' !== $accept ? ' accept="' . esc_attr( $accept ) . '"' : ''
		);
		echo '<p class="description rtcl-acf-file-hint">' . esc_html( Fields::file_constraints_hint( $field ) ) . '</p>';
		echo '</div>';
	}

	/**
	 * Validate submitted values before the user is saved.
	 *
	 * Fires from `user_profile_update_errors` with the shared WP_Error object,
	 * so adding an error aborts the whole profile save (matching core behavior).
	 *
	 * @param \WP_Error $errors Error collector (by reference via do_action_ref_array).
	 * @param bool      $update Whether this is an existing-user update.
	 * @param \stdClass $user   Raw user object being saved.
	 */
	public function validate( $errors, $update, $user ) {
		if ( ! $this->submitted() || empty( $user->ID ) ) {
			return;
		}
		$user_id = (int) $user->ID;
		if ( ! current_user_can( 'edit_user', $user_id ) ) {
			return;
		}

		// Raw (slashed) values: Fields::validate_value() unslashes once internally.
		$submitted = isset( $_POST[ self::INPUT ] ) && is_array( $_POST[ self::INPUT ] ) ? $_POST[ self::INPUT ] : []; // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- nonce verified in submitted(); unslashed + validated in Fields::validate_value().

		foreach ( Fields::all() as $field ) {
			if ( ! Fields::field_visible_for_user( $field, $user_id ) ) {
				continue;
			}
			if ( 'file' === $field['type'] ) {
				$message = Fields::validate_file( $field, $user_id );
			} else {
				$raw     = $submitted[ $field['id'] ] ?? '';
				$message = Fields::validate_value( $field, $raw );
			}
			if ( '' !== $message ) {
				$errors->add( 'rtcl_acf_' . $field['id'], $message );
			}
		}
	}

	/**
	 * Persist the submitted values to user meta.
	 *
	 * @param int $user_id User being saved.
	 */
	public function save( $user_id ) {
		$user_id = (int) $user_id;
		if ( ! $this->submitted() || ! current_user_can( 'edit_user', $user_id ) ) {
			return;
		}

		// Raw (slashed) values: Fields::sanitize_value() unslashes once internally.
		// Pre-unslashing here would double-unslash and corrupt stored values.
		$submitted = isset( $_POST[ self::INPUT ] ) && is_array( $_POST[ self::INPUT ] ) ? $_POST[ self::INPUT ] : []; // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- nonce verified in submitted(); unslashed + sanitized in Fields::sanitize_value().

		foreach ( Fields::all() as $field ) {
			if ( ! Fields::field_visible_for_user( $field, $user_id ) ) {
				continue;
			}
			if ( 'file' === $field['type'] ) {
				Fields::save_file_field( $field, $user_id );
				continue;
			}
			$raw   = $submitted[ $field['id'] ] ?? '';
			$value = Fields::sanitize_value( $field, $raw );
			update_user_meta( $user_id, Fields::meta_key( $field['id'] ), $value );
		}
	}

	/**
	 * Whether our field section was submitted with a valid nonce. Also true for a
	 * file-only submission (uploads live in $_FILES, not $_POST[INPUT]).
	 *
	 * @return bool
	 */
	private function submitted() {
		$nonce = isset( $_POST[ self::NONCE ] ) ? sanitize_key( wp_unslash( $_POST[ self::NONCE ] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, self::NONCE ) ) {
			return false;
		}
		return ( isset( $_POST[ self::INPUT ] ) && is_array( $_POST[ self::INPUT ] ) )
			|| isset( $_FILES[ Fields::FILE_INPUT ] )
			|| isset( $_POST[ Fields::FILE_REMOVE ] );
	}
}
