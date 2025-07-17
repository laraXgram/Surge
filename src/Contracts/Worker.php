<?php

namespace LaraGram\Surge\Contracts;

use LaraGram\Request\Request;
use LaraGram\Surge\RequestContext;

interface Worker
{
    /**
     * Boot / initialize the Surge worker.
     */
    public function boot(): void;

    /**
     * Handle an incoming request and send the response to the client.
     */
    public function handle(Request $request, RequestContext $context): void;

    /**
     * Handle an incoming task.
     *
     * @param  mixed  $data
     * @return mixed
     */
    public function handleTask($data);

    /**
     * Terminate the worker.
     */
    public function terminate(): void;
}
