<?php

namespace LaraGram\Surge\Swoole\Handlers;

use LaraGram\Surge\Swoole\SwooleExtension;

class OnManagerStart
{
    public function __construct(
        protected SwooleExtension $extension,
        protected string $appName,
        protected bool $shouldSetProcessName = true
    ) {
    }

    /**
     * Handle the "managerstart" Swoole event.
     *
     * @return void
     */
    public function __invoke()
    {
        if ($this->shouldSetProcessName) {
            $this->extension->setProcessName($this->appName, 'manager process');
        }
    }
}
