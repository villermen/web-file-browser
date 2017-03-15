// @flow
import typeToReducer from 'type-to-reducer';
import { LOAD_ITEMS } from '../actions/items';

const initialState = {
    path: null,
    loading: false,
    items: {
        websites: [],
        directories: [],
        files: [],
    },
};

export default typeToReducer({
    [LOAD_ITEMS]: {
        PENDING: (state, { payload }) => ({
            ...state,
            loading: true,
            path: payload,
        }),
        FULFILLED: (state, { payload }) => ({
            ...state,
            loading: false,
            items: payload,
        }),
    },
}, initialState);
