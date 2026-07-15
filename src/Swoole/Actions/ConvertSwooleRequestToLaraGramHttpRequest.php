<?php

namespace LaraGram\Surge\Swoole\Actions;

use LaraGram\Http\BaseRequest;
use LaraGram\Http\Request as HttpRequest;

class ConvertSwooleRequestToLaraGramHttpRequest
{
    /**
     * Convert the given Swoole request into a LaraGram HTTP request.
     *
     * Web (browser) traffic is served through the HTTP kernel, so we rebuild a
     * full $_SERVER-shaped request from the Swoole request - query, body, cookies,
     * files, headers - rather than the update-oriented bot request.
     *
     * @param \Swoole\Http\Request $swooleRequest
     */
    public function __invoke($swooleRequest): HttpRequest
    {
        $base = new BaseRequest(
            $swooleRequest->get ?? [],
            $swooleRequest->post ?? [],
            [],
            $swooleRequest->cookie ?? [],
            $this->normaliseFiles($swooleRequest->files ?? []),
            $this->marshalServerVariables($swooleRequest),
            $swooleRequest->getContent(),
        );

        return HttpRequest::createFromBase($base);
    }

    /**
     * Build a $_SERVER-shaped array from the Swoole request.
     *
     * Swoole exposes server vars in lowercase and keeps headers in a separate
     * bag, so we uppercase the former and re-prefix the latter with "HTTP_" to
     * match what the HTTP request expects.
     *
     * @param \Swoole\Http\Request $swooleRequest
     * @return array
     */
    protected function marshalServerVariables($swooleRequest): array
    {
        $server = [];

        foreach ($swooleRequest->server ?? [] as $key => $value) {
            $server[strtoupper($key)] = $value;
        }

        foreach ($swooleRequest->header ?? [] as $key => $value) {
            $server['HTTP_' . strtoupper(str_replace('-', '_', $key))] = $value;
        }

        return $server;
    }

    /**
     * Normalise the Swoole uploaded files array to the $_FILES structure.
     *
     * @return array
     */
    protected function normaliseFiles(array $files): array
    {
        return $files;
    }
}
