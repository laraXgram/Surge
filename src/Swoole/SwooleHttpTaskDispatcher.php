<?php

namespace LaraGram\Surge\Swoole;

use Closure;
use Exception;
use LaraGram\Http\Client\ConnectionException;
use LaraGram\Support\Facades\Crypt;
use LaraGram\Support\Facades\Http;
use LaraGram\Surge\Contracts\DispatchesTasks;
use LaraGram\Surge\Exceptions\TaskExceptionResult;
use LaraGram\Surge\Exceptions\TaskTimeoutException;
use LaraGram\Support\SerializableClosure\SerializableClosure;

class SwooleHttpTaskDispatcher implements DispatchesTasks
{
    public function __construct(
        protected string $host,
        protected string $port,
        protected DispatchesTasks $fallbackDispatcher
    ) {
    }

    /**
     * Concurrently resolve the given callbacks via background tasks, returning the results.
     *
     * Results will be keyed by their given keys - if a task did not finish, the tasks value will be "false".
     *
     *
     * @throws \LaraGram\Surge\Exceptions\TaskException
     * @throws \LaraGram\Surge\Exceptions\TaskTimeoutException
     */
    public function resolve(array $tasks, int $waitMilliseconds = 3000): array
    {
        $tasks = collect($tasks)->mapWithKeys(function ($task, $key) {
            return [$key => $task instanceof Closure
                            ? new SerializableClosure($task)
                            : $task, ];
        })->all();

        try {
            $response = Http::timeout(($waitMilliseconds / 1000) + 5)->post("http://{$this->host}:{$this->port}/surge/resolve-tasks", [
                'tasks' => Crypt::encryptString(serialize($tasks)),
                'wait' => $waitMilliseconds,
            ]);

            return match ($response->status()) {
                200 => unserialize($response),
                504 => throw TaskTimeoutException::after($waitMilliseconds),
                default => throw TaskExceptionResult::from(
                    new Exception('Invalid response from task server.'),
                )->getOriginal(),
            };
        } catch (ConnectionException) {
            return $this->fallbackDispatcher->resolve($tasks, $waitMilliseconds);
        }
    }

    /**
     * Concurrently dispatch the given callbacks via background tasks.
     */
    public function dispatch(array $tasks): void
    {
        $tasks = collect($tasks)->mapWithKeys(function ($task, $key) {
            return [$key => $task instanceof Closure
                            ? new SerializableClosure($task)
                            : $task, ];
        })->all();

        try {
            Http::post("http://{$this->host}:{$this->port}/surge/dispatch-tasks", [
                'tasks' => Crypt::encryptString(serialize($tasks)),
            ]);
        } catch (ConnectionException) {
            $this->fallbackDispatcher->dispatch($tasks);
        }
    }
}
