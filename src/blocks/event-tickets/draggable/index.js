import { Icon } from '@wordpress/components';
import { useRef } from '@wordpress/element';
import { __experimentalUseDragging as useDragging } from '@wordpress/compose';

const DraggableItem = ( props ) => {
	const sortableItemRef = useRef();

	const onDragStart = ( event ) => {
		const element = sortableItemRef.current;
		const clone = event.target
			.closest( '.se-draggable-item' )
			.cloneNode( true );

		clone.classList.add( 'se-draggable-item_dragging-clone' );
		clone.style.top = `${ element.getBoundingClientRect().top }px`;
		clone.style.width = `${ element.offsetWidth }px`;
		element.classList.add( 'se-draggable-item_dragging' );
		element.parentElement.appendChild( clone );

		document.body.classList.add( 'is-dragging-components-draggable' );
	};

	const onDragMove = ( event ) => {
		event.preventDefault();

		const target = event.target.closest( '.se-draggable-item' );
		const clone = document.querySelector(
			'.se-draggable-item_dragging-clone'
		);
		const cursor = event.clientY;
		const bounds = clone.parentElement.getBoundingClientRect();

		if ( ! target || cursor < bounds.top || cursor > bounds.bottom ) {
			return;
		}

		target.after( sortableItemRef.current );

		clone.style.top = `${ cursor - clone.clientHeight / 2 }px`;
	};

	const onDragEnd = () => {
		sortableItemRef.current.classList.remove(
			'se-draggable-item_dragging'
		);

		document.querySelector( '.se-draggable-item_dragging-clone' ).remove();
		document.body.classList.remove( 'is-dragging-components-draggable' );

		const sortableItems = sortableItemRef.current.parentElement.children;
		const updatedOrder = [ ...sortableItems ].map( ( item ) =>
			Number( item.dataset.index )
		);

		props.onChange( updatedOrder );
	};

	const { startDrag } = useDragging( {
		onDragStart,
		onDragMove,
		onDragEnd,
	} );

	const { className = '' } = props;

	return (
		<div
			className={ `se-draggable-item ${ className }` }
			data-index={ props.index }
			ref={ sortableItemRef }
		>
			<div className="se-draggable-item_handle" onMouseDown={ startDrag }>
				<Icon
					icon={
						<svg
							width="18"
							height="18"
							xmlns="http://www.w3.org/2000/svg"
							viewBox="0 0 18 18"
							role="img"
							aria-hidden="true"
							focusable="false"
						>
							<path d="M13,8c0.6,0,1-0.4,1-1s-0.4-1-1-1s-1,0.4-1,1S12.4,8,13,8z M5,6C4.4,6,4,6.4,4,7s0.4,1,1,1s1-0.4,1-1S5.6,6,5,6z M5,10 c-0.6,0-1,0.4-1,1s0.4,1,1,1s1-0.4,1-1S5.6,10,5,10z M13,10c-0.6,0-1,0.4-1,1s0.4,1,1,1s1-0.4,1-1S13.6,10,13,10z M9,6 C8.4,6,8,6.4,8,7s0.4,1,1,1s1-0.4,1-1S9.6,6,9,6z M9,10c-0.6,0-1,0.4-1,1s0.4,1,1,1s1-0.4,1-1S9.6,10,9,10z" />
						</svg>
					}
				/>
			</div>
			{ props.children }
		</div>
	);
};

export default DraggableItem;
