export type Item = {
    path: string,
    label: string,
};

export type Items = {
    websites: Array<Item>,
    directories: Array<Item>,
    files: Array<Item>,
};
