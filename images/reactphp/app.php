<?php

declare(strict_types=1);

use Psr\Http\Message\ResponseInterface;
use React\EventLoop\Loop;
use React\Http\Message\Uri;
use ReactInspector\GlobalState;
use ReactInspector\HttpMiddleware\Labels;
use ReactInspector\HttpMiddleware\MiddlewareCollector;
use ReactInspector\MemoryUsage\MemoryUsage;
use Symfony\Component\Yaml\Yaml;
use Psr\Http\Message\ServerRequestInterface;
use React\Http\Message\Response;
use React\Http\HttpServer;
use WyriHaximus\FakePHPVersion\Versions;
use WyriHaximus\Metrics\Factory;
use WyriHaximus\Metrics\Label;
use WyriHaximus\Metrics\Printer\Prometheus;
use WyriHaximus\React\Http\Middleware\Header;
use WyriHaximus\React\Http\Middleware\WithHeadersMiddleware;

require 'vendor/autoload.php';

$yaml = Yaml::parseFile('/etc/redirect/config.yaml', Yaml::PARSE_OBJECT);

if ($yaml['buildin']['wwwToNonWww'] === true && $yaml['buildin']['nonWwwToWww'] === true) {
    fwrite(STDERR, 'wwwToNonWww and wwwToNonWww can\'t both be enabled at the same time!' . PHP_EOL);
    exit(1);
}

$metrics = [];
$middleware = [];
$metricsMiddleware = [];

$extraHeaders = new WithHeadersMiddleware(
    new Header('Server', 'wyrihaximusnet/redirect (https://github.com/wyrihaximusnet/docker-redirect)'),
    new Header('X-Powered-By', 'PHP/' . Versions::CURRENT),
);

$registry = Factory::create();
GlobalState::register($registry);
$memoryCollector = new MemoryUsage();
$memoryCollector->register($registry);

$middleware[] = $extraHeaders;
$metricsMiddleware[] = $extraHeaders;
$middleware[] = new MiddlewareCollector(\ReactInspector\HttpMiddleware\Metrics::create($registry, new Label('vhost', 'redirects')));
$metricsMiddleware[] = new MiddlewareCollector(\ReactInspector\HttpMiddleware\Metrics::create($registry, new Label('vhost', 'metrics')));

$middleware[] = static function (ServerRequestInterface $request, callable $next) use (&$metrics): ResponseInterface {
    /** @var Labels $labels */
    $labels = $request->getAttribute(MiddlewareCollector::LABELS_ATTRIBUTE);
    $labels->add(new Label('fromHost', $request->getUri()->getHost()));
    /** @var ResponseInterface $response */
    $response = $next($request);
    $labels->add(new Label('toHost', parse_url($response->getHeaderLine('Location'))['host']));
    return $response;
};

$metricsMiddleware[] = static fn (): ResponseInterface => new Response(200, ['Content-Type' => 'text/plain'], $registry->print(new Prometheus()));;

if (isset($yaml['hosts']) && is_array($yaml['hosts']) && count($yaml['hosts']) > 0) {
    $middleware[] = static function (ServerRequestInterface $request, callable $next) use ($yaml): ResponseInterface {
        $host = $request->getUri()->getHost();

        if (array_key_exists($host, $yaml['hosts'])) {
            $uri = $request->getUri()->withHost($yaml['hosts'][$host]);

            if (array_key_exists('enforceHttps', $yaml) && $yaml['enforceHttps'] === true) {
                $uri = $uri->withScheme('https');
            }

            return new Response(
                301,
                [
                    'Location' => (string) $uri,
                ]
            );
        }

        return $next($request);
    };
}

if ($yaml['buildin']['wwwToNonWww'] === true) {
    $middleware[] = static function (ServerRequestInterface $request, callable $next) use ($yaml): ResponseInterface {
        $host = $request->getUri()->getHost();

        if (empty($host)) {
            return $next($request);
        }

        if (filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 | FILTER_FLAG_IPV6) !== false) {
            return $next($request);
        }

        if (stripos($host, 'www.') === 0) {
            $uri = $request->getUri()->withHost(
                substr(
                    $host,
                    4,
                )
            );

            if (array_key_exists('enforceHttps', $yaml) && $yaml['enforceHttps'] === true) {
                $uri = $uri->withScheme('https');
            }

            return new Response(
                301,
                [
                    'Location' => (string) $uri,
                ]
            );
        }

        return $next($request);
    };
}

if ($yaml['buildin']['nonWwwToWww'] === true) {
    $middleware[] = static function (ServerRequestInterface $request, callable $next) use ($yaml): ResponseInterface {
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

        $uri = $request->getUri()->withHost(
            'www.' . $host,
        );

        if (array_key_exists('enforceHttps', $yaml) && $yaml['enforceHttps'] === true) {
            $uri = $uri->withScheme('https');
        }

        return new Response(
            301,
            [
                'Location' => (string) $uri,
            ]
        );
    };
}


if (array_key_exists('enforceHttps', $yaml) && $yaml['enforceHttps'] === true) {
    $yaml['defaultFallbackTarget'] = (string) (new Uri($yaml['defaultFallbackTarget']))->withScheme('https');
}
$middleware[] = static fn (ServerRequestInterface $request): ResponseInterface => new Response(301,['Location' => $yaml['defaultFallbackTarget']]);

$server = new HttpServer(...$middleware);
$server->on('error', static function (Throwable $throwable): void {
    echo $throwable, PHP_EOL;
});

$socket = new React\Socket\SocketServer('0.0.0.0:7132', ['backlog' => 511]);
$socket->on('error', static function (Throwable $throwable): void {
    echo $throwable, PHP_EOL;
});
$server->listen($socket);

$metricsServer = new HttpServer(...$metricsMiddleware);
$metricsServer->on('error', static function (Throwable $throwable): void {
    echo $throwable, PHP_EOL;
});

$metricsSocket = new React\Socket\SocketServer('0.0.0.0:7133', ['backlog' => 511]);
$metricsSocket->on('error', static function (Throwable $throwable): void {
    echo $throwable, PHP_EOL;
});
$metricsServer->listen($metricsSocket);

$signalHandler = function () use (&$signalHandler, $socket, $metricsSocket, $memoryCollector, $registry) {
    echo 'Caught signal', PHP_EOL;
    Loop::removeSignal(SIGINT, $signalHandler);
    Loop::removeSignal(SIGTERM, $signalHandler);
    $socket->close();
    $metricsSocket->close();
    $memoryCollector->unregister($registry);
    echo 'Closed and stopped everything', PHP_EOL;
    die();
};

Loop::addSignal(SIGINT, $signalHandler);
Loop::addSignal(SIGTERM, $signalHandler);
