// @flow
import React from 'react';
import Category from '../Category/Category';

import { Categories } from '../../types';
import styles from './Browser.scss';

type Props = {
    loading: boolean,
    items: Categories,
};

function Browser({ loading, items }: Props) {
    if (loading) {
        return <div>Loading...</div>;
    }

    return (
        <div className={styles.wrapper}>
            <Category title="Websites" items={items.websites} />
            <Category title="Directories" items={items.directories} />
            <Category title="Files" items={items.files} />
        </div>
    );
}

export default Browser;
