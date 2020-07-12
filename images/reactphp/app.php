<?php

use Psr\Http\Message\ResponseInterface;
use React\EventLoop\Factory;
use ReactInspector\Collector\Merger\CollectorMergerCollector;
use ReactInspector\EventLoop\LoopCollector;
use ReactInspector\EventLoop\LoopDecorator;
use ReactInspector\Http\Middleware\Printer\PrinterMiddleware;
use ReactInspector\HttpMiddleware\MiddlewareCollector;
use ReactInspector\MemoryUsage\MemoryUsageCollector;
use ReactInspector\Metrics;
use ReactInspector\Printer\Prometheus\PrometheusPrinter;
use ReactInspector\Stream\IOCollector;
use ReactInspector\Tag;
use ReactInspector\Tags;
use Symfony\Component\Yaml\Yaml;
use Psr\Http\Message\ServerRequestInterface;
use React\Http\Message\Response;
use React\Http\Server as HttpServer;
use WyriHaximus\React\Http\Middleware\Header;
use WyriHaximus\React\Http\Middleware\WithHeadersMiddleware;
use const WyriHaximus\FakePHPVersion\CURRENT;

require 'vendor/autoload.php';

$yaml = Yaml::parseFile('/etc/redirect/config.yaml', Yaml::PARSE_OBJECT);

if ($yaml['buildin']['wwwToNonWww'] === true && $yaml['buildin']['nonWwwToWww'] === true) {
    fwrite(STDERR, 'wwwToNonWww and wwwToNonWww can\'t both be enabled at the same time!' . PHP_EOL);
    exit(1);
}

$loop = new LoopDecorator(Factory::create());

$metrics = [];
$middleware = [];
$metricsMiddleware = [];

$extraHeaders = new WithHeadersMiddleware(
    new Header('Server', 'wyrihaximusnet/redirect (https://hub.docker.com/r/wyrihaximusnet/redirect)'),
    new Header('X-Powered-By', 'PHP/' . CURRENT),
);

$middleware[] = $extraHeaders;
$metricsMiddleware[] = $extraHeaders;
$middlewareCollectorRedirects = new MiddlewareCollector('redirects');
$middlewareCollectorMetrics = new MiddlewareCollector('metrics');
$middleware[] = $middlewareCollectorRedirects;
$metricsMiddleware[] = $middlewareCollectorMetrics;

$middleware[] = static function (ServerRequestInterface $request, callable $next) use (&$metrics): ResponseInterface {
    /** @var Tags $tags */
    $tags = $request->getAttribute(MiddlewareCollector::TAGS_ATTRIBUTE);
    $tags->add(new Tag('fromHost', $request->getUri()->getHost()));
    /** @var ResponseInterface $response */
    $response = $next($request);
    $tags->add(new Tag('toHost', parse_url($response->getHeaderLine('Location'))['host']));
    return $response;
};

$metricsMiddleware[] = new PrinterMiddleware(new PrometheusPrinter(), new Metrics(
    $loop,
    3,
    new LoopCollector($loop),
    new MemoryUsageCollector(),
    new IOCollector(),
    new CollectorMergerCollector(
        $middlewareCollectorRedirects,
        $middlewareCollectorMetrics
    )
));

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

$middleware[] = static fn (ServerRequestInterface $request): ResponseInterface => new Response(301,['Location' => $yaml['defaultFallbackTarget']]);

$server = new HttpServer($loop, ...$middleware);
$server->on('error', static function (Throwable $throwable): void {
    echo $throwable, PHP_EOL;
});

$socket = new React\Socket\Server('0.0.0.0:7132', $loop, ['backlog' => 511]);
$socket->on('error', static function (Throwable $throwable): void {
    echo $throwable, PHP_EOL;
});
$server->listen($socket);

$metricsServer = new HttpServer($loop, ...$metricsMiddleware);
$metricsServer->on('error', static function (Throwable $throwable): void {
    echo $throwable, PHP_EOL;
});

$metricsSocket = new React\Socket\Server('0.0.0.0:7133', $loop, ['backlog' => 511]);
$metricsSocket->on('error', static function (Throwable $throwable): void {
    echo $throwable, PHP_EOL;
});
$metricsServer->listen($metricsSocket);

$signalHandler = function () use (&$signalHandler, $socket, $metricsSocket, $loop) {
    echo 'Caught signal', PHP_EOL;
    $loop->removeSignal(SIGINT, $signalHandler);
    $loop->removeSignal(SIGTERM, $signalHandler);
    $socket->close();
    $metricsSocket->close();
    echo 'Closed and stopped everything', PHP_EOL;
    $loop->stop();
};

$loop->addSignal(SIGINT, $signalHandler);
$loop->addSignal(SIGTERM, $signalHandler);

echo 'Loop::run()', PHP_EOL;
$loop->run();
echo 'Loop::stop()', PHP_EOL;
