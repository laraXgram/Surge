<?php

namespace LaraGram\Surge\Listeners;

use LaraGram\Contracts\Auth\Access\Gate;

class GiveNewApplicationInstanceToAuthorizationGate
{
    /**
     * Handle the event.
     *
     * @param  mixed  $event
     */
    public function handle($event): void
    {
        if (! $event->sandbox->resolved(Gate::class)) {
            return;
        }

        $event->sandbox->make(Gate::class)->setContainer($event->sandbox);
    }
}
