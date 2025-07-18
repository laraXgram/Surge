<?php

namespace LaraGram\Surge\Facades;

use LaraGram\Support\Facades\Facade;

/**
 * @method static \LaraGram\Surge\Swoole\InvokeTickCallable tick(string $key, callable $callback, int $seconds = 1, bool $immediate = true)
 * @method static \Swoole\Table table(string $name)
 * @method static array concurrently(array $tasks, int $waitMilliseconds = 3000)
 *
 * @see \LaraGram\Surge\Octane
 */
class Surge extends Facade
{
    protected static function getFacadeAccessor()
    {
        return 'surge';
    }
}