<?php
/**
 * Custom field repository + per-type sanitize/validate helpers.
 *
 * @package RtclAcf
 */

namespace RtclAcf;

defined( 'ABSPATH' ) || exit;

class Fields {

	/**
	 * Option key that stores all field definitions.
	 */
	const OPTION = 'rtcl_acf_fields';

	/**
	 * Prefix for the user-meta key of every custom field.
	 */
	const META_PREFIX = '_rtcl_acf_';

	/**
	 * Message templates for the front-end script (file + length feedback), shared
	 * by every surface that loads assets/js/frontend.js.
	 *
	 * @return array<string,string>
	 */
	public static function frontend_i18n() {
		return [
			/* translators: %s: comma-separated list of accepted file types */
			'fileType' => __( 'This file type is not allowed. Accepted types: %s.', 'rtcl-account-fields' ),
			/* translators: %s: maximum file size, e.g. "1 MB" */
			'fileSize' => __( 'This file is too large. Maximum size is %s.', 'rtcl-account-fields' ),
			/* translators: %s: minimum number of characters */
			'charsMin' => __( 'Please enter at least %s characters.', 'rtcl-account-fields' ),
			/* translators: %s: maximum number of characters */
			'charsMax' => __( 'Maximum %s characters reached.', 'rtcl-account-fields' ),
		];
	}

	/**
	 * Supported field types => human label.
	 *
	 * @return array<string,string>
	 */
	public static function types() {
		return [
			'text'     => __( 'Text', 'rtcl-account-fields' ),
			'textarea' => __( 'Textarea', 'rtcl-account-fields' ),
			'number'   => __( 'Number', 'rtcl-account-fields' ),
			'email'    => __( 'Email', 'rtcl-account-fields' ),
			'url'      => __( 'URL / Website', 'rtcl-account-fields' ),
			'tel'      => __( 'Phone', 'rtcl-account-fields' ),
			'select'   => __( 'Dropdown', 'rtcl-account-fields' ),
			'radio'    => __( 'Radio', 'rtcl-account-fields' ),
			'checkbox' => __( 'Checkbox', 'rtcl-account-fields' ),
			'switch'   => __( 'Switch', 'rtcl-account-fields' ),
			'file'     => __( 'File upload', 'rtcl-account-fields' ),
		];
	}

	/**
	 * Field name of the $_FILES array holding uploads, and the "remove" flag.
	 */
	const FILE_INPUT  = 'rtcl_acf_file';
	const FILE_REMOVE = 'rtcl_acf_file_remove';

	/**
	 * Types that present a repeatable list of options.
	 *
	 * @return string[]
	 */
	public static function option_types() {
		return [ 'select', 'radio', 'checkbox' ];
	}

	/**
	 * Whether a field stores multiple values (a checkbox group with options).
	 *
	 * @param array $field Field definition.
	 *
	 * @return bool
	 */
	public static function is_multi( array $field ) {
		return 'checkbox' === $field['type'] && ! empty( $field['options'] );
	}

	/**
	 * Default shape of a single field definition.
	 *
	 * @return array
	 */
	public static function defaults() {
		return [
			'id'             => '',
			'label'          => '',
			'description'    => '',
			'type'           => 'text',
			'placeholder'    => '',
			'options'        => [],
			'required'       => false,
			'show_on_author' => true,
			'show_on_register' => false,
			'visible_for'    => 'all',
			'file_ext'       => '',
			'max_size'       => 0,
			'min'            => '',
			'max'            => '',
		];
	}

	/**
	 * Allowed values for the "visible for" (account type) setting.
	 *
	 * @return string[]
	 */
	public static function visibility_values() {
		return [ 'all', 'seller', 'buyer' ];
	}

	/**
	 * All field definitions, normalized against the defaults.
	 *
	 * @return array<int,array>
	 */
	public static function all() {
		$stored = get_option( self::OPTION, [] );
		if ( ! is_array( $stored ) ) {
			return [];
		}

		$fields = [];
		foreach ( $stored as $field ) {
			if ( empty( $field['id'] ) ) {
				continue;
			}
			$field                   = wp_parse_args( $field, self::defaults() );
			$field['options']        = is_array( $field['options'] ) ? $field['options'] : [];
			$field['required']       = ! empty( $field['required'] );
			$field['show_on_author'] = ! empty( $field['show_on_author'] );
			$field['show_on_register'] = ! empty( $field['show_on_register'] );
			$field['visible_for']    = in_array( $field['visible_for'], self::visibility_values(), true ) ? $field['visible_for'] : 'all';
			$field['file_ext']       = implode( ', ', self::normalize_extensions( $field['file_ext'] ) );
			$field['max_size']       = max( 0, (float) $field['max_size'] );
			$field['min']            = self::normalize_number_setting( $field['min'] );
			$field['max']            = self::normalize_number_setting( $field['max'] );
			$fields[]                = $field;
		}

		return $fields;
	}

