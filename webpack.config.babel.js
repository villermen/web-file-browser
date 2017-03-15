import path from 'path';
import webpackMerge from 'webpack-merge';

const baseConfig = {
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
                                ['es2015', { modules: false }],
                                'react',
                            ],
                            plugins: [
                                'transform-flow-strip-types',
                                'transform-object-rest-spread',
                            ],
                        },
                    },
                ],
            },
        ],
    },
};

const developmentConfig = {
    devtool: 'source-map',

    devServer: {
        overlay: true,

        host: '0.0.0.0',
        port: 8080,
        publicPath: '/assets/',
    },

    output: {
        publicPath: 'http://localhost:8080/assets/',
    },
};

const productionConfig = {};

// Merge config based on environment
const NODE_ENV = process.env.NODE_ENV || 'development';
const config = webpackMerge([
    baseConfig,
    NODE_ENV === 'development' ? developmentConfig : productionConfig,
]);

export default config;
