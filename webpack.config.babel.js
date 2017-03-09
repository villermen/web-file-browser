import path from 'path';

export default {
    entry: './client/index.js',

    output: {
        path: path.resolve(__dirname, 'public/assets'),
        filename: 'bundle.js',
    },

    module: {
        rules: [
            {
                test: /\.js$/,
                exclude: /node_modules/,
                use: [
                    {
                        loader: 'babel-loader',
                        options: {
                            presets: [
                                ["es2015", { modules: false }],
                                "react",
                            ],
                        },
                    },
                ],
            },
        ],
    },
};
