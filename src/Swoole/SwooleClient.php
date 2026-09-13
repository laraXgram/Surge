<?php

namespace LaraGram\Surge\Swoole;

use DateTime;
use LaraGram\Foundation\Application;
use LaraGram\Http\BaseResponse as Response;
use LaraGram\Http\BinaryFileResponse;
use LaraGram\Http\Request;
use LaraGram\Http\StreamedResponse;
use LaraGram\Request\Request as BotRequest;
use LaraGram\Request\Response as BotResponse;
use LaraGram\Surge\Contracts\Client;
use LaraGram\Surge\Contracts\ServesStaticFiles;
use LaraGram\Surge\MimeType;
use LaraGram\Surge\RequestContext;
use LaraGram\Surge\Surge;
use LaraGram\Surge\SurgeResponse;
use ReflectionClass;
use Swoole\Http\Response as SwooleResponse;
use Throwable;

class SwooleClient implements Client, ServesStaticFiles
{
    const STATUS_CODE_REASONS = [
        419 => 'Page Expired',
        425 => 'Too Early',
        431 => 'Request Header Fields Too Large',                             // RFC6585
        451 => 'Unavailable For Legal Reasons',                               // RFC7725
    ];

    public function __construct(protected int $chunkSize = 1048576)
    {
    }

    /**
     * Marshal the given request context into a LaraGram request.
     *
     * Telegram webhook updates become bot requests (handled by the bot kernel and
     * the listeners); every other request becomes an HTTP request (handled by the
     * HTTP kernel and the router).
     */
    public function marshalRequest(RequestContext $context): array
    {
        return [
            $this->isBotUpdate($context->swooleRequest)
                ? (new Actions\ConvertSwooleRequestToLaraGramRequest)($context->swooleRequest, PHP_SAPI)
                : (new Actions\ConvertSwooleRequestToLaraGramHttpRequest)($context->swooleRequest, PHP_SAPI),
            $context,
        ];
    }

    /**
     * Marshal a Telegram webhook update into a serializable task payload.
     *
     * Returns the argv-like array consumed by Request::createFromBase() when the
     * request is a Telegram update, or null for any other request. The payload only
     * holds scalars, so it can be shipped to a Swoole task worker.
     *
     * @param  \Swoole\Http\Request  $swooleRequest
     */
    public function marshalBotUpdate($swooleRequest): ?array
    {
        if (! $this->isBotUpdate($swooleRequest)) {
            return null;
        }

        return (new Actions\ConvertSwooleRequestToLaraGramRequest)->toArgv($swooleRequest, PHP_SAPI);
    }

    /**
     * Determine if the given Swoole request carries a Telegram webhook update.
     *
     * @param  \Swoole\Http\Request  $swooleRequest
     */
    public function isBotUpdate($swooleRequest): bool
    {
        if (strtoupper($swooleRequest->server['request_method'] ?? 'GET') !== 'POST') {
            return false;
        }

        $content = $swooleRequest->rawContent();

        if (! is_string($content) || $content === '' || $content[0] !== '{') {
            return false;
        }

        $decoded = json_decode($content, true);

        return is_array($decoded) && array_key_exists('update_id', $decoded);
    }

    /**
     * Determine if the request can be served as a static file.
     */
    public function canServeRequestAsStaticFile(Request $request, RequestContext $context): bool
    {
        $surgeConfig = $context->surgeConfig ?? [];

        if (array_key_exists('serve_static_files', $surgeConfig) &&
            ! $surgeConfig['serve_static_files']) {
            return false;
        }

        if (! ($context->publicPath ?? false) ||
            $request->path() === '/') {
            return false;
        }

        $publicPath = $context->publicPath;

        $pathToFile = realpath($publicPath.'/'.$request->path());

        if ($pathToFile !== false && $this->isValidFileWithinSymlink($request, $publicPath, $pathToFile)) {
            $pathToFile = $publicPath.'/'.$request->path();
        }

        return $this->fileIsServable(
            $publicPath,
            (string) $pathToFile,
        );
    }

    /**
     * Determine if the request is for a valid static file within a symlink.
     */
    private function isValidFileWithinSymlink(Request $request, string $publicPath, string $pathToFile): bool
    {
        $pathAfterSymlink = $this->pathAfterSymlink($publicPath, $request->path());

        return $pathAfterSymlink && str_ends_with($pathToFile, $pathAfterSymlink);
    }

    /**
     * If the given public file is within a symlinked directory, return the path after the symlink.
     *
     * @return string|bool
     */
    private function pathAfterSymlink(string $publicPath, string $path)
    {
        $directories = explode('/', $path);

        while ($directory = array_shift($directories)) {
            $publicPath .= '/'.$directory;

            if (is_link($publicPath)) {
                return implode('/', $directories);
            }
        }

        return false;
    }

    /**
     * Determine if the given file is servable.
     */
    protected function fileIsServable(string $publicPath, string $pathToFile): bool
    {
        return $pathToFile &&
               ! in_array(pathinfo($pathToFile, PATHINFO_EXTENSION), ['php', 'htaccess', 'config']) &&
               str_starts_with($pathToFile, $publicPath) &&
               is_file($pathToFile);
    }

    /**
     * Serve the static file that was requested.
     */
    public function serveStaticFile(Request $request, RequestContext $context): void
    {
        $swooleResponse = $context->swooleResponse;

        $publicPath = $context->publicPath;
        $surgeConfig = $context->surgeConfig ?? [];

        if (! empty($surgeConfig['static_file_headers'])) {
            $formatHeaders = config('surge.swoole.format_headers', true);

            foreach ($surgeConfig['static_file_headers'] as $pattern => $headers) {
                if ($request->is($pattern)) {
                    foreach ($headers as $name => $value) {
                        $swooleResponse->header($name, $value, $formatHeaders);
                    }
                }
            }
        }

        $swooleResponse->status(200);
        $swooleResponse->header('Content-Type', MimeType::get(pathinfo($request->path(), PATHINFO_EXTENSION)));
        $swooleResponse->sendfile(realpath($publicPath.'/'.$request->path()));
    }

