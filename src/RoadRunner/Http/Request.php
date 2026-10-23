<?php

namespace LaraGram\Surge\RoadRunner\Http;

/**
 * An HTTP request received from the RoadRunner server.
 */
final class Request
{
    public const PARSED_BODY_ATTRIBUTE_NAME = 'rr_parsed_body';

    /**
     * Create a new request instance.
     *
     * @param  array<int|string, array<array-key, string>>  $headers
     * @param  array<string, string>  $cookies
     * @param  array<array-key, mixed>  $uploads
     * @param  array<string, mixed>  $attributes
     */
    public function __construct(
        public readonly string $remoteAddr = '127.0.0.1',
        public readonly string $protocol = 'HTTP/1.0',
        public readonly string $method = 'GET',
        public readonly string $uri = 'http://localhost',
        public readonly array $headers = [],
        public readonly array $cookies = [],
        public readonly array $uploads = [],
        public readonly array $attributes = [],
        public readonly array $query = [],
        public readonly string $body = '',
        public readonly bool $parsed = false,
    ) {
    }

    /**
     * Get the address of the client.
     */
    public function getRemoteAddr(): string
    {
        return (string) ($this->attributes['ipAddress'] ?? $this->remoteAddr);
    }

    /**
     * Get the body parsed by the server, if any.
     */
    public function getParsedBody(): ?array
    {
        return $this->parsed
            ? (array) json_decode($this->body, true, 512, JSON_THROW_ON_ERROR)
            : null;
    }
}
