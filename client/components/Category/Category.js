// @flow
import React from 'react';

import { Item, ViewType } from '../../types';
import styles from './Category.scss';
import ItemList from '../ItemList/ItemList';

type Props = {
    title: string,
    items: Array<Item>,
    viewType: ViewType,
};

function Category({ title, items, viewType = 'list' }: Props) {
    return (
        <section className={styles.category}>
            <header className={styles.header}>
                <h1 className={styles.title}>{title}</h1>
                <span className={styles.details}>{items.length} items</span>
            </header>
            <div className={styles.items}>
                <ItemList items={items} viewType={viewType} />
            </div>
        </section>
    );
}

export default Category;
