// @flow
import React from 'react';
import Category from './Category/Category';

import { Items } from '../types';

type Props = {
    loading: boolean,
    items: Items,
};

function Browser({ loading, items }: Props) {
    if (loading) {
        return <div>Loading...</div>;
    }

    return (
        <div>
            <Category title="Websites" items={items.websites} />
            <Category title="Directories" items={items.directories} />
            <Category title="Files" items={items.files} />
        </div>
    );
}

export default Browser;
