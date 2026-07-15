<?php

namespace LaraGram\Surge\Events;

use LaraGram\Foundation\Application;

class RequestReceived
{
    /**
     * @param  \LaraGram\Request\Request|\LaraGram\Http\Request  $request
     */
    public function __construct(
        public Application $app,
        public Application $sandbox,
        public $request
    ) {
    }
}
