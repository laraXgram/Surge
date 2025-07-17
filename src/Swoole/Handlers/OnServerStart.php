<?php

namespace LaraGram\Surge\Swoole\Handlers;

use LaraGram\Surge\Swoole\Actions\EnsureRequestsDontExceedMaxExecutionTime;
use LaraGram\Surge\Swoole\ServerStateFile;
use LaraGram\Surge\Swoole\SwooleExtension;
use Swoole\Timer;

class OnServerStart
{
    public function __construct(
        protected ServerStateFile $serverStateFile,
        protected SwooleExtension $extension,
        protected string $appName,
        protected int $maxExecutionTime,
        protected $timerTable,
        protected bool $shouldTick = true,
        protected bool $shouldSetProcessName = true
    ) {
    }

    /**
     * Handle the "start" Swoole event.
     *
     * @param  \Swoole\Http\Server  $server
     * @return void
     */
    public function __invoke($server)
    {
        $this->serverStateFile->writeProcessIds(
            $server->master_pid,
            $server->manager_pid
        );

        if ($this->shouldSetProcessName) {
            $this->extension->setProcessName($this->appName, 'master process');
        }

        if ($this->shouldTick) {
            Timer::tick(1000, function () use ($server) {
                $server->task('surge-tick');
            });
        }

        if ($this->maxExecutionTime > 0) {
            Timer::tick(1000, function () use ($server) {
                (new EnsureRequestsDontExceedMaxExecutionTime(
                    $this->extension, $this->timerTable, $this->maxExecutionTime, $server
                ))();
            });
        }
    }
}
