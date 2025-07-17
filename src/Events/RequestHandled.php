<?php

namespace LaraGram\Surge\Events;

use LaraGram\Foundation\Application;
use LaraGram\Request\Request;
use LaraGram\Request\Response;

class RequestHandled
{
    public function __construct(
        public Application $sandbox,
        public Request $request,
        public Response $response
    ) {
    }
}
