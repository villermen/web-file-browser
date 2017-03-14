// @flow
import React  from 'react';

type Props = {
    match: {
        params: {
            path: string,
        },
    },
};

function Browser({ match }: Props) {
    const { path } = match.params;
    return <div>Path: {path}</div>;
}

export default Browser;
