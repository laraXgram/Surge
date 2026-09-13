<?php

namespace LaraGram\Surge\FrankenPhp;

use LaraGram\Foundation\Application;
use LaraGram\Http\Request;
use LaraGram\Request\Request as BotRequest;
use LaraGram\Request\Response as BotResponse;
use LaraGram\Surge\Contracts\Client;
use LaraGram\Surge\Surge;
use LaraGram\Surge\SurgeResponse;
use LaraGram\Surge\RequestContext;
use LaraGram\Http\BaseResponse as Response;
use Throwable;

class FrankenPhpClient implements Client
{
    /**
     * Marshal the given request context into a LaraGram HTTP or bot request.
     */
    public function marshalRequest(RequestContext $context): array
    {
        return [
            is_string($content = $this->botUpdateContent())
                ? BotRequest::createFromBase([PHP_SAPI, $content, json_encode($_SERVER)])
                : Request::capture(),
            $context,
        ];
    }

    /**
     * Get the raw body of the current request if it carries a Telegram webhook update.
     */
    protected function botUpdateContent(): ?string
    {
        if (strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            return null;
        }

        $content = file_get_contents('php://input');

        if (! is_string($content) || $content === '' || $content[0] !== '{') {
            return null;
        }

        $decoded = json_decode($content, true);

        return is_array($decoded) && array_key_exists('update_id', $decoded) ? $content : null;
    }

    /**
     * Send the response to the server.
     */
    public function respond(RequestContext $context, SurgeResponse $surgeResponse): void
    {
        if ($surgeResponse->response instanceof BotResponse) {
            $content = $surgeResponse->outputBuffer.$surgeResponse->response->getContent();

            if (! headers_sent()) {
                http_response_code(200);

                if ($content !== '' && json_validate($content)) {
                    header('Content-Type: application/json');
                }
            }

            echo $content;

            $surgeResponse->response->send();

            return;
        }

        $surgeResponse->response->send();
    }

    /**
     * Send an error message to the server.
     */
    public function error(Throwable $e, Application $app, Request|BotRequest $request, RequestContext $context): void
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
