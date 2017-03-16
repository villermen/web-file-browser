// @flow
import React from 'react';
import { Link } from 'react-router-dom';

import { Item } from '../types';

type Props = {
    title: string,
    items: Array<Item>,
};

function Category({ title, items }: Props) {
    return (
        <div>
            <h1>{title}</h1>
            <ul>
                {items.map(({ path, label }) => (
                    <li key={path}>
                        <Link to={path}>{label}</Link>
                    </li>
                ))}
            </ul>
        </div>
    );
}

export default Category;
