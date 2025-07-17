<?php

namespace LaraGram\Surge;

use LaraGram\Request\Response;

class SurgeResponse
{
    public function __construct(public Response $response, public ?string $outputBuffer = null)
    {
    }
}
