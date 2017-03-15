// @flow
import { createAction } from 'redux-actions';
import { fetchItems } from '../helpers/data';

export const LOAD_ITEMS = 'LOAD_ITEMS';
export const loadItems = createAction(LOAD_ITEMS, path => ({
    promise: fetchItems(path),
    data: path,
}));

export function shouldLoadItems({ items: state }, path) {
    return state.path !== path && !state.loading;
}

export function loadItemsIfNeeded(path) {
    return (dispatch, getState) => {
        console.log(shouldLoadItems(getState(), path), getState());

        if (shouldLoadItems(getState(), path)) {
            return dispatch(loadItems(path));
        }

        return Promise.resolve();
    };
}
