<?php

use LaraGram\Container\Container;
use LaraGram\Contracts\Debug\ExceptionHandler;
use LaraGram\Surge\ApplicationFactory;
use LaraGram\Surge\FrankenPhp\FrankenPhpClient;
use LaraGram\Surge\RequestContext;
use LaraGram\Surge\Stream;
use LaraGram\Surge\Worker;
use LaraGram\Console\Output\ConsoleOutput;
use LaraGram\Http\BaseResponse as Response;

if ((! ($_SERVER['FRANKENPHP_WORKER'] ?? false)) || ! function_exists('frankenphp_handle_request')) {
    echo 'FrankenPHP must be in worker mode to use this script.';

    exit(1);
}

ignore_user_abort(true);

$basePath = require __DIR__.'/bootstrap.php';

/*
|--------------------------------------------------------------------------
| Start The Surge Worker
|--------------------------------------------------------------------------
|
| Next we will start the Surge worker, which is a long running process to
| handle incoming requests to the application. This worker will be used
| by FrankenPHP to serve an entire LaraGram application at high speed.
|
*/

$frankenPhpClient = new FrankenPhpClient();

try {
    $worker = tap(new Worker(
        new ApplicationFactory($basePath), $frankenPhpClient
    ))->boot();
} catch (Throwable $e) {
    try {
        $container = Container::getInstance();

        if ($container && $container->bound(ExceptionHandler::class)) {
            $container->make(ExceptionHandler::class)
                ->renderForConsole(new ConsoleOutput, $e);
        } else {
            fwrite(STDERR, sprintf(
                "[surge bootstrap] %s: %s\n  in %s:%d\n%s\n",
                $e::class, $e->getMessage(), $e->getFile(), $e->getLine(), $e->getTraceAsString()
            ));
        }
    } catch (Throwable) {
        fwrite(STDERR, sprintf(
            "[surge bootstrap] %s: %s\n  in %s:%d\n%s\n",
            $e::class, $e->getMessage(), $e->getFile(), $e->getLine(), $e->getTraceAsString()
        ));
    }

    exit(1);
}

$requestCount = 0;
$debugMode = $_ENV['APP_DEBUG'] ?? $_SERVER['APP_DEBUG'] ?? 'false';
$maxRequests = $_ENV['MAX_REQUESTS'] ?? $_SERVER['MAX_REQUESTS'] ?? 1000;
$requestMaxExecutionTime = $_ENV['REQUEST_MAX_EXECUTION_TIME'] ?? $_SERVER['REQUEST_MAX_EXECUTION_TIME'] ?? null;

if (PHP_OS_FAMILY === 'Linux' && ! is_null($requestMaxExecutionTime)) {
    set_time_limit((int) $requestMaxExecutionTime);
}

try {
    $handleRequest = static function () use ($worker, $frankenPhpClient, $debugMode) {
        try {
            [$request, $context] = $frankenPhpClient->marshalRequest(new RequestContext());

            $worker->handle($request, $context);
        } catch (Throwable $e) {
            if ($worker) {
                report($e);
            }

            $response = new Response(
                $debugMode === 'true' ? (string) $e : 'Internal Server Error',
                500,
                [
                    'Status' => '500 Internal Server Error',
                    'Content-Type' => 'text/plain',
                ],
            );

            $response->send();

            Stream::shutdown($e);
        }
    };

    while ($requestCount < $maxRequests && @frankenphp_handle_request($handleRequest)) {
        $requestCount++;
    }
} finally {
    $worker?->terminate();

    gc_collect_cycles();
}