    /**
     * Send the response to the server.
     */
    public function respond(RequestContext $context, SurgeResponse $surgeResponse): void
    {
        if ($surgeResponse->response instanceof BotResponse) {
            $this->sendBotResponse($surgeResponse, $context->swooleResponse);

            return;
        }

        $this->sendResponseHeaders($surgeResponse->response, $context->swooleResponse);
        $this->sendResponseContent($surgeResponse, $context->swooleResponse);
    }

    /**
     * Send the headers from the LaraGram response to the Swoole response.
     *
     * @param  \Swoole\Http\Response  $response
     */
    public function sendResponseHeaders(Response $response, SwooleResponse $swooleResponse): void
    {
        if (! $response->headers->has('Date')) {
            $response->setDate(DateTime::createFromFormat('U', time()));
        }

        $headers = $response->headers->allPreserveCase();

        if (isset($headers['Set-Cookie'])) {
            unset($headers['Set-Cookie']);
        }

        $formatHeaders = config('surge.swoole.format_headers', true);

        foreach ($headers as $name => $values) {
            foreach ($values as $value) {
                $swooleResponse->header($name, $value, $formatHeaders);
            }
        }

        if (! is_null($reason = $this->getReasonFromStatusCode($response->getStatusCode()))) {
            $swooleResponse->status($response->getStatusCode(), $reason);
        } else {
            $swooleResponse->status($response->getStatusCode());
        }

        foreach ($response->headers->getCookies() as $cookie) {
            $shouldDelete = (string) $cookie->getValue() === '';

            $method = $cookie->isRaw() ? 'rawcookie' : 'cookie';

            $params = [
                $cookie->getName(),
                $shouldDelete ? 'deleted' : $cookie->getValue(),
                $cookie->getExpiresTime(),
                $cookie->getPath(),
                $cookie->getDomain() ?? '',
                $cookie->isSecure(),
                $cookie->isHttpOnly(),
                $cookie->getSameSite() ?? '',
            ];

            if (extension_loaded('swoole') && SWOOLE_VERSION_ID >= 60000) {
                $params[] = '';
                $params[] = $cookie->isPartitioned();
            }

            $swooleResponse->$method(...$params);
        }
    }

    /**
     * Send the content from the LaraGram response to the Swoole response.
     *
     * @param  \LaraGram\Surge\SurgeResponse  $response
     * @param  \Swoole\Http\Response  $response
     */
    protected function sendResponseContent(SurgeResponse $surgeResponse, SwooleResponse $swooleResponse): void
    {
        if ($surgeResponse->response instanceof BinaryFileResponse) {
            $swooleResponse->sendfile(
                $surgeResponse->response->getFile()->getPathname(),
                (new ReflectionClass(BinaryFileResponse::class))->getProperty('offset')->getValue($surgeResponse->response)
            );

            return;
        }

        if ($surgeResponse->outputBuffer) {
            $swooleResponse->write($surgeResponse->outputBuffer);
        }

        if ($surgeResponse->response instanceof StreamedResponse) {
            // connection_aborted() is always 0 under Swoole, so long-lived streams
            // (e.g. SSE) can ask the sandbox whether a write to the client failed...
            $disconnected = false;

            app()->instance('surge.disconnected', function () use (&$disconnected): bool {
                return $disconnected;
            });

            ob_start(function ($data) use ($swooleResponse, &$disconnected) {
                if (strlen($data) > 0 && $swooleResponse->write($data) === false) {
                    $disconnected = true;
                }

                return '';
            }, 1);

            $surgeResponse->response->sendContent();

            ob_end_clean();

            $swooleResponse->end();

            return;
        }

        $this->sendContent((string) $surgeResponse->response->getContent(), $swooleResponse);
    }

    /**
     * Send the response of a bot request (a webhook reply) to the Swoole response.
     *
     * Telegram only reads the webhook reply body, so a bot response is sent as-is with a 200 status.
     */
    protected function sendBotResponse(SurgeResponse $surgeResponse, SwooleResponse $swooleResponse): void
    {
        $swooleResponse->status(200);

        $content = (string) $surgeResponse->outputBuffer.$surgeResponse->response->getContent();

        if ($content !== '' && json_validate($content)) {
            $swooleResponse->header('Content-Type', 'application/json');
        }

        $this->sendContent($content, $swooleResponse);
    }

    /**
     * Send the given content, in chunks when it is larger than the chunk size.
     */
    protected function sendContent(string $content, SwooleResponse $swooleResponse): void
    {
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
     *
     * @param  \LaraGram\Http\Request|\LaraGram\Request\Request  $request
     */
    public function error(Throwable $e, Application $app, Request|BotRequest $request, RequestContext $context): void
    {
        $context->swooleResponse->header('Status', '500 Internal Server Error');
        $context->swooleResponse->header('Content-Type', 'text/plain');

        $context->swooleResponse->end(
            Surge::formatExceptionForClient($e, $app->make('config')->get('app.debug'))
        );
    }

    /**
     * Get the HTTP reason clause for non-standard status codes.
     */
    protected function getReasonFromStatusCode(int $code): ?string
    {
        if (array_key_exists($code, self::STATUS_CODE_REASONS)) {
            return self::STATUS_CODE_REASONS[$code];
        }

        return null;
    }
}
