<?php

namespace LaraGram\Surge\Concerns;

use Closure;
use LaraGram\Request\Request;
use LaraGram\Request\Response;

trait ProvidesListening
{
    /**
     * All of the registered Surge listens.
     *
     * @var array
     */
    protected $listens = [];

    /**
     * Register a Surge listen.
     */
    public function listen(string $method, string $pattern, Closure $callback): void
    {
        $this->listens[$method.$pattern] = $callback;
    }

    /**
     * Determine if a listen exists for the given method and Pattern.
     */
    public function hasListenFor(string $method, string $pattern): bool
    {
        return isset($this->listens[$method.$pattern]);
    }

    /**
     * Invoke the route for the given method and URI.
     */
    public function invokeListen(Request $request, string $method, string $pattern): Response
    {
        return call_user_func($this->listens[$method.$pattern], $request);
    }

    /**
     * Get the registered Surge routes.
     */
    public function getListens(): array
    {
        return $this->listens;
    }
}
