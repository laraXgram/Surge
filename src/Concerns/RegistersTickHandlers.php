<?php

namespace LaraGram\Surge\Concerns;

use LaraGram\Contracts\Debug\ExceptionHandler;
use LaraGram\Contracts\Events\Dispatcher;
use LaraGram\Support\Facades\Cache;
use LaraGram\Surge\Events\TickReceived;
use LaraGram\Surge\Swoole\InvokeTickCallable;

trait RegistersTickHandlers
{
    /**
     * Register a callback to be called every N seconds.
     *
     * @return \LaraGram\Surge\Swoole\InvokeTickCallable
     */
    public function tick(string $key, callable $callback, int $seconds = 1, bool $immediate = true)
    {
        $listener = new InvokeTickCallable(
            $key,
            $callback,
            $seconds,
            $immediate,
            Cache::store('surge'),
            app(ExceptionHandler::class)
        );

        app(Dispatcher::class)->listen(
            TickReceived::class,
            $listener
        );

        return $listener;
    }
}
