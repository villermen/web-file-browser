// @flow
import { Items } from '../types';

export function fetchItems(path: string): Promise<Items> {
    return Promise.resolve()
        .then(() => {
            switch (path) {
                case '':
                    return {
                        websites: [
                            { path: 'website-1', label: 'Website 1' },
                        ],
                        directories: [
                            { path: 'asdf', label: 'asdf' },
                        ],
                        files: [],
                    };
                case 'asdf':
                    return {
                        websites: [],
                        directories: [
                            { path: 'asdf/sdasd', label: 'sdasd' },
                        ],
                        files: [],
                    };
                case 'asdf/sdasd':
                    return {
                        websites: [],
                        directories: [],
                        files: [],
                    };
                default:
                    throw new Error('unknown path');
            }
        });
}
