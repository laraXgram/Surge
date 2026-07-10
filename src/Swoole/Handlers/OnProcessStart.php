<?php

namespace LaraGram\Surge\Swoole\Handlers;

use LaraGram\Surge\ApplicationFactory;
use LaraGram\Surge\Stream;
use LaraGram\Surge\Swoole\SwooleExtension;
use Swoole\Http\Server;
use Swoole\Process;
use Throwable;

class OnProcessStart
{
    public function __construct(
        protected SwooleExtension $extension,
        protected string $basePath,
        protected array $serverState,
        protected string $handler,
        protected ?string $name = null,
        protected bool $shouldSetProcessName = true,
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
        try {
            if ($this->shouldSetProcessName) {
                $this->extension->setProcessName(
                    $this->serverState['appName'],
                    ($this->name ?: class_basename($this->handler)).' process',
                );
            }

            $app = (new ApplicationFactory($this->basePath))->createApplication([
                Server::class => $server,
                Process::class => $process,
            ]);

            $handler = $app->make($this->handler);

            $handler($app, $process, $server);
        } catch (Throwable $e) {
            Stream::shutdown($e);
        }
    }
}
