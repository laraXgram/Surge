<?php

namespace LaraGram\Surge\Events;

use LaraGram\Foundation\Application;

class WorkerStarting
{
    public function __construct(public Application $app)
    {
    }
}
