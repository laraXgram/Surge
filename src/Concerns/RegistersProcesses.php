<?php

namespace LaraGram\Surge\Concerns;

trait RegistersProcesses
{
    /**
     * Register a long-lived background process to be launched with the server.
     *
     * The handler is an invokable class string resolved from the container and
     * called as ($application, $process, $server) inside its own process, with
     * its own freshly-booted application instance.
     *
     * Must be called before the server is started (e.g. from a service
     * provider's boot method) so the registration is written into the server
     * state file the runtime reads on boot.
     *
     * @param  string  $handler
     * @param  string|null  $name
     * @param  bool  $coroutine
     * @return void
     */
    public function process(string $handler, ?string $name = null, bool $coroutine = true)
    {
        app('config')->push('surge.processes', [
            'handler' => $handler,
            'name' => $name,
            'coroutine' => $coroutine,
        ]);
    }
}
