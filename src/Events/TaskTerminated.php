<?php

namespace LaraGram\Surge\Events;

use LaraGram\Foundation\Application;
use LaraGram\Surge\Contracts\OperationTerminated;

class TaskTerminated implements OperationTerminated
{
    use HasApplicationAndSandbox;

    public function __construct(
        public Application $app,
        public Application $sandbox,
        public $data,
        public $result,
    ) {
    }
}
