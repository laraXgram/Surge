<?php

namespace LaraGram\Surge\Listeners;

use LaraGram\Luna\ResponseFactory;

class PrepareLunaForNextOperation
{
    /**
     * Handle the event.
     *
     * @param  mixed  $event
     */
    public function handle($event): void
    {
        if (! $event->sandbox->resolved(ResponseFactory::class)) {
            return;
        }

        $factory = $event->sandbox->make(ResponseFactory::class);

        if (method_exists($factory, 'flushShared')) {
            $factory->flushShared();
        }
    }
}
