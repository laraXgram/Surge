<?php

namespace LaraGram\Surge\Swoole;

use LaraGram\Foundation\Application;
use LaraGram\Request\Request;
use LaraGram\Surge\Contracts\Client;
use LaraGram\Surge\Surge;
use LaraGram\Surge\SurgeResponse;
use LaraGram\Surge\RequestContext;
use Swoole\Http\Response as SwooleResponse;
use Throwable;

class SwooleClient implements Client
{

    public function __construct(protected int $chunkSize = 1048576)
    {
    }

    /**
     * Marshal the given request context into an LaraGram request.
     *
     * Bot webhook updates are acknowledged and offloaded to a task worker before
     * this point, so the synchronous request path only ever serves web (browser)
     * traffic - which must run through the HTTP kernel, not the bot kernel.
     */
    public function marshalRequest(RequestContext $context): array
    {
        return [
            (new Actions\ConvertSwooleRequestToLaraGramHttpRequest())(
                $context->swooleRequest
            ),
            $context,
        ];
    }

    /**
     * Marshal a bot webhook update into a serializable task payload.
     *
     * Returns the argv-like array consumed by Request::createFromBase() when the
     * incoming request is a Telegram update (identified by "update_id"), or null
     * for regular web requests. The payload contains only scalars so it can be
     * shipped to a Swoole task worker for background (non-blocking) processing.
     *
     * @param  \Swoole\Http\Request  $swooleRequest
     * @return array|null
     */
    public function marshalBotUpdate($swooleRequest): ?array
    {
        $content = $swooleRequest->getContent();

        $decoded = json_decode($content, true);

        $isBotUpdate = json_last_error() === JSON_ERROR_NONE
            && is_array($decoded)
            && array_key_exists('update_id', $decoded);

        if (! $isBotUpdate) {
            return null;
        }

        return (new Actions\ConvertSwooleRequestToLaraGramRequest())->toArgv($swooleRequest, PHP_SAPI);
    }

    /**
     * Send the response to the server.
     */
    public function respond(RequestContext $context, SurgeResponse $surgeResponse): void
    {
        $this->sendResponseContent($surgeResponse, $context->swooleResponse);
    }

    /**
     * Send the content from the LaraGram response to the Swoole response.
     *
     * @param  \LaraGram\Surge\SurgeResponse  $response
     * @param  \Swoole\Http\Response  $response
     */
    protected function sendResponseContent(SurgeResponse $surgeResponse, SwooleResponse $swooleResponse): void
    {
        if ($surgeResponse->outputBuffer) {
            $swooleResponse->write($surgeResponse->outputBuffer);
        }

        $content = $surgeResponse->response->getContent();

        if (($length = strlen($content)) === 0) {
            $swooleResponse->end();

            return;
        }

        if ($length <= $this->chunkSize || config('surge.swoole.options.open_http2_protocol', false)) {
            $swooleResponse->end($content);

            return;
        }

        for ($offset = 0; $offset < $length; $offset += $this->chunkSize) {
            $swooleResponse->write(substr($content, $offset, $this->chunkSize));
        }

        $swooleResponse->end();
    }

    /**
     * Send an error message to the server.
     */
    public function error(Throwable $e, Application $app, $request, RequestContext $context): void
    {
        $context->swooleResponse->header('Status', '500 Internal Server Error');
        $context->swooleResponse->header('Content-Type', 'text/plain');

        $context->swooleResponse->end(
            Surge::formatExceptionForClient($e, $app->make('config')->get('app.debug'))
        );
    }
}
