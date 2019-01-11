(function () {
    'use strict';
    const PORT = process.env.PORT || 3000;
    const express = require('express');
    const path = require('path');
    const child_process = require('child_process');
    const url = require('url');

    const app = express();

    app.use((req, res, next) => {
        let u = req.protocol + '://' + req.headers.host + req.url;
        u = new URL(u);
        if(u.pathname !== '/') {
            return void next();
        }
        let executable = path.resolve(__dirname, 'kennissysteem/web.php');
        let env = process.env;
        env.CLI_ROOT = path.resolve(__dirname, 'kennissysteem/www');
        env.HTTP_HOST = req.headers.host;
        env.REQUEST_URI = req.url;
        env.QUERY_STRING = u.search;
        env.REQUEST_METHOD = req.method;
        let p = child_process.spawn(executable, [], {
            cwd: path.resolve(__dirname, 'kennissysteem'),
            env: env,
            stdio: ['pipe', 'pipe', 'inherit']
        });
        p.once('error', err => {
            next(err);
        });
        req.pipe(p.stdin);
        res.setHeader('content-type', 'text/html');
        p.stdout.pipe(res);
        p.once('exit', (code, signal) => {
            let j = 0;
        });
    });

    app.get('/:path', express.static(path.resolve(__dirname, 'kennissysteem/www')));

    app.listen(PORT, () => {
        console.log('Server started');
    });
})();