<?php

namespace LaraGram\Surge\Swoole;

use LaraGram\Surge\Contracts\DispatchesCoroutines;
use Swoole\Coroutine;
use Swoole\Coroutine\WaitGroup;
use function Swoole\Coroutine;

class SwooleCoroutineDispatcher implements DispatchesCoroutines
{
    public function __construct(protected bool $withinCoroutineContext)
    {
    }

    /**
     * Concurrently resolve the given callbacks via coroutines, returning the results.
     */
    public function resolve(array $coroutines, int $waitSeconds = -1): array
    {
        $results = [];

        $callback = function () use (&$results, $coroutines, $waitSeconds) {
            $waitGroup = new WaitGroup;

            foreach ($coroutines as $key => $callback) {
                Coroutine::create(function () use ($key, $callback, $waitGroup, &$results) {
                    $waitGroup->add();

                    $results[$key] = $callback();

                    $waitGroup->done();
                });
            }

            $waitGroup->wait($waitSeconds);
        };

        if (! $this->withinCoroutineContext) {
            Coroutine\run($callback);
        } else {
            $callback();
        }

        return $results;
    }
}
