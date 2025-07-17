<?php

namespace LaraGram\Surge\Listeners;

use LaraGram\Listening\ListenCollection;

class GiveNewApplicationInstanceToListener
{
    /**
     * Handle the event.
     *
     * @param  mixed  $event
     */
    public function handle($event): void
    {
        if (! $event->sandbox->resolved('listener')) {
            return;
        }

        $event->sandbox->make('listener')->setContainer($event->sandbox);

        if ($event->sandbox->resolved('listens') && $event->sandbox->make('listens') instanceof ListenCollection) {
            foreach ($event->sandbox->make('listens') as $route) {
                $route->setContainer($event->sandbox);
            }
        }
    }
}
