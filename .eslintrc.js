module.exports = {
    "extends": "airbnb-base",
    rules: {
        'no-plusplus': [2, { allowForLoopAfterthoughts: true }],
        'no-underscore-dangle': [2, {allow: ['_id']}],
    },
    env: {
        "browser": true,
        "jquery": true
    }
};