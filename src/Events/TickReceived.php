<?php

namespace LaraGram\Surge\Events;

use LaraGram\Foundation\Application;

class TickReceived
{
    public function __construct(
        public Application $app,
        public Application $sandbox
    ) {
    }
}
