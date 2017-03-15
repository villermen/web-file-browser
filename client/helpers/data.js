// @flow

export function fetchItems(path: string) {
    console.log('fetch items', path);

    return new Promise(resolve => setTimeout(resolve, 500))
        .then(() => ({
            items: {
                websites: [`website ${path}`],
                directories: new Array(path.length).fill('directory'),
                files: ['file'],
            },
        }))
        .then(result => result.items);
}
