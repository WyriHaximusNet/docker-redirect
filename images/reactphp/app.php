<?php

use Psr\Http\Message\ResponseInterface;
use React\EventLoop\Factory;
use Symfony\Component\Yaml\Yaml;
use Psr\Http\Message\ServerRequestInterface;
use React\Http\Response;
use React\Http\Server as HttpServer;
use WyriHaximus\React\Http\Middleware\WithHeadersMiddleware;
use const WyriHaximus\FakePHPVersion\CURRENT;

require 'vendor/autoload.php';

$yaml = Yaml::parseFile('/etc/redirect/config.yaml', Yaml::PARSE_OBJECT);

if ($yaml['buildin']['wwwToNonWww'] === true && $yaml['buildin']['nonWwwToWww'] === true) {
    fwrite(STDERR, 'wwwToNonWww and wwwToNonWww can\'t both be enabled at the same time!' . PHP_EOL);
    exit(1);
}

$loop = Factory::create();

$metrics = [];
$middleware = [];
$metricsMiddleware = [];

$extraHeaders = new WithHeadersMiddleware([
    'Server' => 'wyrihaximusnet/redirect (https://hub.docker.com/r/wyrihaximusnet/redirect)',
    'X-Powered-By' => 'PHP/' . CURRENT,
]);

$middleware[] = $extraHeaders;
$metricsMiddleware[] = $extraHeaders;

$middleware[] = static function (ServerRequestInterface $request, callable $next) use (&$metrics): ResponseInterface {
    $fromHost = $request->getUri()->getHost();
    $method = $request->getMethod();
    /** @var ResponseInterface $response */
    $response = $next($request);
    $toHost = parse_url($response->getHeaderLine('Location'))['host'];
    $metrics[$fromHost][$method][$toHost]++;
    return $response;
};

$metricsMiddleware[] = static function () use (&$metrics): ResponseInterface {
    $lines = [];
    $lines[] = '# HELP http_requests_total The amount of requests handler per host, HTTP method, and host the client was redirected to';
    $lines[] = '# TYPE http_requests_total counter';
    foreach ($metrics as $fromHost => $methods) {
        foreach ($methods as $method => $toHosts) {
            foreach ($toHosts as $toHost => $count) {
                $lines[] = 'http_requests_total{fromHost="' . $fromHost . '",method="' . $method . '",toHost="' . $toHost . '"} ' . $count . ' ' . \floor(\microtime(true) * 1000);
            }
        }
    }

    return new Response(200, [], \implode("\n", $lines) . "\n");
};


if (isset($yaml['hosts']) && is_array($yaml['hosts']) && count($yaml['hosts']) > 0) {
    $middleware[] = static function (ServerRequestInterface $request, callable $next) use ($yaml): ResponseInterface {
        $host = $request->getUri()->getHost();

        if (array_key_exists($host, $yaml['hosts'])) {
            return new Response(
                301,
                [
                    'Location' => (string) $request->getUri()->withHost($yaml['hosts'][$host]),
                ]
            );
        }

        return $next($request);
    };
}

if ($yaml['buildin']['wwwToNonWww'] === true) {
    $middleware[] = static function (ServerRequestInterface $request, callable $next): ResponseInterface {
        $host = $request->getUri()->getHost();

        if (empty($host)) {
            return $next($request);
        }

        if (filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 | FILTER_FLAG_IPV6) !== false) {
            return $next($request);
        }

        if (stripos($host, 'www.') === 0) {
            return new Response(
                301,
                [
                    'Location' => (string) $request->getUri()->withHost(
                        substr(
                            $host,
                            4,
                        )
                    ),
                ]
            );
        }

        return $next($request);
    };
}

if ($yaml['buildin']['nonWwwToWww'] === true) {
    $middleware[] = static function (ServerRequestInterface $request, callable $next): ResponseInterface {
        $host = $request->getUri()->getHost();

        if (empty($host)) {
            return $next($request);
        }

        if (filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 | FILTER_FLAG_IPV6) !== false) {
            return $next($request);
        }

        if (stripos($host, 'wwww.') === 0) {
            return $next($request);
        }

        return new Response(
            301,
            [
                'Location' => (string) $request->getUri()->withHost(
                    'www.' . $host,
                ),
            ]
        );
    };
}

$middleware[] = function (ServerRequestInterface $request) use ($yaml): ResponseInterface {
    return new Response(
        301,
        [
            'Location' => $yaml['defaultFallbackTarget'],
        ]
    );
};

$server = new HttpServer($middleware);
$server->on('error', static function (Throwable $throwable): void {
    echo $throwable, PHP_EOL;
});

$socket = new React\Socket\Server('0.0.0.0:7132', $loop, ['backlog' => 511]);
$socket->on('error', static function (Throwable $throwable): void {
    echo $throwable, PHP_EOL;
});
$server->listen($socket);

$metricsServer = new HttpServer($metricsMiddleware);
$metricsServer->on('error', static function (Throwable $throwable): void {
    echo $throwable, PHP_EOL;
});

$metricsSocket = new React\Socket\Server('0.0.0.0:7133', $loop, ['backlog' => 511]);
$metricsSocket->on('error', static function (Throwable $throwable): void {
    echo $throwable, PHP_EOL;
});
$metricsServer->listen($metricsSocket);

$signalHandler = function () use (&$signalHandler, $socket, $metricsSocket, $loop) {
    $loop->removeSignal(SIGINT, $signalHandler);
    $socket->close();
    $metricsSocket->close();
};

$loop->addSignal(SIGINT, $signalHandler);

echo 'Loop::run()', PHP_EOL;
$loop->run();
echo 'Loop::stop()', PHP_EOL;
