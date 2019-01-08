(function() {
    'use strict';
    const PORT = process.env.PORT || 3000;
    const express = require('express');
    const path = require('path');

    const app = express();

    app.get(express.static(path.resolve(__dirname, 'public')));

    app.listen(PORT, () => {
        console.log('Server started');
    });
})();