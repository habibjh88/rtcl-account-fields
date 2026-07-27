/**
 * Account Fields — React form builder.
 */
import { useState, useCallback, useMemo, useEffect } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import {
	DndContext,
	DragOverlay,
	PointerSensor,
	KeyboardSensor,
	useSensor,
	useSensors,
	closestCenter,
} from '@dnd-kit/core';
import {
	SortableContext,
	arrayMove,
	sortableKeyboardCoordinates,
	verticalListSortingStrategy,
} from '@dnd-kit/sortable';

import Palette from './components/palette';
import Canvas from './components/canvas';
import SettingsPanel from './components/settings-panel';
import Toolbar from './components/toolbar';
import { bootstrap, saveFields } from './api';

let seq = 0;
const uid = () => `f${ ++seq }-${ Math.random().toString( 36 ).slice( 2, 7 ) }`;

// 6-char lowercase alphanumeric suffix, e.g. "1rhch".
const randSuffix = ( n = 6 ) => {
	const chars = 'abcdefghijklmnopqrstuvwxyz0123456789';
	let out = '';
	for ( let i = 0; i < n; i++ ) {
		out += chars[ Math.floor( Math.random() * chars.length ) ];
	}
	return out;
};

// Default key for a new field of the given type, e.g. "text_1rhch".
const defaultKey = ( type ) => `${ type }_${ randSuffix( 6 ) }`;

const PALETTE_PREFIX = 'palette:';
const CANVAS_DROP = 'rtcl-acf-canvas-drop';

/**
 * Hydrate a stored field into a working copy with a stable client id.
 *
 * @param {Object} field Stored field.
 * @return {Object} Working field.
 */
function hydrate( field ) {
	return {
		...bootstrap.defaults,
		...field,
		options: Array.isArray( field.options ) ? field.options : [],
		_uid: uid(),
	};
}