	/**
	 * Single field definition by id.
	 *
	 * @param string $id Field id.
	 *
	 * @return array|null
	 */
	public static function get( $id ) {
		foreach ( self::all() as $field ) {
			if ( $field['id'] === $id ) {
				return $field;
			}
		}

		return null;
	}

	/**
	 * Persist the full field collection.
	 *
	 * @param array $fields Field definitions.
	 *
	 * @return void
	 */
	public static function save( array $fields ) {
		update_option( self::OPTION, array_values( $fields ) );
	}

	/**
	 * Generate a fresh, unique default key for a field type, e.g. "text_1rhch".
	 *
	 * @param string   $type  Field type.
	 * @param string[] $taken Keys already in use to avoid.
	 *
	 * @return string
	 */
	public static function generate_key( $type, array $taken = [] ) {
		$type = sanitize_key( (string) $type );
		if ( '' === $type ) {
			$type = 'field';
		}
		do {
			$key = $type . '_' . self::random_suffix( 6 );
		} while ( in_array( $key, $taken, true ) );

		return $key;
	}

	/**
	 * Normalize a submitted field key: sanitize, ensure it is non-empty, and
	 * de-duplicate against keys already taken.
	 *
	 * @param string   $key   Raw submitted key.
	 * @param string   $type  Field type (used to build a default when empty).
	 * @param string[] $taken Keys already in use.
	 *
	 * @return string
	 */
	public static function normalize_key( $key, $type, array $taken = [] ) {
		$key = sanitize_key( (string) $key );

		if ( '' === $key ) {
			$key = self::generate_key( $type, $taken );
		}

		// De-duplicate.
		if ( in_array( $key, $taken, true ) ) {
			$base = $key;
			$n    = 2;
			do {
				$key = $base . '_' . $n;
				$n++;
			} while ( in_array( $key, $taken, true ) );
		}

		return $key;
	}

	/**
	 * A short lowercase alphanumeric random string.
	 *
	 * @param int $length Number of characters.
	 *
	 * @return string
	 */
	private static function random_suffix( $length ) {
		$chars = 'abcdefghijklmnopqrstuvwxyz0123456789';
		$max   = strlen( $chars ) - 1;
		$out   = '';
		for ( $i = 0; $i < $length; $i++ ) {
			$out .= $chars[ random_int( 0, $max ) ];
		}

		return $out;
	}

	/**
	 * Normalize a raw collection coming from the builder into clean, persistable
	 * field definitions.
	 *
	 * The field key is user-editable and kept unique. Note: the key is the storage
	 * key, so changing an existing field's key starts a fresh value. Invalid
	 * entries (no label) are dropped.
	 *
	 * @param array $raw_fields Raw field definitions from the client.
	 *
	 * @return array<int,array> Clean, ordered field definitions.
	 */
	public static function normalize_collection( array $raw_fields ) {
		$types = self::types();
		$taken = [];
		$clean = [];

		foreach ( $raw_fields as $raw ) {
			if ( ! is_array( $raw ) ) {
				continue;
			}

			$label = isset( $raw['label'] ) ? sanitize_text_field( (string) $raw['label'] ) : '';
			if ( '' === $label ) {
				continue;
			}

			$type = isset( $raw['type'] ) ? sanitize_key( (string) $raw['type'] ) : 'text';
			if ( ! array_key_exists( $type, $types ) ) {
				$type = 'text';
			}

			$visible_for = isset( $raw['visible_for'] ) ? sanitize_key( (string) $raw['visible_for'] ) : 'all';
			if ( ! in_array( $visible_for, self::visibility_values(), true ) ) {
				$visible_for = 'all';
			}

			// Respect the submitted (editable) key: enforce prefix + uniqueness.
			$id      = self::normalize_key( $raw['id'] ?? '', $type, $taken );
			$taken[] = $id;

			// Options: one per line or array, cleaned + de-duplicated.
			$options = [];
			$raw_opt = $raw['options'] ?? [];
			if ( is_string( $raw_opt ) ) {
				$raw_opt = preg_split( '/\r\n|\r|\n/', $raw_opt );
			}
			if ( is_array( $raw_opt ) ) {
				foreach ( $raw_opt as $line ) {
					$line = sanitize_text_field( (string) $line );
					if ( '' !== $line ) {
						$options[] = $line;
					}
				}
				$options = array_values( array_unique( $options ) );
			}

			$clean[] = [
				'id'             => $id,
				'label'          => $label,
				'description'    => isset( $raw['description'] ) ? sanitize_textarea_field( (string) $raw['description'] ) : '',
				'type'           => $type,
				'placeholder'    => isset( $raw['placeholder'] ) ? sanitize_text_field( (string) $raw['placeholder'] ) : '',
				'options'        => $options,
				'required'       => ! empty( $raw['required'] ),
				'show_on_author' => ! empty( $raw['show_on_author'] ),
				'show_on_register' => ! empty( $raw['show_on_register'] ),
				'visible_for'    => $visible_for,
				'file_ext'       => implode( ', ', self::normalize_extensions( $raw['file_ext'] ?? '' ) ),
				'max_size'       => isset( $raw['max_size'] ) ? max( 0, (float) $raw['max_size'] ) : 0,
				'min'            => self::normalize_number_setting( $raw['min'] ?? '' ),
				'max'            => self::normalize_number_setting( $raw['max'] ?? '' ),
			];
		}

		return $clean;
	}

