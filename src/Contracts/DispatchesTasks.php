<?php

namespace LaraGram\Surge\Contracts;

interface DispatchesTasks
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
    public function resolve(array $tasks, int $waitMilliseconds = 3000): array;

    /**
     * Concurrently dispatch the given callbacks via background tasks.
     */
    public function dispatch(array $tasks): void;
}
