// @flow
import { createStore, combineReducers, compose, applyMiddleware } from 'redux';
import thunkMiddleware from 'redux-thunk';
import promiseMiddleware from 'redux-promise-middleware';

import items from './reducers/items';

export default function configureStore() {
    const reducer = combineReducers({
        items,
    });

    const composeEnhancers = window.__REDUX_DEVTOOLS_EXTENSION_COMPOSE__ || compose;

    const middleware = [
        thunkMiddleware,
        promiseMiddleware(),
    ];

    return createStore(
        reducer,
        composeEnhancers(applyMiddleware(...middleware)),
    );
}
