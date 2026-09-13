<?php

namespace LaraGram\Surge;

use LaraGram\Surge\RoadRunner\Factory\ResponseFactory;
use LaraGram\Surge\RoadRunner\Factory\StreamFactory;
use LaraGram\Http\BaseRequest;
use LaraGram\Http\BaseResponse;
use LaraGram\Http\BinaryFileResponse;
use LaraGram\Http\Files\UploadedFile;
use LaraGram\Http\Request;
use LaraGram\Http\StreamedResponse;
use LaraGram\Request\Request as BotRequest;
use LaraGram\Request\Response as BotResponse;
use LaraGram\Http\Factory\ResponseFactoryInterface;
use LaraGram\Http\Factory\ResponseInterface;
use LaraGram\Http\Factory\ServerRequestInterface;
use LaraGram\Http\Factory\StreamFactoryInterface;
use LaraGram\Http\Factory\UploadedFileInterface;

/**
 * Converts between PSR-7 messages and LaraGram requests / responses.
 *
 * The PSR bridge that ships with the LaraGram core is built on LaraGram namespaced
 * message interfaces, which RoadRunner's PSR-7 messages do not implement, so the
 * conversion is performed here against the real PSR-7 interfaces.
 */
trait MarshalsPsr7RequestsAndResponses
{
    /**
     * The PSR-17 response factory.
     *
     * @var \LaraGram\Http\Factory\ResponseFactoryInterface|null
     */
    protected $psrResponseFactory;

    /**
     * The PSR-17 stream factory.
     *
     * @var \LaraGram\Http\Factory\StreamFactoryInterface|null
     */
    protected $psrStreamFactory;

    /**
     * Convert the given PSR-7 request to a LaraGram HTTP or bot request.
     */
    protected function toLaraGramRequest(ServerRequestInterface $request): Request|BotRequest
    {
        return $this->isBotUpdate($request)
            ? $this->toBotRequest($request)
            : $this->toHttpFoundationRequest($request);
    }

    /**
     * Convert the given PSR-7 request to an HttpFoundation request.
     */
    protected function toHttpFoundationRequest(ServerRequestInterface $request): Request
    {
        $server = [];
        $uri = $request->getUri();

        $server['SERVER_NAME'] = $uri->getHost();
        $server['SERVER_PORT'] = $uri->getPort() ?: ($uri->getScheme() === 'https' ? 443 : 80);
        $server['REQUEST_URI'] = $uri->getPath();
        $server['QUERY_STRING'] = $uri->getQuery();

        if ($server['QUERY_STRING'] !== '') {
            $server['REQUEST_URI'] .= '?'.$server['QUERY_STRING'];
        }

        if ($uri->getScheme() === 'https') {
            $server['HTTPS'] = 'on';
        }

        $server['REQUEST_METHOD'] = $request->getMethod();

        $server = array_replace($request->getServerParams(), $server);

        $parsedBody = $request->getParsedBody();

        $baseRequest = new BaseRequest(
            $request->getQueryParams(),
            is_array($parsedBody) ? $parsedBody : [],
            $request->getAttributes(),
            $request->getCookieParams(),
            $this->toLaraGramUploadedFiles($request->getUploadedFiles()),
            $server,
            $this->psr7RequestContent($request),
        );

        $baseRequest->headers->add($request->getHeaders());

        return Request::createFromBase($baseRequest);
    }

    /**
     * Convert the given PSR-7 request carrying a Telegram webhook update into a bot request.
     */
    protected function toBotRequest(ServerRequestInterface $request): BotRequest
    {
        $server = $request->getServerParams();

        foreach ($request->getHeaders() as $name => $values) {
            $key = strtoupper(str_replace('-', '_', $name));

            $server[in_array($key, ['CONTENT_TYPE', 'CONTENT_LENGTH', 'CONTENT_MD5'], true) ? $key : 'HTTP_'.$key] = implode(', ', $values);
        }

        $server['REQUEST_METHOD'] = $request->getMethod();
        $server['REQUEST_URI'] = (string) $request->getUri()->getPath();

        return BotRequest::createFromBase([PHP_SAPI, $this->psr7RequestContent($request), json_encode($server)]);
    }

