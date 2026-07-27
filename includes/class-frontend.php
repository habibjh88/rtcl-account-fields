<?php
/**
 * Front-end integration: render custom fields on the account form, validate &
 * save them, and display them on the public author page.
 *
 * All integration is done through the base plugin's own hooks, so the add-on
 * adds nothing to the front-end when the base plugin is inactive.
 *
 * @package RtclAcf
 */

namespace RtclAcf;

defined( 'ABSPATH' ) || exit;

class Frontend {

	/**
	 * Name of the $_POST array holding all custom-field values.
	 */
	const INPUT = 'rtcl_acf';

	public function __construct() {
		// Render on the front-end "Account Details" edit form.
		add_action( 'rtcl_edit_account_form', [ $this, 'render_account_fields' ], 99 );

		// Validate + save through the base account-update pipeline.
		add_action( 'rtcl_my_account_validate_user_data', [ $this, 'validate' ], 10, 1 );
		add_filter( 'rtcl_update_user_account_data', [ $this, 'save' ], 10, 2 );

		// Display on the public author page (via the base template hook).
		add_action( 'rtcl_author_details_after_meta', [ $this, 'render_author_fields' ] );

		// Assets.
		add_action( 'wp_enqueue_scripts', [ $this, 'enqueue' ] );
	}

	/**
	 * Enqueue front-end CSS on the account page and the author page only.
	 */
	public function enqueue() {
		$is_account = class_exists( '\Rtcl\Helpers\Functions' ) && \Rtcl\Helpers\Functions::is_account_page();

		if ( $is_account || is_author() ) {
			wp_enqueue_style( 'rtcl-acf-frontend', RTCL_ACF_URL . 'assets/css/frontend.css', [], RTCL_ACF_VERSION );
		}

		// The file "Remove" feedback + on-change validation script is only needed
		// on the account form.
		if ( $is_account ) {
			wp_enqueue_script( 'rtcl-acf-frontend', RTCL_ACF_URL . 'assets/js/frontend.js', [], RTCL_ACF_VERSION, true );
			wp_localize_script( 'rtcl-acf-frontend', 'rtclAcfFront', [ 'i18n' => Fields::frontend_i18n() ] );
		}
	}