	/**
	 * Normalize a numeric setting (min/max). Returns a clean numeric string, or
	 * '' when the value is blank or non-numeric — '' meaning "no bound set".
	 *
	 * @param mixed $raw Raw value.
	 *
	 * @return string
	 */
	public static function normalize_number_setting( $raw ) {
		$raw = is_string( $raw ) ? trim( $raw ) : $raw;
		if ( '' === $raw || null === $raw || ! is_numeric( $raw ) ) {
			return '';
		}

		return (string) ( 0 + $raw );
	}

	/**
	 * Default maximum character length for a type when the admin sets none.
	 *
	 * @param string $type Field type.
	 *
	 * @return int 0 when the type has no default cap.
	 */
	public static function default_max_length( $type ) {
		if ( 'textarea' === $type ) {
			return 500;
		}
		if ( 'text' === $type ) {
			return 150;
		}

		return 0;
	}

	/**
	 * Default maximum *value* for a number field when the admin sets no max.
	 *
	 * Without an upper bound a user can submit an absurdly large value (e.g.
	 * 1e79) which PHP then stores in scientific notation. We cap at the signed
	 * 32-bit integer max — the conventional MySQL `INT` ceiling — which is large
	 * enough for real input yet reads cleanly in the validation message.
	 *
	 * @return int
	 */
	public static function default_max_number() {
		// Signed 32-bit integer max (MySQL INT), ~2.1 billion.
		return 2147483647;
	}

	/**
	 * Effective maximum value for a number field: the admin's value, or the
	 * default cap ({@see default_max_number()}) when left unset.
	 *
	 * @param array $field Field definition.
	 *
	 * @return float
	 */
	public static function number_max( array $field ) {
		if ( isset( $field['max'] ) && '' !== $field['max'] ) {
			return (float) $field['max'];
		}

		return self::default_max_number();
	}

	/**
	 * Minimum character length configured for a text/textarea field (0 = none).
	 *
	 * @param array $field Field definition.
	 *
	 * @return int
	 */
	public static function length_min( array $field ) {
		$min = isset( $field['min'] ) && '' !== $field['min'] ? (int) $field['min'] : 0;

		return max( 0, $min );
	}

	/**
	 * Effective maximum character length for a text/textarea field: the admin's
	 * value, or the per-type default (text 150, textarea 500) when unset.
	 *
	 * @param array $field Field definition.
	 *
	 * @return int
	 */
	public static function length_max( array $field ) {
		if ( isset( $field['max'] ) && '' !== $field['max'] && (int) $field['max'] > 0 ) {
			return (int) $field['max'];
		}

		return self::default_max_length( $field['type'] );
	}

	/**
	 * HTML5 constraint attributes for a field's input: min/max/step for number,
	 * minlength/maxlength for text & textarea. Returns a pre-escaped attribute
	 * string (leading space), ready to inject into the tag.
	 *
	 * @param array $field Field definition.
	 *
	 * @return string
	 */
	public static function html_constraint_attrs( array $field ) {
		$attrs = '';

		if ( 'number' === $field['type'] ) {
			if ( isset( $field['min'] ) && '' !== $field['min'] ) {
				$attrs .= ' min="' . esc_attr( $field['min'] ) . '"';
			}
			$max = self::number_max( $field );
			$attrs .= ' max="' . esc_attr( $max ) . '"';
			$attrs .= ' step="any"';
		} elseif ( 'text' === $field['type'] || 'textarea' === $field['type'] ) {
			$min = self::length_min( $field );
			$max = self::length_max( $field );
			if ( $min > 0 ) {
				$attrs .= ' minlength="' . (int) $min . '"';
			}
			if ( $max > 0 ) {
				$attrs .= ' maxlength="' . (int) $max . '"';
			}
		}

		return $attrs;
	}

