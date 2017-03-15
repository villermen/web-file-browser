// @flow
import React from 'react';
import { Link } from 'react-router-dom';

type Props = {
    loading: boolean,
    items: Array<any>,
};

function Browser({ loading, items }: Props) {
    if (loading) {
        return <div>Loading...</div>;
    }

    console.log(items, 'aa');

    return (
        <div>
            Items
            <br />
            <Link to="/asdf">/asdf</Link>
            <br />
            <Link to="/asdf/sdasd">/asdf/sdasd</Link>
        </div>
    );
}

export default Browser;
