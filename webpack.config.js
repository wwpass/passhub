const path = require('path');

module.exports = {

  entry: {
    // new_file: './js/src/new_file.js',
    // item_form: './js/src/item_form.js',
    index: './src/js/index.js',
    iam: './src/js/iam.js',
    upsert_user: './src/js/upsert_user.js',
    login: './src/js/login.js',
    timers: './src/js/timers.js',
    payment: './src/js/payment.js',
  },

  output: {
    filename: '[name].js',
    path: path.resolve(__dirname, 'public/js/dist'),
  },

  externals: {
    jquery: 'jQuery',
  },
/*

  optimization: {
    minimize: true,
  },
*/
  devtool: 'source-map',
  module: {
    rules: [{
      test: /\.js$/,
      // exclude: /node_modules/,
      loader: 'babel-loader' 
    }]
  } 
};
