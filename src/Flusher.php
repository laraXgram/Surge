<?php

namespace LaraGram\Surge;

use Closure;
use LaraGram\Foundation\Application;
use LaraGram\Surge\Events\TaskReceived;
use LaraGram\Surge\Events\TaskTerminated;

/**
 * Resets application state between operations that are handled outside of the
 * worker loop, such as the updates handled by a background process (e.g. the
 * MTProto pump). It uses the same lifecycle events as Worker::handleTask().
 */
class Flusher
{
    use DispatchesEvents;

    /**
     * The per-request state listeners that do not need a request instance.
     *
     * @var array<int, class-string>
     */
    public const STATE_LISTENERS = [
        Listeners\FlushLocaleState::class,
        Listeners\FlushSessionState::class,
        Listeners\FlushAuthenticationState::class,
    ];

    /**
     * The configuration as it was before any operation ran.
     *
     * @var \LaraGram\Config\Repository
     */
    protected $config;

    /**
     * The number of operations currently running on the application itself.
     */
    protected int $running = 0;

    public function __construct(protected Application $app)
    {
        $this->config = clone $app['config'];
    }

    /**
     * Run the operation in a fresh sandbox of the application.
     *
     * The sandbox replaces the global container, so operations must not run
     * concurrently (e.g. in several coroutines) while using this method.
     *
     * @template TResult
     *
     * @param  \Closure(\LaraGram\Foundation\Application): TResult  $operation
     * @return TResult
     */
    public function sandbox(Closure $operation): mixed
    {
        CurrentApplication::set($sandbox = clone $this->app);

        try {
            $this->dispatchEvent($sandbox, $received = new TaskReceived($this->app, $sandbox, $operation));

            $this->resetState($received);

            $result = $operation($sandbox);

            $this->dispatchEvent($sandbox, new TaskTerminated($this->app, $sandbox, $operation, $result));

            return $result;
        } finally {
            $sandbox->flush();

            unset($sandbox);

            CurrentApplication::set($this->app);
        }
    }

    /**
     * Mark the start of an operation that runs on the application itself.
     */
    public function begin(): void
    {
        $this->running++;
    }

    /**
     * Mark the end of an operation, resetting the state once no operation is running.
     */
    public function flush(): void
    {
        $this->running = max(0, $this->running - 1);

        if ($this->running > 0) {
            return;
        }

        $this->app->instance('config', clone $this->config);

        $this->dispatchEvent($this->app, new TaskTerminated($this->app, $this->app, null, null));

        $this->resetState(new TaskReceived($this->app, $this->app, null));
    }

    /**
     * Run the per-request state listeners for the given operation.
     */
    protected function resetState(TaskReceived $event): void
    {
        foreach (static::STATE_LISTENERS as $listener) {
            $event->sandbox->make($listener)->handle($event);
        }
    }
}
