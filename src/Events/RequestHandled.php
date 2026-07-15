<?php

namespace LaraGram\Surge\Events;

use LaraGram\Foundation\Application;

class RequestHandled
{
    /**
     * @param  \LaraGram\Request\Request|\LaraGram\Http\Request  $request
     * @param  \LaraGram\Request\Response|\LaraGram\Http\Response  $response
     */
    public function __construct(
        public Application $sandbox,
        public $request,
        public $response
    ) {
    }
}
