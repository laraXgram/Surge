<?php

declare(strict_types=1);

namespace LaraGram\Surge\RoadRunner\Factory;

use Override;
use LaraGram\Http\Factory\ResponseFactoryInterface;
use LaraGram\Http\Factory\ResponseInterface;

class ResponseFactory implements ResponseFactoryInterface
{
    /**
     * {@inheritDoc}
     */
    #[Override]
    public function createResponse(int $code = 200, string $reasonPhrase = ''): ResponseInterface
    {
        return (new Response())
            ->withStatus($code, $reasonPhrase);
    }
}
