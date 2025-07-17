<?php

namespace LaraGram\Surge\Events;

use LaraGram\Foundation\Application;
use LaraGram\Surge\Contracts\OperationTerminated;

class TickTerminated implements OperationTerminated
{
    use HasApplicationAndSandbox;

    public function __construct(
        public Application $app,
        public Application $sandbox
    ) {
    }
}