	/**
	 * Normalize a raw "allowed file types" value (a comma/space/newline separated
	 * string, or an array) into a clean list of lowercase extensions.
	 *
	 * @param mixed $raw Raw extensions value.
	 *
	 * @return string[] Clean, unique extensions (no leading dot), e.g. [ 'pdf', 'jpg' ].
	 */
	public static function normalize_extensions( $raw ) {
		if ( is_string( $raw ) ) {
			$raw = preg_split( '/[\s,]+/', $raw );
		}
		$exts = [];
		foreach ( (array) $raw as $ext ) {
			$ext = strtolower( trim( (string) $ext ) );
			$ext = preg_replace( '/[^a-z0-9]/', '', ltrim( $ext, '.' ) );
			if ( '' !== $ext ) {
				$exts[] = $ext;
			}
		}

		return array_values( array_unique( $exts ) );
	}

	/**
	 * User-meta key for a given field id.
	 *
	 * @param string $id Field id.
	 *
	 * @return string
	 */
	public static function meta_key( $id ) {
		return self::META_PREFIX . $id;
	}

	/**
	 * Current stored value for a user + field.
	 *
	 * @param int   $user_id User id.
	 * @param array $field   Field definition.
	 *
	 * @return string
	 */
	public static function user_value( $user_id, array $field ) {
		return (string) get_user_meta( $user_id, self::meta_key( $field['id'] ), true );
	}

	/**
	 * Raw stored value for a user + field (may be an array for multi-checkbox, or
	 * an attachment id string for file fields).
	 *
	 * @param int   $user_id User id.
	 * @param array $field   Field definition.
	 *
	 * @return mixed
	 */
	public static function user_value_raw( $user_id, array $field ) {
		return get_user_meta( $user_id, self::meta_key( $field['id'] ), true );
	}

	/**
	 * The Classified Listing account type for a user: 'seller', 'buyer', or ''
	 * when unknown / not set (the base plugin stores it in `_rtcl_user_type`).
	 *
	 * @param int $user_id User id.
	 *
	 * @return string
	 */
	public static function user_account_type( $user_id ) {
		$type = get_user_meta( (int) $user_id, '_rtcl_user_type', true );
		$type = is_string( $type ) ? strtolower( trim( $type ) ) : '';
		if ( ! in_array( $type, [ 'seller', 'buyer' ], true ) ) {
			$type = '';
		}

		/**
		 * Filter the resolved account type used for field visibility.
		 *
		 * @param string $type    'seller', 'buyer', or '' when unknown.
		 * @param int    $user_id User id.
		 */
		return (string) apply_filters( 'rtcl_acf_user_account_type', $type, (int) $user_id );
	}

	/**
	 * Whether a field should be shown to a given user, based on its "visible for"
	 * setting and the user's account type. Unknown account type shows everything,
	 * so fields are never hidden unexpectedly.
	 *
	 * @param array $field   Field definition.
	 * @param int   $user_id User id.
	 *
	 * @return bool
	 */
	public static function field_visible_for_user( array $field, $user_id ) {
		$visible = isset( $field['visible_for'] ) ? $field['visible_for'] : 'all';
		if ( 'all' === $visible ) {
			return true;
		}
		$type = self::user_account_type( $user_id );
		if ( '' === $type ) {
			return true;
		}
		return $visible === $type;
	}

