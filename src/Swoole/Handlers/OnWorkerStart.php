<?php

namespace LaraGram\Surge\Swoole\Handlers;

use LaraGram\Support\Str;
use LaraGram\Surge\ApplicationFactory;
use LaraGram\Surge\Stream;
use LaraGram\Surge\Swoole\SwooleClient;
use LaraGram\Surge\Swoole\SwooleExtension;
use LaraGram\Surge\Swoole\WorkerState;
use LaraGram\Surge\Worker;
use Swoole\Http\Server;
use Throwable;

class OnWorkerStart
{
    public function __construct(
        protected SwooleExtension $extension,
        protected $basePath,
        protected array $serverState,
        protected WorkerState $workerState,
        protected bool $shouldSetProcessName = true
    ) {
    }

    /**
     * Handle the "workerstart" Swoole event.
     *
     * @param  \Swoole\Http\Server  $server
     * @return void
     */
    public function __invoke($server, int $workerId)
    {
        $this->clearOpcodeCache();

        $this->workerState->server = $server;
        $this->workerState->workerId = $workerId;
        $this->workerState->workerPid = posix_getpid();
        $this->workerState->worker = $this->bootWorker($server);

        $this->dispatchServerTickTaskEverySecond($server);
        $this->streamRequestsToConsole($server);

        if ($this->shouldSetProcessName) {
            $isTaskWorker = $workerId >= $server->setting['worker_num'];

            $this->extension->setProcessName(
                $this->serverState['appName'],
                $isTaskWorker ? 'task worker process' : 'worker process',
            );
        }
    }

    /**
     * Boot the Surge worker and application.
     *
     * @param  \Swoole\Http\Server  $server
     * @return \LaraGram\Surge\Worker|null
     */
    protected function bootWorker($server)
    {
        try {
            return tap(new Worker(
                new ApplicationFactory($this->basePath),
                $this->workerState->client = new SwooleClient
            ))->boot([
                'surge.cacheTable' => $this->workerState->cacheTable,
                Server::class => $server,
                WorkerState::class => $this->workerState,
            ]);
        } catch (Throwable $e) {
            Stream::shutdown($e);

            $server->shutdown();
        }
    }

    /**
     * Start the Surge server tick to dispatch the tick task every second.
     *
     * @param  \Swoole\Http\Server  $server
     * @return void
     */
    protected function dispatchServerTickTaskEverySecond($server)
    {
        // ...
    }

    /**
     * Register the request handled listener that will output request information per request.
     *
     * @param  \Swoole\Http\Server  $server
     * @return void
     */
    protected function streamRequestsToConsole($server)
    {
        $this->workerState->worker->onRequestHandled(function ($request, $response, $sandbox) {
            if (! $sandbox->environment('local', 'testing')) {
                return;
            }

            $pattern = $request->message->text
                ?? $request->message->caption
                ?? $request->callback_query->data
                ?? $request->inline_query->query
                ?? '*';

            $pattern = Str::limit($pattern, 8, '...');

            Stream::request(
                $request->method(),
                $pattern,
                (microtime(true) - $this->workerState->lastRequestTime) * 1000,
            );
        });
    }

    /**
     * Clear the APCu and Opcache caches.
     *
     * @return void
     */
    protected function clearOpcodeCache()
    {
        foreach (['apcu_clear_cache', 'opcache_reset'] as $function) {
            if (function_exists($function)) {
                $function();
            }
        }
    }
}
