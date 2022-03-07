const path = require('path');

module.exports = {

  entry: {
//    index: './src/js/index.js',
//    iam: './src/js/iam.js',
    upsert_user: './src/js/upsert_user.js',
    login: './src/js/login.js',
//    payment: './src/js/payment.js',
//    survey: './src/js/survey.js',
  },

  output: {
    filename: '[name].js',
    path: path.resolve(__dirname, 'public/js/dist'),
  },

  externals: {
//    jquery: 'jQuery',
  },
  devtool: 'source-map',
  module: {
    rules: [{
      test: /\.js$/,
      // exclude: /node_modules/,
      loader: 'babel-loader' 
    }]
  } 
};
