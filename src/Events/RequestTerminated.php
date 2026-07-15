<?php

namespace LaraGram\Surge\Events;

use LaraGram\Foundation\Application;
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
        public $request,
        public $response
    ) {
    }
}
