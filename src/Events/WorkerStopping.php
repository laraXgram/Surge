<?php

namespace LaraGram\Surge\Events;

use LaraGram\Foundation\Application;

class WorkerStopping
{
    public function __construct(public Application $app)
    {
    }
}
