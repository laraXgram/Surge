<?php

namespace LaraGram\Surge\RoadRunner\Http;

use Generator;
use LaraGram\Http\Factory\ResponseInterface;
use LaraGram\Http\Factory\ServerRequestFactoryInterface;
use LaraGram\Http\Factory\ServerRequestInterface;
use LaraGram\Http\Factory\StreamFactoryInterface;
use LaraGram\Http\Factory\StreamInterface;
use LaraGram\Http\Factory\UploadedFileFactoryInterface;
use LaraGram\Surge\RoadRunner\Worker\Worker;

/**
 * Receives PSR-7 requests from the RoadRunner server and sends PSR-7 responses back.
 */
class PSR7Worker
{
    /**
     * The chunk size of streamed response bodies, or zero to send bodies at once.
     */
    public int $chunkSize = 0;

    /**
     * The underlying HTTP worker.
     */
    protected HttpWorker $httpWorker;

    /**
     * Create a new PSR-7 worker instance.
     */
    public function __construct(
        Worker $worker,
        protected ServerRequestFactoryInterface $requestFactory,
        protected StreamFactoryInterface $streamFactory,
        protected UploadedFileFactoryInterface $uploadsFactory,
    ) {
        $this->httpWorker = new HttpWorker($worker);
    }

    /**
     * Get the underlying worker.
     */
    public function getWorker(): Worker
    {
        return $this->httpWorker->getWorker();
    }

    /**
     * Get the underlying HTTP worker.
     */
    public function getHttpWorker(): HttpWorker
    {
        return $this->httpWorker;
    }

    /**
     * Wait for the next request, or null when the worker should stop.
     */
    public function waitRequest(bool $populateServer = true): ?ServerRequestInterface
    {
        if (is_null($httpRequest = $this->httpWorker->waitRequest())) {
            return null;
        }

        $server = GlobalState::enrichServerVars($httpRequest);

        if ($populateServer) {
            $_SERVER = $server;
        }

        return $this->mapRequest($httpRequest, $server);
    }

    /**
     * Send the given response to the server.
     */
    public function respond(ResponseInterface $response): void
    {
        $this->httpWorker->respond(
            $response->getStatusCode(),
            $this->chunkSize > 0 ? $this->streamToGenerator($response->getBody()) : (string) $response->getBody(),
            $response->getHeaders(),
        );
    }

    /**
     * Map the given RoadRunner request to a PSR-7 request.
     */
    protected function mapRequest(Request $httpRequest, array $server): ServerRequestInterface
    {
        $request = $this->requestFactory->createServerRequest($httpRequest->method, $httpRequest->uri, $server)
            ->withProtocolVersion($this->protocolVersion($httpRequest->protocol))
            ->withCookieParams($httpRequest->cookies)
            ->withQueryParams($httpRequest->query)
            ->withUploadedFiles($this->wrapUploads($httpRequest->uploads));

        foreach ($httpRequest->attributes as $name => $value) {
            $request = $request->withAttribute($name, $value);
        }

        foreach ($httpRequest->headers as $name => $value) {
            $request = $request->withHeader((string) $name, $value);
        }

        if ($httpRequest->parsed) {
            $request = $request->withParsedBody($httpRequest->getParsedBody());
        }

        return $httpRequest->body !== ''
            ? $request->withBody($this->streamFactory->createStream($httpRequest->body))
            : $request;
    }

    /**
     * Wrap the given uploaded files into PSR-7 uploaded files.
     */
    protected function wrapUploads(array $files): array
    {
        $result = [];

        foreach ($files as $index => $file) {
            if (! isset($file['name'])) {
                $result[$index] = $this->wrapUploads($file);

                continue;
            }

            $result[$index] = $this->uploadsFactory->createUploadedFile(
                $file['error'] === UPLOAD_ERR_OK
                    ? $this->streamFactory->createStreamFromFile($file['tmpName'])
                    : $this->streamFactory->createStream(),
                $file['size'],
                $file['error'],
                $file['name'],
                $file['mime'],
            );
        }

        return $result;
    }

    /**
     * Get the PSR-7 protocol version of the given protocol, e.g. "HTTP/1.1".
     */
    protected function protocolVersion(string $protocol): string
    {
        $version = substr($protocol, 5);

        return match (true) {
            $version === '2.0' => '2',
            in_array($version, ['1.0', '1.1', '2'], true) => $version,
            default => '1.1',
        };
    }

    /**
     * Read the given stream in chunks.
     */
    protected function streamToGenerator(StreamInterface $stream): Generator
    {
        $stream->rewind();

        $size = $stream->getSize();

        if ($size !== null && $size < $this->chunkSize) {
            return (string) $stream;
        }

        $sum = 0;

        while (! $stream->eof()) {
            if ($size === null) {
                $chunk = $stream->read($this->chunkSize);
            } else {
                $left = $size - $sum;
                $chunk = $stream->read(min($this->chunkSize, $left));

                if ($left <= $this->chunkSize && strlen($chunk) === $left) {
                    return $chunk;
                }
            }

            $sum += strlen($chunk);

            yield $chunk;
        }
    }
}