	/**
	 * Sanitize a raw submitted value according to the field type.
	 *
	 * @param array  $field Field definition.
	 * @param mixed  $raw   Raw (possibly slashed) value from $_POST.
	 *
	 * @return string
	 */
	public static function sanitize_value( array $field, $raw ) {
		// Multi-checkbox: keep the subset of configured options that were checked,
		// in the field's own option order.
		if ( self::is_multi( $field ) ) {
			$checked = is_array( $raw ) ? array_map( 'strval', wp_unslash( $raw ) ) : [];
			$clean   = [];
			foreach ( $field['options'] as $option ) {
				if ( in_array( (string) $option, $checked, true ) ) {
					$clean[] = $option;
				}
			}
			return $clean;
		}

		$raw = is_array( $raw ) ? '' : wp_unslash( (string) $raw );
		$raw = trim( $raw );

		switch ( $field['type'] ) {
			case 'textarea':
				return sanitize_textarea_field( $raw );

			case 'number':
				return is_numeric( $raw ) ? (string) ( 0 + $raw ) : '';

			case 'email':
				return sanitize_email( $raw );

			case 'url':
				return esc_url_raw( $raw );

			case 'switch':
			case 'checkbox':
				// Single yes/no toggle (checkbox without options, or a switch).
				return '' === $raw ? '' : '1';

			case 'radio':
			case 'select':
				// Only allow one of the configured options.
				return in_array( $raw, $field['options'], true ) ? $raw : '';

			case 'file':
				// Stored as an attachment id; real uploads are handled on save.
				return preg_replace( '/[^0-9]/', '', $raw );

			case 'tel':
			case 'text':
			default:
				return sanitize_text_field( $raw );
		}
	}

	/**
	 * Validate a raw submitted value. Returns an error message, or '' when valid.
	 *
	 * @param array $field Field definition.
	 * @param mixed $raw   Raw (possibly slashed) value from $_POST.
	 *
	 * @return string Empty string when valid, otherwise the error message.
	 */
	public static function validate_value( array $field, $raw ) {
		// Multi-checkbox: value is a set of options.
		if ( self::is_multi( $field ) ) {
			$checked  = is_array( $raw ) ? array_map( 'strval', wp_unslash( $raw ) ) : [];
			$selected = array_intersect( array_map( 'strval', $field['options'] ), $checked );
			if ( $field['required'] && empty( $selected ) ) {
				/* translators: %s: field label */
				return sprintf( __( '%s is required.', 'rtcl-account-fields' ), $field['label'] );
			}
			return '';
		}

		// File fields are validated by the save routine (it can see $_FILES).
		if ( 'file' === $field['type'] ) {
			return '';
		}

		$value = is_array( $raw ) ? '' : trim( wp_unslash( (string) $raw ) );

		// Required check.
		if ( $field['required'] && '' === $value ) {
			/* translators: %s: field label */
			return sprintf( __( '%s is required.', 'rtcl-account-fields' ), $field['label'] );
		}

		// Nothing else to validate for an empty optional value.
		if ( '' === $value ) {
			return '';
		}

		switch ( $field['type'] ) {
			case 'email':
				if ( ! is_email( $value ) ) {
					/* translators: %s: field label */
					return sprintf( __( 'Please enter a valid email address for %s.', 'rtcl-account-fields' ), $field['label'] );
				}
				break;

			case 'url':
				if ( ! filter_var( $value, FILTER_VALIDATE_URL ) ) {
					/* translators: %s: field label */
					return sprintf( __( 'Please enter a valid URL for %s.', 'rtcl-account-fields' ), $field['label'] );
				}
				break;

			case 'number':
				if ( ! is_numeric( $value ) ) {
					/* translators: %s: field label */
					return sprintf( __( 'Please enter a valid number for %s.', 'rtcl-account-fields' ), $field['label'] );
				}
				$num = 0 + $value;
				if ( isset( $field['min'] ) && '' !== $field['min'] && $num < (float) $field['min'] ) {
					/* translators: 1: field label, 2: minimum value */
					return sprintf( __( '%1$s must be at least %2$s.', 'rtcl-account-fields' ), $field['label'], $field['min'] );
				}
				$max = self::number_max( $field );
				if ( $num > $max ) {
					/* translators: 1: field label, 2: maximum value */
					return sprintf( __( '%1$s must be at most %2$s.', 'rtcl-account-fields' ), $field['label'], $max );
				}
				break;

			case 'text':
			case 'textarea':
				$len = function_exists( 'mb_strlen' ) ? mb_strlen( $value ) : strlen( $value );
				$min = self::length_min( $field );
				$max = self::length_max( $field );
				if ( $min > 0 && $len < $min ) {
					/* translators: 1: field label, 2: minimum number of characters */
					return sprintf( __( '%1$s must be at least %2$d characters.', 'rtcl-account-fields' ), $field['label'], $min );
				}
				if ( $max > 0 && $len > $max ) {
					/* translators: 1: field label, 2: maximum number of characters */
					return sprintf( __( '%1$s must be %2$d characters or fewer.', 'rtcl-account-fields' ), $field['label'], $max );
				}
				break;

			case 'tel':
				$digits = preg_replace( '/[^0-9]/', '', $value );
				if ( ! preg_match( '/^\+?[0-9\s\-().]+$/', $value ) || strlen( $digits ) < 6 || strlen( $digits ) > 15 ) {
					/* translators: %s: field label */
					return sprintf( __( 'Please enter a valid phone number for %s.', 'rtcl-account-fields' ), $field['label'] );
				}
				break;

			case 'radio':
			case 'select':
				if ( ! in_array( $value, $field['options'], true ) ) {
					/* translators: %s: field label */
					return sprintf( __( 'Please choose a valid option for %s.', 'rtcl-account-fields' ), $field['label'] );
				}
				break;
		}

		return '';
	}

