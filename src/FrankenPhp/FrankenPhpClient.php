<?php

namespace LaraGram\Surge\FrankenPhp;

use LaraGram\Foundation\Application;
use LaraGram\Http\Request;
use LaraGram\Surge\Contracts\Client;
use LaraGram\Surge\Surge;
use LaraGram\Surge\SurgeResponse;
use LaraGram\Surge\RequestContext;
use LaraGram\Http\BaseResponse as Response;
use Throwable;

class FrankenPhpClient implements Client
{
    /**
     * Marshal the given request context into an Illuminate request.
     */
    public function marshalRequest(RequestContext $context): array
    {
        return [
            Request::capture(),
            $context,
        ];
    }

    /**
     * Send the response to the server.
     */
    public function respond(RequestContext $context, SurgeResponse $surgeResponse): void
    {
        $surgeResponse->response->send();
    }

    /**
     * Send an error message to the server.
     */
    public function error(Throwable $e, Application $app, Request $request, RequestContext $context): void
    {
        $response = new Response(
            Surge::formatExceptionForClient($e, $app->make('config')->get('app.debug')),
            500,
            [
                'Status' => '500 Internal Server Error',
                'Content-Type' => 'text/plain',
            ],
        );

        $response->send();
    }
}
