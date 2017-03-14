import React from 'react';
import { Route } from 'react-router-dom';

function App() {
    return (
        <div>
            <Route path="/:path*" render={props => <div>Path: {console.log(props)}{props.match.params.path}</div>} />
        </div>
    );
}

export default App;
