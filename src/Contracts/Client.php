<?php

namespace LaraGram\Surge\Contracts;

use LaraGram\Foundation\Application;
use LaraGram\Surge\SurgeResponse;
use LaraGram\Surge\RequestContext;
use Throwable;

interface Client
{
    /**
     * Marshal the given request context into an LaraGram request.
     */
    public function marshalRequest(RequestContext $context): array;

    /**
     * Send the response to the server.
     */
    public function respond(RequestContext $context, SurgeResponse $response): void;

    /**
     * Send an error message to the server.
     *
     * @param  \LaraGram\Request\Request|\LaraGram\Http\Request  $request
     */
    public function error(Throwable $e, Application $app, $request, RequestContext $context): void;
}
