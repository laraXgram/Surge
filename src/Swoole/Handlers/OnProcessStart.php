<?php

namespace LaraGram\Surge\Swoole\Handlers;

use LaraGram\Surge\ApplicationFactory;
use LaraGram\Surge\Stream;
use LaraGram\Surge\Swoole\SwooleExtension;
use LaraGram\Surge\Swoole\WorkerState;
use Swoole\Coroutine;
use Swoole\Http\Server;
use Swoole\Process;
use Throwable;

class OnProcessStart
{
    public function __construct(
        protected SwooleExtension $extension,
        protected string $basePath,
        protected array $serverState,
        protected WorkerState $workerState,
        protected string $handler,
        protected ?string $name = null,
        protected bool $shouldSetProcessName = true,
        protected int $minimumLifetime = 5,
    ) {
    }

    /**
     * Handle the start of a Surge-managed background process.
     *
     * Boots a dedicated application instance (once for the life of the process)
     * and hands control to the registered invokable handler. The handler is
     * expected to be long-lived; Swoole supervises and restarts it if it exits.
     */
    public function __invoke(Server $server, Process $process): void
    {
        $startedAt = microtime(true);

        $this->workerState->server = $server;
        $this->workerState->workerPid = posix_getpid();

        try {
            if ($this->shouldSetProcessName) {
                $this->extension->setProcessName(
                    $this->serverState['appName'],
                    ($this->name ?: class_basename($this->handler)).' process',
                );
            }

            $app = (new ApplicationFactory($this->basePath))->createApplication([
                'surge.cacheTable' => $this->workerState->cacheTable,
                Server::class => $server,
                Process::class => $process,
                WorkerState::class => $this->workerState,
            ]);

            $handler = $app->make($this->handler);

            $handler($app, $process, $server);
        } catch (Throwable $e) {
            Stream::shutdown($e);
        } finally {
            $this->delayRestart($startedAt);
        }
    }

    /**
     * Keep a handler that exits right away from being restarted by Swoole in a tight loop.
     */
    protected function delayRestart(float $startedAt): void
    {
        $remaining = $this->minimumLifetime - (microtime(true) - $startedAt);

        if ($remaining <= 0) {
            return;
        }

        Coroutine::getCid() > 0
            ? Coroutine::sleep($remaining)
            : usleep((int) ($remaining * 1_000_000));
    }
}
