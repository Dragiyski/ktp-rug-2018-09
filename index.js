(function() {
    'use strict';

    const express = require('express');
    const path = require('path');

    const app = express();

    app.get(express.static(path.resolve(__dirname, 'public')));

    app.listen(80, () => {
        console.log('Server started');
    });
})();