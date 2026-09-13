<?php

namespace LaraGram\Surge\RoadRunner;

use Generator;
use LaraGram\Foundation\Application;
use LaraGram\Http\Request;
use LaraGram\Surge\Contracts\Client;
use LaraGram\Surge\Contracts\StoppableClient;
use LaraGram\Surge\MarshalsPsr7RequestsAndResponses;
use LaraGram\Surge\Surge;
use LaraGram\Surge\SurgeResponse;
use LaraGram\Surge\RequestContext;
use ReflectionFunction;
use Spiral\RoadRunner\Http\PSR7Worker;
use LaraGram\Http\BinaryFileResponse;
use LaraGram\Http\StreamedResponse;
use Throwable;

class RoadRunnerClient implements Client, StoppableClient
{
    use MarshalsPsr7RequestsAndResponses;

    public function __construct(protected PSR7Worker $client)
    {
    }

    /**
     * Marshal the given request context into an Illuminate request.
     */
    public function marshalRequest(RequestContext $context): array
    {
        return [
            $this->toHttpFoundationRequest($context->psr7Request),
            $context,
        ];
    }

    /**
     * Send the response to the server.
     */
    public function respond(RequestContext $context, SurgeResponse $surgeResponse): void
    {
        if ($surgeResponse->outputBuffer &&
            ! $surgeResponse->response instanceof StreamedResponse &&
            ! $surgeResponse->response instanceof BinaryFileResponse) {
            $surgeResponse->response->setContent(
                $surgeResponse->outputBuffer.$surgeResponse->response->getContent()
            );
        }

        if (($surgeResponse->response instanceof StreamedResponse) &&
            ! is_null($responseCallback = static::resolveStreamResponseCallback($surgeResponse->response))) {
            $this->client->getHttpWorker()->respond(
                $surgeResponse->response->getStatusCode(),
                $responseCallback(),
                $this->toPsr7Response($surgeResponse->response)->getHeaders(),
            );

            return;
        }

        $this->client->respond($this->toPsr7Response($surgeResponse->response));
    }

    /**
     * Resolve the stream response callback from the given response.
     *
     * @param  \LaraGram\Http\StreamedResponse  $response
     * @return \Closure|null
     */
    public static function resolveStreamResponseCallback(StreamedResponse $response)
    {
        if (is_null($responseCallback = $response->getCallback())) {
            return null;
        }

        $reflection = new ReflectionFunction($responseCallback);

        if ($reflection->hasReturnType() === true &&
            in_array($reflection->getReturnType()?->getName(), [Generator::class, 'string'])) {
            return $responseCallback;
        }

        return null;
    }

    /**
     * Send an error message to the server.
     */
    public function error(Throwable $e, Application $app, Request $request, RequestContext $context): void
    {
        $this->client->getWorker()->error(Surge::formatExceptionForClient(
            $e,
            $app->make('config')->get('app.debug')
        ));
    }

    /**
     * Stop the underlying server / worker.
     */
    public function stop(): void
    {
        $this->client->getWorker()->stop();
    }
}
