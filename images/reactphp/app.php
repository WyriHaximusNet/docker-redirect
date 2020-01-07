<?php

use Psr\Http\Message\ResponseInterface;
use React\EventLoop\Factory;
use Symfony\Component\Yaml\Yaml;
use Psr\Http\Message\ServerRequestInterface;
use React\Http\Response;
use React\Http\Server as HttpServer;

require 'vendor/autoload.php';

$yaml = Yaml::parseFile('/etc/redirect/config.yaml', Yaml::PARSE_OBJECT);

if ($yaml['buildin']['wwwToNonWww'] === true && $yaml['buildin']['nonWwwToWww'] === true) {
    fwrite(STDERR, 'wwwToNonWww and wwwToNonWww can\'t both be enabled at the same time!' . PHP_EOL);
    exit(1);
}

$loop = Factory::create();
$middleware = [];

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

$socket = new React\Socket\Server('0.0.0.0:1337', $loop);
$socket->on('error', static function (Throwable $throwable): void {
    echo $throwable, PHP_EOL;
});
$server->listen($socket);

$signalHandler = function () use (&$signalHandler, $socket, $loop) {
    $loop->removeSignal(SIGINT, $signalHandler);
    $socket->close();
};

$loop->addSignal(SIGINT, $signalHandler);

echo 'Loop::run()', PHP_EOL;
$loop->run();
echo 'Loop::stop()', PHP_EOL;
