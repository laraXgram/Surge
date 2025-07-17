<?php

namespace LaraGram\Surge\Events;

use LaraGram\Foundation\Application;

class TaskReceived
{
    public function __construct(
        public Application $app,
        public Application $sandbox,
        public $data
    ) {
    }
}