	/**
	 * Render the custom fields inside the account edit form.
	 */
	public function render_account_fields() {
		$user_id = get_current_user_id();
		if ( ! $user_id ) {
			return;
		}

		$fields = array_values(
			array_filter(
				Fields::all(),
				static function ( $field ) use ( $user_id ) {
					return Fields::field_visible_for_user( $field, $user_id );
				}
			)
		);
		if ( empty( $fields ) ) {
			return;
		}
		?>
		<h3 class="rtcl-myaccount-content-title rtcl-acf-section-title">
			<?php echo esc_html( apply_filters( 'rtcl_acf_account_section_title', __( 'Additional Information', 'rtcl-account-fields' ) ) ); ?>
		</h3>
		<div class="rtcl-form-group-wrap rtcl-acf-account-wrap">
			<?php foreach ( $fields as $field ) : ?>
				<?php $this->render_account_field( $field, Fields::user_value_raw( $user_id, $field ) ); ?>
			<?php endforeach; ?>
			<?php if ( $this->has_file_field( $fields ) ) : ?>
				<script>( function () { var s = document.currentScript; var f = s && s.closest( 'form' ); if ( f ) { f.setAttribute( 'enctype', 'multipart/form-data' ); } } )();</script>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Whether the collection contains at least one file field.
	 *
	 * @param array $fields Field definitions.
	 *
	 * @return bool
	 */
	private function has_file_field( array $fields ) {
		foreach ( $fields as $field ) {
			if ( 'file' === $field['type'] ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Render a single field row for the account form (matches base markup).
	 *
	 * @param array  $field Field definition.
	 * @param string $value Current stored value.
	 */
	private function render_account_field( array $field, $value ) {
		$input_id = 'rtcl-acf-' . $field['id'];
		$name     = self::INPUT . '[' . $field['id'] . ']';
		$required = $field['required'] ? ' required="required"' : '';
		$selected = is_array( $value ) ? array_map( 'strval', $value ) : [];
		$scalar   = is_array( $value ) ? '' : (string) $value;
		?>
		<div class="rtcl-form-group rtcl-acf-field rtcl-acf-type-<?php echo esc_attr( $field['type'] ); ?>">
			<label for="<?php echo esc_attr( $input_id ); ?>" class="rtcl-field-label">
				<?php echo esc_html( $field['label'] ); ?>
				<?php if ( $field['required'] ) : ?><span class="require-star">*</span><?php endif; ?>
			</label>
			<div class="rtcl-field-col">
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

					case 'file':
						$this->render_file_input( $field, (int) $scalar, $input_id );
						break;

					case 'tel':
						// Reuse the base plugin's international phone widget. Its
						// `rtcl-edit-account` script auto-inits every `.rtcl-intl-phone`
						// element and syncs the full number into the hidden input named
						// by `data-target`. We save that hidden value ($name).
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
							$required // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- controlled static attribute.
						);
						break;

					default:
						$input_type = in_array( $field['type'], [ 'number', 'email', 'url' ], true ) ? $field['type'] : 'text';
						printf(
							'<input type="%1$s" name="%2$s" id="%3$s" value="%4$s" class="rtcl-form-control" placeholder="%5$s"%6$s%7$s>',
							esc_attr( $input_type ),
							esc_attr( $name ),
							esc_attr( $input_id ),
							esc_attr( $scalar ),
							esc_attr( $field['placeholder'] ),
							$required, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
							Fields::html_constraint_attrs( $field ) // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- pre-escaped in helper.
						);
						break;
				}
				?>
				<?php if ( '' !== $field['description'] ) : ?>
					<p class="description small"><?php echo esc_html( $field['description'] ); ?></p>
				<?php endif; ?>
			</div>
		</div>
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
		echo '<div class="rtcl-acf-file">';
		if ( $url ) {
			echo '<div class="rtcl-acf-file-current">';
			if ( wp_attachment_is_image( $att_id ) ) {
				echo wp_get_attachment_image( $att_id, [ 60, 60 ], false, [ 'class' => 'rtcl-acf-file-thumb' ] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- core-escaped image markup.
			}
			echo '<a href="' . esc_url( $url ) . '" target="_blank" rel="noopener nofollow">' . esc_html( wp_basename( (string) get_attached_file( $att_id ) ) ) . '</a>';
			printf(
				'<label class="rtcl-acf-file-remove"><input type="checkbox" name="%1$s[%2$s]" value="1"> %3$s</label>',
				esc_attr( Fields::FILE_REMOVE ),
				esc_attr( $field['id'] ),
				esc_html__( 'Remove', 'rtcl-account-fields' )
			);
			echo '</div>';
		}
		// Note: no HTML "accept" attribute here on purpose. The base account form
		// runs jQuery Validate, which turns an `accept` attribute into a mimetype
		// rule that misreads extensions and rejects valid files. Types are enforced
		// server-side (Fields::field_allowed_mimes) and validated on change by our
		// own script using the data-* attributes below.
		$allowed_exts = Fields::field_allowed_extensions( $field );
		$max_bytes    = Fields::field_max_upload_size( $field );
		$data_attrs   = ' data-maxsize="' . (int) $max_bytes . '"'
			. ' data-maxlabel="' . esc_attr( size_format( $max_bytes ) ) . '"';
		if ( ! empty( $allowed_exts ) ) {
			$data_attrs .= ' data-allowed="' . esc_attr( implode( ',', $allowed_exts ) ) . '"'
				. ' data-allowed-label="' . esc_attr( Fields::allowed_extensions_label( $field ) ) . '"';
		}
		printf(
			'<input type="file" name="%1$s[%2$s]" id="%3$s" class="rtcl-form-control"%4$s>',
			esc_attr( Fields::FILE_INPUT ),
			esc_attr( $field['id'] ),
			esc_attr( $input_id ),
			$data_attrs // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- pre-escaped above.
		);
		echo '<p class="description small rtcl-acf-file-hint">' . esc_html( Fields::file_constraints_hint( $field ) ) . '</p>';
		echo '</div>';
	}

	/**
	 * Validate submitted custom-field values.
	 *
	 * Fires from `rtcl_my_account_validate_user_data`, after the base plugin
	 * has already verified the nonce and the logged-in user.
	 *
	 * @param \WP_Error $errors Error collector passed by reference by do_action.
	 */
	public function validate( $errors ) {
		if ( ! is_wp_error( $errors ) ) {
			return;
		}

		// If our field section wasn't rendered (e.g. a theme overrides the
		// account form and drops the hook), don't validate — otherwise we'd
		// block saving for required fields the user never saw.
		if ( ! $this->submitted() ) {
			return;
		}

		$user_id   = get_current_user_id();
		// Pass raw (slashed) values through: Fields::validate_value() unslashes
		// once internally. Pre-unslashing here would double-unslash the value.
		$submitted = isset( $_POST[ self::INPUT ] ) && is_array( $_POST[ self::INPUT ] ) ? $_POST[ self::INPUT ] : []; // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- unslashed + validated in Fields::validate_value().

		foreach ( Fields::all() as $field ) {
			// Never validate a field the user's account type can't see.
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
	 * Whether our field section (any input, file upload, or remove flag) posted.
	 *
	 * @return bool
	 */
	private function submitted() {
		return ( isset( $_POST[ self::INPUT ] ) && is_array( $_POST[ self::INPUT ] ) ) // phpcs:ignore WordPress.Security.NonceVerification.Missing
			|| isset( $_FILES[ Fields::FILE_INPUT ] )
			|| isset( $_POST[ Fields::FILE_REMOVE ] ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
	}

	/**
	 * Sanitize + queue custom-field meta for saving.
	 *
	 * Fires from `rtcl_update_user_account_data`. Returns the meta array that
	 * the base plugin then persists with update_user_meta().
	 *
	 * @param array $user_meta Meta key => value pairs queued for saving.
	 * @param mixed $user      User id / object passed by the base filter.
	 *
	 * @return array
	 */
	public function save( $user_meta, $user = null ) {
		if ( ! is_array( $user_meta ) ) {
			$user_meta = [];
		}

		// Only touch our meta when our form section was actually submitted, so
		// we never wipe stored values on a form that didn't render the fields.
		if ( ! $this->submitted() ) {
			return $user_meta;
		}

		$submitted = isset( $_POST[ self::INPUT ] ) && is_array( $_POST[ self::INPUT ] ) ? $_POST[ self::INPUT ] : []; // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$user_id   = $this->resolve_user_id( $user );

		foreach ( Fields::all() as $field ) {
			// Skip fields hidden from this user's account type — don't wipe them.
			if ( $user_id && ! Fields::field_visible_for_user( $field, $user_id ) ) {
				continue;
			}
			// File uploads are processed directly (they write their own meta).
			if ( 'file' === $field['type'] ) {
				if ( $user_id ) {
					Fields::save_file_field( $field, $user_id );
				}
				continue;
			}
			$raw = $submitted[ $field['id'] ] ?? '';
			$user_meta[ Fields::meta_key( $field['id'] ) ] = Fields::sanitize_value( $field, $raw );
		}

		return $user_meta;
	}

	/**
	 * Resolve a user id from whatever the base filter passed as the 2nd arg.
	 *
	 * @param mixed $user User id, WP_User, or array.
	 *
	 * @return int
	 */
	private function resolve_user_id( $user ) {
		if ( is_numeric( $user ) ) {
			return (int) $user;
		}
		if ( $user instanceof \WP_User ) {
			return (int) $user->ID;
		}
		if ( is_array( $user ) && ! empty( $user['ID'] ) ) {
			return (int) $user['ID'];
		}
		return get_current_user_id();
	}

	/**
	 * Render the custom fields on the public author page.
	 *
	 * @param int $user_id Author user id (passed by the base template hook).
	 */
	public function render_author_fields( $user_id ) {
		$user_id = absint( $user_id );
		if ( ! $user_id ) {
			return;
		}

		$rows = [];
		foreach ( Fields::all() as $field ) {
			if ( ! $field['show_on_author'] ) {
				continue;
			}
			if ( ! Fields::field_visible_for_user( $field, $user_id ) ) {
				continue;
			}
			$value = Fields::user_value_raw( $user_id, $field );
			if ( is_array( $value ) ? empty( $value ) : ( '' === (string) $value ) ) {
				continue;
			}
			$rows[] = [ 'field' => $field, 'value' => $value ];
		}

		if ( empty( $rows ) ) {
			return;
		}
		?>
		<div class="rtcl-acf-author-fields">
			<?php foreach ( $rows as $row ) : ?>
				<div class="rtcl-acf-author-item rtcl-acf-author-<?php echo esc_attr( $row['field']['type'] ); ?>">
					<span class="rtcl-acf-author-label"><?php echo esc_html( $row['field']['label'] ); ?></span>
					<span class="rtcl-acf-author-value"><?php echo $this->format_author_value( $row['field'], $row['value'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped per branch below. ?></span>
				</div>
			<?php endforeach; ?>
		</div>
		<?php
	}

	/**
	 * Format a value for public display, safely escaped per type.
	 *
	 * @param array $field Field definition.
	 * @param mixed $value Stored value (string, array for multi-checkbox, or id).
	 *
	 * @return string Safe HTML.
	 */
	private function format_author_value( array $field, $value ) {
		switch ( $field['type'] ) {
			case 'url':
				return '<a href="' . esc_url( (string) $value ) . '" target="_blank" rel="nofollow noopener">' . esc_html( (string) $value ) . '</a>';

			case 'email':
				return '<a href="mailto:' . esc_attr( antispambot( (string) $value ) ) . '">' . esc_html( antispambot( (string) $value ) ) . '</a>';

			case 'tel':
				return '<a href="tel:' . esc_attr( preg_replace( '/[^0-9+]/', '', (string) $value ) ) . '">' . esc_html( (string) $value ) . '</a>';

			case 'checkbox':
				if ( is_array( $value ) ) {
					return esc_html( implode( ', ', $value ) );
				}
				return esc_html__( 'Yes', 'rtcl-account-fields' );

			case 'switch':
				return esc_html__( 'Yes', 'rtcl-account-fields' );

			case 'file':
				return $this->format_file_value( (int) $value );

			case 'textarea':
				return nl2br( esc_html( (string) $value ) );

			default:
				return esc_html( (string) $value );
		}
	}

	/**
	 * Public display for a file value: image thumbnail or a download link.
	 *
	 * @param int $att_id Attachment id.
	 *
	 * @return string Safe HTML.
	 */
	private function format_file_value( $att_id ) {
		$url = $att_id ? wp_get_attachment_url( $att_id ) : '';
		if ( ! $url ) {
			return '';
		}
		if ( wp_attachment_is_image( $att_id ) ) {
			return '<a href="' . esc_url( $url ) . '" target="_blank" rel="nofollow noopener">'
				. wp_get_attachment_image( $att_id, [ 120, 120 ] )
				. '</a>';
		}
		return '<a href="' . esc_url( $url ) . '" target="_blank" rel="nofollow noopener">'
			. esc_html( wp_basename( (string) get_attached_file( $att_id ) ) )
			. '</a>';
	}
}
