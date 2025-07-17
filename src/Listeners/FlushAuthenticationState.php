<?php

namespace LaraGram\Surge\Listeners;

class FlushAuthenticationState
{
    /**
     * Handle the event.
     *
     * @param  mixed  $event
     */
    public function handle($event): void
    {
        if ($event->sandbox->resolved('auth')) {
            with($event->sandbox->make('auth'), function ($auth) use ($event) {
                $auth->setApplication($event->sandbox);
            });
        }
    }
}