	/**
	 * Whether the current request carries a new upload for a file field.
	 *
	 * @param array $field Field definition.
	 *
	 * @return bool
	 */
	public static function has_upload( array $field ) {
		$key = $field['id'];
		return isset( $_FILES[ self::FILE_INPUT ]['name'][ $key ] )
			&& '' !== $_FILES[ self::FILE_INPUT ]['name'][ $key ]
			&& ( ! isset( $_FILES[ self::FILE_INPUT ]['error'][ $key ] ) || UPLOAD_ERR_NO_FILE !== (int) $_FILES[ self::FILE_INPUT ]['error'][ $key ] );
	}

	/**
	 * Mime types accepted for file uploads (the site's own allow-list).
	 *
	 * @return array<string,string> Map of extension pattern => mime type.
	 */
	public static function allowed_file_mimes() {
		return get_allowed_mime_types();
	}

	/**
	 * A short, curated list of profile-relevant file types for the builder's
	 * "Allowed file types" checkboxes — the common document/image formats a user
	 * or company would attach (CV, brochure, logo, photo). Types the site does
	 * not permit are omitted, and each entry carries every extension in its mime
	 * group so ticking one covers all of them (e.g. JPG also allows .jpeg/.jpe).
	 *
	 * @return array<int,array{ext:string,exts:string[],label:string}>
	 */
	public static function available_file_types() {
		/**
		 * Curated types shown in the builder, in display order: primary extension
		 * (the saved value) => label.
		 *
		 * @param array<string,string> $curated Extension => label.
		 */
		$curated = apply_filters(
			'rtcl_acf_allowed_file_types',
			[
				'pdf'  => 'PDF',
				'doc'  => 'DOC',
				'docx' => 'DOCX',
				'jpg'  => 'JPG',
				'png'  => 'PNG',
				'gif'  => 'GIF',
				'webp' => 'WEBP',
			]
		);

		$patterns = array_keys( self::allowed_file_mimes() );
		$types    = [];

		foreach ( $curated as $ext => $label ) {
			$group = null;
			foreach ( $patterns as $pattern ) {
				$exts = explode( '|', $pattern );
				if ( in_array( $ext, $exts, true ) ) {
					$group = $exts;
					break;
				}
			}
			if ( null === $group ) {
				continue; // Site does not permit this type — don't offer it.
			}
			$types[] = [
				'ext'   => $ext,
				'exts'  => $group,
				'label' => $label,
			];
		}

		return $types;
	}

	/**
	 * Maximum upload size in bytes (respects PHP + WordPress limits).
	 *
	 * @return int
	 */
	public static function max_upload_size() {
		return (int) wp_max_upload_size();
	}

	/**
	 * Mime types accepted for a specific file field: the intersection of the
	 * field's configured extensions with the site's own allow-list. When the
	 * field sets no extensions, the full site allow-list is used.
	 *
	 * The site allow-list is always the outer bound, so a field can only ever
	 * narrow what is accepted — never widen it to a type WordPress disallows.
	 *
	 * @param array $field Field definition.
	 *
	 * @return array<string,string> Map of extension pattern => mime type.
	 */
	public static function field_allowed_mimes( array $field ) {
		$site = self::allowed_file_mimes();
		$exts = self::normalize_extensions( isset( $field['file_ext'] ) ? $field['file_ext'] : '' );
		if ( empty( $exts ) ) {
			return $site;
		}

		$filtered = [];
		foreach ( $site as $pattern => $mime ) {
			if ( array_intersect( explode( '|', $pattern ), $exts ) ) {
				$filtered[ $pattern ] = $mime;
			}
		}

		return $filtered;
	}

