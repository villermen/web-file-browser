export type Item = {
    path: string,
    label: string,
};

export type Categories = {
    websites: Array<Item>,
    directories: Array<Item>,
    files: Array<Item>,
};

export type ViewType = 'list' | 'grid';
