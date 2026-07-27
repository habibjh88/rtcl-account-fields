/**
 * Right panel: edit the selected field's settings.
 */
import { useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import { IconClose, IconTrash, IconPlus, IconCopy, IconCheck } from '../icons';

/**
 * Copy text to the clipboard, with a fallback for non-secure (http) contexts.
 *
 * @param {string} text Text to copy.
 * @return {Promise<boolean>} Whether the copy succeeded.
 */
async function copyToClipboard( text ) {
	try {
		if ( navigator.clipboard && window.isSecureContext ) {
			await navigator.clipboard.writeText( text );
			return true;
		}
	} catch ( e ) {
		// Fall through to the legacy path.
	}
	try {
		const ta = document.createElement( 'textarea' );
		ta.value = text;
		ta.style.position = 'fixed';
		ta.style.opacity = '0';
		document.body.appendChild( ta );
		ta.focus();
		ta.select();
		const ok = document.execCommand( 'copy' );
		document.body.removeChild( ta );
		return ok;
	} catch ( e ) {
		return false;
	}
}

const OPTION_TYPES = [ 'select', 'radio', 'checkbox' ];
const NO_PLACEHOLDER = [ 'select', 'radio', 'checkbox', 'switch', 'file' ];
const VISIBILITY = ( window.rtclAcf && window.rtclAcf.visibility ) || {
	all: 'All (seller & buyer)',
	seller: 'Seller',
	buyer: 'Buyer',
};

// Available file types for the "Allowed file types" checkboxes (from the server).
const FILE_TYPES = ( window.rtclAcf && window.rtclAcf.fileTypes ) || [];

// Restrict a field key to valid meta-key characters.
const sanitizeKey = ( s ) => s.toLowerCase().replace( /[^a-z0-9_]+/g, '_' ).replace( /^_+/, '' );

// Parse a stored "file_ext" string ("pdf, jpg") into a lowercase extension set.
const parseExts = ( value ) =>
	new Set(
		String( value || '' )
			.toLowerCase()
			.split( /[\s,]+/ )
			.map( ( e ) => e.replace( /[^a-z0-9]/g, '' ) )
			.filter( Boolean )
	);

/**
 * Checkbox grid for the file field's allowed types. Toggling a group adds its
 * primary extension or clears every extension it covers.
 *
 * @param {Object}   props
 * @param {string}   props.value    Stored comma-separated extensions.
 * @param {Function} props.onChange Called with the new comma-separated string.
 * @return {JSX.Element} The grid.
 */
function FileTypePicker( { value, onChange } ) {
	const selected = parseExts( value );

	const toggle = ( type ) => {
		const isOn = type.exts.some( ( e ) => selected.has( e ) );
		const next = new Set( selected );
		if ( isOn ) {
			type.exts.forEach( ( e ) => next.delete( e ) );
		} else {
			next.add( type.ext );
		}
		onChange( Array.from( next ).join( ', ' ) );
	};

	if ( ! FILE_TYPES.length ) {
		return null;
	}

	return (
		<div className="rtcl-acf-filetypes">
			{ FILE_TYPES.map( ( type ) => {
				const isOn = type.exts.some( ( e ) => selected.has( e ) );
				return (
					<label key={ type.ext } className={ `rtcl-acf-filetypes__item${ isOn ? ' is-on' : '' }` }>
						<input type="checkbox" checked={ isOn } onChange={ () => toggle( type ) } />
						<span>{ type.label }</span>
					</label>
				);
			} ) }
		</div>
	);
}

function Toggle( { checked, onChange, label, hint } ) {
	return (
		<label className="rtcl-acf-toggle">
			<span className="rtcl-acf-toggle__text">
				<span className="rtcl-acf-toggle__label">{ label }</span>
				{ hint && <span className="rtcl-acf-toggle__hint">{ hint }</span> }
			</span>
			<span className={ `rtcl-acf-switch${ checked ? ' is-on' : '' }` }>
				<input
					type="checkbox"
					checked={ !! checked }
					onChange={ ( e ) => onChange( e.target.checked ) }
				/>
				<span className="rtcl-acf-switch__slider" />
			</span>
		</label>
	);
}

function OptionsRepeater( { options, onChange } ) {
	const rows = options.length ? options : [ '' ];

	const update = ( i, val ) => {
		const next = rows.slice();
		next[ i ] = val;
		onChange( next );
	};
	const add = () => onChange( [ ...rows, '' ] );
	const remove = ( i ) => {
		const next = rows.filter( ( _, idx ) => idx !== i );
		onChange( next );
	};

	return (
		<div className="rtcl-acf-repeater">
			{ rows.map( ( opt, i ) => (
				// eslint-disable-next-line react/no-array-index-key
				<div className="rtcl-acf-repeater__row" key={ i }>
					<span className="rtcl-acf-repeater__num">{ i + 1 }</span>
					<input
						type="text"
						value={ opt }
						onChange={ ( e ) => update( i, e.target.value ) }
						placeholder={ sprintf(
							/* translators: %d: option number */
							__( 'Option %d', 'rtcl-account-fields' ),
							i + 1
						) }
					/>
					<button
						type="button"
						className="rtcl-acf-iconbtn rtcl-acf-iconbtn--danger"
						onClick={ () => remove( i ) }
						aria-label={ __( 'Remove option', 'rtcl-account-fields' ) }
					>
						<IconTrash />
					</button>
				</div>
			) ) }
			<button type="button" className="rtcl-acf-repeater__add" onClick={ add }>
				<IconPlus /> { __( 'Add option', 'rtcl-account-fields' ) }
			</button>
		</div>
	);
}

export default function SettingsPanel( { field, types, onChange, onClose, onRemove } ) {
	const [ copied, setCopied ] = useState( false );

	if ( ! field ) {
		return (
			<aside className="rtcl-acf-settings is-empty">
				<div className="rtcl-acf-settings__placeholder">
					<h3>{ __( 'No field selected', 'rtcl-account-fields' ) }</h3>
					<p>{ __( 'Select a field on the canvas to edit its label, type and options.', 'rtcl-account-fields' ) }</p>
				</div>
			</aside>
		);
	}

	const hasOptions = OPTION_TYPES.includes( field.type );
	const hasPlaceholder = ! NO_PLACEHOLDER.includes( field.type );
	const onCopyKey = async () => {
		const ok = await copyToClipboard( field.id || '' );
		if ( ok ) {
			setCopied( true );
			window.setTimeout( () => setCopied( false ), 1500 );
		}
	};

	return (
		<aside className="rtcl-acf-settings">
			<div className="rtcl-acf-settings__head">
				<h2>{ __( 'Field settings', 'rtcl-account-fields' ) }</h2>
				<button type="button" className="rtcl-acf-iconbtn" onClick={ onClose } aria-label={ __( 'Close', 'rtcl-account-fields' ) }>
					<IconClose />
				</button>
			</div>

			<div className="rtcl-acf-settings__body">
				<div className="rtcl-acf-field">
					<label htmlFor="rtcl-acf-set-type">{ __( 'Field type', 'rtcl-account-fields' ) }</label>
					<select
						id="rtcl-acf-set-type"
						value={ field.type }
						onChange={ ( e ) => onChange( { type: e.target.value } ) }
					>
						{ Object.keys( types ).map( ( key ) => (
							<option key={ key } value={ key }>{ types[ key ] }</option>
						) ) }
					</select>
				</div>

				<div className="rtcl-acf-field">
					<label htmlFor="rtcl-acf-set-visible">{ __( 'Visible for', 'rtcl-account-fields' ) }</label>
					<select
						id="rtcl-acf-set-visible"
						value={ field.visible_for || 'all' }
						onChange={ ( e ) => onChange( { visible_for: e.target.value } ) }
					>
						{ Object.keys( VISIBILITY ).map( ( key ) => (
							<option key={ key } value={ key }>{ VISIBILITY[ key ] }</option>
						) ) }
					</select>
					<p className="rtcl-acf-field__hint">{ __( 'Which account type sees this field on the front-end.', 'rtcl-account-fields' ) }</p>
				</div>

				<div className="rtcl-acf-field">
					<label htmlFor="rtcl-acf-set-label">{ __( 'Label', 'rtcl-account-fields' ) } <span className="req">*</span></label>
					<input
						id="rtcl-acf-set-label"
						type="text"
						value={ field.label }
						onChange={ ( e ) => onChange( { label: e.target.value } ) }
						placeholder={ __( 'e.g. Company name', 'rtcl-account-fields' ) }
					/>
				</div>

				<div className="rtcl-acf-field">
					<label htmlFor="rtcl-acf-set-key">{ __( 'Field key', 'rtcl-account-fields' ) }</label>
					<div className="rtcl-acf-keyinput">
						<input
							id="rtcl-acf-set-key"
							type="text"
							value={ field.id || '' }
							spellCheck={ false }
							autoCapitalize="off"
							autoCorrect="off"
							onChange={ ( e ) => onChange( { id: sanitizeKey( e.target.value ) } ) }
						/>
						<button
							type="button"
							className={ `rtcl-acf-keyinput__copy${ copied ? ' is-copied' : '' }` }
							onClick={ onCopyKey }
							title={ copied ? __( 'Copied!', 'rtcl-account-fields' ) : __( 'Copy key', 'rtcl-account-fields' ) }
							aria-label={ __( 'Copy field key', 'rtcl-account-fields' ) }
						>
							{ copied ? <IconCheck /> : <IconCopy /> }
						</button>
					</div>
					<p className="rtcl-acf-field__hint">{ __( 'Unique storage key. Editable — changing it on an existing field starts a new value.', 'rtcl-account-fields' ) }</p>
				</div>

				{ hasOptions && (
					<div className="rtcl-acf-field">
						<label>{ __( 'Options', 'rtcl-account-fields' ) }</label>
						<OptionsRepeater
							options={ field.options || [] }
							onChange={ ( options ) => onChange( { options } ) }
						/>
						{ field.type === 'checkbox' && (
							<p className="rtcl-acf-field__hint">{ __( 'Leave empty for a single Yes/No checkbox.', 'rtcl-account-fields' ) }</p>
						) }
					</div>
				) }

				{ field.type === 'file' && (
					<>
						<div className="rtcl-acf-field">
							<div className="rtcl-acf-note">
								{ __( 'Users can upload a file or image. It is stored in the Media Library and can be shown on the author page.', 'rtcl-account-fields' ) }
							</div>
						</div>

						<div className="rtcl-acf-field">
							<label>{ __( 'Allowed file types', 'rtcl-account-fields' ) }</label>
							<FileTypePicker
								value={ field.file_ext || '' }
								onChange={ ( file_ext ) => onChange( { file_ext } ) }
							/>
							<p className="rtcl-acf-field__hint">{ __( 'Tick the types users may upload. Leave all unticked to allow every type the site permits.', 'rtcl-account-fields' ) }</p>
						</div>

						<div className="rtcl-acf-field">
							<label htmlFor="rtcl-acf-set-maxsize">{ __( 'Maximum file size (MB)', 'rtcl-account-fields' ) }</label>
							<input
								id="rtcl-acf-set-maxsize"
								type="number"
								min="0"
								step="0.1"
								value={ field.max_size ? String( field.max_size ) : '' }
								onChange={ ( e ) => onChange( { max_size: e.target.value } ) }
								placeholder={ __( '1', 'rtcl-account-fields' ) }
							/>
							<p className="rtcl-acf-field__hint">{ __( 'Leave empty (or 0) for a 1 MB limit. Cannot exceed the server limit.', 'rtcl-account-fields' ) }</p>
						</div>
					</>
				) }

				{ field.type === 'tel' && (
					<div className="rtcl-acf-field">
						<div className="rtcl-acf-note">
							{ __( 'Default country and allowed countries are inherited from Classified Listing → Settings → Misc → Phone. Change them there to update this field on the front-end.', 'rtcl-account-fields' ) }
						</div>
					</div>
				) }

				{ [ 'number', 'text', 'textarea' ].includes( field.type ) && (
					<div className="rtcl-acf-field">
						<label>
							{ field.type === 'number'
								? __( 'Value range', 'rtcl-account-fields' )
								: __( 'Character length', 'rtcl-account-fields' ) }
						</label>
						<div className="rtcl-acf-minmax">
							<input
								className="rtcl-acf-minmax__input"
								type="number"
								step={ field.type === 'number' ? 'any' : '1' }
								min={ field.type === 'number' ? undefined : '0' }
								value={ field.min === undefined || field.min === null ? '' : String( field.min ) }
								onChange={ ( e ) => onChange( { min: e.target.value } ) }
								placeholder={ __( 'Min', 'rtcl-account-fields' ) }
								aria-label={ __( 'Minimum', 'rtcl-account-fields' ) }
							/>
							<span className="rtcl-acf-minmax__sep">–</span>
							<input
								className="rtcl-acf-minmax__input"
								type="number"
								step={ field.type === 'number' ? 'any' : '1' }
								min={ field.type === 'number' ? undefined : '0' }
								value={ field.max === undefined || field.max === null ? '' : String( field.max ) }
								onChange={ ( e ) => onChange( { max: e.target.value } ) }
								placeholder={
									field.type === 'text'
										? '150'
										: field.type === 'textarea'
											? '500'
											: __( 'Max', 'rtcl-account-fields' )
								}
								aria-label={ __( 'Maximum', 'rtcl-account-fields' ) }
							/>
						</div>
						<p className="rtcl-acf-field__hint">
							{ field.type === 'number'
								? __( 'Values outside this range are rejected on submit. Leave a box empty for no bound.', 'rtcl-account-fields' )
								: __( 'Min / max number of characters. Leave max empty to use the default (text 150, textarea 500).', 'rtcl-account-fields' ) }
						</p>
					</div>
				) }

				{ hasPlaceholder && (
					<div className="rtcl-acf-field">
						<label htmlFor="rtcl-acf-set-ph">{ __( 'Placeholder', 'rtcl-account-fields' ) }</label>
						<input
							id="rtcl-acf-set-ph"
							type="text"
							value={ field.placeholder || '' }
							onChange={ ( e ) => onChange( { placeholder: e.target.value } ) }
						/>
					</div>
				) }

				<div className="rtcl-acf-field">
					<label htmlFor="rtcl-acf-set-desc">{ __( 'Description', 'rtcl-account-fields' ) }</label>
					<textarea
						id="rtcl-acf-set-desc"
						rows={ 3 }
						value={ field.description || '' }
						onChange={ ( e ) => onChange( { description: e.target.value } ) }
						placeholder={ __( 'Optional help text shown under the field.', 'rtcl-account-fields' ) }
					/>
				</div>

				<div className="rtcl-acf-toggles">
					<Toggle
						checked={ field.required }
						onChange={ ( v ) => onChange( { required: v } ) }
						label={ __( 'Required', 'rtcl-account-fields' ) }
						hint={ __( 'User must fill this in.', 'rtcl-account-fields' ) }
					/>
					<Toggle
						checked={ field.show_on_author }
						onChange={ ( v ) => onChange( { show_on_author: v } ) }
						label={ __( 'Show on author page', 'rtcl-account-fields' ) }
						hint={ __( 'Display publicly on the author profile.', 'rtcl-account-fields' ) }
					/>
					{ field.type !== 'file' && (
						<Toggle
							checked={ field.show_on_register }
							onChange={ ( v ) => onChange( { show_on_register: v } ) }
							label={ __( 'Show on registration form', 'rtcl-account-fields' ) }
							hint={ __( 'Also collect this field when a user registers.', 'rtcl-account-fields' ) }
						/>
					) }
				</div>
			</div>

			<div className="rtcl-acf-settings__foot">
				<button type="button" className="rtcl-acf__btn rtcl-acf__btn--ghost-danger" onClick={ onRemove }>
					<IconTrash /> { __( 'Delete field', 'rtcl-account-fields' ) }
				</button>
			</div>
		</aside>
	);
}
