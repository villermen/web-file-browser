// @flow
import React from 'react';
import { Link } from 'react-router-dom';

import { Item as ItemType, ViewType } from '../../types';

type ItemListProps = {
    items: Array<ItemType>,
    viewType: ViewType,
}

function ItemList({ items, viewType }: ItemListProps) {
    return (
        <div>
            View type: {viewType}
            <ol>
                {items.map(({ path, label }) => (
                    <li key={path}>
                        <Link to={path}>{label}</Link>
                    </li>
                ))}
            </ol>
        </div>
    );
}

export default ItemList;
