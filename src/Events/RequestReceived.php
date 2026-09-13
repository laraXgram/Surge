<?php

namespace LaraGram\Surge\Events;

use LaraGram\Foundation\Application;
use LaraGram\Http\Request as HttpRequest;
use LaraGram\Request\Request as BotRequest;

class RequestReceived
{
    /**
     * @param  \LaraGram\Request\Request|\LaraGram\Http\Request  $request
     */
    public function __construct(
        public Application $app,
        public Application $sandbox,
        public BotRequest|HttpRequest $request,
    ) {
    }
}
