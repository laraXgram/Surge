<?php

namespace LaraGram\Surge\Swoole\Actions;

use LaraGram\Request\Request;

class ConvertSwooleRequestToLaraGramRequest
{
    /**
     * Convert the given Swoole request carrying a Telegram webhook update into a LaraGram bot request.
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
     * Index 1 is the raw update JSON and index 2 the JSON-encoded $_SERVER variables
     * (which carry the webhook secret token header). The payload only holds scalars,
     * so it can be passed to a Swoole task worker.
     *
     * @param  \Swoole\Http\Request  $swooleRequest
     */
    public function toArgv($swooleRequest, string $phpSapi): array
    {
        return [
            $phpSapi,
            (string) $swooleRequest->rawContent(),
            json_encode((new ConvertSwooleRequestToLaraGramHttpRequest)->prepareServerVariables(
                $swooleRequest->server ?? [],
                $swooleRequest->header ?? [],
                $phpSapi
            )),
        ];
    }
}
