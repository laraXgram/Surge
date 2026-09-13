<?php

namespace LaraGram\Surge\Facades;

use LaraGram\Support\Facades\Facade;

/**
 * @method static \LaraGram\Surge\Swoole\InvokeTickCallable tick(string $key, callable $callback, int $seconds = 1, bool $immediate = true)
 * @method static \Swoole\Table table(string $name)
 * @method static \LaraGram\Http\BaseResponse invokeRoute(\LaraGram\Http\Request $request, string $method, string $uri)
 * @method static array concurrently(array $tasks, int $waitMilliseconds = 3000)
 * @method static bool hasRouteFor(string $method, string $uri)
 * @method static void route(string $method, string $uri, \Closure $callback)
 *
 * @see \LaraGram\Surge\Surge
 */
class Surge extends Facade
{
    protected static function getFacadeAccessor()
    {
        return 'surge';
    }
}