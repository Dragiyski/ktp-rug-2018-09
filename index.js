(function () {
    'use strict';
    const PORT = process.env.PORT || 3000;
    const express = require('express');
    const path = require('path');
    const child_process = require('child_process');
    const url = require('url');
    const cookieParser = require('cookie-parser');
    const cookieSession = require('cookie-session');

    const app = express();

    app.use(cookieParser());
    app.use(cookieSession({
        httpOnly: true,
        name: 'session',
        secret: 'This is a random string determined by throwing a dice. It yield 4'
    }));

    app.all('/', (req, res, next) => {
        let u = req.protocol + '://' + req.headers.host + req.url;
        u = new URL(u);
        let executable = path.resolve(__dirname, 'kennissysteem/web.php');
        let env = process.env;
        env.CLI_ROOT = path.resolve(__dirname, 'kennissysteem/www');
        env.HTTP_HOST = req.headers.host;
        env.REQUEST_URI = req.url;
        env.QUERY_STRING = u.search;
        env.REQUEST_METHOD = req.method;
        let kb = path.resolve(__dirname, 'sugar.xml');
        let p = child_process.spawn(executable, [kb], {
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

    app.all('/json', (req, res, next) => {
        let session = req.session;
        let json = {
            state: session.solver_state
        };
        debugger;
    });

    app.get('/:path', express.static(path.resolve(__dirname, 'kennissysteem/www')));

    app.listen(PORT, () => {
        console.log('Server started');
    });
})();