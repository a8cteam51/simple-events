import { useState, useCallback } from '@wordpress/element';

/**
 * Object state with a partial-merge setter.
 *
 * Replaces the deprecated `withState` HOC (removed from @wordpress/compose in
 * favour of useState since WP 5.8). `withState` spread its state into props and
 * injected a `setState` that merged whatever you passed it, so call sites do
 * `setState( { loading: false } )` and leave the rest alone. useState replaces
 * state wholesale, so this restores the merge and lets those call sites stand.
 *
 * @param {Object} initialState Starting state.
 * @return {[Object, Function]} Current state, and a merging setter.
 */
export const useMergeState = ( initialState ) => {
	const [ state, setState ] = useState( initialState );

	const mergeState = useCallback( ( partial ) => {
		setState( ( previous ) => ( {
			...previous,
			...( typeof partial === 'function' ? partial( previous ) : partial ),
		} ) );
	}, [] );

	return [ state, mergeState ];
};
