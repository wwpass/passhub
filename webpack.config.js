const path = require('path');

module.exports = {

  entry: {
    new_file: './js/src/new_file.js',
    item_form: './js/src/item_form.js',
    index: './js/src/index.js',
    iam: './js/src/iam.js',
    upsert_user: './js/src/upsert_user.js',
    login: './js/src/login.js',
    timers: './js/src/timers.js',
  },

  output: {
    filename: '[name].js',
    path: path.resolve(__dirname, 'js/dist'),
  },

  optimization: {
    minimize: false,
  },

  externals: {
    jquery: 'jQuery',
  },
};