    /**
     * Determine if the given PSR-7 request carries a Telegram webhook update.
     */
    protected function isBotUpdate(ServerRequestInterface $request): bool
    {
        if (strtoupper($request->getMethod()) !== 'POST') {
            return false;
        }

        $content = $this->psr7RequestContent($request);

        if ($content === '' || $content[0] !== '{') {
            return false;
        }

        $decoded = json_decode($content, true);

        return is_array($decoded) && array_key_exists('update_id', $decoded);
    }

    /**
     * Get the raw body of the given PSR-7 request.
     */
    protected function psr7RequestContent(ServerRequestInterface $request): string
    {
        $body = $request->getBody();

        if ($body->isSeekable()) {
            $body->rewind();
        }

        return (string) $body;
    }

    /**
     * Convert the given PSR-7 uploaded files into LaraGram uploaded files.
     */
    protected function toLaraGramUploadedFiles(array $uploadedFiles): array
    {
        $files = [];

        foreach ($uploadedFiles as $key => $value) {
            $files[$key] = $value instanceof UploadedFileInterface
                ? $this->toLaraGramUploadedFile($value)
                : $this->toLaraGramUploadedFiles($value);
        }

        return $files;
    }

    /**
     * Convert the given PSR-7 uploaded file into a LaraGram uploaded file.
     */
    protected function toLaraGramUploadedFile(UploadedFileInterface $file): UploadedFile
    {
        $path = '';

        if ($file->getError() !== UPLOAD_ERR_NO_FILE) {
            $path = $file->getStream()->getMetadata('uri') ?? '';

            if (! is_string($path) || $path === '' || ! is_file($path)) {
                $file->moveTo($path = tempnam(sys_get_temp_dir(), 'laragram'));
            }
        }

        return new UploadedFile(
            $path,
            (string) $file->getClientFilename(),
            $file->getClientMediaType(),
            $file->getError(),
            true,
        );
    }

    /**
     * Convert the given HttpFoundation or bot response into a PSR-7 response.
     */
    protected function toPsr7Response(BaseResponse|BotResponse $response): ResponseInterface
    {
        if ($response instanceof BotResponse) {
            $content = (string) $response->getContent();

            $psrResponse = $this->psr7ResponseFactory()
                ->createResponse(200)
                ->withBody($this->psr7StreamFactory()->createStream($content));

            return $content !== '' && json_validate($content)
                ? $psrResponse->withHeader('Content-Type', 'application/json')
                : $psrResponse;
        }

        $psrResponse = $this->psr7ResponseFactory()->createResponse(
            $response->getStatusCode(), BaseResponse::$statusTexts[$response->getStatusCode()] ?? ''
        );

        if ($response instanceof BinaryFileResponse && ! $response->headers->has('Content-Range')) {
            $stream = $this->psr7StreamFactory()->createStreamFromFile($response->getFile()->getPathname());
        } else {
            $stream = $this->psr7StreamFactory()->createStreamFromFile('php://temp', 'wb+');

            if ($response instanceof StreamedResponse || $response instanceof BinaryFileResponse) {
                ob_start(static function ($buffer) use ($stream) {
                    $stream->write($buffer);

                    return '';
                }, 1);

                try {
                    $response->sendContent();
                } finally {
                    ob_end_clean();
                }
            } else {
                $stream->write((string) $response->getContent());
            }
        }

        $psrResponse = $psrResponse->withBody($stream);

        $headers = $response->headers->all();

        if ($cookies = $response->headers->getCookies()) {
            $headers['Set-Cookie'] = array_map(fn ($cookie) => (string) $cookie, $cookies);
        }

        foreach ($headers as $name => $value) {
            try {
                $psrResponse = $psrResponse->withHeader($name, $value);
            } catch (\InvalidArgumentException) {
                // Ignore invalid headers...
            }
        }

        return $psrResponse->withProtocolVersion($response->getProtocolVersion());
    }

    /**
     * Create the PSR-17 response factory.
     */
    protected function psr7ResponseFactory(): ResponseFactoryInterface
    {
        return $this->psrResponseFactory ?: ($this->psrResponseFactory = new ResponseFactory);
    }

    /**
     * Create the PSR-17 stream factory.
     */
    protected function psr7StreamFactory(): StreamFactoryInterface
    {
        return $this->psrStreamFactory ?: ($this->psrStreamFactory = new StreamFactory);
    }
}
