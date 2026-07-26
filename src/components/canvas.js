/**
 * Center canvas: the droppable, sortable list of configured fields.
 */
import { __ } from '@wordpress/i18n';
import { useDroppable } from '@dnd-kit/core';
import SortableField from './sortable-field';
import { IconPlus } from '../icons';

export default function Canvas( { dropId, fields, types, selected, onSelect, onRemove, onDuplicate } ) {
	const { setNodeRef, isOver } = useDroppable( { id: dropId } );

	return (
		<section className="rtcl-acf-canvas">
			<div
				ref={ setNodeRef }
				className={ `rtcl-acf-canvas__surface${ isOver ? ' is-over' : '' }${ fields.length === 0 ? ' is-empty' : '' }` }
			>
				{ fields.length === 0 ? (
					<div className="rtcl-acf-empty">
						<div className="rtcl-acf-empty__icon"><IconPlus /></div>
						<h3>{ __( 'Start building your form', 'rtcl-account-fields' ) }</h3>
						<p>{ __( 'Drag a field type from the left, or click one to add it here.', 'rtcl-account-fields' ) }</p>
					</div>
				) : (
					fields.map( ( field, index ) => (
						<SortableField
							key={ field._uid }
							index={ index }
							field={ field }
							typeLabel={ types[ field.type ] || field.type }
							isSelected={ field._uid === selected }
							onSelect={ () => onSelect( field._uid ) }
							onRemove={ () => onRemove( field._uid ) }
							onDuplicate={ () => onDuplicate( field._uid ) }
						/>
					) )
				) }
			</div>
		</section>
	);
}
