/**
 * Sticky top toolbar: title, dirty state, save button and inline notice.
 */
import { __, sprintf, _n } from '@wordpress/i18n';
import { IconCheck, IconClose } from '../icons';

export default function Toolbar( { count, dirty, saving, notice, onSave, onDismissNotice } ) {
	return (
		<header className="rtcl-acf__toolbar">
			<div className="rtcl-acf__brand">
				<div className="rtcl-acf__logo" aria-hidden="true">
					<IconCheck />
				</div>
				<div className="rtcl-acf__titles">
					<h1>{ __( 'Account Fields Builder', 'rtcl-account-fields' ) }</h1>
					<p>
						{ sprintf(
							/* translators: %d: number of fields */
							_n( '%d field configured', '%d fields configured', count, 'rtcl-account-fields' ),
							count
						) }
					</p>
				</div>
			</div>

			<div className="rtcl-acf__toolbar-actions">
				{ notice && (
					<div className={ `rtcl-acf__notice is-${ notice.type }` } role="status">
						<span>{ notice.text }</span>
						<button type="button" className="rtcl-acf__notice-close" onClick={ onDismissNotice } aria-label={ __( 'Dismiss', 'rtcl-account-fields' ) }>
							<IconClose />
						</button>
					</div>
				) }

				{ dirty && ! saving && (
					<span className="rtcl-acf__dirty">{ __( 'Unsaved changes', 'rtcl-account-fields' ) }</span>
				) }

				<button
					type="button"
					className="rtcl-acf__btn rtcl-acf__btn--primary"
					onClick={ onSave }
					disabled={ saving || ! dirty }
				>
					{ saving ? __( 'Saving…', 'rtcl-account-fields' ) : __( 'Save changes', 'rtcl-account-fields' ) }
				</button>
			</div>
		</header>
	);
}
