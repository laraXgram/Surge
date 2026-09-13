<?php

namespace LaraGram\Surge\Events;

use LaraGram\Foundation\Application;
use LaraGram\Http\Request as HttpRequest;
use LaraGram\Http\Response as HttpResponse;
use LaraGram\Request\Request as BotRequest;
use LaraGram\Request\Response as BotResponse;
use LaraGram\Surge\Contracts\OperationTerminated;

class RequestTerminated implements OperationTerminated
{
    use HasApplicationAndSandbox;

    /**
     * @param  \LaraGram\Request\Request|\LaraGram\Http\Request  $request
     * @param  \LaraGram\Request\Response|\LaraGram\Http\Response  $response
     */
    public function __construct(
        public Application $app,
        public Application $sandbox,
        public BotRequest|HttpRequest $request,
        public BotResponse|HttpResponse $response
    ) {
    }
}
