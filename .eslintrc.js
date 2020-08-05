module.exports = {
    "extends": "airbnb-base",
    rules: {
        'no-plusplus': [2, { allowForLoopAfterthoughts: true }],
        'no-underscore-dangle': [2, {allow: ['_id']}],
        'object-shorthand': ["error", "properties"]
    },
    env: {
        "browser": true,
        "jquery": true
    }
};