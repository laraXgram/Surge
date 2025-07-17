<?php

namespace LaraGram\Surge;

use LaraGram\Surge\Contracts\DispatchesTasks;
use LaraGram\Surge\Exceptions\TaskExceptionResult;
use Throwable;

class SequentialTaskDispatcher implements DispatchesTasks
{
    /**
     * Concurrently resolve the given callbacks via background tasks, returning the results.
     *
     * Results will be keyed by their given keys - if a task did not finish, the tasks value will be "false".
     *
     *
     * @throws \LaraGram\Surge\Exceptions\TaskException
     * @throws \LaraGram\Surge\Exceptions\TaskTimeoutException
     */
    public function resolve(array $tasks, int $waitMilliseconds = 1): array
    {
        return collect($tasks)->mapWithKeys(
            fn ($task, $key) => [$key => (function () use ($task) {
                try {
                    return $task();
                } catch (Throwable $e) {
                    report($e);

                    return TaskExceptionResult::from($e);
                }
            })()]
        )->each(function ($result) {
            if ($result instanceof TaskExceptionResult) {
                throw $result->getOriginal();
            }
        })->all();
    }

    /**
     * Concurrently dispatch the given callbacks via background tasks.
     */
    public function dispatch(array $tasks): void
    {
        try {
            $this->resolve($tasks);
        } catch (Throwable) {
            // ..
        }
    }
}
