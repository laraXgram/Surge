<?php

namespace LaraGram\Surge\Swoole\Actions;

use LaraGram\Request\Request;

class ConvertSwooleRequestToLaraGramRequest
{
    /**
     * Convert the given Swoole request into an LaraGram request.
     *
     * @param  \Swoole\Http\Request  $swooleRequest
     */
    public function __invoke($swooleRequest, string $phpSapi): Request
    {
        return Request::createFromBase($this->toArgv($swooleRequest, $phpSapi));
    }

    /**
     * Build the argv-like payload consumed by Request::createFromBase().
     *
     * Index 1 is the raw JSON body and index 2 is a JSON-encoded $_SERVER array.
     * Swoole exposes the server vars in lowercase and keeps headers separately,
     * so we normalise both into a PHP $_SERVER-shaped payload. The result is a
     * plain array of scalars, which makes it safe to pass to a Swoole task.
     *
     * @param  \Swoole\Http\Request  $swooleRequest
     * @return array
     */
    public function toArgv($swooleRequest, string $phpSapi): array
    {
        return [
            $phpSapi,
            $swooleRequest->getContent(),
            json_encode($this->marshalServerVariables($swooleRequest)),
        ];
    }

    /**
     * Build a $_SERVER-shaped array from the Swoole request.
     *
     * @param  \Swoole\Http\Request  $swooleRequest
     * @return array
     */
    protected function marshalServerVariables($swooleRequest): array
    {
        $server = [];

        foreach ($swooleRequest->server ?? [] as $key => $value) {
            $server[strtoupper($key)] = $value;
        }

        foreach ($swooleRequest->header ?? [] as $key => $value) {
            $server['HTTP_'.strtoupper(str_replace('-', '_', $key))] = $value;
        }

        return $server;
    }
}
