const path = require('path');
const webpack = require("webpack");
const imgPath = path.resolve(__dirname, 'frontend/img/')

const SRC_DIR = __dirname + '/adminmenu/js/src';
const DIST_DIR = __dirname + '/adminmenu/js/dist';

const isEnvDevelopment = process.env.NODE_ENV === 'development'
const isEnvProduction = process.env.NODE_ENV === 'production'

const cssRegex = /\.css$/;
const cssModuleRegex = /\.module\.css$/;
const sassRegex = /\.(scss|sass)$/;
const sassModuleRegex = /\.module\.(scss|sass)$/;
const shouldUseSourceMap = process.env.GENERATE_SOURCEMAP !== 'false';

module.exports = {
    mode: isEnvDevelopment ? 'development' : 'production',
    devServer: {
        contentBase: DIST_DIR,
        hot: true,
    },
    devtool: 'inline-source-map',
    resolve: {
        alias: {
            '@CSS': path.resolve(__dirname, 'adminmenu/css/'),
            '@Images': imgPath,
            '@Components': SRC_DIR + '/components/',
            //'@FrontendComponents': path.resolve(__dirname, 'frontend/js/src/components/'),
            '@Utilities': SRC_DIR + '/utils/',
            //'@FrontendUtilities': path.resolve(__dirname, 'frontend/js/src/utils/'),
            '@AdminMenuSrc': SRC_DIR,
        },
        extensions: ['.tsx', '.ts', '.js', '.jsx', '.scss']
    },
    entry: {
        'adminmenu/js/dist/main': SRC_DIR + '/main.tsx',
        // 'frontend/js/dist/main': './frontend/js/src/main.js'
    },
    output: {
        filename: '[name].js',
        path: __dirname,
    },
    module: {
        rules: [
            // JS(X)
            {
                test: /\.(js|jsx)$/,
                exclude: /node_modules/,
                use: {
                    loader: "babel-loader",
                    options: {
                        sourceMap: isEnvDevelopment,
                        presets: [
                            "@babel/preset-env",
                            "@babel/preset-react"
                        ],
                        plugins: [
                            "@babel/plugin-proposal-class-properties",
                        ]
                    }
                }
            },
            // TS(x)
            {
                test: /\.(tsx|ts)?$/,
                use: {
                    loader: 'ts-loader',
                },
                exclude: /node_modules/,
            },
            // SCSS
            {
                test: /\.scss$/,
                exclude: /node_modules/,
                use: [
                    {
                        loader: 'style-loader',
                        options: {
                            injectType: 'singletonStyleTag'
                        }
                    },
                    {
                        loader: 'css-loader',
                        options: {
                            importLoaders: 1,
                            modules: {
                                localIdentName: '[local]__[hash:base64:7]'
                            }
                        }
                    },
                    {
                        loader: 'sass-loader',
                        options: {
                            sourceMap: isEnvDevelopment
                        }
                    },
                    {
                        loader: 'postcss-loader',
                        options: {
                            postcssOptions: {
                                plugins: [
                                    require('tailwindcss'),
                                    'autoprefixer',
                                ]
                            }
                        }
                    },
                ],
                include: /\.module\.scss$/
            },
            // CSS
            {
                test: /\.css$/,
                use: [
                    'style-loader',
                    'css-loader',
                    {
                        loader: 'postcss-loader',
                        options: {
                            postcssOptions: {
                                plugins: [
                                    require('tailwindcss'),
                                    'autoprefixer',
                                ]
                            }
                        }
                    },
                ],
                exclude: [
                    /node_modules/,
                    /\.module\.css$/
                ]
            },
            // SVG
            {
                test: /\.svg$/,
                use: ['@svgr/webpack'],
            },


        ]
    }
};
