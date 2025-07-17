<?php

namespace LaraGram\Surge\Events;

use LaraGram\Foundation\Application;
use LaraGram\Request\Request;
use LaraGram\Surge\Contracts\OperationTerminated;
use LaraGram\Request\Response;

class RequestTerminated implements OperationTerminated
{
    use HasApplicationAndSandbox;

    public function __construct(
        public Application $app,
        public Application $sandbox,
        public Request $request,
        public Response $response
    ) {
    }
}