export default function App() {
	const types = bootstrap.types || {};
	const typeList = useMemo(
		() => Object.keys( types ).map( ( key ) => ( { type: key, label: types[ key ] } ) ),
		[ types ]
	);

	const [ fields, setFields ] = useState( () => ( bootstrap.fields || [] ).map( hydrate ) );
	const [ selected, setSelected ] = useState( null );
	const [ active, setActive ] = useState( null );
	const [ dirty, setDirty ] = useState( false );
	const [ saving, setSaving ] = useState( false );
	const [ notice, setNotice ] = useState( null );

	const sensors = useSensors(
		useSensor( PointerSensor, { activationConstraint: { distance: 5 } } ),
		useSensor( KeyboardSensor, { coordinateGetter: sortableKeyboardCoordinates } )
	);

	// A fresh edit supersedes the previous save result: drop the notice so the
	// "Changes saved." badge never lingers next to "Unsaved changes".
	useEffect( () => {
		if ( dirty ) {
			setNotice( null );
		}
	}, [ dirty ] );

	// Warn before leaving with unsaved changes.
	useEffect( () => {
		const handler = ( e ) => {
			if ( ! dirty ) {
				return;
			}
			e.preventDefault();
			e.returnValue = '';
		};
		window.addEventListener( 'beforeunload', handler );
		return () => window.removeEventListener( 'beforeunload', handler );
	}, [ dirty ] );

	const makeField = useCallback(
		( type ) => ( {
			...bootstrap.defaults,
			_uid: uid(),
			id: defaultKey( type ),
			type,
			label: types[ type ] || __( 'Untitled', 'rtcl-account-fields' ),
			options: [ 'select', 'radio', 'checkbox' ].includes( type )
				? [ __( 'Option 1', 'rtcl-account-fields' ), __( 'Option 2', 'rtcl-account-fields' ) ]
				: [],
			// A new File field allows the common document/image types by default.
			file_ext: type === 'file' ? 'pdf, jpg, png' : '',
		} ),
		[ types ]
	);

	const addField = useCallback(
		( type, index ) => {
			const field = makeField( type );
			setFields( ( prev ) => {
				const next = [ ...prev ];
				const at = index === undefined || index < 0 ? next.length : index;
				next.splice( at, 0, field );
				return next;
			} );
			setSelected( field._uid );
			setDirty( true );
		},
		[ makeField ]
	);

	const updateField = useCallback( ( fieldUid, patch ) => {
		setFields( ( prev ) => prev.map( ( f ) => ( f._uid === fieldUid ? { ...f, ...patch } : f ) ) );
		setDirty( true );
	}, [] );

	const removeField = useCallback( ( fieldUid ) => {
		setFields( ( prev ) => prev.filter( ( f ) => f._uid !== fieldUid ) );
		setSelected( ( cur ) => ( cur === fieldUid ? null : cur ) );
		setDirty( true );
	}, [] );

	const duplicateField = useCallback( ( fieldUid ) => {
		setFields( ( prev ) => {
			const idx = prev.findIndex( ( f ) => f._uid === fieldUid );
			if ( idx === -1 ) {
				return prev;
			}
			const copy = { ...prev[ idx ], _uid: uid(), id: defaultKey( prev[ idx ].type ), label: `${ prev[ idx ].label } (copy)` };
			const next = [ ...prev ];
			next.splice( idx + 1, 0, copy );
			return next;
		} );
		setDirty( true );
	}, [] );

	const onDragStart = useCallback(
		( { active: a } ) => {
			const id = String( a.id );
			if ( id.startsWith( PALETTE_PREFIX ) ) {
				setActive( { kind: 'palette', type: id.slice( PALETTE_PREFIX.length ) } );
			} else {
				const field = fields.find( ( f ) => f._uid === id );
				setActive( field ? { kind: 'field', field } : null );
			}
		},
		[ fields ]
	);

	const onDragEnd = useCallback(
		( { active: a, over } ) => {
			setActive( null );
			if ( ! over ) {
				return;
			}
			const activeId = String( a.id );
			const overId = String( over.id );

			// Dropping a new field type from the palette.
			if ( activeId.startsWith( PALETTE_PREFIX ) ) {
				const type = activeId.slice( PALETTE_PREFIX.length );
				if ( overId === CANVAS_DROP ) {
					addField( type );
				} else {
					const idx = fields.findIndex( ( f ) => f._uid === overId );
					addField( type, idx === -1 ? undefined : idx );
				}
				return;
			}

			// Reordering existing fields.
			if ( activeId !== overId && overId !== CANVAS_DROP ) {
				setFields( ( prev ) => {
					const from = prev.findIndex( ( f ) => f._uid === activeId );
					const to = prev.findIndex( ( f ) => f._uid === overId );
					if ( from === -1 || to === -1 ) {
						return prev;
					}
					return arrayMove( prev, from, to );
				} );
				setDirty( true );
			}
		},
		[ fields, addField ]
	);

	const onSave = useCallback( async () => {
		setSaving( true );
		setNotice( null );
		try {
			const res = await saveFields( fields );
			const saved = ( res.fields || [] ).map( hydrate );
			setFields( saved );
			setSelected( null );
			setDirty( false );
			setNotice( { type: 'success', text: __( 'Changes saved.', 'rtcl-account-fields' ) } );
		} catch ( err ) {
			setNotice( {
				type: 'error',
				text: err?.message || __( 'Could not save. Please try again.', 'rtcl-account-fields' ),
			} );
		} finally {
			setSaving( false );
		}
	}, [ fields ] );

	const selectedField = fields.find( ( f ) => f._uid === selected ) || null;
	const itemIds = useMemo( () => fields.map( ( f ) => f._uid ), [ fields ] );

	return (
		<div className="rtcl-acf">
			<Toolbar
				count={ fields.length }
				dirty={ dirty }
				saving={ saving }
				notice={ notice }
				onSave={ onSave }
				onDismissNotice={ () => setNotice( null ) }
			/>

			<DndContext
				sensors={ sensors }
				collisionDetection={ closestCenter }
				onDragStart={ onDragStart }
				onDragEnd={ onDragEnd }
				onDragCancel={ () => setActive( null ) }
			>
				<div className="rtcl-acf__body">
					<Palette items={ typeList } prefix={ PALETTE_PREFIX } onAdd={ ( type ) => addField( type ) } />

					<SortableContext items={ itemIds } strategy={ verticalListSortingStrategy }>
						<Canvas
							dropId={ CANVAS_DROP }
							fields={ fields }
							types={ types }
							selected={ selected }
							onSelect={ setSelected }
							onRemove={ removeField }
							onDuplicate={ duplicateField }
						/>
					</SortableContext>

					<SettingsPanel
						key={ selectedField ? selectedField._uid : 'none' }
						field={ selectedField }
						types={ types }
						onChange={ ( patch ) => selectedField && updateField( selectedField._uid, patch ) }
						onClose={ () => setSelected( null ) }
						onRemove={ () => selectedField && removeField( selectedField._uid ) }
					/>
				</div>

				<DragOverlay dropAnimation={ null }>
					{ active ? (
						<div className="rtcl-acf-drag-overlay">
							{ active.kind === 'palette'
								? types[ active.type ] || active.type
								: active.field?.label }
						</div>
					) : null }
				</DragOverlay>
			</DndContext>
		</div>
	);
}
