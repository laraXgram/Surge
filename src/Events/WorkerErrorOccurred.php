<?php

namespace LaraGram\Surge\Events;

use LaraGram\Foundation\Application;
use Throwable;

class WorkerErrorOccurred
{
    public function __construct(public Throwable $exception, public Application $sandbox)
    {
    }
}
