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
        return Request::createFromBase([
            $phpSapi,
            $swooleRequest->getContent(),
            $swooleRequest->server ?? [],
        ]);
    }
}
