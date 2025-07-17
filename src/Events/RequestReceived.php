<?php

namespace LaraGram\Surge\Events;

use LaraGram\Foundation\Application;
use LaraGram\Request\Request;

class RequestReceived
{
    public function __construct(
        public Application $app,
        public Application $sandbox,
        public Request $request
    ) {
    }
}
