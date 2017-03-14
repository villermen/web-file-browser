// @flow
import React from 'react';

type Props = {
    match: {
        params: {
            path: string,
        },
    },
};

function Browser({ match }: Props) {
    return <div>Path: {match.params.path}</div>;
}

export default Browser;
