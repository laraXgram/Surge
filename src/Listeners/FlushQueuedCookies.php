<?php

namespace LaraGram\Surge\Listeners;

class FlushQueuedCookies
{
    /**
     * Handle the event.
     *
     * @param  mixed  $event
     */
    public function handle($event): void
    {
        if (! $event->sandbox->resolved('cookie')) {
            return;
        }

        $event->sandbox->make('cookie')->flushQueuedCookies();
    }
}
