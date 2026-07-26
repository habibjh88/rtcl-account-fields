/**
 * A single sortable field card on the canvas.
 */
import { __ } from '@wordpress/i18n';
import { useSortable } from '@dnd-kit/sortable';
import { CSS } from '@dnd-kit/utilities';
import { TYPE_ICONS, IconText, IconGrip, IconTrash, IconStar, IconEye } from '../icons';

const VISIBILITY = ( window.rtclAcf && window.rtclAcf.visibility ) || { seller: 'Seller', buyer: 'Buyer' };

export default function SortableField( {
	field,
	typeLabel,
	isSelected,
	onSelect,
	onRemove,
	onDuplicate,
} ) {
	const { attributes, listeners, setNodeRef, transform, transition, isDragging } = useSortable( {
		id: field._uid,
	} );

	const style = {
		transform: CSS.Transform.toString( transform ),
		transition,
	};

	const Icon = TYPE_ICONS[ field.type ] || IconText;

	return (
		<div
			ref={ setNodeRef }
			style={ style }
			className={ `rtcl-acf-card${ isSelected ? ' is-selected' : '' }${ isDragging ? ' is-dragging' : '' }` }
			onClick={ onSelect }
			role="button"
			tabIndex={ 0 }
			onKeyDown={ ( e ) => {
				if ( e.key === 'Enter' || e.key === ' ' ) {
					e.preventDefault();
					onSelect();
				}
			} }
		>
			<button
				type="button"
				className="rtcl-acf-card__grip"
				aria-label={ __( 'Drag to reorder', 'rtcl-account-fields' ) }
				onClick={ ( e ) => e.stopPropagation() }
				{ ...attributes }
				{ ...listeners }
			>
				<IconGrip />
			</button>

			<span className="rtcl-acf-card__type" title={ typeLabel }><Icon /></span>

			<div className="rtcl-acf-card__body">
				<div className="rtcl-acf-card__label">
					{ field.label || __( '(untitled field)', 'rtcl-account-fields' ) }
					{ field.required && (
						<span className="rtcl-acf-chip rtcl-acf-chip--req" title={ __( 'Required', 'rtcl-account-fields' ) }>
							<IconStar /> { __( 'Required', 'rtcl-account-fields' ) }
						</span>
					) }
					{ field.show_on_author && (
						<span className="rtcl-acf-chip rtcl-acf-chip--author" title={ __( 'Shown on author page', 'rtcl-account-fields' ) }>
							<IconEye /> { __( 'Author', 'rtcl-account-fields' ) }
						</span>
					) }
					{ field.visible_for && field.visible_for !== 'all' && (
						<span className={ `rtcl-acf-chip rtcl-acf-chip--vis rtcl-acf-chip--${ field.visible_for }` } title={ __( 'Visible for account type', 'rtcl-account-fields' ) }>
							{ VISIBILITY[ field.visible_for ] || field.visible_for }
						</span>
					) }
				</div>
				<div className="rtcl-acf-card__meta">
					<span className="rtcl-acf-card__typelabel">{ typeLabel }</span>
					{ field.id && <code className="rtcl-acf-card__key">{ field.id }</code> }
				</div>
			</div>

			<div className="rtcl-acf-card__actions">
				<button
					type="button"
					className="rtcl-acf-iconbtn"
					onClick={ ( e ) => { e.stopPropagation(); onDuplicate(); } }
					title={ __( 'Duplicate', 'rtcl-account-fields' ) }
					aria-label={ __( 'Duplicate field', 'rtcl-account-fields' ) }
				>
					<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true">
						<rect x="9" y="9" width="11" height="11" rx="2" /><path d="M5 15V5a2 2 0 0 1 2-2h10" />
					</svg>
				</button>
				<button
					type="button"
					className="rtcl-acf-iconbtn rtcl-acf-iconbtn--danger"
					onClick={ ( e ) => { e.stopPropagation(); onRemove(); } }
					title={ __( 'Delete', 'rtcl-account-fields' ) }
					aria-label={ __( 'Delete field', 'rtcl-account-fields' ) }
				>
					<IconTrash />
				</button>
			</div>
		</div>
	);
}
