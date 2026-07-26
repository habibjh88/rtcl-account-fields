/**
 * Left palette of draggable field types.
 */
import { __ } from '@wordpress/i18n';
import { useDraggable } from '@dnd-kit/core';
import { TYPE_ICONS, TYPE_HINTS, IconText } from '../icons';

function PaletteItem( { type, label, prefix, onAdd } ) {
	const { attributes, listeners, setNodeRef, isDragging } = useDraggable( { id: `${ prefix }${ type }` } );
	const Icon = TYPE_ICONS[ type ] || IconText;

	return (
		<button
			type="button"
			ref={ setNodeRef }
			className={ `rtcl-acf-palette-item${ isDragging ? ' is-dragging' : '' }` }
			onClick={ () => onAdd( type ) }
			title={ __( 'Click or drag to add', 'rtcl-account-fields' ) }
			{ ...listeners }
			{ ...attributes }
		>
			<span className="rtcl-acf-palette-item__icon"><Icon /></span>
			<span className="rtcl-acf-palette-item__text">
				<span className="rtcl-acf-palette-item__label">{ label }</span>
				<span className="rtcl-acf-palette-item__hint">{ TYPE_HINTS[ type ] || '' }</span>
			</span>
		</button>
	);
}

export default function Palette( { items, prefix, onAdd } ) {
	return (
		<aside className="rtcl-acf-palette">
			<div className="rtcl-acf-palette__head">
				<h2>{ __( 'Field types', 'rtcl-account-fields' ) }</h2>
				<p>{ __( 'Drag onto the canvas or click to add.', 'rtcl-account-fields' ) }</p>
			</div>
			<div className="rtcl-acf-palette__list">
				{ items.map( ( item ) => (
					<PaletteItem
						key={ item.type }
						type={ item.type }
						label={ item.label }
						prefix={ prefix }
						onAdd={ onAdd }
					/>
				) ) }
			</div>
		</aside>
	);
}
