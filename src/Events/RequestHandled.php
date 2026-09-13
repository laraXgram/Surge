<?php

namespace LaraGram\Surge\Events;

use LaraGram\Foundation\Application;
use LaraGram\Request\Request as BotRequest;
use LaraGram\Request\Response as BotResponse;
use LaraGram\Http\Request as HttpRequest;
use LaraGram\Http\BaseResponse as HttpResponse;

class RequestHandled
{
    /**
     * @param  \LaraGram\Request\Request|\LaraGram\Http\Request  $request
     * @param  \LaraGram\Request\Response|\LaraGram\Http\BaseResponse  $response
     */
    public function __construct(
        public Application $sandbox,
        public BotRequest|HttpRequest $request,
        public BotResponse|HttpResponse $response
    ) {
    }
}