	/**
	 * The flat list of file extensions a field accepts, expanded from its mime
	 * groups (so a field allowing "jpg" also accepts "jpeg"/"jpe"). Empty array
	 * when the field places no extension restriction.
	 *
	 * @param array $field Field definition.
	 *
	 * @return string[]
	 */
	public static function field_allowed_extensions( array $field ) {
		$exts = self::normalize_extensions( isset( $field['file_ext'] ) ? $field['file_ext'] : '' );
		if ( empty( $exts ) ) {
			return [];
		}

		$all = [];
		foreach ( array_keys( self::field_allowed_mimes( $field ) ) as $pattern ) {
			foreach ( explode( '|', $pattern ) as $ext ) {
				$all[] = $ext;
			}
		}

		return array_values( array_unique( $all ) );
	}

	/**
	 * Effective maximum upload size in bytes for a specific field: the field's
	 * own limit (in MB), or a sensible 1 MB default when it is empty/0. Always
	 * capped by the site/PHP limit.
	 *
	 * @param array $field Field definition.
	 *
	 * @return int
	 */
	public static function field_max_upload_size( array $field ) {
		$site = self::max_upload_size();
		$mb   = isset( $field['max_size'] ) ? (float) $field['max_size'] : 0;
		if ( $mb <= 0 ) {
			$mb = 1; // Default 1 MB when no limit is set.
		}

		return min( (int) round( $mb * MB_IN_BYTES ), $site );
	}

	/**
	 * Human-readable list of accepted extensions for a field, e.g. "pdf, jpg, png".
	 * Falls back to the full site allow-list when the field sets no extensions.
	 *
	 * @param array $field Field definition.
	 *
	 * @return string
	 */
	public static function allowed_extensions_label( array $field ) {
		$exts = self::normalize_extensions( isset( $field['file_ext'] ) ? $field['file_ext'] : '' );
		if ( empty( $exts ) ) {
			foreach ( array_keys( self::allowed_file_mimes() ) as $pattern ) {
				foreach ( explode( '|', $pattern ) as $ext ) {
					$exts[] = $ext;
				}
			}
			$exts = array_values( array_unique( $exts ) );
		}

		return implode( ', ', $exts );
	}

	/**
	 * Value for the file input's HTML "accept" attribute, e.g. ".pdf,.jpg,.png".
	 * Empty when the field places no extension restriction.
	 *
	 * @param array $field Field definition.
	 *
	 * @return string
	 */
	public static function file_accept_attr( array $field ) {
		$exts = self::normalize_extensions( isset( $field['file_ext'] ) ? $field['file_ext'] : '' );
		if ( empty( $exts ) ) {
			return '';
		}

		return '.' . implode( ',.', $exts );
	}

	/**
	 * Short human hint describing a file field's constraints, e.g.
	 * "Allowed: pdf, jpg · Max size: 2 MB".
	 *
	 * @param array $field Field definition.
	 *
	 * @return string
	 */
	public static function file_constraints_hint( array $field ) {
		$parts = [];
		$exts  = self::normalize_extensions( isset( $field['file_ext'] ) ? $field['file_ext'] : '' );
		if ( ! empty( $exts ) ) {
			/* translators: %s: comma-separated list of file extensions */
			$parts[] = sprintf( __( 'Allowed: %s', 'rtcl-account-fields' ), implode( ', ', $exts ) );
		}
		/* translators: %s: maximum file size, e.g. "2 MB" */
		$parts[] = sprintf( __( 'Max size: %s', 'rtcl-account-fields' ), size_format( self::field_max_upload_size( $field ) ) );

		return implode( ' · ', $parts );
	}

