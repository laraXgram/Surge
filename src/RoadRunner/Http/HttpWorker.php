<?php

namespace LaraGram\Surge\RoadRunner\Http;

use Generator;
use InvalidArgumentException;
use LaraGram\Surge\RoadRunner\Goridge\Frame;
use LaraGram\Surge\RoadRunner\Worker\Messages\StreamStop;
use LaraGram\Surge\RoadRunner\Worker\Payload;
use LaraGram\Surge\RoadRunner\Worker\Worker;
use Throwable;

/**
 * Receives HTTP requests from the RoadRunner server and sends the responses back.
 */
class HttpWorker
{
    /**
     * The codec the server uses for the request headers (JSON or protobuf).
     */
    protected static ?int $codec = null;

    /**
     * Create a new HTTP worker instance.
     */
    public function __construct(protected Worker $worker)
    {
    }

    /**
     * Get the underlying worker.
     */
    public function getWorker(): Worker
    {
        return $this->worker;
    }

    /**
     * Wait for the next request, or null when the worker should stop.
     */
    public function waitRequest(): ?Request
    {
        $payload = $this->worker->waitPayload();

        if ($payload === null || (! $payload->body && ! $payload->header)) {
            return null;
        }

        static::$codec ??= json_validate($payload->header) ? Frame::CODEC_JSON : Frame::CODEC_PROTO;

        if (static::$codec === Frame::CODEC_PROTO) {
            return $this->requestFromProto($payload->body, ProtobufCodec::decodeRequest($payload->header));
        }

        return $this->requestFromArray($payload->body, json_decode($payload->header, true, 512, JSON_THROW_ON_ERROR));
    }

    /**
     * Send the given response to the server.
     *
     * @param  array<array-key, array<array-key, string>>  $headers
     */
    public function respond(int $status, string|Generator $body = '', array $headers = [], bool $endOfStream = true): void
    {
        if ($status < 200 && $status >= 100 && $body !== '') {
            throw new InvalidArgumentException('Unable to send a body with informational status code.');
        }

        if ($body instanceof Generator) {
            $this->respondStream($status, $body, $headers, $endOfStream);

            return;
        }

        $this->worker->respond($this->createRespondPayload($status, $body, $headers, $endOfStream), static::$codec);
    }

    /**
     * Stream the chunks of the given generator to the server.
     *
     * @param  array<array-key, array<array-key, string>>  $headers
     */
    protected function respondStream(int $status, Generator $body, array $headers = [], bool $endOfStream = true): void
    {
        $worker = $this->worker->withStreamMode();

        while (true) {
            if (! $body->valid()) {
                $content = (string) $body->getReturn();

                if ($endOfStream || $content !== '') {
                    $worker->respond($this->createRespondPayload($status, $content, $headers, $endOfStream), static::$codec);
                }

                return;
            }

            $content = (string) $body->current();

            if ($worker->getPayload(StreamStop::class) !== null) {
                $body->throw(new StreamStoppedException);

                // RoadRunner waits for a stream stop frame to confirm the stream is closed...
                $worker->respond(new Payload(''));

                return;
            }

            $worker->respond($this->createRespondPayload($status, $content, $headers, false), static::$codec);

            try {
                $body->next();
            } catch (Throwable) {
                $worker->respond(new Payload(''));

                return;
            }
        }
    }

    /**
     * Create a request from the given JSON context.
     */
    protected function requestFromArray(string $body, array $context): Request
    {
        parse_str($context['rawQuery'], $query);

        return new Request(
            remoteAddr: $context['remoteAddr'],
            protocol: $context['protocol'],
            method: $context['method'],
            uri: $context['uri'],
            headers: $this->filterHeaders((array) ($context['headers'] ?? [])),
            cookies: (array) ($context['cookies'] ?? []),
            uploads: (array) ($context['uploads'] ?? []),
            attributes: [Request::PARSED_BODY_ATTRIBUTE_NAME => $context['parsed']] + (array) ($context['attributes'] ?? []),
            query: $query,
            body: $body,
            parsed: $context['parsed'],
        );
    }

    /**
     * Create a request from the given decoded protobuf message.
     */
    protected function requestFromProto(string $body, array $message): Request
    {
        parse_str($message['raw_query'], $query);

        return new Request(
            remoteAddr: $message['remote_addr'],
            protocol: $message['protocol'],
            method: $message['method'],
            uri: $message['uri'],
            headers: $this->filterHeaders($message['header']),
            cookies: array_map(fn (array $values) => implode(',', $values), $message['cookies']),
            uploads: json_decode($message['uploads'], true) ?? [],
            attributes: [Request::PARSED_BODY_ATTRIBUTE_NAME => $message['parsed']]
                + array_map(fn (array $values) => array_shift($values), $message['attributes']),
            query: $query,
            body: $message['parsed'] && $body === '' ? '{}' : $body,
            parsed: $message['parsed'],
        );
    }

    /**
     * Drop the empty header name, which PSR-7 rejects.
     */
    protected function filterHeaders(array $headers): array
    {
        unset($headers['']);

        return $headers;
    }

    /**
     * Create the payload of the given response.
     *
     * @param  array<array-key, array<array-key, string>>  $headers
     */
    protected function createRespondPayload(int $status, string $body, array $headers = [], bool $eos = true): Payload
    {
        $header = static::$codec === Frame::CODEC_PROTO
            ? ProtobufCodec::encodeResponse($status, $headers)
            : json_encode(['status' => $status, 'headers' => $headers ?: (object) []], JSON_THROW_ON_ERROR);

        return new Payload($body, $header, $eos);
    }
}
