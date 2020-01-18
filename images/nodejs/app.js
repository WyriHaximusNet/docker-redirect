const fs = require('fs');
const http = require('http');
const YAML = require('yaml');
const isIp = require('is-ip');
const url = require('url');
const prometheus = require('prom-client');
const collectDefaultMetrics = prometheus.collectDefaultMetrics;
const Registry = prometheus.Registry;
const register = new Registry();
collectDefaultMetrics({ register });
const counter = new prometheus.Counter({
    name: 'http_requests_total',
    help: 'The amount of requests handler per host, HTTP method, and host the client was redirected to',
    labelNames: ['fromHost', 'method', 'toHost'],
    registers: [register],
});

const file = fs.readFileSync('/etc/redirect/config.yaml', 'utf8');
const yamlConfig = YAML.parse(file);

const fullUrl = function (req) {
    let protocol = 'http';
    if (req.protocol) {
        protocol = req.protocol;
    }

    return url.format({
        protocol: protocol,
        host: req.headers.host,
    }) + req.url;
};

const hostsHandler = function (req, res) {
    let url = new URL(fullUrl(req));

    if (req.headers.host === '' || req.headers.host === undefined || isIp(url.hostname)) {
        return false;
    }

    if ('hosts' in yamlConfig && url.hostname in yamlConfig.hosts) {
        url.hostname = yamlConfig.hosts[url.hostname];

        res.writeHead(
            301,
            {
                'Location': url.toString(),
            }
        ).end('');

        counter.inc({fromHost: (new URL(fullUrl(req))).hostname, method: req.method, toHost: url.hostname});

        return true;
    }

    return false;
};

const wwwToNonWwwHandler = function (req, res) {
    let url = new URL(fullUrl(req));

    if (req.headers.host === '' || req.headers.host === undefined || isIp(url.hostname)) {
        return false;
    }

    if (req.headers.host.indexOf('www.') === 0) {

        url.hostname = url.hostname.substring(4);
        res.writeHead(
            301,
            {
                'Location': url.toString(),
            }
        ).end('');

        counter.inc({fromHost: 'www.' + url.hostname, method: req.method, toHost: url.hostname});

        return true;
    }

    return false;
};

const nonWwwToWwwHandler = function (req, res) {
    let url = new URL(fullUrl(req));

    if (req.headers.host === '' || req.headers.host === undefined || isIp(url.hostname)) {
        return false;
    }

    if (req.headers.host.indexOf('www.') === -1) {
        url.hostname = 'www.' + url.hostname;
        res.writeHead(
            301,
            {
                'Location': url.toString(),
            }
        ).end('');

        counter.inc({fromHost: url.hostname.substring(4), method: req.method, toHost: url.hostname});

        return true;
    }

    return false;
};

const defaultFallbackHandler = function (req, res) {
    let url = new URL(fullUrl(req));

    res.writeHead(
        301,
        {
            'Location': yamlConfig.defaultFallbackTarget
        }
    ).end('');

    counter.inc({fromHost: url.hostname, method: req.method, toHost: (new URL(yamlConfig.defaultFallbackTarget)).hostname});

    return true;
};

const handler = function (req, res) {
    let resp = hostsHandler(req, res);
    if (resp !== false) {
        return;
    }

    if (yamlConfig.buildin.wwwToNonWww === true) {
        let resp = wwwToNonWwwHandler(req, res);
        if (resp !== false) {
            return;
        }
    }

    if (yamlConfig.buildin.nonWwwToWww === true) {
        let resp = nonWwwToWwwHandler(req, res);
        if (resp !== false) {
            return;
        }
    }

    return defaultFallbackHandler(req, res);
};

const redirectServer = http.createServer(handler);
redirectServer.listen(7132, '0.0.0.0');

const metricsServer = http.createServer((req, res) => {
    res.writeHead(
        200,
        {
            'Content-Type': register.contentType,
        }
    ).end(register.metrics());
});
metricsServer.listen(7133, '0.0.0.0');

['SIGTERM', 'SIGINT'].forEach(code => {
    process.once(code, function (code) {
        console.log(code + ' received...');
        console.log('Loop::stop()');
        redirectServer.close();
        metricsServer.close();
    });
});

console.log('Loop::run()');
