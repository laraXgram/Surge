<?php

namespace LaraGram\Surge\Contracts;

use LaraGram\Foundation\Application;
use LaraGram\Http\Request;
use LaraGram\Request\Request as BotRequest;
use LaraGram\Surge\RequestContext;
use LaraGram\Surge\SurgeResponse;
use Throwable;

interface Client
{
    /**
     * Marshal the given request context into a LaraGram request.
     *
     * The first element is a LaraGram\Http\Request for web requests, or a
     * LaraGram\Request\Request for Telegram webhook updates.
     */
    public function marshalRequest(RequestContext $context): array;

    /**
     * Send the response to the server.
     */
    public function respond(RequestContext $context, SurgeResponse $response): void;

    /**
     * Send an error message to the server.
     */
    public function error(Throwable $e, Application $app, Request|BotRequest $request, RequestContext $context): void;
}