	/**
	 * Validate a file field: a new upload's error state, size and mime type, or
	 * (when no new file) the required-check against the existing stored file.
	 *
	 * @param array $field   Field definition.
	 * @param int   $user_id User id.
	 *
	 * @return string Error message, or '' when valid.
	 */
	public static function validate_file( array $field, $user_id ) {
		$key      = $field['id'];
		$files    = isset( $_FILES[ self::FILE_INPUT ] ) ? $_FILES[ self::FILE_INPUT ] : []; // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$removing = ! empty( $_POST[ self::FILE_REMOVE ][ $key ] ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$error    = isset( $files['error'][ $key ] ) ? (int) $files['error'][ $key ] : UPLOAD_ERR_NO_FILE;

		$max_size = self::field_max_upload_size( $field );

		// A new file is being uploaded — validate it.
		if ( UPLOAD_ERR_NO_FILE !== $error ) {
			if ( UPLOAD_ERR_OK !== $error ) {
				if ( UPLOAD_ERR_INI_SIZE === $error || UPLOAD_ERR_FORM_SIZE === $error ) {
					/* translators: 1: field label, 2: max file size */
					return sprintf( __( '%1$s is too large. Maximum size is %2$s.', 'rtcl-account-fields' ), $field['label'], size_format( $max_size ) );
				}
				/* translators: %s: field label */
				return sprintf( __( 'The upload for %s failed. Please try again.', 'rtcl-account-fields' ), $field['label'] );
			}

			$size = isset( $files['size'][ $key ] ) ? (int) $files['size'][ $key ] : 0;
			if ( $size <= 0 ) {
				/* translators: %s: field label */
				return sprintf( __( 'The uploaded file for %s is empty.', 'rtcl-account-fields' ), $field['label'] );
			}
			if ( $size > $max_size ) {
				/* translators: 1: field label, 2: max file size */
				return sprintf( __( '%1$s is too large. Maximum size is %2$s.', 'rtcl-account-fields' ), $field['label'], size_format( $max_size ) );
			}

			$name    = isset( $files['name'][ $key ] ) ? sanitize_file_name( (string) $files['name'][ $key ] ) : '';
			$tmp     = isset( $files['tmp_name'][ $key ] ) ? (string) $files['tmp_name'][ $key ] : '';
			$allowed = self::field_allowed_mimes( $field );
			$check   = wp_check_filetype_and_ext( $tmp, $name, $allowed );
			$type    = ! empty( $check['type'] ) ? $check['type'] : '';

			if ( '' === $type || ! in_array( $type, $allowed, true ) ) {
				/* translators: 1: field label, 2: allowed file types */
				return sprintf( __( 'The file type for %1$s is not allowed. Accepted types: %2$s.', 'rtcl-account-fields' ), $field['label'], self::allowed_extensions_label( $field ) );
			}
			return '';
		}

		// No new upload — enforce required against the existing stored file.
		if ( $field['required'] ) {
			$has_existing = ! $removing && (int) self::user_value_raw( $user_id, $field ) > 0;
			if ( ! $has_existing ) {
				/* translators: %s: field label */
				return sprintf( __( '%s is required.', 'rtcl-account-fields' ), $field['label'] );
			}
		}
		return '';
	}

	/**
	 * Process a file field for one user: store a new upload, remove the existing
	 * one, or keep it. Persists the attachment id to user meta.
	 *
	 * @param array $field   Field definition.
	 * @param int   $user_id User id.
	 *
	 * @return int|\WP_Error New/kept attachment id (0 when cleared), or error.
	 */
	public static function save_file_field( array $field, $user_id ) {
		$key      = $field['id'];
		$meta_key = self::meta_key( $key );
		$current  = (int) get_user_meta( $user_id, $meta_key, true );
		$remove   = ! empty( $_POST[ self::FILE_REMOVE ][ $key ] ); // phpcs:ignore WordPress.Security.NonceVerification.Missing

		if ( self::has_upload( $field ) ) {
			$attachment_id = self::handle_upload( $field, $user_id );
			if ( is_wp_error( $attachment_id ) ) {
				return $attachment_id;
			}
			if ( $current && $current !== $attachment_id ) {
				wp_delete_attachment( $current, true );
			}
			update_user_meta( $user_id, $meta_key, $attachment_id );
			return $attachment_id;
		}

		if ( $remove ) {
			if ( $current ) {
				wp_delete_attachment( $current, true );
			}
			delete_user_meta( $user_id, $meta_key );
			return 0;
		}

		return $current;
	}

	/**
	 * Move a single uploaded file into the media library.
	 *
	 * @param array $field   Field definition (its id indexes the $_FILES group).
	 * @param int   $user_id Uploading user (used as attachment author).
	 *
	 * @return int|\WP_Error Attachment id or error.
	 */
	private static function handle_upload( array $field, $user_id ) {
		require_once ABSPATH . 'wp-admin/includes/image.php';
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';

		$key   = $field['id'];
		$group = $_FILES[ self::FILE_INPUT ]; // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized

		// Rebuild the nested $_FILES entry as a flat single-file array.
		$_FILES['rtcl_acf_upload'] = [
			'name'     => $group['name'][ $key ],
			'type'     => $group['type'][ $key ],
			'tmp_name' => $group['tmp_name'][ $key ],
			'error'    => $group['error'][ $key ],
			'size'     => $group['size'][ $key ],
		];

		$attachment_id = media_handle_upload(
			'rtcl_acf_upload',
			0,
			[ 'post_author' => (int) $user_id ],
			[
				'test_form' => false,
				'mimes'     => self::field_allowed_mimes( $field ),
			]
		);

		unset( $_FILES['rtcl_acf_upload'] );

		return $attachment_id;
	}
}
