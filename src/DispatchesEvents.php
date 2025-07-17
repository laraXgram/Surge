<?php

namespace LaraGram\Surge;

use LaraGram\Contracts\Events\Dispatcher;
use LaraGram\Foundation\Application;

trait DispatchesEvents
{
    /**
     * Dispatch the given event via the given application.
     *
     * @param  mixed  $event
     */
    public function dispatchEvent(Application $app, $event): void
    {
        if ($app->bound(Dispatcher::class)) {
            $app[Dispatcher::class]->dispatch($event);
        }
    }
}
