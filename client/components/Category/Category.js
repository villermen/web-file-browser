// @flow
import React from 'react';
import { Link } from 'react-router-dom';

import { Item } from '../../types';
import styles from './Category.scss';

type Props = {
    title: string,
    items: Array<Item>,
};

function Category({ title, items }: Props) {
    return (
        <section className={styles.category}>
            <header className={styles.header}>
                <h1 className={styles.title}>{title}</h1>
                <span className={styles.details}>{items.length} items</span>
            </header>
            <ul className={styles.items}>
                {items.map(({ path, label }) => (
                    <li key={path}>
                        <Link to={path}>{label}</Link>
                    </li>
                ))}
            </ul>
        </section>
    );
}

export default Category;
