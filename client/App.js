// @flow
import React from 'react';
import { Route } from 'react-router-dom';

import Browser from './containers/Browser';

function App() {
    return (
        <div>
            <Route path="/:path*" component={Browser} />
        </div>
    );
}

export default App;
